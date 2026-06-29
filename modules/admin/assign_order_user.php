<?php
session_start();
include '../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);
$type = $_POST['type'] ?? '';

if ($order_id <= 0 || !in_array($type, ['pickup', 'delivery'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$ordStmt = $conn->prepare('SELECT id, status, delivery_shipper_id FROM emslss_orders WHERE id=? LIMIT 1');
$ordStmt->bind_param('i', $order_id);
$ordStmt->execute();
$order = $ordStmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$currentStatus = (string) $order['status'];

if ($type === 'pickup') {
  $field = 'pickup_shipper_id';
  $status = 'assigned_pickup';
  $note = 'Assigned pickup shipper';
  $allowed = ['new_order', 'assigned_pickup', 'picked_up'];
  if ($user_id > 0 && !in_array($currentStatus, $allowed, true)) {
      echo json_encode(['success' => false, 'message' => 'Không thể gán pickup ở trạng thái ' . $currentStatus]);
      exit;
  }
} else {
  $field = 'delivery_shipper_id';
  $status = 'assigned_delivery';
  $note = 'Assigned delivery shipper';
  $canDelivery = in_array($currentStatus, ['in_transit', 'assigned_delivery'], true)
      || ($currentStatus === 'failed' && (int)($order['delivery_shipper_id'] ?? 0) > 0);
  if ($user_id > 0 && !$canDelivery) {
      echo json_encode(['success' => false, 'message' => 'Cần Operation nhập kho (in_transit) trước khi gán delivery']);
      exit;
  }
  if ($user_id <= 0) {
      echo json_encode(['success' => false, 'message' => 'Không thể bỏ gán delivery']);
      exit;
  }
}

if ($user_id > 0) {
    if (!emslss_user_has_role_code($conn, $user_id, 'shipper')) {
        echo json_encode(['success' => false, 'message' => 'User không có role shipper']);
        exit;
    }
    $active = $conn->prepare('SELECT id FROM emslss_users WHERE id=? AND is_active=1 LIMIT 1');
    $active->bind_param('i', $user_id);
    $active->execute();
    if (!$active->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Shipper không hoạt động']);
        exit;
    }
}

try {
    $conn->begin_transaction();

    if ($user_id > 0) {
        $stmt = $conn->prepare("UPDATE emslss_orders SET $field=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('isi', $user_id, $status, $order_id);
    } else {
        $stmt = $conn->prepare("UPDATE emslss_orders SET $field=NULL, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $order_id);
    }
    $stmt->execute();

    if ($user_id > 0) {
        $admin_id = (int) $_SESSION['user_id'];
        $tr = $conn->prepare('INSERT INTO emslss_tracking(order_id,status,note,created_by) VALUES(?,?,?,?)');
        $tr->bind_param('issi', $order_id, $status, $note, $admin_id);
        $tr->execute();
    }

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Assign failed']);
}
