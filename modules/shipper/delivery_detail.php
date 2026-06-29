<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/upload.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['shipper', 'admin', 'operation'], true)) {
    die('Access denied');
}

$order_id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare('
    SELECT * FROM emslss_orders
    WHERE id = ? AND delivery_shipper_id = ?
');
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Không tìm thấy đơn hoặc không có quyền');
}

$imgStmt = $conn->prepare('
    SELECT id, image_path, created_at
    FROM emslss_images
    WHERE order_id = ?
    ORDER BY created_at DESC
');
$imgStmt->bind_param('i', $order_id);
$imgStmt->execute();
$imageRows = [];
$res = $imgStmt->get_result();
while ($img = $res->fetch_assoc()) {
    $img['image_path'] = emslss_upload_url($img['image_path'] ?? '');
    $imageRows[] = $img;
}
$canDeleteImages = in_array($order['status'], ['assigned_delivery', 'in_transit', 'failed'], true);

$meta = [];
$metaStmt = $conn->prepare('SELECT meta_key, meta_value FROM emslss_order_meta WHERE order_id = ?');
$metaStmt->bind_param('i', $order_id);
$metaStmt->execute();
$metaRes = $metaStmt->get_result();
while ($m = $metaRes->fetch_assoc()) {
    $meta[$m['meta_key']][] = $m['meta_value'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f6f9; }
.card-box { border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
</style>
</head>
<body>

<div class="container py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4>📦 Chi tiết giao hàng</h4>
        <a href="shipper_dashboard.php" class="btn btn-secondary">← Quay lại</a>
    </div>

    <div class="card card-box p-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Mã EMS:</strong><br>
                <?= shipper_order_code_html($order, '/modules/admin/admin_order_detail.php?id=' . (int) $order['id']) ?>
            </div>
            <div class="col-md-6">
                <strong>Loại dịch vụ:</strong><br>
                <?= htmlspecialchars($order['service_type']) ?>
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
    </div>

    <div class="card card-box p-4 mt-4">
        <h5>🖼 Ảnh đơn hàng</h5>
        <?php if ($canDeleteImages): ?>
            <p class="small text-muted">Có thể xóa ảnh trước khi hoàn tất giao hàng.</p>
        <?php endif; ?>
        <?php
        $can_delete = $canDeleteImages;
        include __DIR__ . '/../../templates/shipper_image_gallery.php';
        ?>
    </div>

    <?php if (!empty($meta['delivery_recipient_name'][0]) || !empty($meta['delivery_note'][0]) || !empty($meta['fail_note'][0]) || !empty($meta['customer_signature'][0])): ?>
    <div class="card card-box p-4 mt-4">
        <h5>📝 Thông tin giao hàng</h5>
        <?php if (!empty($meta['delivery_recipient_name'][0])): ?>
            <p class="mb-2"><strong>Người nhận (đã ký nhận):</strong><br><?= htmlspecialchars($meta['delivery_recipient_name'][0]) ?></p>
        <?php endif; ?>
        <?php if (!empty($meta['delivery_note'][0])): ?>
            <p class="mb-2"><strong>Ghi chú phát:</strong><br><?= htmlspecialchars($meta['delivery_note'][0]) ?></p>
        <?php endif; ?>
        <?php if (!empty($meta['fail_note'][0])): ?>
            <p class="mb-2"><strong>Lý do không thành công:</strong><br><?= htmlspecialchars($meta['fail_note'][0]) ?></p>
        <?php endif; ?>
        <?php if (!empty($meta['customer_signature'][0])): ?>
            <strong>✍️ Chữ ký người nhận:</strong><br>
            <img src="<?= htmlspecialchars(emslss_upload_url($meta['customer_signature'][0])) ?>" class="img-fluid rounded mt-2" style="max-height:200px">
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card card-box p-4 mt-4">
        <h5>📜 Tracking</h5>
        <?php
        $trk = $conn->prepare('
            SELECT t.*, u.full_name
            FROM emslss_tracking t
            LEFT JOIN emslss_users u ON t.created_by = u.id
            WHERE t.order_id = ?
            ORDER BY t.created_at DESC
        ');
        $trk->bind_param('i', $order_id);
        $trk->execute();
        $tracks = $trk->get_result();
        while ($t = $tracks->fetch_assoc()):
        ?>
            <div class="border-bottom py-2">
                <strong><?= htmlspecialchars($t['status']) ?></strong><br>
                <?= htmlspecialchars($t['note'] ?? '') ?><br>
                <small><?= htmlspecialchars($t['full_name'] ?? '') ?> | <?= htmlspecialchars($t['created_at']) ?></small>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
