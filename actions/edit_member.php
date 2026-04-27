<?php
session_start();

//jenom editor muze upravovat
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

//nacteni z databaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $jmeno = $_POST['jmeno'] ?? '';
    $pozice = $_POST['pozice'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefon = $_POST['telefon'] ?? '';
    $zajmy = $_POST['zajmy'] ?? '';

    if ($id <= 0 || empty($jmeno)) {
        header("Location: ../index.php?tab=kontakt&error=missing_fields");
        exit;
    }

    //nacteni fotky (prepared statements)
    $stmt_select = $conn->prepare("SELECT fotka FROM clenove WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $member = $result->fetch_assoc();
    $aktualniFotka = $member['fotka'] ?? 'default.jpg';
    $stmt_select->close();

    //nahravani nove fotky
    //kontrola jestli je na serveru
    if (isset($_FILES['fotka']) && $_FILES['fotka']['error'] === UPLOAD_ERR_OK && !empty($_FILES['fotka']['name'])) {
        $file = $_FILES['fotka'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif'];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (in_array($ext, $allowedExts) && in_array($mime, $allowedMime)) {
            
            //delete stare fotky z disku
            if ($aktualniFotka !== 'default.jpg') {
                $old_file_path = __DIR__ . '/../uploads/Clenove/' . $aktualniFotka;
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }

            //ulozeni nove fotky s unique name
            $filename = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            
            $uploadDir = __DIR__ . '/../uploads/Clenove/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $aktualniFotka = $filename;
            } else {
                header("Location: ../index.php?tab=kontakt&error=upload_failed");
                exit;
            }
        } else {
            header("Location: ../index.php?tab=kontakt&error=invalid_file_type");
            exit;
        }
    }

    //bezpecny update (prepared statement)
    $stmt_update = $conn->prepare("UPDATE clenove SET jmeno = ?, pozice = ?, email = ?, telefon = ?, zajmy = ?, fotka = ? WHERE id = ?");
    $stmt_update->bind_param("ssssssi", $jmeno, $pozice, $email, $telefon, $zajmy, $aktualniFotka, $id);

    if ($stmt_update->execute()) {
        header("Location: ../index.php?tab=kontakt&success=member_updated");
    } else {
        header("Location: ../index.php?tab=kontakt&error=database");
    }

    $stmt_update->close();
}

$conn->close();
?>