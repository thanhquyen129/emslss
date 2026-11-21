<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$total = (int) $conn->query("SELECT COUNT(*) c FROM emslss_orders")->fetch_assoc()['c'];
$delivered = (int) $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='delivered'")->fetch_assoc()['c'];
$failed = (int) $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='failed'")->fetch_assoc()['c'];
$new = (int) $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='new_order'")->fetch_assoc()['c'];
$cancelled = (int) $conn->query("SELECT COUNT(*) c FROM emslss_orders WHERE status='cancelled'")->fetch_assoc()['c'];

$deliveredMonth = (int) $conn->query("
    SELECT COUNT(*) c FROM emslss_orders
    WHERE status='delivered' AND DATE(updated_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];

$inProgress = (int) $conn->query("
    SELECT COUNT(*) c FROM emslss_orders
    WHERE status IN ('picked_up','in_transit','assigned_delivery')
")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Báo cáo hệ thống</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f7fb; }
.card-stat { border: none; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
</style>
</head>
<body>

<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0">📊 Báo cáo hệ thống</h4>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher'], true)): ?>
        <a href="order_export.php" class="btn btn-primary">📥 Kết xuất CSV — chốt công nợ</a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-primary text-white p-3">
                <div class="small opacity-75">Tổng đơn</div>
                <div class="fs-3 fw-bold"><?= $total ?></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-success text-white p-3">
                <div class="small opacity-75">Đã giao</div>
                <div class="fs-3 fw-bold"><?= $delivered ?></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-info text-white p-3">
                <div class="small opacity-75">Giao tháng này</div>
                <div class="fs-3 fw-bold"><?= $deliveredMonth ?></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-warning text-dark p-3">
                <div class="small">Đang xử lý</div>
                <div class="fs-3 fw-bold"><?= $inProgress ?></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-danger text-white p-3">
                <div class="small opacity-75">Thất bại</div>
                <div class="fs-3 fw-bold"><?= $failed ?></div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card card-stat bg-secondary text-white p-3">
                <div class="small opacity-75">Hủy / từ chối</div>
                <div class="fs-3 fw-bold"><?= $cancelled ?></div>
            </div>
        </div>
    </div>

    <div class="card card-stat p-4">
        <h5 class="mb-3">Kết xuất phục vụ thanh toán</h5>
        <p class="text-muted">
            Tải file CSV chi tiết từng đơn hoặc tổng hợp theo shipper / trạng thái để đối soát với EMS và shipper.
            File hỗ trợ tiếng Việt (UTF-8), mở trực tiếp bằng Excel.
        </p>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'dispatcher'], true)): ?>
        <a href="order_export.php" class="btn btn-outline-primary">Mở trang kết xuất →</a>
        <?php else: ?>
        <p class="small text-warning mb-0">Chỉ admin / dispatcher được kết xuất dữ liệu.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
