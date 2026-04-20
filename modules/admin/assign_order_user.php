<?php
session_start();
include '../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$order_id = intval($_POST['order_id']);
$user_id = intval($_POST['user_id']);
$type = $_POST['type'] ?? '';

if (!in_array($type, ['pickup', 'delivery'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid type'
    ]);
    exit;
}

if ($type == 'pickup') {
    $field = 'pickup_shipper_id';
    $status = 'assigned_pickup';
    $note = 'Assigned pickup shipper';
} else {
    $field = 'delivery_shipper_id';
    $status = 'assigned_delivery';
    $note = 'Assigned delivery shipper';
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        UPDATE emslss_orders
        SET $field=?, status=?
        WHERE id=?
    ");
    $stmt->bind_param("isi", $user_id, $status, $order_id);
    $stmt->execute();

    $admin_id = intval($_SESSION['user_id'] ?? 0);
    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by)
        VALUES(?,?,?,?)
    ");
    $tr->bind_param("issi", $order_id, $status, $note, $admin_id);
    $tr->execute();

    $conn->commit();

    echo json_encode([
        'success' => true
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Assign failed'
    ]);
}
?>