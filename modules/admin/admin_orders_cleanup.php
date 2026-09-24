<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_filter(array_map('intval', $_POST['order_ids'] ?? []));
    $confirm = trim($_POST['confirm_text'] ?? '');

    if ($confirm !== 'XOA') {
        $message = 'Gõ chính xác XOA để xác nhận xóa.';
        $messageType = 'danger';
    } elseif ($ids === []) {
        $message = 'Chưa chọn đơn nào.';
        $messageType = 'warning';
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        try {
            $conn->begin_transaction();
            foreach (['emslss_tracking', 'emslss_images', 'emslss_order_meta'] as $tbl) {
                $sql = "DELETE FROM $tbl WHERE order_id IN ($placeholders)";
                $st = $conn->prepare($sql);
                $st->bind_param($types, ...$ids);
                $st->execute();
            }
            $st = $conn->prepare("DELETE FROM emslss_orders WHERE id IN ($placeholders)");
            $st->bind_param($types, ...$ids);
            $st->execute();
            $deleted = $st->affected_rows;
            $conn->commit();
            $message = "Đã xóa $deleted đơn và dữ liệu liên quan.";
            $messageType = 'success';
        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Xóa thất bại: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

$keyword = trim($_GET['q'] ?? '');
$where = '';
if ($keyword !== '') {
    $like = '%' . $conn->real_escape_string($keyword) . '%';
    $where = "WHERE ems_code LIKE '$like' OR holder_name LIKE '$like' OR receiver_name LIKE '$like'";
}

$orders = $conn->query("
    SELECT id, ems_code, status, post_office_name, created_at
    FROM emslss_orders $where
    ORDER BY id DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Xóa đơn test</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../templates/admin_topbar.php'; ?>

<div class="container py-4" style="max-width:960px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Xóa đơn test</h4>
        <a href="admin_orders.php" class="btn btn-sm btn-outline-secondary">← Tất cả đơn</a>
    </div>

    <div class="alert alert-warning">
        <strong>Cảnh báo:</strong> Xóa vĩnh viễn đơn, tracking, ảnh và meta. Chỉ dùng để dọn dữ liệu test.
    </div>

    <?php if ($message !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-8">
            <input type="text" name="q" class="form-control" placeholder="Tìm mã EMS, tên..." value="<?= htmlspecialchars($keyword) ?>">
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary w-100">Tìm</button>
        </div>
    </form>

    <form method="POST" onsubmit="return confirm('Chắc chắn xóa các đơn đã chọn?');">
        <div class="table-responsive bg-white border rounded">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" id="checkAll"></th>
                        <th>ID</th><th>Mã EMS</th><th>Status</th><th>Bưu cục</th><th>Tạo lúc</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($row = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" name="order_ids[]" value="<?= (int)$row['id'] ?>" class="order-chk"></td>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['ems_code']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td><?= htmlspecialchars($row['post_office_name']) ?></td>
                        <td><small><?= htmlspecialchars($row['created_at']) ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Không có đơn</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3 row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Gõ <strong>XOA</strong> để xác nhận</label>
                <input type="text" name="confirm_text" class="form-control" autocomplete="off" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-danger w-100">Xóa đơn đã chọn</button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function(){
    document.querySelectorAll('.order-chk').forEach(c => c.checked = this.checked);
});
</script>
</body>
</html>
