<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/order_helpers.php';
require_once __DIR__ . '/../../api/callback_cancel.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$order_id = (int) ($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$reason_detail = trim($_POST['reason_detail'] ?? '');

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn']);
    exit;
}

$allowedReasons = emslss_order_reject_reasons();
if ($reason === '' || !in_array($reason, $allowedReasons, true)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn lý do từ chối']);
    exit;
}

if ($reason === 'Lý do khác' && $reason_detail === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập chi tiết lý do']);
    exit;
}

$fullReason = $reason;
if ($reason_detail !== '') {
    $fullReason .= ($reason === 'Lý do khác' ? ': ' : ' — ') . $reason_detail;
}

$ordStmt = $conn->prepare('SELECT id, ems_code, status FROM emslss_orders WHERE id=? LIMIT 1');
$ordStmt->bind_param('i', $order_id);
$ordStmt->execute();
$order = $ordStmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn']);
    exit;
}

if (!emslss_admin_can_reject_order((string) $order['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Chỉ từ chối được đơn ở trạng thái new_order hoặc assigned_pickup (chưa thu gom)',
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    $lock = $conn->prepare('SELECT status FROM emslss_orders WHERE id=? FOR UPDATE');
    $lock->bind_param('i', $order_id);
    $lock->execute();
    $current = $lock->get_result()->fetch_assoc();
    if (!$current || !emslss_admin_can_reject_order((string) $current['status'])) {
        throw new RuntimeException('Đơn không còn ở trạng thái cho phép từ chối');
    }

    $newStatus = 'cancelled';
    $up = $conn->prepare("
        UPDATE emslss_orders
        SET status=?, pickup_shipper_id=NULL, delivery_shipper_id=NULL, updated_at=NOW()
        WHERE id=?
    ");
    $up->bind_param('si', $newStatus, $order_id);
    $up->execute();

    $metaStmt = $conn->prepare('INSERT INTO emslss_order_meta(order_id, meta_key, meta_value) VALUES(?,?,?)');
    $pairs = [
        ['lss_reject_reason', $fullReason],
        ['lss_reject_by', (string) $user_id],
        ['lss_reject_at', date('Y-m-d H:i:s')],
    ];
    foreach ($pairs as [$key, $val]) {
        $metaStmt->bind_param('iss', $order_id, $key, $val);
        $metaStmt->execute();
    }

    $trackNote = 'LSS từ chối nhận đơn: ' . $fullReason;
    $tr = $conn->prepare('INSERT INTO emslss_tracking(order_id, status, note, created_by) VALUES(?,?,?,?)');
    $tr->bind_param('issi', $order_id, $newStatus, $trackNote, $user_id);
    $tr->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$cb = sendCancelCallback($order_id, ['reason' => $fullReason]);

echo json_encode([
    'success' => true,
    'message' => 'Đã từ chối nhận đơn' . ($cb['success'] ? '' : ' (callback EMS thất bại, xem tracking)'),
    'callback_ok' => $cb['success'],
]);
