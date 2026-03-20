<?php
session_start();
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', '1'); // Display errors on the screen
ini_set('display_startup_errors', '1'); // Display startup errors
include '../../config/db.php';
require_once 'ems_callback_delivery.php';

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

    $result = sendDeliveryCallback($order_id, $conn);

    $note = $result['success']
        ? 'Manual resend success'
        : 'Manual resend fail';

    $status = $result['success']
        ? 'callback_success'
        : 'callback_retry';

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
SELECT 
o.id,
o.ems_code,
o.status as order_status,

MAX(CASE WHEN t.status='callback_fail' THEN t.created_at END) as callback_fail_time,

SUM(CASE WHEN t.status='callback_retry' THEN 1 ELSE 0 END) as retry_count,

MAX(CASE WHEN t.status='callback_dead' THEN 1 ELSE 0 END) as is_dead

FROM emslss_orders o

LEFT JOIN emslss_tracking t ON o.id=t.order_id

GROUP BY o.id

HAVING callback_fail_time IS NOT NULL
OR retry_count > 0
OR is_dead=1

ORDER BY callback_fail_time DESC
";

$res = $conn->query($sql);

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

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>