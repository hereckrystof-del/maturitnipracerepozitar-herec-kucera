<?php
session_start(['cookie_lifetime' => 0]);

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}
//databaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        header("Location: ../index.php?baner_error=empty");
        exit;
    }

    $imageName = null;

    //nahravani obrazku
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/Banery/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $fileType = mime_content_type($_FILES['image']['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            header("Location: ../index.php?baner_error=filetype");
            exit;
        }

        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            header("Location: ../index.php?baner_error=filesize");
            exit;
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageName = uniqid('baner_', true) . '.' . $ext;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
            header("Location: ../index.php?baner_error=upload");
            exit;
        }
    }

    //ulozeni do databaze
    $stmt = $conn->prepare("INSERT INTO baner (content, image) VALUES (?, ?)");
    $stmt->bind_param("ss", $content, $imageName);

    if ($stmt->execute()) {
        header("Location: ../index.php?baner_success=1");
    } else {
        header("Location: ../index.php?baner_error=db");
    }

    $stmt->close();
    exit;
}

header("Location: ../index.php");
exit;
