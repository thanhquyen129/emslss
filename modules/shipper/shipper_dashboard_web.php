<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Shipper';

// KPI
$kpi = $conn->query(" 
    SELECT 
        SUM(status='assigned_pickup') as pickup,
        SUM(status='assigned_delivery') as delivery,
        SUM(status IN ('picked_up','in_transit')) as processing,
        SUM(status='delivered') as done
    FROM emslss_orders
    WHERE pickup_shipper_id = $user_id OR delivery_shipper_id = $user_id
")->fetch_assoc();

// Pickup
$pickup = $conn->query(" 
    SELECT * FROM emslss_orders
    WHERE pickup_shipper_id = $user_id
    ORDER BY status='picked_up', created_at DESC
");

// Delivery
$delivery = $conn->query(" 
    SELECT * FROM emslss_orders
    WHERE delivery_shipper_id = $user_id
    ORDER BY status='delivered', created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Shipper Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f9}
.card{border-radius:12px}
</style>
</head>
<body>

<div class="container mt-3">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>👋 Xin chào, <b><?= htmlspecialchars($user_name) ?></b></h5>
        <a href="../../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <!-- KPI -->
    <div class="row text-center mb-4">
        <div class="col">
            <div class="card p-2">
                <b><?= $kpi['pickup'] ?? 0 ?></b><br>Chờ pickup
            </div>
        </div>
        <div class="col">
            <div class="card p-2">
                <b><?= $kpi['delivery'] ?? 0 ?></b><br>Chờ giao
            </div>
        </div>
        <div class="col">
            <div class="card p-2">
                <b><?= $kpi['processing'] ?? 0 ?></b><br>Đang xử lý
            </div>
        </div>
        <div class="col">
            <div class="card p-2">
                <b><?= $kpi['done'] ?? 0 ?></b><br>Đã hoàn tất
            </div>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pickup">Pickup</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#delivery">Delivery</button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- PICKUP -->
        <div class="tab-pane fade show active" id="pickup">
            <div class="row">
                <?php while($row = $pickup->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">

                            <div class="d-flex justify-content-between">
                                <b><?= $row['ems_code'] ?></b>
                                <span class="badge bg-warning text-dark"><?= $row['status'] ?></span>
                            </div>

                            <p class="mb-1"><b>Bưu cục:</b> <?= $row['post_office_name'] ?></p>
                            <p class="mb-1 small text-muted"><?= $row['post_office_address'] ?></p>

                            <p class="mb-1"><b>Người liên hệ:</b> <?= $row['holder_name'] ?></p>
                            <p class="mb-2"><b>SĐT:</b> <?= $row['holder_phone'] ?></p>

                            <div class="d-flex gap-2">
                                <a href="pickup_detail.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm w-50">Chi tiết</a>
                                <a href="pickup_scan.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm w-50">Pickup</a>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- DELIVERY -->
        <div class="tab-pane fade" id="delivery">
            <div class="row">
                <?php while($row = $delivery->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">

                            <div class="d-flex justify-content-between">
                                <b><?= $row['ems_code'] ?></b>
                                <span class="badge bg-info"><?= $row['status'] ?></span>
                            </div>

                            <p class="mb-1"><b>Người nhận:</b> <?= $row['receiver_name'] ?></p>
                            <p class="mb-1"><b>SĐT:</b> <?= $row['receiver_phone'] ?></p>
                            <p class="mb-2 small text-muted"><?= $row['receiver_address'] ?></p>

                            <div class="d-flex gap-2">
                                <a href="delivery_detail.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm w-50">Chi tiết</a>
                                <a href="delivery_complete.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm w-50">Giao hàng</a>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>