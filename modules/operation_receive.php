<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$sql = "
    SELECT *
    FROM emslss_orders
    WHERE id = $order_id
    LIMIT 1
";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Không tìm thấy đơn");
}

$order = $result->fetch_assoc();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $scan_code = trim($_POST['scan_code']);

    if ($scan_code != $order['ems_code']) {

        $message = '<div class="alert alert-danger">❌ Mã scan không đúng</div>';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Update status
        |--------------------------------------------------------------------------
        */

        $update = "
            UPDATE emslss_orders
            SET status='in_transit',
                updated_at=NOW()
            WHERE id=$order_id
        ";

        $conn->query($update);

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        $tracking = "
            INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
            VALUES($order_id,'in_transit','Operation đã nhận hàng',$user_id,NOW())
        ";

        $conn->query($tracking);

        header("Location: operation_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Operation Receive</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}

.box{
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.07);
    margin-bottom:16px;
}

.btn-action{
    border-radius:12px;
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="box">
        <h5>📦 Operation nhận hàng</h5>
        <small class="text-muted"><?= htmlspecialchars($order['ems_code']) ?></small>
    </div>

    <?= $message ?>

    <form method="POST">

        <div class="box">

            <div class="mb-3">
                <label class="form-label">Scan mã EMS</label>
                <input type="text" name="scan_code" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100 btn-action">
                ✅ Xác nhận nhận kho
            </button>

        </div>

    </form>

</div>

</body>
</html>