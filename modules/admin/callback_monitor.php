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
        header("Location: callback_monitor.php");
        exit;
    }

    if ($order['status'] === 'picked_up') {
        $result = sendPickupCallback($order_id);
    } else {
        $result = sendDeliveryCallback($order_id);
    }

    $note = $result['success']
        ? 'Manual resend success'
        : 'Manual resend fail: HTTP ' . ($result['http_code'] ?? 0);

    $status = 'callback_retry';

    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by)
        VALUES(?,?,?,?)
    ");

    $admin_id = $_SESSION['user_id'];

    $tr->bind_param("issi", $order_id, $status, $note, $admin_id);
    $tr->execute();

    header("Location: callback_monitor.php");
    exit;
}

/*
lấy callback fail / retry / dead
*/

$sql = "
SELECT *
FROM (
    SELECT 
        o.id,
        o.ems_code,
        o.status AS order_status,
        MAX(CASE WHEN t.status='callback_fail' THEN t.created_at END) AS callback_fail_time,
        SUM(CASE WHEN t.status='callback_retry' THEN 1 ELSE 0 END) AS retry_count,
        MAX(CASE WHEN t.status='callback_dead' THEN 1 ELSE 0 END) AS is_dead,
        MAX(t.created_at) AS last_tracking_time
    FROM emslss_orders o
    LEFT JOIN emslss_tracking t ON o.id = t.order_id
    GROUP BY o.id, o.ems_code, o.status
) x
WHERE x.callback_fail_time IS NOT NULL
   OR x.retry_count > 0
   OR x.is_dead = 1
ORDER BY COALESCE(x.callback_fail_time, x.last_tracking_time) DESC
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

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>📡 Callback Monitor</h4>
    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
</div>

<div class="card card-box">

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead class="table-light">
<tr>
<th>Mã EMS</th>
<th>Order Status</th>
<th>Callback Fail</th>
<th>Retry Count</th>
<th>Queue</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if (!$hasRows): ?>
<tr>
<td colspan="6" class="text-center text-muted py-4">
Chưa có callback fail/retry/dead.
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
<?= $row['callback_fail_time'] ?>
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
if($row['is_dead']) {
    echo '<span class="badge bg-danger badge-status">DEAD</span>';
} elseif($row['retry_count'] > 0) {
    echo '<span class="badge bg-warning badge-status">RETRY</span>';
} else {
    echo '<span class="badge bg-danger badge-status">FAIL</span>';
}
?>

</td>

<td>

<a href="?resend=<?= $row['id'] ?>"
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