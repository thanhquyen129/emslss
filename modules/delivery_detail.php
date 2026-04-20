<?php
$id = intval($_GET['id'] ?? 0);
header("Location: /modules/shipper/delivery_detail.php?id=" . $id);
exit;
?>