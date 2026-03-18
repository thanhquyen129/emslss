<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

if (!in_array($role, ['shipper', 'admin', 'operation'])) {
    die("Access denied");
}

$order_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT *
    FROM emslss_orders
    WHERE id = ?
    AND delivery_shipper_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Không tìm thấy đơn");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $scan_code = trim($_POST['scan_code']);

    if ($scan_code !== $order['ems_code']) {
        $error = "Sai mã vận đơn. Không thể xác nhận.";
    } else {
        header("Location: delivery_detail.php?id=".$order_id."&verified=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Scan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
body{
    background:#f4f6f9;
}
.card-box{
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
#reader{
    width:100%;
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="mb-4 d-flex justify-content-between">
        <h4>📷 Scan xác nhận giao hàng</h4>
        <a href="delivery_detail.php?id=<?= $order_id ?>" class="btn btn-secondary">← Quay lại</a>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card card-box p-4">

        <div class="mb-3">
            <strong>Mã EMS cần scan:</strong><br>
            <span class="text-primary fs-5"><?= $order['ems_code'] ?></span>
        </div>

        <div id="reader"></div>

        <form method="POST" class="mt-4">

            <div class="mb-3">
                <label class="form-label">Hoặc nhập tay mã vận đơn</label>
                <input type="text" name="scan_code" id="scan_code" class="form-control">
            </div>

            <button class="btn btn-primary">
                Xác nhận mã
            </button>

        </form>

    </div>

</div>

<script>

function onScanSuccess(decodedText, decodedResult) {

    document.getElementById('scan_code').value = decodedText;

    if(decodedText === "<?= $order['ems_code'] ?>"){
        alert("✅ Đúng mã đơn");
        document.forms[0].submit();
    }else{
        alert("❌ Sai mã đơn");
    }
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader",
    {
        fps: 10,
        qrbox: 250
    }
);

html5QrcodeScanner.render(onScanSuccess);

</script>

</body>
</html>