<?php
include '../../config/db.php';

$order_id = intval($_POST['order_id']);
$user_id = intval($_POST['user_id']);
$type = $_POST['type'];

if ($type == 'pickup') {
    $field = 'pickup_shipper_id';
    $status = 'assigned_pickup';
} else {
    $field = 'delivery_shipper_id';
    $status = 'assigned_delivery';
}

$stmt = $conn->prepare("
    UPDATE emslss_orders
    SET $field=?, status=?
    WHERE id=?
");

$stmt->bind_param("isi", $user_id, $status, $order_id);
$stmt->execute();

echo json_encode([
    'success'=>true
]);
?>