<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//databaze
require_once __DIR__ . '/../../config/config.php';

//kontola
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_photos']) && is_array($_POST['selected_photos'])) {
    
    if ($conn) {
        $stmt_select = $conn->prepare("SELECT nazev_souboru FROM fotky WHERE id = ?");
        $stmt_delete = $conn->prepare("DELETE FROM fotky WHERE id = ?");

        foreach ($_POST['selected_photos'] as $photo_id) {
            $photo_id = intval($photo_id);
            if ($photo_id <= 0) continue;

            //cesta k souboru
            $stmt_select->bind_param("i", $photo_id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $db_path = $row['nazev_souboru'];
                
                //bezpecnost
                $clean_path = str_replace(['../', '..\\'], '', $db_path);
                $full_path = __DIR__ . "/../" . $clean_path;

                //smazani z sevreru
                if (!empty($clean_path) && file_exists($full_path) && is_file($full_path)) {
                    unlink($full_path);
                }

                //smazani z databeze
                $stmt_delete->bind_param("i", $photo_id);
                $stmt_delete->execute();
            }
        }
        
        $stmt_select->close();
        $stmt_delete->close();
        $conn->close();
    }

    //presperovani zpet na rok a kateg
    $vybrany_rok = intval($_POST['redirect_rok'] ?? 0);
    $vybrana_kat = $_POST['redirect_kat'] ?? '';
    
    $redirect_url = "../index.php?tab=fotogalerie";
    if ($vybrany_rok > 0) $redirect_url .= "&rok=" . $vybrany_rok;
    if (!empty($vybrana_kat)) $redirect_url .= "&kat=" . urlencode($vybrana_kat);

    header("Location: " . $redirect_url . "&success=photos_deleted");
    exit();

} else {
    header("Location: ../index.php?tab=fotogalerie");
    exit();
}