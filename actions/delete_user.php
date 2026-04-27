<?php
session_start();

//prihlaseny admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

//databaze
require_once __DIR__ . '/../../config/config.php';

//zabezpeceni
if (isset($_GET['login']) && !empty($_GET['login'])) {
    $user_to_delete = $_GET['login'];

    //nesmis smazat sam sebe
    if ($user_to_delete === $_SESSION['user']) {
        header("Location: ../index.php?error=self_delete");
        exit;
    }

    if ($conn) {
        //sql
        $stmt = $conn->prepare("DELETE FROM uzivatele WHERE login = ?");
        $stmt->bind_param("s", $user_to_delete);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: ../index.php?success=user_deleted");
            exit;
        } else {
            //error v databaizi
            header("Location: ../index.php?error=db_error");
            exit;
        }
    }
} else {
    //back na halvi stranz
    header("Location: ../index.php");
    exit;
}