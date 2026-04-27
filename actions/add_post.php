<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//dataaze
require_once __DIR__ . '/../../config/config.php';

//formular
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title    = $_POST['title'] ?? '';
    $content  = $_POST['content'] ?? '';
    $datum_od = $_POST['datum'] ?? '';
    $datum_do = !empty($_POST['datum_do']) ? $_POST['datum_do'] : null;
    $odkaz    = !empty($_POST['odkaz'])    ? $_POST['odkaz']    : null;
    
    //extract date
    $rok = !empty($datum_od) ? substr($datum_od, 0, 4) : date('Y');
    
    //dynamic - pole pro neomezene obrazku
    $uploadedImages = [];
    
    $target_base_dir = __DIR__ . "/../uploads/Akce/";
    $target_dir = $target_base_dir . $rok . "/";

    //vytvoreni slozek
    if (!is_dir($target_base_dir)) { mkdir($target_base_dir, 0755, true); }
    if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }

    //nahravani obrazku
    if (!empty($_FILES['image']['name'][0])) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['image']['name'] as $key => $val) {
            if ($_FILES['image']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $file_tmp  = $_FILES['image']['tmp_name'][$key];
            $file_name = $_FILES['image']['name'][$key];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $mime      = finfo_file($finfo, $file_tmp);

            //zabezpeceni
            if (in_array($file_ext, $allowed_ext) && in_array($mime, $allowed_mimes)) {
                //bezpecny nazev (přidán $key pro jistotu, kdyby se nahrávaly ve stejnou milisekundu)
                $new_filename = bin2hex(random_bytes(8)) . "_" . time() . "_" . $key . "." . $file_ext;
                
                if (move_uploaded_file($file_tmp, $target_dir . $new_filename)) {
                    $uploadedImages[] = $new_filename;
                }
            }
        }
        
        //reduce RAM pro ntebooky jako je ten muj
        finfo_close($finfo);
    }

    $autor = $_SESSION['user']; //vezme si id prihlaseneho uzi

    //Bezpecne ulozeni akce
    $sql = "INSERT INTO posts (title, content, datum, datum_do, odkaz, rok, autor) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    //7x string (6x+1autor)
    $stmt->bind_param("sssssss", 
        $title, 
        $content, 
        $datum_od, 
        $datum_do, 
        $odkaz, 
        $rok,
        $autor
    );

    if ($stmt->execute()) {
        $post_id = $conn->insert_id;
        $stmt->close();

        //vlozeni to tab 
        if (!empty($uploadedImages)) {
            $img_sql = "INSERT INTO post_images (post_id, image_path) VALUES (?, ?)";
            $img_stmt = $conn->prepare($img_sql);
            
            foreach ($uploadedImages as $img_name) {
                $img_stmt->bind_param("is", $post_id, $img_name);
                $img_stmt->execute();
            }
            $img_stmt->close();
        }

        $conn->close();
        header("Location: ../index.php?tab=akce&success=post_added");
        exit();
    } else {
        header("Location: ../index.php?tab=akce&error=db_error");
        exit();
    }
}

header("Location: ../index.php?tab=akce");
exit();
?>