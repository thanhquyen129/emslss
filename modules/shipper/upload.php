<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

$order_id = intval($_POST['order_id']);

if (!isset($_FILES['image'])) die("No file");

$target_dir = "../../uploads/";
$filename = time() . "_" . basename($_FILES["image"]["name"]);
$target_file = $target_dir . $filename;

move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);

// Save DB
$conn->query("
    INSERT INTO emslss_images (order_id, image_path, uploaded_by)
    VALUES ($order_id, '$filename', ".$_SESSION['user_id'].")
");

echo "OK";