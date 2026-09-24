<?php
session_start();
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', '1'); // Display errors on the screen
ini_set('display_startup_errors', '1'); // Display startup errors
include '../../config/db.php';
require_once '../../api/callback_delivery.php';
require_once '../../api/callback_pickup.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    die("Access denied");
}

$allowedFilters = ['all', 'fail', 'retry', 'dead', 'success'];
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$filterQuery = $filter === 'all' ? '' : ('&filter=' . urlencode($filter));

/*
resend thủ công
*/
if (isset($_GET['resend'])) {

    $order_id = intval($_GET['resend']);
    $orderStmt = $conn->prepare("SELECT status FROM emslss_orders WHERE id=? LIMIT 1");
    $orderStmt->bind_param("i", $order_id);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();

    if (!$order) {
        header("Location: callback_monitor.php" . ($filterQuery !== '' ? '?' . ltrim($filterQuery, '&') : ''));
        exit;
    }

    $result = emslss_resend_order_callback($order_id);

    $errorBody = '';
    if (!empty($result['response'])) {
        $errorBody = trim((string)$result['response']);
        if (strlen($errorBody) > 180) {
            $errorBody = substr($errorBody, 0, 180) . '...';
        }
    }
    $note = $result['success']
        ? 'Manual resend success'
        : 'Manual resend fail: HTTP ' . ($result['http_code'] ?? 0) . ($errorBody !== '' ? ' | ' . $errorBody : '');

    $status = $result['success'] ? 'callback_success' : 'callback_fail';

    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by)
        VALUES(?,?,?,?)
    ");

    $admin_id = $_SESSION['user_id'];

    $tr->bind_param("issi", $order_id, $status, $note, $admin_id);
    $tr->execute();

    header("Location: callback_monitor.php" . ($filterQuery !== '' ? '?' . ltrim($filterQuery, '&') : ''));
    exit;
}

/*
lấy callback fail / retry / dead / success gần nhất
*/

$statusFilterSql = " IN ('callback_fail', 'callback_retry', 'callback_dead', 'callback_success') ";
if ($filter === 'fail') {
    $statusFilterSql = " = 'callback_fail' ";
} elseif ($filter === 'retry') {
    $statusFilterSql = " = 'callback_retry' ";
} elseif ($filter === 'dead') {
    $statusFilterSql = " = 'callback_dead' ";
} elseif ($filter === 'success') {
    $statusFilterSql = " = 'callback_success' ";
}

$sql = "
SELECT
    o.id,
    o.ems_code,
    o.status AS order_status,
    agg.callback_fail_time,
    agg.retry_count,
    COALESCE(last_cb.status, '') AS last_callback_status,
    COALESCE(last_cb.note, '') AS last_callback_note,
    last_cb.created_at AS last_callback_time
FROM emslss_orders o
LEFT JOIN (
    SELECT
        t.order_id,
        MAX(CASE WHEN t.status='callback_fail' THEN t.created_at END) AS callback_fail_time,
        SUM(CASE WHEN t.status='callback_retry' THEN 1 ELSE 0 END) AS retry_count
    FROM emslss_tracking t
    GROUP BY t.order_id
) agg ON agg.order_id = o.id
LEFT JOIN emslss_tracking last_cb ON last_cb.id = (
    SELECT t2.id
    FROM emslss_tracking t2
    WHERE t2.order_id = o.id
      AND t2.status IN ('callback_fail', 'callback_retry', 'callback_dead', 'callback_success')
    ORDER BY t2.created_at DESC, t2.id DESC
    LIMIT 1
)
WHERE COALESCE(last_cb.status, '') $statusFilterSql
ORDER BY COALESCE(last_cb.created_at, agg.callback_fail_time) DESC
";

$res = $conn->query($sql);
$hasRows = ($res && $res->num_rows > 0);

?>

<!DOCTYPE html>
<html lang="vi">
	<head>
		<meta charset="UTF-8">
		<title>Callback Monitor</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

		<style>
			body{
				background:#f5f7fa;
			}
			.card-box{
				border-radius:14px;
				box-shadow:0 4px 12px rgba(0,0,0,0.08);
			}
			.badge-status{
				font-size:13px;
				padding:6px 10px;
			}
		</style>
	</head>
<body>

<?php include __DIR__ . '/../../templates/admin_topbar.php'; ?>

<div class="container py-4">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">📡 Callback Monitor</h4>
</div>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
<?php
$tabDefs = [
    'all' => 'Tất cả',
    'fail' => 'Fail',
    'retry' => 'Retry',
    'dead' => 'Dead',
    'success' => 'Success',
];
foreach ($tabDefs as $key => $label) {
    $active = $filter === $key ? 'active' : '';
    $href = $key === 'all' ? 'callback_monitor.php' : 'callback_monitor.php?filter=' . urlencode($key);
    echo '<li class="nav-item"><a class="nav-link ' . $active . '" href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a></li>';
}
?>
</ul>

<div class="card card-box">

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead class="table-light">
<tr>
<th>Mã EMS</th>
<th>Order Status</th>
<th>Callback Time</th>
<th>Retry Count</th>
<th>Queue</th>
<th>Lỗi gần nhất</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if (!$hasRows): ?>
<tr>
<td colspan="7" class="text-center text-muted py-4">
Chưa có callback status.
</td>
</tr>
<?php else: ?>
<?php while($row = $res->fetch_assoc()): ?>

<tr>

<td>
<strong><?= $row['ems_code'] ?></strong>
</td>

<td>
<?= $row['order_status'] ?>
</td>

<td>
<?= htmlspecialchars($row['last_callback_time'] ?: '-') ?>
</td>

<td>

<?php if($row['retry_count'] > 0): ?>
<span class="badge bg-warning badge-status">
<?= $row['retry_count'] ?>
</span>
<?php else: ?>
0
<?php endif; ?>

</td>

<td>

<?php
if($row['last_callback_status'] === 'callback_dead') {
    echo '<span class="badge bg-danger badge-status">DEAD</span>';
} elseif($row['last_callback_status'] === 'callback_fail') {
    echo '<span class="badge bg-danger badge-status">FAIL</span>';
} elseif($row['last_callback_status'] === 'callback_retry') {
    echo '<span class="badge bg-warning badge-status">RETRY</span>';
} elseif($row['last_callback_status'] === 'callback_success') {
    echo '<span class="badge bg-success badge-status">SUCCESS</span>';
} else {
    echo '<span class="badge bg-secondary badge-status">UNKNOWN</span>';
}
?>

</td>

<td>
<?= htmlspecialchars($row['last_callback_note'] ?: '-') ?>
</td>

<td>

<a href="?resend=<?= (int)$row['id'] ?><?= htmlspecialchars($filterQuery) ?>"
   class="btn btn-sm btn-primary"
   onclick="return confirm('Resend callback?')">

   Resend
</a>

</td>

</tr>

<?php endwhile; ?>
<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>