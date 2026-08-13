<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare('UPDATE emslss_users SET is_active = IF(is_active=1,0,1) WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

header('Location: admin_users.php');
exit;
