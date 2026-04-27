<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) { 
    die("Nepovolený přístup."); 
}

//databaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["fotka"])) {
    $rok = intval($_POST['rok']);
    $kategorie = $_POST['kategorie'] ?? '';

    //kategorie
    $povolene_kategorie = ['tabor', 'expedice'];

    if (empty($kategorie) || !in_array($kategorie, $povolene_kategorie)) {
        echo "<h2 style='color:red; font-family:sans-serif;'>Chyba: Vyberte platnou kategorii...</h2>";
        header("Refresh: 3; url=../index.php?tab=fotogalerie");
        exit; 
    }
    
    //cest apro uloeni na server
    $rel_dir = "uploads/" . $rok . "/" . $kategorie . "/";
    $target_dir = __DIR__ . "/../" . $rel_dir; 

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $pocet_souboru = count($_FILES["fotka"]["name"]);
    $uspesne = 0;

    //vlozeni do databze
    $stmt = $conn->prepare("INSERT INTO fotky (nazev_souboru, rok, popis, datum_nahrani) VALUES (?, ?, ?, NOW())");

    //povolene pripony
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    for ($i = 0; $i < $pocet_souboru; $i++) {
        if ($_FILES["fotka"]["error"][$i] !== UPLOAD_ERR_OK) continue;

        $tmp_name = $_FILES["fotka"]["tmp_name"][$i];
        $original_name = basename($_FILES["fotka"]["name"][$i]);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        //bezpecnosti kontrola
        $check = getimagesize($tmp_name);

        if ($check !== false && in_array($file_ext, $allowed_ext)) {
            
            //vytvoreni nazvu swouboru
            $new_file_name = bin2hex(random_bytes(4)) . "_" . time() . "_" . $i . "." . $file_ext;
            
            $target_file = $target_dir . $new_file_name; 
            $db_path = $rel_dir . $new_file_name;

            if (move_uploaded_file($tmp_name, $target_file)) {
                //bepecne ulozeni do db
                $stmt->bind_param("sis", $db_path, $rok, $kategorie);
                
                if ($stmt->execute()) {
                    $uspesne++;
                }
            }
        }
    }

    $stmt->close();
    $conn->close();

    echo "<h2 style='color:green; font-family:sans-serif;'>Nahráno souborů: $uspesne. Přesměrovávám...</h2>";
    header("Refresh: 2; url=../index.php?tab=fotogalerie&rok=$rok&kat=$kategorie");
} else {
    header("Location: ../index.php?tab=fotogalerie");
    exit;
}