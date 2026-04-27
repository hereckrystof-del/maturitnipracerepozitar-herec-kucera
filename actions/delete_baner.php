<?php
session_start(['cookie_lifetime' => 0]);

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: ../index.php?baner_error=invalid");
    exit;
}

//nacteni pro smazani
$stmt = $conn->prepare("SELECT image FROM baner WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: ../index.php?baner_error=notfound");
    exit;
}

//smazani na serveru
if (!empty($row['image'])) {
    $uploadDir = __DIR__ . '/../uploads/Banery/';
    $filePath  = $uploadDir . $row['image'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

//smazani v databazi
$stmt = $conn->prepare("DELETE FROM baner WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: ../index.php?baner_success=3");
} else {
    header("Location: ../index.php?baner_error=db");
}

$stmt->close();
exit;
