<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ds_bg_export_helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'ems'], true)) {
    header('Location: ../login.php');
    exit;
}

$isEmsPortal = ($_SESSION['role'] ?? '') === 'ems';
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = trim($_GET['date_to'] ?? date('Y-m-d'));

$filters = emslss_ds_bg_build_filters([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
$dateFrom = $filters['date_from'];
$dateTo = $filters['date_to'];
$previewCount = emslss_ds_bg_count($conn, $filters['sql'], $filters['types'], $filters['params']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Xuất DS bưu gửi EMS qua LSS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f0f4f8; }
.card { border: none; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
</style>
</head>
<body>
<?php if ($isEmsPortal): include __DIR__ . '/../../templates/ems_topbar.php'; else: include __DIR__ . '/../../templates/admin_topbar.php'; endif; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Xuất danh sách bưu gửi EMS qua LSS</h4>
            <small class="text-muted">File Excel theo mẫu EMS (STT, mã bưu gửi, ngày/giờ nhận, phát lần đầu, kết quả phát)</small>
        </div>
        <a href="<?= $isEmsPortal ? '/modules/ems/dashboard.php' : 'admin_reports.php' ?>" class="btn btn-outline-secondary btn-sm">← Quay lại</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-start border-primary border-4">
                <div class="text-muted small">Số bưu gửi trong khoảng lọc</div>
                <div class="fs-3 fw-bold text-primary"><?= (int) $previewCount ?></div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card p-3 h-100">
                <div class="text-muted small mb-1">Cột trong file xuất</div>
                <div class="small">
                    STT · Mã bưu gửi · Ngày/Giờ LSS nhận dữ liệu · Ngày/Giờ phát lần đầu · Kết quả phát
                </div>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <form method="GET" class="row g-3 mb-3" id="filterForm">
            <div class="col-md-3">
                <label class="form-label">Từ ngày (LSS nhận dữ liệu)</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" required>
            </div>
            <div class="col-md-6 d-flex align-items-end gap-2 flex-wrap">
                <button type="submit" class="btn btn-outline-primary">Xem số lượng</button>
                <button type="submit" class="btn btn-success" formaction="ds_bg_export_download.php" formmethod="post">
                    Tải Excel (.xlsx)
                </button>
            </div>
        </form>
        <p class="small text-muted mb-0">
            Lọc theo ngày EMS đẩy đơn vào LSS (<code>created_at</code>).
            “Phát lần đầu” lấy mốc tracking <code>delivered</code>/<code>failed</code> sớm nhất;
            “Kết quả phát” theo trạng thái hiện tại của đơn.
        </p>
    </div>
</div>
</body>
</html>
