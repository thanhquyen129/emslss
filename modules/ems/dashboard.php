<?php
require_once __DIR__ . '/init.php';
ems_require_role();

$statusFilter = trim($_GET['status'] ?? '');
$rejectFilter = ($_GET['filter'] ?? '') === 'lss_rejected';
$keyword = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$allowedStatuses = ems_allowed_statuses();
$kpi = [];
foreach ($allowedStatuses as $st) {
    $q = $conn->prepare('SELECT COUNT(*) AS total FROM emslss_orders WHERE status=?');
    $q->bind_param('s', $st);
    $q->execute();
    $kpi[$st] = (int) ($q->get_result()->fetch_assoc()['total'] ?? 0);
}

$pushToday = $conn->query("
    SELECT COUNT(DISTINCT o.id) AS total
    FROM emslss_orders o
    INNER JOIN emslss_tracking t ON t.order_id = o.id AND t.note = 'EMS pushed order'
    WHERE DATE(o.created_at) = CURDATE()
")->fetch_assoc()['total'] ?? 0;

$callbackFail = (int) ($conn->query("
    SELECT COUNT(DISTINCT order_id) AS total FROM emslss_tracking
    WHERE status IN ('callback_fail', 'callback_dead')
")->fetch_assoc()['total'] ?? 0);

$lssRejectedCount = ems_lss_rejected_count($conn);

$where = ['1=1'];
$types = '';
$params = [];

if ($rejectFilter) {
    $where[] = "o.status = 'cancelled'";
    $where[] = "EXISTS (SELECT 1 FROM emslss_order_meta m WHERE m.order_id = o.id AND m.meta_key = 'lss_reject_reason')";
} elseif ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = 'o.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}

if ($keyword !== '') {
    $where[] = '(o.ems_code LIKE ? OR o.holder_phone LIKE ? OR o.receiver_phone LIKE ? OR o.sender_name LIKE ? OR o.receiver_name LIKE ?)';
    $types .= 'sssss';
    $like = '%' . $keyword . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'DATE(o.created_at) >= ?';
    $types .= 's';
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'DATE(o.created_at) <= ?';
    $types .= 's';
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) AS total FROM emslss_orders o WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $perPage));

$listSql = "
    SELECT o.id, o.ems_code, o.status, o.service_type, o.cargo_type, o.weight,
           o.created_at, o.updated_at,
           o.post_office_name, o.holder_name, o.holder_phone,
           o.sender_address, o.receiver_address, o.receiver_name
    FROM emslss_orders o
    WHERE $whereSql
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT ? OFFSET ?
";
$listStmt = $conn->prepare($listSql);
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$orders = $listStmt->get_result();

$orderIds = [];
$orderRows = [];
while ($row = $orders->fetch_assoc()) {
    $orderRows[] = $row;
    $orderIds[] = (int) $row['id'];
}
$orderMeta = emslss_order_meta_bulk($conn, $orderIds, ['lss_reject_reason', 'cargo_description']);

function ems_active_kpi(string $key, string $current, bool $rejectFilter = false): string
{
    if ($key === 'lss_rejected' && $rejectFilter) {
        return 'active-kpi';
    }
    if ($rejectFilter) {
        return '';
    }
    return $key === $current ? 'active-kpi' : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EMS Portal — Đơn hàng</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f0f4f8; }
.kpi-card { border: none; border-radius: 14px; min-height: 90px; cursor: pointer; transition: .2s; text-decoration: none; color: inherit; display: block; }
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.1); }
.active-kpi { outline: 3px solid #0d6efd; }
.order-row:hover { background: #f8fafc; }
</style>
</head>
<body>
<?php include __DIR__ . '/../../templates/ems_topbar.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Đơn push qua API EMS</h4>
            <small class="text-muted">Theo dõi đơn từ <code>POST /api/ems_push_order.php</code> · Tự làm mới <span id="countdown">60</span>s</small>
        </div>
        <div class="text-end">
            <span class="badge bg-info">Hôm nay: <?= (int) $pushToday ?> đơn mới</span>
            <?php if ($callbackFail > 0): ?>
                <a href="callbacks.php" class="badge bg-danger text-decoration-none"><?= $callbackFail ?> callback lỗi</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php
        $kpiCards = [
            ['new_order', 'Mới', $kpi['new_order'] ?? 0, 'primary'],
            ['assigned_pickup', 'Pickup', $kpi['assigned_pickup'] ?? 0, 'warning'],
            ['picked_up', 'Đã lấy', $kpi['picked_up'] ?? 0, 'info'],
            ['assigned_delivery', 'Giao', $kpi['assigned_delivery'] ?? 0, 'dark'],
            ['delivered', 'Hoàn tất', $kpi['delivered'] ?? 0, 'success'],
            ['failed', 'Lỗi', $kpi['failed'] ?? 0, 'danger'],
            ['cancelled', 'Hủy', $kpi['cancelled'] ?? 0, 'secondary'],
        ];
        foreach ($kpiCards as [$code, $label, $count, $color]):
        ?>
        <div class="col-6 col-md-4 col-lg">
            <a href="?status=<?= urlencode($code) ?>" class="kpi-card card bg-<?= $color ?> text-white <?= ems_active_kpi($code, $statusFilter) ?>">
                <div class="card-body py-2 px-3">
                    <small><?= htmlspecialchars($label) ?></small>
                    <div class="fs-4 fw-bold"><?= (int) $count ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-md-4 col-lg">
            <a href="?filter=lss_rejected" class="kpi-card card bg-danger text-white <?= ems_active_kpi('lss_rejected', $statusFilter, $rejectFilter) ?>">
                <div class="card-body py-2 px-3">
                    <small>Từ chối (LSS)</small>
                    <div class="fs-4 fw-bold"><?= (int) $lssRejectedCount ?></div>
                </div>
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <?php if ($rejectFilter): ?>
                    <input type="hidden" name="filter" value="lss_rejected">
                <?php elseif ($statusFilter): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label small">Tìm kiếm</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Mã EMS, SĐT, tên..."
                           value="<?= htmlspecialchars($keyword) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Từ ngày</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Đến ngày</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Lọc</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Xóa lọc</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Danh sách (<?= $total ?> đơn)</span>
            <?php if ($statusFilter || $rejectFilter): ?>
                <a href="dashboard.php" class="btn btn-sm btn-link">Bỏ lọc trạng thái</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã EMS</th>
                        <th>Dịch vụ</th>
                        <th>Hàng hóa</th>
                        <th>Bưu cục / Người giữ</th>
                        <th>Người nhận</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($orderRows === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Không có đơn phù hợp</td></tr>
                <?php endif; ?>
                <?php foreach ($orderRows as $row):
                    $meta = $orderMeta[(int) $row['id']] ?? [];
                ?>
                    <tr class="order-row">
                        <td><strong><?= htmlspecialchars($row['ems_code']) ?></strong></td>
                        <td><small><?= htmlspecialchars($row['service_type'] ?? '-') ?></small></td>
                        <td><small><?= emslss_order_cargo_html($row, $meta) ?></small></td>
                        <td>
                            <small><?= htmlspecialchars($row['post_office_name'] ?? '') ?></small><br>
                            <?= htmlspecialchars($row['holder_name'] ?? '') ?>
                            <?php if ($row['holder_phone']): ?>
                                · <?= htmlspecialchars($row['holder_phone']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['receiver_name'] ?? '') ?></td>
                        <td><?= ems_status_badge($row['status'], $meta) ?></td>
                        <td><small><?= htmlspecialchars($row['created_at']) ?></small></td>
                        <td>
                            <a href="order_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Chi tiết</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $qs = $_GET;
                    for ($p = 1; $p <= min($totalPages, 10); $p++):
                        $qs['page'] = $p;
                    ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
let timeLeft = 60;
const el = document.getElementById('countdown');
setInterval(() => {
    timeLeft--;
    if (el) el.textContent = timeLeft;
    if (timeLeft <= 0) location.reload();
}, 1000);
</script>
</body>
</html>
