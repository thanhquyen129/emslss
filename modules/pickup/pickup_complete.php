<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_GET['id'])) {
    die("Thiếu ID đơn");
}

$order_id = intval($_GET['id']);

$sql = "
    SELECT *
    FROM emslss_orders
    WHERE id = $order_id
    LIMIT 1
";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Không tìm thấy đơn");
}

$order = $result->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Count images
|--------------------------------------------------------------------------
*/

$img_sql = "
    SELECT COUNT(*) as total
    FROM emslss_images
    WHERE order_id = $order_id
";

$img_result = $conn->query($img_sql);
$img_count = $img_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Pickup Complete</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}

.box{
    background:white;
    border-radius:16px;
    padding:22px;
    box-shadow:0 4px 14px rgba(0,0,0,.07);
    margin-bottom:16px;
}

.success-icon{
    font-size:52px;
}

.btn-action{
    border-radius:12px;
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="box text-center">
        <div class="success-icon">✅</div>
        <h4 class="mt-3">Pickup thành công</h4>
        <p class="text-muted">Đơn hàng đã được ghi nhận vào hệ thống</p>
    </div>

    <div class="box">
        <div class="mb-3">
            <small class="text-muted">Mã EMS</small>
            <div><strong><?= htmlspecialchars($order['ems_code']) ?></strong></div>
        </div>

        <div class="mb-3">
            <small class="text-muted">Bưu cục</small>
            <div><strong><?= htmlspecialchars($order['post_office_name']) ?></strong></div>
        </div>

        <div class="mb-3">
            <small class="text-muted">Ảnh đã lưu</small>
            <div><strong><?= $img_count ?> ảnh</strong></div>
        </div>

        <div class="mb-3">
            <small class="text-muted">Trạng thái hiện tại</small>
            <div><strong><?= htmlspecialchars($order['status']) ?></strong></div>
        </div>
    </div>

    <div class="d-grid gap-2">

        <a href="pickup_dashboard.php" class="btn btn-primary btn-lg btn-action">
            🚚 Về dashboard pickup
        </a>

        <a href="/modules/operation/receive.php?id=<?= $order_id ?>" class="btn btn-outline-success btn-action">
            📦 Bàn giao sang operation
        </a>

    </div>

</div>

</body>
</html>