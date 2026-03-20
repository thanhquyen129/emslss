<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];

$order_id = intval($_POST['order_id']);
$scanned_code = $_POST['scanned_code'];
$lat = $_POST['lat'];
$lng = $_POST['lng'];

$order = $conn->query("SELECT * FROM emslss_orders WHERE id = $order_id")->fetch_assoc();

if (!$order) die("Order not found");

// Validate EMS code
if ($scanned_code != $order['ems_code']) {
    die("Sai mã EMS");
}

// Determine next status
if ($order['status'] == 'assigned_pickup') {
    $new_status = 'picked_up';
} elseif ($order['status'] == 'assigned_delivery') {
    $new_status = 'delivered';
} else {
    die("Invalid status");
}

// Update order
$conn->query("
    UPDATE emslss_orders 
    SET status = '$new_status'
    WHERE id = $order_id
");

// Save tracking (kèm GPS)
$note = "GPS: $lat,$lng";

$conn->query("
    INSERT INTO emslss_tracking (order_id, status, note, created_by)
    VALUES ($order_id, '$new_status', '$note', $user_id)
");

header("Location: shipper_dashboard.php");
exit;