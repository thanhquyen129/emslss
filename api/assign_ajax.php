<?php
include '../config/db.php';

$id = intval($_POST['id']);
$shipper = intval($_POST['shipper_id']);

$stmt = $conn->prepare("
UPDATE emslss_orders
SET shipper_id=?, status='assigned'
WHERE id=?
");

$stmt->bind_param("ii",$shipper,$id);
$stmt->execute();

$stmt2 = $conn->prepare("
INSERT INTO emslss_tracking(order_id,status,note,created_by)
VALUES(?,?,?,1)
");

$status='assigned';
$note='assigned from dashboard';

$stmt2->bind_param("iss",$id,$status,$note);
$stmt2->execute();

header("Location: ../modules/dashboard.php?filter=processing");
exit;
?>