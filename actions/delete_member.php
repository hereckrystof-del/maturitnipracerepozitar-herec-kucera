<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

//databze
require_once __DIR__ . '/../../config/config.php';

//id
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: ../index.php?tab=kontakt&error=invalid_id");
    exit;
}

if ($conn) {
    //nazev
    $stmt_select = $conn->prepare("SELECT fotka FROM clenove WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($row = $result->fetch_assoc()) {
        $nazevFotky = $row['fotka'];

        //smazani z serveru
        if (!empty($nazevFotky) && $nazevFotky !== 'default.jpg') {
            $file_path = __DIR__ . '/../uploads/Clenove/' . $nazevFotky;
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        //smazani databaze
        $stmt_delete = $conn->prepare("DELETE FROM clenove WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        
        if ($stmt_delete->execute()) {
            $stmt_delete->close();
            $stmt_select->close();
            $conn->close();
            header("Location: ../index.php?tab=kontakt&success=member_deleted");
            exit;
        }
    }
    
    $stmt_select->close();
    $conn->close();
}

header("Location: ../index.php?tab=kontakt&error=database");
exit;