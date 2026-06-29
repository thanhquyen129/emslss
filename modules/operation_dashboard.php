<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: /modules/login.php");
    exit;
}

if ($_SESSION['role'] != 'operation' && $_SESSION['role'] != 'admin') {
    die("Access denied");
}

$user_id   = (int) $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Operation';
$role      = $_SESSION['role'];

// Tìm kiếm
$q    = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

// Tab đang mở
$allowedTabs = ['new', 'transit', 'assigned', 'done', 'failed'];
$activeTab = $_GET['tab'] ?? 'new';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'new';
}
if (isset($_GET['page'])) {
    $activeTab = 'done';
}

/*
|--------------------------------------------------------------------------
| KPI (tổng, không phụ thuộc tìm kiếm)
|--------------------------------------------------------------------------
*/
function opCount(mysqli $conn, string $status): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM emslss_orders WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

$kpi = [
    'picked_up'         => opCount($conn, 'picked_up'),
    'in_transit'        => opCount($conn, 'in_transit'),
    'assigned_delivery' => opCount($conn, 'assigned_delivery'),
    'failed'            => opCount($conn, 'failed'),
];

$dtStmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM emslss_orders
    WHERE status='delivered' AND DATE(updated_at) = CURDATE()
");
$dtStmt->execute();
$kpi['delivered_today'] = (int) ($dtStmt->get_result()->fetch_assoc()['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| Danh sách shipper để assign delivery
|--------------------------------------------------------------------------
*/
$shippers = emslss_fetch_active_users_by_role($conn, 'shipper');

/*
|--------------------------------------------------------------------------
| Helper lấy đơn theo status + tìm kiếm
|--------------------------------------------------------------------------
*/
function opOrders(mysqli $conn, string $status, string $like, string $orderBy = 'created_at DESC', string $limitSql = ''): array
{
    $sql = "
        SELECT o.*, u.full_name AS shipper_name
        FROM emslss_orders o
        LEFT JOIN emslss_users u ON o.delivery_shipper_id = u.id
        WHERE o.status = ?
          AND (o.ems_code LIKE ? OR o.receiver_name LIKE ? OR o.post_office_name LIKE ?)
        ORDER BY $orderBy
        $limitSql
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $status, $like, $like, $like);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$new_orders      = opOrders($conn, 'picked_up', $like, 'updated_at DESC');
$transit_orders  = opOrders($conn, 'in_transit', $like, 'updated_at DESC');
$assigned_orders = opOrders($conn, 'assigned_delivery', $like, 'updated_at DESC');

// Đơn lỗi kèm lý do thất bại (tracking gần nhất)
$failedSql = "
    SELECT o.*,
           (SELECT t.note FROM emslss_tracking t
            WHERE t.order_id = o.id AND t.status = 'failed'
            ORDER BY t.created_at DESC LIMIT 1) AS fail_note
    FROM emslss_orders o
    WHERE o.status = 'failed'
      AND (o.ems_code LIKE ? OR o.receiver_name LIKE ? OR o.post_office_name LIKE ?)
    ORDER BY o.updated_at DESC
";
$failedStmt = $conn->prepare($failedSql);
$failedStmt->bind_param("sss", $like, $like, $like);
$failedStmt->execute();
$failed_orders = $failedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/*
|--------------------------------------------------------------------------
| Đã giao — phân trang (mặc định tất cả, >100 thì 50/trang)
|--------------------------------------------------------------------------
*/
$perPage = 50;
$paginateThreshold = 100;

$doneCntStmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM emslss_orders
    WHERE status='delivered'
      AND (ems_code LIKE ? OR receiver_name LIKE ? OR post_office_name LIKE ?)
");
$doneCntStmt->bind_param("sss", $like, $like, $like);
$doneCntStmt->execute();
$doneTotal = (int) ($doneCntStmt->get_result()->fetch_assoc()['total'] ?? 0);

$useDonePagination = $doneTotal > $paginateThreshold;
$donePage = 1;
$doneTotalPages = 1;
$doneLimitSql = '';
if ($useDonePagination) {
    $doneTotalPages = (int) ceil($doneTotal / $perPage);
    $donePage = max(1, intval($_GET['page'] ?? 1));
    if ($donePage > $doneTotalPages) {
        $donePage = $doneTotalPages;
    }
    $doneOffset = ($donePage - 1) * $perPage;
    $doneLimitSql = "LIMIT $perPage OFFSET $doneOffset";
}
$done_orders = opOrders($conn, 'delivered', $like, 'updated_at DESC', $doneLimitSql);

function opPageUrl($p, $q)
{
    $params = ['tab' => 'done'];
    if ($q !== '') {
        $params['q'] = $q;
    }
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

// Cảnh báo SLA: picked_up quá lâu chưa nhập kho (phút)
$slaPickupMinutes = 120;
function minutesSince(?string $ts): int
{
    if (!$ts) {
        return 0;
    }
    return (int) floor((time() - strtotime($ts)) / 60);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Operation Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f5f6fa; }
    .card { border-radius:12px; }
    .kpi-card { border:none; border-radius:16px; color:#fff; box-shadow:0 6px 18px rgba(0,0,0,.06); }
    .kpi-card .kpi-value { font-size:28px; font-weight:700; }
    .kpi-card .kpi-title { font-size:13px; opacity:.92; }
    .kpi-blue   { background:linear-gradient(135deg,#0d6efd,#4ea3ff); }
    .kpi-orange { background:linear-gradient(135deg,#ff9800,#ffc107); }
    .kpi-cyan   { background:linear-gradient(135deg,#00bcd4,#26c6da); }
    .kpi-green  { background:linear-gradient(135deg,#28a745,#5fd37a); }
    .kpi-red    { background:linear-gradient(135deg,#dc3545,#ff6b6b); }
    .table td { vertical-align:middle; }
</style>
</head>
<body>

<div class="container-fluid px-4 mt-4" style="max-width:1300px;">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0">📦 Operation Dashboard</h4>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted">Xin chào <b><?= htmlspecialchars($full_name) ?></b></span>
            <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($role) ?></span>
            <?php if ($role === 'admin'): ?>
                <a href="/modules/admin/admin_dashboard_realtime.php" class="btn btn-sm btn-outline-primary">← Admin Dashboard</a>
            <?php endif; ?>
            <a href="../change_password.php" class="btn btn-sm btn-outline-secondary">Đổi MK</a>
            <a href="../logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md col-6">
            <div class="card kpi-card kpi-orange p-3 h-100">
                <div class="kpi-title">⏳ Chờ nhận kho</div>
                <div class="kpi-value"><?= $kpi['picked_up'] ?></div>
            </div>
        </div>
        <div class="col-md col-6">
            <div class="card kpi-card kpi-blue p-3 h-100">
                <div class="kpi-title">🏭 Đang ở kho</div>
                <div class="kpi-value"><?= $kpi['in_transit'] ?></div>
            </div>
        </div>
        <div class="col-md col-6">
            <div class="card kpi-card kpi-cyan p-3 h-100">
                <div class="kpi-title">🚚 Đã giao việc</div>
                <div class="kpi-value"><?= $kpi['assigned_delivery'] ?></div>
            </div>
        </div>
        <div class="col-md col-6">
            <div class="card kpi-card kpi-green p-3 h-100">
                <div class="kpi-title">✅ Giao hôm nay</div>
                <div class="kpi-value"><?= $kpi['delivered_today'] ?></div>
            </div>
        </div>
        <div class="col-md col-6">
            <div class="card kpi-card kpi-red p-3 h-100">
                <div class="kpi-title">⚠️ Đơn lỗi</div>
                <div class="kpi-value"><?= $kpi['failed'] ?></div>
            </div>
        </div>
    </div>

    <!-- Tìm kiếm -->
    <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <div class="col-sm-6 col-md-4">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control"
                   placeholder="Tìm mã EMS / người nhận / bưu cục...">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Tìm</button>
            <?php if ($q !== ''): ?>
                <a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-outline-secondary">Xóa</a>
            <?php endif; ?>
        </div>
    </form>

    <ul class="nav nav-tabs">
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'new' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#new">
                Chờ nhận kho <span class="badge bg-warning text-dark"><?= count($new_orders) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'transit' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#transit">
                Tại kho <span class="badge bg-primary"><?= count($transit_orders) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'assigned' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#assigned">
                Đã assign delivery <span class="badge bg-info"><?= count($assigned_orders) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'done' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#done">
                Đã giao
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'failed' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#failed">
                Đơn lỗi <span class="badge bg-danger"><?= count($failed_orders) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3">

        <!-- CHỜ NHẬN KHO (picked_up) -->
        <div class="tab-pane fade <?= $activeTab === 'new' ? 'show active' : '' ?>" id="new">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>EMS Code</th><th>Người nhận</th><th>Địa chỉ</th><th>Chờ (kể từ pickup)</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($new_orders as $o):
                        $mins = minutesSince($o['updated_at']); ?>
                        <tr>
                            <td><b><a href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['ems_code']) ?></a></b></td>
                            <td><?= htmlspecialchars($o['receiver_name']) ?><br><small class="text-muted"><?= htmlspecialchars($o['receiver_phone']) ?></small></td>
                            <td><small><?= htmlspecialchars($o['receiver_address']) ?></small></td>
                            <td>
                                <?php if ($mins > $slaPickupMinutes): ?>
                                    <span class="badge bg-danger">Quá hạn · <?= intdiv($mins, 60) ?>h<?= $mins % 60 ?>m</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= intdiv($mins, 60) ?>h<?= $mins % 60 ?>m</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="/modules/operation/receive.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-success">📥 Nhận kho</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($new_orders) === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted">Không có đơn chờ nhận kho</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>

        <!-- TẠI KHO (in_transit) -->
        <div class="tab-pane fade <?= $activeTab === 'transit' ? 'show active' : '' ?>" id="transit">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>EMS Code</th><th>Người nhận</th><th>Địa chỉ</th><th>Assign delivery</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transit_orders as $o): ?>
                        <tr>
                            <td><b><a href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['ems_code']) ?></a></b></td>
                            <td><?= htmlspecialchars($o['receiver_name']) ?><br><small class="text-muted"><?= htmlspecialchars($o['receiver_phone']) ?></small></td>
                            <td><small><?= htmlspecialchars($o['receiver_address']) ?></small></td>
                            <td>
                                <select class="form-select shipper_select" data-id="<?= (int)$o['id'] ?>">
                                    <option value="">-- chọn shipper --</option>
                                    <?php foreach ($shippers as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($transit_orders) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted">Không có đơn tại kho chờ giao</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>

        <!-- ĐÃ ASSIGN DELIVERY -->
        <div class="tab-pane fade <?= $activeTab === 'assigned' ? 'show active' : '' ?>" id="assigned">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>EMS</th><th>Người nhận</th><th>Shipper</th><th>Trạng thái</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assigned_orders as $o): ?>
                        <tr>
                            <td><a href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['ems_code']) ?></a></td>
                            <td><?= htmlspecialchars($o['receiver_name']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($o['shipper_name'] ?? '-') ?></span></td>
                            <td><span class="badge bg-warning text-dark">Assigned Delivery</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($assigned_orders) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted">Chưa có đơn assigned delivery</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>

        <!-- ĐÃ GIAO -->
        <div class="tab-pane fade <?= $activeTab === 'done' ? 'show active' : '' ?>" id="done">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>EMS</th><th>Người nhận</th><th>Shipper</th><th>Thời gian</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($done_orders as $o): ?>
                        <tr>
                            <td><a href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['ems_code']) ?></a></td>
                            <td><?= htmlspecialchars($o['receiver_name']) ?></td>
                            <td><?= htmlspecialchars($o['shipper_name'] ?? '-') ?></td>
                            <td><small><?= htmlspecialchars($o['updated_at']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($done_orders) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted">Chưa có đơn delivered</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($useDonePagination): ?>
                <?php
                    $winStart = max(1, $donePage - 2);
                    $winEnd = min($doneTotalPages, $donePage + 2);
                ?>
                <div class="d-flex flex-column align-items-center mt-3">
                    <div class="text-muted small mb-2">
                        Trang <?= $donePage ?>/<?= $doneTotalPages ?> · tổng <?= $doneTotal ?> đơn đã giao
                    </div>
                    <nav>
                        <ul class="pagination flex-wrap mb-0">
                            <li class="page-item <?= $donePage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(opPageUrl(max(1, $donePage - 1), $q)) ?>">‹</a>
                            </li>
                            <?php if ($winStart > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
                                <li class="page-item <?= $p == $donePage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(opPageUrl($p, $q)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($winEnd < $doneTotalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item <?= $donePage >= $doneTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(opPageUrl(min($doneTotalPages, $donePage + 1), $q)) ?>">›</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div></div>
        </div>

        <!-- ĐƠN LỖI -->
        <div class="tab-pane fade <?= $activeTab === 'failed' ? 'show active' : '' ?>" id="failed">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>EMS</th><th>Người nhận</th><th>Lý do</th><th>Thời gian</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($failed_orders as $o): ?>
                        <tr>
                            <td><a href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['ems_code']) ?></a></td>
                            <td><?= htmlspecialchars($o['receiver_name']) ?></td>
                            <td><small><?= htmlspecialchars($o['fail_note'] ?? '-') ?></small></td>
                            <td><small><?= htmlspecialchars($o['updated_at']) ?></small></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="/modules/admin/admin_order_detail.php?id=<?= (int)$o['id'] ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($failed_orders) === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted">Không có đơn lỗi</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll(".shipper_select").forEach(el => {
    el.addEventListener("change", function () {
        let order_id = this.dataset.id;
        let shipper_id = this.value;
        if (!shipper_id) return;

        fetch("/modules/operation/assign_delivery.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `order_id=${encodeURIComponent(order_id)}&shipper_id=${encodeURIComponent(shipper_id)}`
        })
        .then(res => res.json())
        .then(r => {
            if (r.success) {
                alert("Assigned successfully");
                location.reload();
            } else {
                alert(r.message || "Assign failed");
            }
        })
        .catch(() => alert("Assign failed"));
    });
});
</script>

</body>
</html>
