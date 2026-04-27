<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

require_once __DIR__ . '/../../config/config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($conn) {
        //get info o obrazcich a roku
        $stmt_rok = $conn->prepare("SELECT rok FROM posts WHERE id = ?");
        $stmt_rok->bind_param("i", $id);
        $stmt_rok->execute();
        $res_rok = $stmt_rok->get_result();
        $rok = date('Y');
        if ($row_rok = $res_rok->fetch_assoc()) {
            $rok = $row_rok['rok'];
        }
        $stmt_rok->close();

        $stmt_img = $conn->prepare("SELECT image_path FROM post_images WHERE post_id = ?");
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $result = $stmt_img->get_result();

        //smazani obrazku
        while ($row = $result->fetch_assoc()) {
            //clean nazev
            $clean_name = basename($row['image_path']);
            $file_path = __DIR__ . "/../uploads/Akce/" . $rok . "/" . $clean_name;
            
            if (file_exists($file_path) && is_file($file_path)) {
                unlink($file_path);
            }
        }
        $stmt_img->close();

        //smazani dz datab
        $stmt_delete = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }
}

if ($conn) $conn->close();

//back na akce
header("Location: ../index.php?tab=akce&success=post_deleted");
exit();
?>