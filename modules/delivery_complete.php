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
SELECT * FROM emslss_orders
WHERE id=? AND delivery_shipper_id=?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Không tìm thấy đơn");
}

if ($order['status'] == 'delivered') {
    die("Đơn đã hoàn tất, không thể submit lại.");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'];
    $fail_reason = trim($_POST['fail_reason'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $signature = $_POST['signature_data'] ?? '';

    $new_status = ($action == 'success') ? 'delivered' : 'failed';

    // lock chống submit 2 lần
    $check = $conn->prepare("SELECT status FROM emslss_orders WHERE id=?");
    $check->bind_param("i", $order_id);
    $check->execute();
    $current = $check->get_result()->fetch_assoc();

    if ($current['status'] == 'delivered') {
        die("Đơn đã được submit trước đó.");
    }

    // lưu signature
    if ($signature) {
        $sig_dir = 'uploads/signatures/';
        if (!is_dir($sig_dir)) mkdir($sig_dir, 0777, true);

        $sig_file = $sig_dir . time() . "_sig.png";
        $sig_data = explode(',', $signature)[1];
        file_put_contents($sig_file, base64_decode($sig_data));

        $img_stmt = $conn->prepare("
            INSERT INTO emslss_images(order_id,image_path,uploaded_by)
            VALUES(?,?,?)
        ");
        $img_stmt->bind_param("isi", $order_id, $sig_file, $user_id);
        $img_stmt->execute();
    }

    // upload nhiều ảnh
    if (!empty($_FILES['proof_images']['name'][0])) {

        $upload_dir = 'uploads/delivery/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($_FILES['proof_images']['tmp_name'] as $k => $tmp) {

            $filename = time().'_'.$k.'_'.basename($_FILES['proof_images']['name'][$k]);
            $target = $upload_dir.$filename;

            if (move_uploaded_file($tmp, $target)) {
                $img_stmt = $conn->prepare("
                    INSERT INTO emslss_images(order_id,image_path,uploaded_by)
                    VALUES(?,?,?)
                ");
                $img_stmt->bind_param("isi", $order_id, $target, $user_id);
                $img_stmt->execute();
            }
        }
    }

    // update order
    $up = $conn->prepare("
        UPDATE emslss_orders
        SET status=?
        WHERE id=?
    ");
    $up->bind_param("si", $new_status, $order_id);
    $up->execute();

    // tracking
    $track_note = ($action == 'success')
        ? 'Giao hàng thành công'
        : 'Giao thất bại: '.$fail_reason.' | '.$note;

    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id,status,note,created_by)
        VALUES(?,?,?,?)
    ");
    $tr->bind_param("issi", $order_id, $new_status, $track_note, $user_id);
    $tr->execute();

    // callback EMS giả lập
    $payload = json_encode([
        'ems_code' => $order['ems_code'],
        'status' => $new_status,
        'note' => $track_note
    ], JSON_UNESCAPED_UNICODE);

    $response = json_encode([
        'success' => true,
        'message' => 'EMS callback ok'
    ]);

    $log = $conn->prepare("
        INSERT INTO emslss_api_logs(source,payload,response)
        VALUES('delivery_complete',?,?)
    ");
    $log->bind_param("ss", $payload, $response);
    $log->execute();

    header("Location: delivery_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Complete</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fa;}
canvas{
    border:1px solid #ccc;
    width:100%;
    max-width:400px;
    height:200px;
}
.card-box{
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
</style>
</head>
<body>

<div class="container py-4">

<div class="card card-box p-4">

<h4>✅ Hoàn tất giao hàng</h4>

<form method="POST" enctype="multipart/form-data" id="completeForm">

<div class="mb-3">
<label>Ảnh bằng chứng (nhiều ảnh)</label>
<input type="file" name="proof_images[]" multiple class="form-control" accept="image/*">
</div>

<div class="mb-3">
<label>Lý do thất bại (nếu có)</label>
<select name="fail_reason" class="form-select">
<option value="">-- chọn --</option>
<option>Không liên hệ được khách</option>
<option>Khách từ chối nhận</option>
<option>Sai địa chỉ</option>
<option>Khách hẹn lại</option>
</select>
</div>

<div class="mb-3">
<label>Ghi chú</label>
<textarea name="note" class="form-control"></textarea>
</div>

<div class="mb-3">
<label>Ký nhận khách hàng</label><br>
<canvas id="signature-pad"></canvas>
<input type="hidden" name="signature_data" id="signature_data">
<br>
<button type="button" class="btn btn-secondary mt-2" onclick="clearPad()">Xóa ký</button>
</div>

<div class="d-flex gap-2">
<button name="action" value="success" class="btn btn-success" onclick="prepareSubmit()">Giao thành công</button>
<button name="action" value="fail" class="btn btn-danger" onclick="prepareSubmit()">Giao thất bại</button>
</div>

</form>

</div>

</div>

<script>
const canvas = document.getElementById('signature-pad');
const ctx = canvas.getContext('2d');

canvas.width = 400;
canvas.height = 200;

let drawing = false;

canvas.addEventListener('mousedown', e=>{
    drawing = true;
    ctx.beginPath();
});

canvas.addEventListener('mousemove', e=>{
    if(!drawing) return;
    const rect = canvas.getBoundingClientRect();
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    ctx.stroke();
});

canvas.addEventListener('mouseup', ()=>{
    drawing = false;
});

function clearPad(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
}

function prepareSubmit(){
    document.getElementById('signature_data').value = canvas.toDataURL();
    document.getElementById('completeForm').submit();
}
</script>

</body>
</html>