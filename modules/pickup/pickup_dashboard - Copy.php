<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

date_default_timezone_set('Asia/Ho_Chi_Minh');

/*
|--------------------------------------------------------------------------
| Query logic
|--------------------------------------------------------------------------
| pickup shipper -> chỉ thấy đơn của mình
| admin/operation -> thấy toàn bộ để hỗ trợ khi cần
|--------------------------------------------------------------------------
*/

if ($role == 'shipper') {
    $sql = "
        SELECT *
        FROM emslss_orders
        WHERE pickup_shipper_id = $user_id
        ORDER BY created_at DESC
    ";
} else {
    $sql = "
        SELECT *
        FROM emslss_orders
        ORDER BY created_at DESC
    ";
}

$result = $conn->query($sql);

/*
|--------------------------------------------------------------------------
| Count trạng thái
|--------------------------------------------------------------------------
*/

$count_waiting = 0;
$count_progress = 0;
$count_done = 0;

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;

    if (in_array($row['status'], ['new_order', 'assigned_pickup'])) {
        $count_waiting++;
    } elseif ($row['status'] == 'picked_up') {
        $count_done++;
    } else {
        $count_progress++;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Pickup Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}

.header-box{
    background:white;
    padding:18px;
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,.06);
    margin-bottom:20px;
}

.order-card{
    border:none;
    border-radius:16px;
    box-shadow:0 4px 14px rgba(0,0,0,.08);
    margin-bottom:16px;
}

.status-badge{
    font-size:13px;
    padding:6px 10px;
    border-radius:20px;
}

.waiting{
    background:#fff3cd;
    color:#856404;
}

.progressing{
    background:#cfe2ff;
    color:#084298;
}

.done{
    background:#d1e7dd;
    color:#0f5132;
}

.info-label{
    font-size:13px;
    color:#777;
}

.info-value{
    font-weight:600;
}

.btn-action{
    border-radius:12px;
}

.tab-box{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    overflow-x:auto;
}

.tab-item{
    min-width:120px;
    padding:10px;
    border-radius:12px;
    text-align:center;
    background:white;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
    font-weight:600;
    font-size:14px;
}

@media(max-width:768px){
    .tab-item{
        min-width:100px;
        font-size:13px;
    }
}
</style>
</head>
<body>

<div class="container py-3">

    <div class="header-box">
        <h5 class="mb-1">Xin chào <?= htmlspecialchars($full_name) ?> 👋</h5>
        <small class="text-muted">Pickup Shipper Dashboard</small>
    </div>

    <div class="tab-box">
        <div class="tab-item">
            📥 Chờ pickup <br><strong><?= $count_waiting ?></strong>
        </div>
        <div class="tab-item">
            🚚 Đang xử lý <br><strong><?= $count_progress ?></strong>
        </div>
        <div class="tab-item">
            ✅ Hoàn tất <br><strong><?= $count_done ?></strong>
        </div>
    </div>

    <?php foreach($orders as $row): ?>

        <?php
        $badge = 'progressing';
        $statusText = 'Đang xử lý';

        if (in_array($row['status'], ['new_order','assigned_pickup'])) {
            $badge = 'waiting';
            $statusText = 'Chờ pickup';
        }

        if ($row['status'] == 'picked_up') {
            $badge = 'done';
            $statusText = 'Đã pickup';
        }
        ?>

        <div class="card order-card p-3">

            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="mb-1"><?= htmlspecialchars($row['ems_code']) ?></h6>
                    <span class="status-badge <?= $badge ?>">
                        <?= $statusText ?>
                    </span>
                </div>
                <small class="text-muted">
                    <?= date('d/m H:i', strtotime($row['created_at'])) ?>
                </small>
            </div>

            <div class="mb-2">
                <div class="info-label">Bưu cục</div>
                <div class="info-value"><?= htmlspecialchars($row['post_office_name']) ?></div>
            </div>

            <div class="mb-2">
                <div class="info-label">Người giữ thư</div>
                <div class="info-value">
                    <?= htmlspecialchars($row['holder_name']) ?>
                    | <?= htmlspecialchars($row['holder_phone']) ?>
                </div>
            </div>

            <div class="mb-2">
                <div class="info-label">Dịch vụ</div>
                <div class="info-value"><?= htmlspecialchars($row['service_type']) ?></div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <a href="pickup_detail.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm btn-action w-50">
                    Xem chi tiết
                </a>

                <a href="pickup_scan.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm btn-action w-50">
                    Pickup ngay
                </a>
            </div>

        </div>

    <?php endforeach; ?>

</div>

</body>
</html>