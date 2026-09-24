<?php
session_start();
include '../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';
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

    $action       = $_POST['action'] ?? 'success';
    $scanned_code = trim($_POST['scanned_code'] ?? '');
    $note         = trim($_POST['note'] ?? '');
    $fail_reason  = trim($_POST['fail_reason'] ?? '');

    if (!in_array($action, ['success', 'fail'], true)) {
        $message = '<div class="alert alert-danger">Thao tác không hợp lệ</div>';
    } elseif (in_array($order['status'], $doneStatuses, true)) {
        $message = '<div class="alert alert-warning">Đơn đã pickup/xử lý trước đó.</div>';
    } elseif ($action === 'success' && $scanned_code != $order['ems_code']) {
        $message = '<div class="alert alert-danger">❌ Mã EMS không khớp</div>';
    } elseif ($action === 'fail' && $fail_reason === '') {
        $message = '<div class="alert alert-danger">Vui lòng chọn lý do thu gom không thành công.</div>';
    } elseif ($action === 'success') {

        /*
        |--------------------------------------------------------------------------
        | Thu gom thành công
        |--------------------------------------------------------------------------
        */

        $update = $conn->prepare("
            UPDATE emslss_orders
            SET status='picked_up', updated_at=NOW()
            WHERE id=?
        ");
        $update->bind_param("i", $order_id);
        $update->execute();

        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
            VALUES(?, 'picked_up', ?, ?, NOW())
        ");
        $tr->bind_param("isi", $order_id, $note, $user_id);
        $tr->execute();

        $pickupPaths = [];
        foreach (['images_camera', 'images_gallery'] as $imgField) {
            if (!empty($_FILES[$imgField]['name'][0])) {
                $pickupPaths = array_merge($pickupPaths, emslss_upload_save_multipart_field('pickup', $_FILES[$imgField]));
            }
        }
        if ($pickupPaths !== []) {
            emslss_upload_insert_images($conn, $order_id, $pickupPaths, $user_id);
        }

        sendPickupCallback($order_id, 'picked_up');

        header("Location: shipper_complete.php?id=".$order_id);
        exit;
    } else {

        /*
        |--------------------------------------------------------------------------
        | Thu gom KHÔNG thành công — EMS chỉ có trạng thái chung 'failed' + reason
        |--------------------------------------------------------------------------
        */

        $update = $conn->prepare("
            UPDATE emslss_orders
            SET status='failed', updated_at=NOW()
            WHERE id=?
        ");
        $update->bind_param("i", $order_id);
        $update->execute();

        $track_note = 'Thu gom thất bại — Lý do: ' . $fail_reason . ($note !== '' ? '. Ghi chú: ' . $note : '');
        $tr = $conn->prepare("
            INSERT INTO emslss_tracking(order_id,status,note,created_by,created_at)
            VALUES(?, 'failed', ?, ?, NOW())
        ");
        $tr->bind_param("isi", $order_id, $track_note, $user_id);
        $tr->execute();

        $metaKey = 'fail_note';
        $metaValue = 'Thu gom thất bại: ' . $fail_reason . ($note !== '' ? '. ' . $note : '');
        $meta = $conn->prepare("
            INSERT INTO emslss_order_meta(order_id, meta_key, meta_value)
            VALUES(?,?,?)
        ");
        $meta->bind_param("iss", $order_id, $metaKey, $metaValue);
        $meta->execute();

        $pickupPaths = [];
        foreach (['images_camera', 'images_gallery'] as $imgField) {
            if (!empty($_FILES[$imgField]['name'][0])) {
                $pickupPaths = array_merge($pickupPaths, emslss_upload_save_multipart_field('pickup', $_FILES[$imgField]));
            }
        }
        if ($pickupPaths !== []) {
            emslss_upload_insert_images($conn, $order_id, $pickupPaths, $user_id);
        }

        $callbackReason = $fail_reason . ($note !== '' ? '. ' . $note : '');
        sendPickupCallback($order_id, 'failed', ['reason' => $callbackReason]);

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
                <label class="form-label">📸 Ảnh bằng chứng — chụp</label>
                <input type="file" name="images_camera[]" class="form-control" multiple accept="image/*" capture="environment">
            </div>
            <div class="mb-3">
                <label class="form-label">🖼️ Ảnh bằng chứng — chọn từ thư viện (nhiều ảnh)</label>
                <input type="file" name="images_gallery[]" class="form-control" multiple accept="image/*">
            </div>

            <div class="mb-3">
                <label class="form-label">📝 Ghi chú</label>
                <textarea name="note" class="form-control" rows="3" placeholder="Ví dụ: Nhận tại quầy số 2"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">⚠️ Lý do thu gom không thành công</label>
                <select name="fail_reason" id="fail_reason" class="form-select">
                    <option value="">-- chọn --</option>
                    <option>Không có hàng để thu gom</option>
                    <option>Người gửi hẹn lại hôm sau</option>
                    <option>Không liên lạc được người gửi</option>
                    <option>Sai địa chỉ thu gom</option>
                    <option>Hàng không đúng quy cách</option>
                    <option>Lý do khác</option>
                </select>
                <div class="form-text">Bắt buộc khi báo thu gom không thành công.</div>
            </div>
        </div>

        <div class="d-grid gap-2 pb-4">
            <button type="submit" name="action" value="success" class="btn btn-primary btn-lg btn-action">
                ✅ Xác nhận đã pickup
            </button>

            <button type="submit" name="action" value="fail" id="btnFail" formnovalidate class="btn btn-danger btn-action">
                ⚠️ Thu gom không thành công
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

document.getElementById('btnFail').addEventListener('click', function (e) {
    if (!document.getElementById('fail_reason').value) {
        e.preventDefault();
        alert('Vui lòng chọn lý do thu gom không thành công.');
    }
});
</script>

</body>
</html>