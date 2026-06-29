<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /modules/login.php");
    exit;
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

$order_id = intval($_GET['id']);
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Nhập kho là việc của bộ phận operation (hoặc admin), không phải shipper
if (!in_array($role, ['operation', 'admin'], true)) {
    die("Access denied");
}

$stmt = $conn->prepare("SELECT * FROM emslss_orders WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Không tìm thấy đơn");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $scan_code = trim($_POST['scan_code'] ?? '');

    if ($scan_code != $order['ems_code']) {

        $message = '<div class="alert alert-danger">❌ Mã scan không đúng</div>';

    } else {

        try {
            $conn->begin_transaction();

            $update = $conn->prepare("
                UPDATE emslss_orders
                SET status='in_transit', updated_at=NOW()
                WHERE id=?
            ");
            $update->bind_param("i", $order_id);
            $update->execute();

            $tr = $conn->prepare("
                INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
                VALUES(?, 'in_transit', 'Operation đã nhận hàng', ?, NOW())
            ");
            $tr->bind_param("ii", $order_id, $user_id);
            $tr->execute();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Lỗi xử lý: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        if ($message === '') {
            header("Location: /modules/operation/dashboard.php");
            exit;
        }
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
                <label class="form-label">📷 Camera scan barcode/QR</label>
                <div id="reader"></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Hoặc nhập mã EMS</label>
                <input type="text" name="scan_code" id="scan_code" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100 btn-action">
                ✅ Xác nhận nhận kho
            </button>

        </div>

    </form>

    <a href="/modules/operation/dashboard.php" class="btn btn-outline-secondary w-100 btn-action">
        ← Quay lại dashboard
    </a>

</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
function onScanSuccess(decodedText) {
    document.getElementById('scan_code').value = decodedText;
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: 250 }
);

html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>