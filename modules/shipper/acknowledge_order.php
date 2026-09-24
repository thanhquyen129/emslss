<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'shipper') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$order_id = (int) ($_POST['order_id'] ?? 0);
$type = $_POST['type'] ?? '';
$user_id = (int) $_SESSION['user_id'];
$full_name = trim((string) ($_SESSION['full_name'] ?? 'Shipper'));

if ($order_id <= 0 || !in_array($type, ['pickup', 'delivery'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM emslss_orders WHERE id=? LIMIT 1');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn']);
    exit;
}

if ($type === 'pickup') {
    if ((int) $order['pickup_shipper_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Không phải đơn pickup của bạn']);
        exit;
    }
    $metaKey = 'shipper_ack_pickup';
    $trackNote = 'Shipper đã nhận thông tin đơn pickup';
} else {
    if ((int) $order['delivery_shipper_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Không phải đơn delivery của bạn']);
        exit;
    }
    $metaKey = 'shipper_ack_delivery';
    $trackNote = 'Shipper đã nhận thông tin đơn delivery';
}

$chk = $conn->prepare('SELECT id FROM emslss_order_meta WHERE order_id=? AND meta_key=? LIMIT 1');
$chk->bind_param('is', $order_id, $metaKey);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    echo json_encode(['success' => true, 'message' => 'Đã nhận thông tin trước đó']);
    exit;
}

$value = $full_name . ' — ' . date('Y-m-d H:i:s');

try {
    $conn->begin_transaction();

    $ins = $conn->prepare('INSERT INTO emslss_order_meta(order_id, meta_key, meta_value) VALUES(?,?,?)');
    $ins->bind_param('iss', $order_id, $metaKey, $value);
    $ins->execute();

    $tr = $conn->prepare('INSERT INTO emslss_tracking(order_id, status, note, created_by) VALUES(?, ?, ?, ?)');
    $ackStatus = 'shipper_ack';
    $tr->bind_param('issi', $order_id, $ackStatus, $trackNote, $user_id);
    $tr->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Đã xác nhận nhận thông tin']);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
