<?php
include '../config/db.php';

$order_id = intval($_POST['order_id']);
$status = $_POST['status'];

$stmt = $conn->prepare("
UPDATE emslss_orders
SET status=?
WHERE id=?
");

$stmt->bind_param("si",$status,$order_id);
$stmt->execute();

$stmt2 = $conn->prepare("
INSERT INTO emslss_tracking(order_id,status,note,created_by)
VALUES(?,?,?,1)
");

$note='manual update';

$stmt2->bind_param("iss",$order_id,$status,$note);
$stmt2->execute();

header("Location: order_detail.php?id=".$order_id);
exit;
?>