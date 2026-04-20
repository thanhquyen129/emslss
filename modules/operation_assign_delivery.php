<?php

session_start();
require_once __DIR__ . "/../config/db.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] != 'operation' && $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$shipper_id = intval($_POST['shipper_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);

if ($order_id <= 0 || $shipper_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order_id or shipper_id']);
    exit;
}

try {
    $conn->begin_transaction();

    $up = $conn->prepare("
        UPDATE emslss_orders
        SET delivery_shipper_id = ?, status = 'assigned_delivery', updated_at = NOW()
        WHERE id = ?
    ");
    $up->bind_param("ii", $shipper_id, $order_id);
    $up->execute();

    $note = 'Operation assigned delivery shipper';
    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
        VALUES(?, 'assigned_delivery', ?, ?, NOW())
    ");
    $tr->bind_param("isi", $order_id, $note, $user_id);
    $tr->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Assign failed']);
}