<?php
session_start();

//prihlaseni uzivatele
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}
//databaze
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    
    if (empty($title)) {
        header("Location: ../index.php?tab=kontakt&error=missing_title");
        exit();
    }

    if (isset($_FILES['dokument']) && $_FILES['dokument']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/../uploads/Dokumenty/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }

        $file_name = $_FILES['dokument']['name'];
        $file_tmp  = $_FILES['dokument']['tmp_name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'odt', 'ods', 'odp', 'odg'];

        //zabezpeceni
        $is_valid_mime = false;
        if (class_exists('finfo')) {
            $finfo = new finfo(defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : 16);
            $mime = $finfo->file($file_tmp);
            
            $allowed_mimes = [
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv', 'text/plain'
            ];
            if (in_array($mime, $allowed_mimes)) { $is_valid_mime = true; }
        } else {
            $is_valid_mime = true; 
        }

        if (in_array($file_ext, $allowed_ext) && $is_valid_mime) {
            $clean_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file_name, PATHINFO_FILENAME));
            $new_filename = bin2hex(random_bytes(4)) . "_" . $clean_filename . "." . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $stmt = $conn->prepare("INSERT INTO dokumenty (title, filename) VALUES (?, ?)");
                $stmt->bind_param("ss", $title, $new_filename);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();
                    header("Location: ../index.php?tab=kontakt&success=document_added");
                    exit();
                } else {
                    unlink($target_file);
                    header("Location: ../index.php?tab=kontakt&error=db_error");
                    exit();
                }
            }
        } else {
            header("Location: ../index.php?tab=kontakt&error=invalid_file_type");
            exit();
        }
    }
}
header("Location: ../index.php?tab=kontakt");
exit();