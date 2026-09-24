<?php
require_once __DIR__ . '/init.php';
ems_require_role();

$filter = $_GET['filter'] ?? 'fail';

$sql = "
    SELECT o.id, o.ems_code, o.status, o.updated_at,
           MAX(CASE WHEN t.status = 'callback_fail' THEN t.created_at END) AS last_fail,
           MAX(CASE WHEN t.status = 'callback_dead' THEN 1 ELSE 0 END) AS is_dead,
           COUNT(CASE WHEN t.status = 'callback_retry' THEN 1 END) AS retry_count
    FROM emslss_orders o
    INNER JOIN emslss_tracking t ON t.order_id = o.id
    WHERE t.status IN ('callback_fail', 'callback_dead', 'callback_retry')
";

if ($filter === 'dead') {
    $sql .= " GROUP BY o.id HAVING is_dead = 1";
} else {
    $sql .= " GROUP BY o.id HAVING MAX(CASE WHEN t.status = 'callback_fail' THEN 1 ELSE 0 END) = 1";
}

$sql .= " ORDER BY last_fail DESC LIMIT 100";

$orders = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Callback EMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../../templates/ems_topbar.php'; ?>

<div class="container-fluid py-4">
    <h4 class="mb-2">Theo dõi Callback về EMS</h4>
    <p class="text-muted small mb-3">Đơn có callback pickup/delivery thất bại khi trả trạng thái về hệ thống EMS (chỉ xem, không thao tác)</p>

    <div class="btn-group mb-3">
        <a href="?filter=fail" class="btn btn-sm <?= $filter !== 'dead' ? 'btn-danger' : 'btn-outline-danger' ?>">Đang lỗi</a>
        <a href="?filter=dead" class="btn btn-sm <?= $filter === 'dead' ? 'btn-dark' : 'btn-outline-dark' ?>">Dead queue</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã EMS</th>
                        <th>Trạng thái đơn</th>
                        <th>Lỗi lần cuối</th>
                        <th>Retry</th>
                        <th>Dead</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$orders || $orders->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Không có đơn callback lỗi</td></tr>
                <?php endif; ?>
                <?php while ($row = $orders->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['ems_code']) ?></strong></td>
                    <td><?= ems_status_badge($row['status']) ?></td>
                    <td><small><?= htmlspecialchars($row['last_fail'] ?? '-') ?></small></td>
                    <td><?= (int) $row['retry_count'] ?></td>
                    <td><?= $row['is_dead'] ? '<span class="badge bg-dark">Dead</span>' : '-' ?></td>
                    <td>
                        <a href="order_detail.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Chi tiết</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
