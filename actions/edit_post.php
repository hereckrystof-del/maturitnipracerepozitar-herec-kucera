<?php
session_start();

//uzivatel
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//db
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $datum_od = $_POST['datum'] ?? '';
    $datum_do = !empty($_POST['datum_do']) ? $_POST['datum_do'] : null;
    $odkaz = trim($_POST['odkaz'] ?? '');
    $rok = !empty($datum_od) ? substr($datum_od, 0, 4) : date('Y');

    if ($id <= 0) {
        header("Location: ../index.php?tab=akce&error=invalid_id");
        exit();
    }

    //update uzivatele kery to vytvoril
    $autor = $_SESSION['user'];

    //update textu a ulozeni autora
    $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ?, datum = ?, datum_do = ?, odkaz = ?, rok = ?, autor = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $title, $content, $datum_od, $datum_do, $odkaz, $rok, $autor, $id);
    $stmt->execute();
    $stmt->close();

    //smazani vyb. obr
    if (!empty($_POST['remove_image']) && is_array($_POST['remove_image'])) {
        $allowedFields = ['image', 'image2', 'image3'];
        
        $stmt_get = $conn->prepare("SELECT image, image2, image3, rok FROM posts WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $row = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($row) {
            foreach ($_POST['remove_image'] as $field) {
                if (in_array($field, $allowedFields) && !empty($row[$field])) {
                    $clean_file = basename($row[$field]);
                    $path = __DIR__ . "/../uploads/Akce/" . $row['rok'] . "/" . $clean_file;
                    
                    if (file_exists($path) && is_file($path)) {
                        unlink($path);
                    }

                    $stmt_del = $conn->prepare("UPDATE posts SET $field = NULL WHERE id = ?");
                    $stmt_del->bind_param("i", $id);
                    $stmt_del->execute();
                    $stmt_del->close();
                }
            }
        }
    }

    //nahrani fotek
    if (!empty($_FILES['image']['name'][0])) {
        $target_dir = __DIR__ . "/../uploads/Akce/" . $rok . "/";
        if (!is_dir($target_dir)) { 
            mkdir($target_dir, 0755, true); 
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $stmt_check = $conn->prepare("SELECT image, image2, image3 FROM posts WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $current = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        $imageFields = ['image', 'image2', 'image3'];

        foreach ($_FILES['image']['name'] as $key => $val) {
            if ($key > 2) break; 
            if ($_FILES['image']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $tmp_name = $_FILES['image']['tmp_name'][$key];
            $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));

            //getimagesize
            $check = getimagesize($tmp_name);
            
            if ($check !== false && in_array($ext, $allowed_ext)) {
                $targetField = '';
                foreach ($imageFields as $f) {
                    if (empty($current[$f])) {
                        $targetField = $f;
                        $current[$f] = 'occupied';
                        break;
                    }
                }
                
                if ($targetField) {
                    $new_name = bin2hex(random_bytes(8)) . "_" . time() . "." . $ext;
                    if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {
                        $stmt_img = $conn->prepare("UPDATE posts SET $targetField = ? WHERE id = ?");
                        $stmt_img->bind_param("si", $new_name, $id);
                        $stmt_img->execute();
                        $stmt_img->close();
                    }
                }
            }
        }
    }
}

if (isset($conn)) $conn->close();
header("Location: ../index.php?tab=akce&success=post_updated");
exit();