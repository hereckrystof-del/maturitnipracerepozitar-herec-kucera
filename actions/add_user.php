<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

//databaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    //nacteni dat
    $new_login = $_POST['new_login'] ?? '';
    $new_role  = $_POST['new_role']  ?? 'editor';
    $password_raw = $_POST['new_password'] ?? '';

    if (empty($new_login) || empty($password_raw)) {
        header("Location: ../index.php?error=empty_fields");
        exit;
    }

    //kontreola kdyz by uzivael extistoval
    $check_stmt = $conn->prepare("SELECT login FROM `uzivatele` WHERE login = ?");
    $check_stmt->bind_param("s", $new_login);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result && $check_result->num_rows > 0) {
        $check_stmt->close();
        header("Location: ../index.php?error=user_exists");
        exit;
    }
    $check_stmt->close();

    //hashovanii hesel
    $new_password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

    //vlozeni noveho uzi
    $stmt = $conn->prepare("INSERT INTO `uzivatele` (login, heslo, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $new_login, $new_password_hash, $new_role);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: ../index.php?success=user_added");
        exit;
    } else {
        header("Location: ../index.php?error=db_error");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}