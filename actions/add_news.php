<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//databaze
require_once __DIR__ . '/../../config/config.php';

//formular
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    //nacteni dat
    $title   = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $datum   = $_POST['datum'] ?? '';
    
    //NULL pro prazdny pole
    $datum_do = !empty($_POST['datum_do']) ? $_POST['datum_do'] : null;
    $odkaz    = !empty($_POST['odkaz'])    ? $_POST['odkaz']    : null;
    $rok = !empty($datum) ? date('Y', strtotime($datum)) : date('Y');

    //pole pro nove obrazky
    $uploadedImages = [];
    $target_dir = __DIR__ . "/../uploads/Aktuality/";
        
    //vytvoreni slozky
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (!empty($_FILES['image']['name'][0])) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['image']['name'] as $key => $val) {
            if ($_FILES['image']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $file_name = $_FILES['image']['name'][$key];
            $file_tmp  = $_FILES['image']['tmp_name'][$key];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $mime      = finfo_file($finfo, $file_tmp);

            if (in_array($file_ext, $allowed_ext) && in_array($mime, $allowed_mimes)) {
                //unikatni nazzv
                $clean_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file_name, PATHINFO_FILENAME));
                $new_filename = bin2hex(random_bytes(4)) . "_" . time() . "_" . $key . "_" . $clean_filename . "." . $file_ext;
                
                if (move_uploaded_file($file_tmp, $target_dir . $new_filename)) {
                    $uploadedImages[] = $new_filename;
                }
            }
        }
        finfo_close($finfo);
    }

    //vezme prihlaseneho uzivatele
    $autor = $_SESSION['user'];

    //sql
    $sql = "INSERT INTO aktuality (title, content, datum, datum_do, rok, odkaz, autor) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    //sssssss 7x text
    $stmt->bind_param("sssssss", $title, $content, $datum, $datum_do, $rok, $odkaz, $autor);
    
    if ($stmt->execute()) {
        $news_id = $conn->insert_id;
        $stmt->close();

        //ulozi fotky do pomocne tabulky
        if (!empty($uploadedImages)) {
            $img_sql = "INSERT INTO news_images (news_id, image_path) VALUES (?, ?)";
            $img_stmt = $conn->prepare($img_sql);
            foreach ($uploadedImages as $img_name) {
                $img_stmt->bind_param("is", $news_id, $img_name);
                $img_stmt->execute();
            }
            $img_stmt->close();
        }

        $conn->close();
        header("Location: ../index.php?tab=aktuality&success=news_added");
        exit();
    } else {
        //chyba
        header("Location: ../index.php?tab=aktuality&error=db_error");
        exit();
    }
}

//zabezpeceni
header("Location: ../index.php?tab=aktuality");
exit();
?>