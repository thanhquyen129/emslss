<?php

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['operation', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$orderIds = $_POST['order_ids'] ?? [];
if (!is_array($orderIds)) {
    $orderIds = [];
}
$orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
$maxBulk = 100;
if (count($orderIds) > $maxBulk) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Chọn tối đa ' . $maxBulk . ' đơn mỗi lần']);
    exit;
}
if ($orderIds === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Chưa chọn đơn nào']);
    exit;
}

$processed = 0;
$skipped = [];

$checkStmt = $conn->prepare('SELECT id, ems_code, status FROM emslss_orders WHERE id = ? LIMIT 1');
$updateStmt = $conn->prepare("
    UPDATE emslss_orders
    SET status = 'in_transit', updated_at = NOW()
    WHERE id = ? AND status = 'picked_up'
");
$trackStmt = $conn->prepare("
    INSERT INTO emslss_tracking(order_id, status, note, created_by, created_at)
    VALUES(?, 'in_transit', 'Operation đã nhận hàng (hàng loạt)', ?, NOW())
");

foreach ($orderIds as $orderId) {
    $checkStmt->bind_param('i', $orderId);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    if (!$row) {
        $skipped[] = ['id' => $orderId, 'reason' => 'Không tìm thấy đơn'];
        continue;
    }
    if ($row['status'] !== 'picked_up') {
        $skipped[] = ['id' => $orderId, 'ems_code' => $row['ems_code'], 'reason' => 'Trạng thái không phải picked_up'];
        continue;
    }

    try {
        $conn->begin_transaction();

        $updateStmt->bind_param('i', $orderId);
        $updateStmt->execute();
        if ($updateStmt->affected_rows === 0) {
            throw new RuntimeException('Không cập nhật được đơn');
        }

        $trackStmt->bind_param('ii', $orderId, $user_id);
        $trackStmt->execute();

        $conn->commit();
        $processed++;
    } catch (Throwable $e) {
        $conn->rollback();
        $skipped[] = ['id' => $orderId, 'ems_code' => $row['ems_code'], 'reason' => 'Lỗi xử lý'];
    }
}

echo json_encode([
    'success' => $processed > 0,
    'processed' => $processed,
    'skipped' => count($skipped),
    'skipped_detail' => $skipped,
    'message' => $processed > 0
        ? 'Đã nhập kho ' . $processed . ' đơn' . (count($skipped) > 0 ? ' · bỏ qua ' . count($skipped) . ' đơn' : '')
        : 'Không nhập kho được đơn nào',
], JSON_UNESCAPED_UNICODE);
