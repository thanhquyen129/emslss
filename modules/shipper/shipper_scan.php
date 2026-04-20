<?php
session_start();
include '../../config/db.php';
require_once '../../api/callback_pickup.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

if (!isset($_GET['id'])) {
    die("Thiếu ID đơn");
}

$order_id = intval($_GET['id']);

/*
|--------------------------------------------------------------------------
| Query order
|--------------------------------------------------------------------------
*/

if ($role == 'shipper') {
    $sql = "
        SELECT *
        FROM emslss_orders
        WHERE id = $order_id
        AND pickup_shipper_id = $user_id
        LIMIT 1
    ";
} else {
    $sql = "
        SELECT *
        FROM emslss_orders
        WHERE id = $order_id
        LIMIT 1
    ";
}

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Không tìm thấy đơn");
}

$order = $result->fetch_assoc();

$message = '';
$doneStatuses = ['picked_up', 'in_transit', 'assigned_delivery', 'delivered', 'failed', 'cancelled'];
if (in_array($order['status'], $doneStatuses, true)) {
    $message = '<div class="alert alert-warning">Đơn đã pickup/xử lý trước đó.</div>';
}

/*
|--------------------------------------------------------------------------
| Confirm pickup
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $scanned_code = trim($_POST['scanned_code']);
    $note         = trim($_POST['note']);

    if ($scanned_code != $order['ems_code']) {
        $message = '<div class="alert alert-danger">❌ Mã EMS không khớp</div>';
    } elseif (in_array($order['status'], $doneStatuses, true)) {
        $message = '<div class="alert alert-warning">Đơn đã pickup/xử lý trước đó.</div>';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Update order
        |--------------------------------------------------------------------------
        */

        $update = "
            UPDATE emslss_orders
            SET status='picked_up',
                updated_at=NOW()
            WHERE id=$order_id
        ";

        $conn->query($update);

        /*
        |--------------------------------------------------------------------------
        | Insert tracking
        |--------------------------------------------------------------------------
        */

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
            VALUES(?, 'picked_up', ?, ?, NOW())
        ");
        $tr->bind_param("isi", $order_id, $note, $user_id);
        $tr->execute();

        /*
        |--------------------------------------------------------------------------
        | Upload images
        |--------------------------------------------------------------------------
        */

        if (!empty($_FILES['images']['name'][0])) {

            $upload_dir_fs = __DIR__ . '/../../uploads/pickup/';
            $upload_dir_db = '/uploads/pickup/';

            if (!is_dir($upload_dir_fs)) {
                mkdir($upload_dir_fs, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {

                $file_name = time() . '_' . $key . '_' . basename($_FILES['images']['name'][$key]);
                $target_fs = $upload_dir_fs . $file_name;
                $target_db = $upload_dir_db . $file_name;

                if (move_uploaded_file($tmp_name, $target_fs)) {
                    $img = $conn->prepare("
                        INSERT INTO emslss_images(order_id,image_path,uploaded_by,created_at)
                        VALUES(?,?,?,NOW())
                    ");
                    $img->bind_param("isi", $order_id, $target_db, $user_id);
                    $img->execute();
                }
            }
        }

        sendPickupCallback($order_id);

        header("Location: shipper_complete.php?id=".$order_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Pickup Scan</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
body{
    background:#f5f7fb;
}

.box{
    background:white;
    border-radius:16px;
    padding:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.07);
    margin-bottom:16px;
}

.btn-action{
    border-radius:12px;
}

#reader{
    width:100%;
}

.label{
    font-size:13px;
    color:#777;
}

.value{
    font-weight:600;
}
</style>
</head>
<body>

<div class="container py-3">

    <div class="box">
        <h5><?= htmlspecialchars($order['ems_code']) ?></h5>
        <small class="text-muted">Scan xác nhận pickup</small>
    </div>

    <?= $message ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="box">
            <div class="mb-3">
                <label class="form-label">📷 Camera scan EMS</label>
                <div id="reader"></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Hoặc nhập mã EMS</label>
                <input type="text" name="scanned_code" id="scanned_code" class="form-control" required>
            </div>
        </div>

        <div class="box">
            <div class="mb-3">
                <label class="form-label">📸 Ảnh bằng chứng</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*" capture="environment">
            </div>

            <div class="mb-3">
                <label class="form-label">📝 Ghi chú</label>
                <textarea name="note" class="form-control" rows="3" placeholder="Ví dụ: Nhận tại quầy số 2"></textarea>
            </div>
        </div>

        <div class="d-grid gap-2 pb-4">
            <button type="submit" class="btn btn-primary btn-lg btn-action">
                ✅ Xác nhận đã pickup
            </button>

            <a href="shipper_order_detail.php?id=<?= $order_id ?>" class="btn btn-outline-secondary btn-action">
                ← Quay lại
            </a>
        </div>

    </form>

</div>

<script>
function onScanSuccess(decodedText) {
    document.getElementById('scanned_code').value = decodedText;
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