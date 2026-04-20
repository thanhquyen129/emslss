<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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

$imgStmt = $conn->prepare("
    SELECT image_path, created_at
    FROM emslss_images
    WHERE order_id = ?
    ORDER BY created_at DESC
");
$imgStmt->bind_param("i", $order_id);
$imgStmt->execute();
$images = $imgStmt->get_result();
$imageRows = [];
while ($img = $images->fetch_assoc()) {
    $path = $img['image_path'] ?? '';
    $type = 'proof';
    if (stripos($path, '/signatures/') !== false) {
        $type = 'signature';
    }
    $img['image_type'] = $type;
    $imageRows[] = $img;
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
.image-filter .btn{
    border-radius:20px;
}
.thumb-img{
    width:100%;
    height:150px;
    object-fit:cover;
    cursor:pointer;
}
.lightbox-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.88);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:16px;
}
.lightbox-overlay.show{
    display:flex;
}
.lightbox-overlay img{
    max-width:100%;
    max-height:100%;
    border-radius:8px;
}
.lightbox-close{
    position:absolute;
    top:12px;
    right:16px;
    color:#fff;
    font-size:28px;
    cursor:pointer;
    user-select:none;
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4>📦 Chi tiết giao hàng</h4>
        <a href="delivery_dashboard.php" class="btn btn-secondary">← Quay lại</a>
    </div>

    <div class="card card-box p-4">

        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Mã EMS:</strong><br>
                <a href="/modules/admin/admin_order_detail.php?id=<?= intval($order['id']) ?>">
                    <?= htmlspecialchars($order['ems_code']) ?>
                </a>
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
        <h5>🖼 Ảnh bằng chứng</h5>
        <div class="image-filter d-flex gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-dark filter-btn active" data-filter="all">Tất cả</button>
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="proof">Ảnh giao hàng</button>
            <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="signature">Chữ ký</button>
        </div>
        <div class="row g-3">
            <?php if (count($imageRows) === 0): ?>
                <div class="col-12 text-muted">Chưa có ảnh</div>
            <?php else: ?>
                <?php foreach($imageRows as $img): ?>
                <div class="col-6 col-md-3 image-item" data-image-type="<?= htmlspecialchars($img['image_type']) ?>">
                    <img
                        src="<?= htmlspecialchars($img['image_path']) ?>"
                        data-full="<?= htmlspecialchars($img['image_path']) ?>"
                        class="img-fluid rounded border thumb-img"
                        alt="proof"
                    >
                    <span class="badge <?= $img['image_type'] === 'signature' ? 'bg-secondary' : 'bg-primary' ?>">
                        <?= $img['image_type'] === 'signature' ? 'Chữ ký' : 'Giao hàng' ?>
                    </span>
                    <small class="text-muted d-block mt-1"><?= htmlspecialchars($img['created_at']) ?></small>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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

<div id="lightbox" class="lightbox-overlay">
    <span class="lightbox-close" id="lightboxClose">&times;</span>
    <img id="lightboxImage" src="" alt="preview">
</div>

<script>
const filterButtons = document.querySelectorAll('.filter-btn');
const imageItems = document.querySelectorAll('.image-item');

filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const filter = btn.dataset.filter;
        filterButtons.forEach(b => {
            b.classList.remove('active', 'btn-dark');
            b.classList.add('btn-outline-primary');
        });
        btn.classList.add('active', 'btn-dark');
        btn.classList.remove('btn-outline-primary', 'btn-outline-secondary');

        imageItems.forEach(item => {
            const type = item.dataset.imageType;
            item.style.display = (filter === 'all' || type === filter) ? '' : 'none';
        });
    });
});

const lightbox = document.getElementById('lightbox');
const lightboxImage = document.getElementById('lightboxImage');
const lightboxClose = document.getElementById('lightboxClose');

document.querySelectorAll('.thumb-img').forEach(img => {
    img.addEventListener('click', () => {
        lightboxImage.src = img.dataset.full;
        lightbox.classList.add('show');
    });
});

lightboxClose.addEventListener('click', () => lightbox.classList.remove('show'));
lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) {
        lightbox.classList.remove('show');
    }
});
</script>

</body>
</html>