<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$image_id = (int) ($_POST['image_id'] ?? $_GET['image_id'] ?? 0);
$order_id = (int) ($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

if ($image_id <= 0 || $order_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

if ($role === 'shipper') {
    $chk = $conn->prepare('
        SELECT id FROM emslss_orders
        WHERE id=? AND (pickup_shipper_id=? OR delivery_shipper_id=?)
        LIMIT 1
    ');
    $chk->bind_param('iii', $order_id, $user_id, $user_id);
} elseif (in_array($role, ['admin', 'operation'], true)) {
    $chk = $conn->prepare('SELECT id FROM emslss_orders WHERE id=? LIMIT 1');
    $chk->bind_param('i', $order_id);
} else {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if (emslss_upload_delete_image($conn, $image_id, $order_id)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Image not found']);
}
