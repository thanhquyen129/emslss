<?php
require_once __DIR__ . '/../../config/db.php';

$id = intval($_GET['id']);

$conn->query("
    UPDATE emslss_users
    SET is_active = IF(is_active=1,0,1)
    WHERE id=$id
");

header("Location: admin_users.php");
exit;