<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

$order_id = (int) ($_POST['order_id'] ?? 0);
if ($order_id <= 0 || empty($_FILES['image']['name'])) {
    die('No file');
}

$paths = emslss_upload_save_multipart_field('misc', $_FILES['image']);
if ($paths === []) {
    die('Upload failed');
}

emslss_upload_insert_image($conn, $order_id, $paths[0], (int) $_SESSION['user_id']);
echo 'OK';
