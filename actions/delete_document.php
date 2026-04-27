<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

//databaze
require_once __DIR__ . '/../../config/config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    //nazev s
    $stmt_select = $conn->prepare("SELECT filename FROM dokumenty WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($row = $result->fetch_assoc()) {
        $filename = $row['filename'];
        $file_path = "../uploads/Dokumenty/" . $filename;

        //smazani serveru
        if (!empty($filename) && file_exists($file_path)) {
            unlink($file_path);
        }

        //smazani z databaze
        $stmt_delete = $conn->prepare("DELETE FROM dokumenty WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        
        if ($stmt_delete->execute()) {
            $stmt_delete->close();
        }
    }
    $stmt_select->close();
}

$conn->close();

//back na kontakt
header("Location: ../index.php?tab=kontakt&success=document_deleted");
exit();