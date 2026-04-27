<?php
session_start();

////prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_id'])) {
    $id = intval($_POST['post_id']);
    
    if (!empty($_POST['remove_image']) && is_array($_POST['remove_image'])) {
        
        //data z datb (zjisteni roku)
        $stmt_rok = $conn->prepare("SELECT rok FROM posts WHERE id = ?");
        $stmt_rok->bind_param("i", $id);
        $stmt_rok->execute();
        $rok = date('Y');
        if ($row_rok = $stmt_rok->get_result()->fetch_assoc()) {
            $rok = $row_rok['rok'];
        }
        $stmt_rok->close();

        foreach ($_POST['remove_image'] as $imgSrc) {
            //kontorla
            $filename = basename($imgSrc);
            
            //bezpecny smazani
            $filePath = __DIR__ . "/../uploads/Akce/" . $rok . "/" . $filename;
            if (file_exists($filePath) && is_file($filePath)) {
                unlink($filePath);
            }
            
            //update databaze a zabezpeccc (z nové tabulky)
            $stmt_del = $conn->prepare("DELETE FROM post_images WHERE post_id = ? AND image_path = ?");
            $stmt_del->bind_param("is", $id, $filename);
            $stmt_del->execute();
            $stmt_del->close();
        }
    }
}

if ($conn) $conn->close();

//back na upravovani akce
$redirect_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
if ($redirect_id > 0) {
    header("Location: ../index.php?tab=akce&edit=" . $redirect_id . "&success=images_removed");
} else {
    header("Location: ../index.php?tab=akce");
}
exit();
?>