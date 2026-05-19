<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';

$order_id = (int) ($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    header('Location: order_detail.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 1);
$paths = emslss_upload_save_multipart_field('misc', $_FILES['image'] ?? []);
emslss_upload_insert_images($conn, $order_id, $paths, $userId);

header('Location: order_detail.php?id=' . $order_id);
exit;
