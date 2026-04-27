<?php
session_start();

//prihlaseny uzivatel
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

//dataaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    //nacteni dat
    $jmeno   = $_POST['jmeno'] ?? '';
    $pozice  = $_POST['pozice'] ?? '';
    $email   = $_POST['email'] ?? '';
    $telefon = $_POST['telefon'] ?? '';
    $zajmy   = $_POST['zajmy'] ?? '';

    //kontrola
    if (empty($jmeno)) {
        header("Location: ../index.php?tab=kontakt&error=missing_fields");
        exit;
    }

    //nahravani fotek
    $nazevFotky = 'default.jpg'; 

    //bezpecny upload
    if (isset($_FILES['fotka']) && $_FILES['fotka']['error'] === UPLOAD_ERR_OK && !empty($_FILES['fotka']['name'])) {
        $file = $_FILES['fotka'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif'];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        //kontrola
        if (in_array($ext, $allowedExts) && in_array($mime, $allowedMime)) {
            
            //nazev souboru
            $filename = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            
            //cesta
            $uploadDir = __DIR__ . '/../uploads/Clenove/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $nazevFotky = $filename;
            } else {
                header("Location: ../index.php?tab=kontakt&error=upload_failed");
                exit;
            }
        } else {
            header("Location: ../index.php?tab=kontakt&error=invalid_file_type");
            exit;
        }
    }

    //SQL
    $stmt = $conn->prepare("INSERT INTO clenove (jmeno, pozice, email, telefon, zajmy, fotka, poradi) VALUES (?, ?, ?, ?, ?, ?, 0)");
    
    //ssssss 6x text
    $stmt->bind_param("ssssss", $jmeno, $pozice, $email, $telefon, $zajmy, $nazevFotky);

    if ($stmt->execute()) {
        header("Location: ../index.php?tab=kontakt&success=member_added");
    } else {
        header("Location: ../index.php?tab=kontakt&error=database_insert_failed");
    }

    $stmt->close();
}

$conn->close();
?>