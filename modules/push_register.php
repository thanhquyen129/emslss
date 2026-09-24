<?php
/**
 * Đăng ký thiết bị push (session bắt buộc).
 * POST JSON: { subscription_id?: string, platform?: string }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/onesignal.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? '');
$platform = preg_replace('/[^a-z0-9_]/i', '', (string)($data['platform'] ?? 'android')) ?: 'android';
$subscriptionId = trim((string)($data['subscription_id'] ?? ''));
if (strlen($subscriptionId) > 64) {
    $subscriptionId = substr($subscriptionId, 0, 64);
}

emslss_ensure_push_devices_table($conn);

$stmt = $conn->prepare("
    INSERT INTO emslss_push_devices (user_id, onesignal_subscription_id, platform, role_code)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        onesignal_subscription_id = VALUES(onesignal_subscription_id),
        role_code = VALUES(role_code),
        updated_at = CURRENT_TIMESTAMP
");
$subVal = $subscriptionId !== '' ? $subscriptionId : '';
$stmt->bind_param('isss', $userId, $subVal, $platform, $role);
$ok = $stmt->execute();

echo json_encode([
    'status' => $ok ? 'success' : 'error',
    'user_id' => $userId,
    'role' => $role,
], JSON_UNESCAPED_UNICODE);
