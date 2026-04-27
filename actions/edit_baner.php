<?php
session_start(['cookie_lifetime' => 0]);

//uzivatel
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = intval($_POST['id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if ($id <= 0 || empty($content)) {
        header("Location: ../index.php?baner_error=empty");
        exit;
    }

    //nacteni zaznamu
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
    $imageName = $row['image'];

    //novy obr
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
        $newImageName = uniqid('baner_', true) . '.' . $ext;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newImageName)) {
            //smazani staryho obr
            if (!empty($imageName) && file_exists($uploadDir . $imageName)) {
                unlink($uploadDir . $imageName);
            }
            $imageName = $newImageName;
        } else {
            header("Location: ../index.php?baner_error=upload");
            exit;
        }
    }

    //aktualizac ezaznamu
    $stmt = $conn->prepare("UPDATE baner SET content = ?, image = ? WHERE id = ?");
    $stmt->bind_param("ssi", $content, $imageName, $id);

    if ($stmt->execute()) {
        header("Location: ../index.php?baner_success=2");
    } else {
        header("Location: ../index.php?baner_error=db");
    }

    $stmt->close();
    exit;
}

header("Location: ../index.php");
exit;
