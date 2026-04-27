<?php
session_start();

//prihlaseny uzivatel
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//db
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $datum = $_POST['datum'] ?? '';
    
    $datum_do = !empty($_POST['datum_do']) ? $_POST['datum_do'] : null;
    $odkaz = !empty($_POST['odkaz']) ? $_POST['odkaz'] : null;
    $rok = !empty($datum) ? date('Y', strtotime($datum)) : date('Y');
    
    //ziskani autora
    $autor = $_SESSION['user'];

    if ($id <= 0) {
        header("Location: ../index.php?tab=aktuality&error=invalid_id");
        exit();
    }

    //update textu a autora
    $stmt = $conn->prepare("UPDATE aktuality SET title = ?, content = ?, datum = ?, datum_do = ?, odkaz = ?, rok = ?, autor = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $title, $content, $datum, $datum_do, $odkaz, $rok, $autor, $id);
    $stmt->execute();
    $stmt->close();

    $target_dir = __DIR__ . "/../uploads/Aktuality/";

    //smazani vybranych fot
    if (!empty($_POST['remove_image']) && is_array($_POST['remove_image'])) {
        foreach ($_POST['remove_image'] as $imgSrc) {
            $filename = basename($imgSrc);
            $path = $target_dir . $filename;
            
            //smazani ze sevreru
            if (file_exists($path) && is_file($path)) {
                unlink($path);
            }

            //smazani z pomocne tab
            $stmt_del = $conn->prepare("DELETE FROM news_images WHERE news_id = ? AND image_path = ?");
            $stmt_del->bind_param("is", $id, $filename);
            $stmt_del->execute();
            $stmt_del->close();
        }
    }

    //nahrani fotzek
    if (!empty($_FILES['image']['name'][0])) {
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        //prikaz pro zapis
        $stmt_img = $conn->prepare("INSERT INTO news_images (news_id, image_path) VALUES (?, ?)");

        foreach ($_FILES['image']['name'] as $key => $val) {
            if ($_FILES['image']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $file_name = $_FILES['image']['name'][$key];
            $tmp_name  = $_FILES['image']['tmp_name'][$key];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $mime = finfo_file($finfo, $tmp_name);

            if (in_array($ext, $allowed_ext) && in_array($mime, $allowed_mimes)) {
                $clean_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file_name, PATHINFO_FILENAME));
                $new_name = bin2hex(random_bytes(4)) . "_" . time() . "_" . $key . "_" . $clean_filename . "." . $ext;
                
                if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {
                    $stmt_img->bind_param("is", $id, $new_name);
                    $stmt_img->execute();
                }
            }
        }
        finfo_close($finfo);
        $stmt_img->close();
    }
}

if ($conn) $conn->close();

header("Location: ../index.php?tab=aktuality&success=news_updated");
exit();
?>