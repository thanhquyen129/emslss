<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../config/upload.php';
ems_require_role();

$order_id = (int) ($_GET['id'] ?? 0);
if ($order_id <= 0) {
    die('Invalid order');
}

$stmt = $conn->prepare("
    SELECT o.*,
           pu.full_name AS pickup_shipper_name,
           du.full_name AS delivery_shipper_name
    FROM emslss_orders o
    LEFT JOIN emslss_users pu ON o.pickup_shipper_id = pu.id
    LEFT JOIN emslss_users du ON o.delivery_shipper_id = du.id
    WHERE o.id = ?
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    die('Order not found');
}

$trackStmt = $conn->prepare("
    SELECT t.*, u.full_name
    FROM emslss_tracking t
    LEFT JOIN emslss_users u ON t.created_by = u.id
    WHERE t.order_id = ?
    ORDER BY t.created_at DESC
");
$trackStmt->bind_param('i', $order_id);
$trackStmt->execute();
$tracking = $trackStmt->get_result();

$imgStmt = $conn->prepare('SELECT * FROM emslss_images WHERE order_id = ? ORDER BY created_at DESC');
$imgStmt->bind_param('i', $order_id);
$imgStmt->execute();
$images = $imgStmt->get_result();

$meta = [];
$metaStmt = $conn->prepare('SELECT meta_key, meta_value FROM emslss_order_meta WHERE order_id = ?');
$metaStmt->bind_param('i', $order_id);
$metaStmt->execute();
$metaRes = $metaStmt->get_result();
while ($m = $metaRes->fetch_assoc()) {
    $meta[$m['meta_key']][] = $m['meta_value'];
}

$emsCode = $order['ems_code'];
$logLike = '%' . $emsCode . '%';
$logStmt = $conn->prepare("
    SELECT * FROM emslss_api_logs
    WHERE payload LIKE ? OR response LIKE ?
    ORDER BY created_at DESC
    LIMIT 50
");
$logStmt->bind_param('ss', $logLike, $logLike);
$logStmt->execute();
$callback_logs = $logStmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chi tiết <?= htmlspecialchars($order['ems_code']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f0f4f8; }
.card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
.timeline-item { border-left: 3px solid #0d6efd; padding-left: 12px; margin-bottom: 12px; }
.img-thumb { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; margin: 4px; }
pre { font-size: 11px; white-space: pre-wrap; max-height: 200px; overflow: auto; }
</style>
</head>
<body>
<?php include __DIR__ . '/../../templates/ems_topbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4>📦 <?= htmlspecialchars($order['ems_code']) ?> <?= ems_status_badge($order['status'], [
            'lss_reject_reason' => $meta['lss_reject_reason'][0] ?? '',
        ]) ?></h4>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">← Danh sách</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card p-3 mb-3">
                <h6>Thông tin đơn (read-only)</h6>
                <table class="table table-sm mb-0">
                    <tr><td width="140">Dịch vụ</td><td><?= htmlspecialchars($order['service_type'] ?? '-') ?></td></tr>
                    <tr><td>Loại hàng</td><td><?= htmlspecialchars($order['cargo_type'] ?? '-') ?></td></tr>
                    <?php if (!empty($meta['cargo_description'][0])): ?>
                    <tr><td>Nội dung hàng</td><td><?= htmlspecialchars($meta['cargo_description'][0]) ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Khối lượng</td><td><?= emslss_format_weight_html($order['weight'] ?? null) ?></td></tr>
                    <tr><td>Bưu cục</td><td><?= htmlspecialchars($order['post_office_name'] ?? '') ?><br><small class="text-muted"><?= htmlspecialchars($order['post_office_address'] ?? '') ?></small></td></tr>
                    <tr><td>Người giữ</td><td><?= htmlspecialchars($order['holder_name'] ?? '') ?> · <?= htmlspecialchars($order['holder_phone'] ?? '') ?></td></tr>
                    <tr><td>Người gửi</td><td><?= htmlspecialchars($order['sender_name'] ?? '') ?><br><?= htmlspecialchars($order['sender_address'] ?? '') ?></td></tr>
                    <tr><td>Người nhận</td><td><?= htmlspecialchars($order['receiver_name'] ?? '') ?> · <?= htmlspecialchars($order['receiver_phone'] ?? '') ?><br><?= htmlspecialchars($order['receiver_address'] ?? '') ?></td></tr>
                    <tr><td>Pickup shipper</td><td><?= htmlspecialchars($order['pickup_shipper_name'] ?: '-') ?></td></tr>
                    <tr><td>Delivery shipper</td><td><?= htmlspecialchars($order['delivery_shipper_name'] ?: '-') ?></td></tr>
                    <tr><td>Tạo lúc</td><td><?= htmlspecialchars($order['created_at']) ?></td></tr>
                    <tr><td>Cập nhật</td><td><?= htmlspecialchars($order['updated_at'] ?? '-') ?></td></tr>
                </table>
            </div>

            <div class="card p-3 mb-3">
                <h6>Ghi chú / Meta</h6>
                <p class="mb-1"><b>Người nhận (đã ký nhận):</b> <?= htmlspecialchars($meta['delivery_recipient_name'][0] ?? '-') ?></p>
                <p class="mb-1"><b>Fail note:</b> <?= htmlspecialchars($meta['fail_note'][0] ?? '-') ?></p>
                <p class="mb-1"><b>Delivery note:</b> <?= htmlspecialchars($meta['delivery_note'][0] ?? '-') ?></p>
                <?php if (!empty($meta['lss_reject_reason'][0])): ?>
                <p class="mb-0 text-danger"><b>LSS từ chối nhận:</b> <?= htmlspecialchars($meta['lss_reject_reason'][0]) ?>
                <?php if (!empty($meta['lss_reject_at'][0])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($meta['lss_reject_at'][0]) ?></small>
                <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card p-3 mb-3">
                <h6>📍 Timeline</h6>
                <?php while ($t = $tracking->fetch_assoc()): ?>
                <div class="timeline-item">
                    <b><?= htmlspecialchars($t['status']) ?></b>
                    <?php if ($t['note']): ?><br><?= htmlspecialchars($t['note']) ?><?php endif; ?>
                    <br><small class="text-muted"><?= htmlspecialchars($t['created_at']) ?>
                    <?= $t['full_name'] ? ' · ' . htmlspecialchars($t['full_name']) : '' ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <h6>🖼 Ảnh pickup / delivery</h6>
        <?php if ($images->num_rows === 0): ?>
            <p class="text-muted mb-0">Chưa có ảnh</p>
        <?php endif; ?>
        <?php while ($img = $images->fetch_assoc()): ?>
            <?php $imgUrl = emslss_upload_url($img['image_path']); ?>
            <a href="<?= htmlspecialchars($imgUrl) ?>" target="_blank">
                <img src="<?= htmlspecialchars($imgUrl) ?>" class="img-thumb" alt="proof">
            </a>
        <?php endwhile; ?>
    </div>

    <?php if (!empty($meta['customer_signature'][0])): ?>
    <div class="card p-3 mb-3">
        <h6>✍️ Chữ ký khách</h6>
        <img src="<?= htmlspecialchars(emslss_upload_url($meta['customer_signature'][0])) ?>" class="img-fluid rounded" style="max-height:200px">
    </div>
    <?php endif; ?>

    <div class="card p-3">
        <h6>📡 API / Callback logs</h6>
        <?php if (!$callback_logs || $callback_logs->num_rows === 0): ?>
            <p class="text-muted">Chưa có log API cho mã này</p>
        <?php endif; ?>
        <?php while ($log = $callback_logs->fetch_assoc()): ?>
        <div class="border rounded p-2 mb-2 bg-light">
            <b><?= htmlspecialchars($log['source']) ?></b>
            <small class="text-muted ms-2"><?= htmlspecialchars($log['created_at']) ?></small>
            <pre class="mb-1 mt-1"><?= htmlspecialchars($log['payload']) ?></pre>
            <pre class="mb-0 text-muted"><?= htmlspecialchars($log['response']) ?></pre>
        </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>
