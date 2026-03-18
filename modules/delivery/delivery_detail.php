<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role      = $_SESSION['role'];

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
    die("Không tìm thấy đơn hoặc không có quyền");
}

$message = "";

/* submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'];
    $note   = trim($_POST['note'] ?? '');

    $new_status = '';

    if ($action == 'success') {
        $new_status = 'delivered';
    } elseif ($action == 'fail') {
        $new_status = 'failed';
    }

    /* upload image */
    if (!empty($_FILES['proof_image']['name'])) {

        $upload_dir = 'uploads/delivery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . '_' . basename($_FILES['proof_image']['name']);
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target)) {

            $img_stmt = $conn->prepare("
                INSERT INTO emslss_images(order_id, image_path, uploaded_by)
                VALUES (?, ?, ?)
            ");
            $img_stmt->bind_param("isi", $order_id, $target, $user_id);
            $img_stmt->execute();
        }
    }

    /* update order */
    $up = $conn->prepare("
        UPDATE emslss_orders
        SET status = ?
        WHERE id = ?
    ");
    $up->bind_param("si", $new_status, $order_id);
    $up->execute();

    /* tracking */
    $track_note = ($action == 'success')
        ? 'Giao hàng thành công'
        : 'Giao hàng thất bại: ' . $note;

    $tr = $conn->prepare("
        INSERT INTO emslss_tracking(order_id, status, note, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $tr->bind_param("issi", $order_id, $new_status, $track_note, $user_id);
    $tr->execute();

    /*
    callback EMS sau này:
    include ems_callback.php
    */

    $message = "Cập nhật thành công";
    
    header("Refresh:1; url=delivery_dashboard.php");
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background:#f4f6f9;
}
.card-box{
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4>📦 Chi tiết giao hàng</h4>
        <a href="delivery_dashboard.php" class="btn btn-secondary">← Quay lại</a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <div class="card card-box p-4">

        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Mã EMS:</strong><br>
                <?= $order['ems_code'] ?>
            </div>
            <div class="col-md-6">
                <strong>Loại dịch vụ:</strong><br>
                <?= $order['service_type'] ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Người nhận:</strong><br>
                <?= htmlspecialchars($order['receiver_name']) ?>
            </div>
            <div class="col-md-6">
                <strong>Điện thoại:</strong><br>
                <?= htmlspecialchars($order['receiver_phone']) ?>
            </div>
        </div>

        <div class="mb-3">
            <strong>Địa chỉ:</strong><br>
            <?= htmlspecialchars($order['receiver_address']) ?>
        </div>

        <div class="mb-3">
            <strong>Loại hàng:</strong><br>
            <?= htmlspecialchars($order['cargo_type']) ?>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label">Ảnh bằng chứng giao hàng</label>
                <input type="file" name="proof_image" class="form-control" accept="image/*">
            </div>

            <div class="mb-3">
                <label class="form-label">Ghi chú (nếu thất bại)</label>
                <textarea name="note" class="form-control" rows="3"></textarea>
            </div>

            <div class="d-flex gap-2">

                <button name="action" value="success" class="btn btn-success">
                    ✅ Giao thành công
                </button>

                <button name="action" value="fail" class="btn btn-danger">
                    ❌ Giao thất bại
                </button>

            </div>

        </form>

    </div>

    <div class="card card-box p-4 mt-4">

        <h5>📜 Tracking</h5>

        <?php
        $trk = $conn->prepare("
            SELECT t.*, u.full_name
            FROM emslss_tracking t
            LEFT JOIN emslss_users u ON t.created_by = u.id
            WHERE t.order_id = ?
            ORDER BY t.created_at DESC
        ");
        $trk->bind_param("i", $order_id);
        $trk->execute();
        $tracks = $trk->get_result();

        while($t = $tracks->fetch_assoc()):
        ?>

            <div class="border-bottom py-2">
                <strong><?= $t['status'] ?></strong><br>
                <?= htmlspecialchars($t['note']) ?><br>
                <small><?= $t['full_name'] ?> | <?= $t['created_at'] ?></small>
            </div>

        <?php endwhile; ?>

    </div>

</div>

</body>
</html>