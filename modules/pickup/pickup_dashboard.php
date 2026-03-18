<?php
session_start();
include '../../config/db.php';
include '../../templates/admin_topbar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM emslss_orders
    WHERE pickup_shipper_id = ?
    AND status = 'assigned_pickup'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$kpi = $conn->prepare("
    SELECT COUNT(*) total
    FROM emslss_orders
    WHERE pickup_shipper_id = ?
    AND status = 'assigned_pickup'
");
$kpi->bind_param("i", $user_id);
$kpi->execute();
$total = $kpi->get_result()->fetch_assoc()['total'];
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📦 Pickup Dashboard</h3>
        <span class="badge bg-primary fs-6">Pending: <?= $total ?></span>
    </div>

    <?php if ($result->num_rows == 0): ?>
        <div class="alert alert-info shadow-sm">
            Hiện chưa có đơn pickup nào.
        </div>
    <?php endif; ?>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-body">

                        <div class="d-flex justify-content-between mb-2">
                            <h5 class="mb-0"><?= htmlspecialchars($row['ems_code']) ?></h5>
                            <span class="badge bg-warning text-dark">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </div>

                        <p class="mb-1">
                            <strong>Người gửi:</strong>
                            <?= htmlspecialchars($row['sender_name']) ?>
                        </p>

                        <p class="mb-1">
                            <strong>SĐT:</strong>
                            <?= htmlspecialchars($row['sender_phone']) ?>
                        </p>

                        <p class="mb-1">
                            <strong>Bưu cục:</strong>
                            <?= htmlspecialchars($row['post_office_name']) ?>
                        </p>

                        <p class="mb-2 small text-muted">
                            <?= htmlspecialchars($row['sender_address']) ?>
                        </p>

                        <p class="small text-secondary">
                            <?= $row['created_at'] ?>
                        </p>

                        <div class="d-flex gap-2">
                            <a href="pickup_detail.php?id=<?= $row['id'] ?>"
                               class="btn btn-primary btn-sm w-50">
                               Chi tiết
                            </a>

                            <a href="pickup_scan.php?id=<?= $row['id'] ?>"
                               class="btn btn-success btn-sm w-50">
                               Scan
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

</div>