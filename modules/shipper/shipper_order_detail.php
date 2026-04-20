<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_GET['id'])) {
    die("Thiếu ID đơn hàng");
}

$order_id = intval($_GET['id']);
$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'];

/*
|--------------------------------------------------------------------------
| Query đơn hàng
|--------------------------------------------------------------------------
*/

if ($role == 'shipper') {
    $sql = "
        SELECT *
        FROM emslss_orders
        WHERE id = $order_id
        AND pickup_shipper_id = $user_id
        LIMIT 1
    ";
} else {
    $sql = "
        SELECT *
        FROM emslss_orders
        WHERE id = $order_id
        LIMIT 1
    ";
}

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Không tìm thấy đơn hàng");
}

$order = $result->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Tracking gần nhất
|--------------------------------------------------------------------------
*/

$tracking_sql = "
    SELECT *
    FROM emslss_tracking
    WHERE order_id = $order_id
    ORDER BY created_at DESC
    LIMIT 5
";

$tracking_result = $conn->query($tracking_sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết Pickup</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}

.box{
    background:white;
    border-radius:16px;
    padding:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.07);
    margin-bottom:16px;
}

.label{
    font-size:13px;
    color:#777;
}

.value{
    font-weight:600;
    font-size:15px;
}

.section-title{
    font-size:15px;
    font-weight:700;
    margin-bottom:12px;
}

.btn-action{
    border-radius:12px;
}

.timeline-item{
    border-left:3px solid #0d6efd;
    padding-left:12px;
    margin-bottom:12px;
}

.timeline-time{
    font-size:12px;
    color:#777;
}

.header-code{
    font-size:20px;
    font-weight:700;
}

.status-badge{
    padding:6px 10px;
    border-radius:20px;
    font-size:13px;
    background:#cfe2ff;
    color:#084298;
}
</style>
</head>
<body>

<div class="container py-3">

    <div class="box">

        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <div class="header-code">
                    <a href="/modules/admin/admin_order_detail.php?id=<?= intval($order['id']) ?>">
                        <?= htmlspecialchars($order['ems_code']) ?>
                    </a>
                </div>
                <span class="status-badge"><?= htmlspecialchars($order['status']) ?></span>
            </div>
            <small class="text-muted">
                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
            </small>
        </div>

    </div>

    <div class="box">
        <div class="section-title">📍 Thông tin pickup</div>

        <div class="mb-3">
            <div class="label">Bưu cục</div>
            <div class="value"><?= htmlspecialchars($order['post_office_name']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Địa chỉ bưu cục</div>
            <div class="value"><?= htmlspecialchars($order['post_office_address']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Người giữ thư</div>
            <div class="value"><?= htmlspecialchars($order['holder_name']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Số điện thoại</div>
            <div class="value"><?= htmlspecialchars($order['holder_phone']) ?></div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <a href="tel:<?= htmlspecialchars($order['holder_phone']) ?>" class="btn btn-success btn-action w-50">
                📞 Gọi ngay
            </a>

            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($order['post_office_address']) ?>" 
               target="_blank"
               class="btn btn-outline-primary btn-action w-50">
                🧭 Mở map
            </a>
        </div>
    </div>

    <div class="box">
        <div class="section-title">📦 Thông tin hàng</div>

        <div class="mb-3">
            <div class="label">Người gửi</div>
            <div class="value"><?= htmlspecialchars($order['sender_name']) ?> | <?= htmlspecialchars($order['sender_phone']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Người nhận</div>
            <div class="value"><?= htmlspecialchars($order['receiver_name']) ?> | <?= htmlspecialchars($order['receiver_phone']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Khối lượng</div>
            <div class="value"><?= htmlspecialchars($order['weight']) ?> kg</div>
        </div>

        <div class="mb-3">
            <div class="label">Loại hàng</div>
            <div class="value"><?= htmlspecialchars($order['cargo_type']) ?></div>
        </div>

        <div class="mb-3">
            <div class="label">Dịch vụ</div>
            <div class="value"><?= htmlspecialchars($order['service_type']) ?></div>
        </div>
    </div>

    <div class="box">
        <div class="section-title">🕒 Tracking gần nhất</div>

        <?php while($track = $tracking_result->fetch_assoc()): ?>
            <div class="timeline-item">
                <div><strong><?= htmlspecialchars($track['status']) ?></strong></div>
                <div><?= htmlspecialchars($track['note']) ?></div>
                <div class="timeline-time">
                    <?= date('d/m H:i', strtotime($track['created_at'])) ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="d-grid gap-2 pb-4">
        <?php if ($order['status'] === 'assigned_pickup'): ?>
            <a href="shipper_scan.php?id=<?= intval($order['id']) ?>" class="btn btn-primary btn-lg btn-action">
                📷 Pickup ngay
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-secondary btn-lg btn-action" disabled>
                Đã pickup
            </button>
        <?php endif; ?>

        <a href="shipper_dashboard.php" class="btn btn-outline-secondary btn-action">
            ← Quay lại dashboard
        </a>
    </div>

</div>

</body>
</html>