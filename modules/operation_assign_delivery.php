<?php

session_start();
require_once "config/db.php";

if($_SESSION['role']!='operation' && $_SESSION['role']!='admin'){
    die("Access denied");
}

$order_id = intval($_POST['order_id']);
$shipper_id = intval($_POST['shipper_id']);
$user_id = $_SESSION['user_id'];

$conn->query("
UPDATE emslss_orders
SET delivery_shipper_id='$shipper_id',
status='assigned_delivery'
WHERE id='$order_id'
");

$conn->query("
INSERT INTO emslss_tracking
(order_id,status,note,created_by)
VALUES
('$order_id','assigned_delivery','Operation assigned delivery shipper','$user_id')
");

echo "OK";