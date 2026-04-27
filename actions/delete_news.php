<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

// databbze
require_once __DIR__ . '/../../config/config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($conn) {
        //nazev obr (z nove tabulky)
        $stmt_img = $conn->prepare("SELECT image_path FROM news_images WHERE news_id = ?");
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res = $stmt_img->get_result();
        
        while ($row = $res->fetch_assoc()) {
            //smazani ze serveru
            $file_path = __DIR__ . "/../uploads/Aktuality/" . basename($row['image_path']);
            if (file_exists($file_path) && is_file($file_path)) {
                unlink($file_path);
            }
        }
        $stmt_img->close();

        //smaznani datavaze
        $stmt_delete = $conn->prepare("DELETE FROM aktuality WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }
}

if ($conn) $conn->close();

//back na aktuality
header("Location: ../index.php?tab=aktuality&success=news_deleted");
exit();
?>