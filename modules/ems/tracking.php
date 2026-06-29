<?php
require_once __DIR__ . '/init.php';
ems_require_role();

$ems_code = strtoupper(trim($_GET['ems_code'] ?? $_POST['ems_code'] ?? ''));
$order = null;
$timeline = null;
$error = '';

if ($ems_code !== '') {
    $stmt = $conn->prepare('SELECT * FROM emslss_orders WHERE ems_code = ? LIMIT 1');
    $stmt->bind_param('s', $ems_code);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $error = 'Không tìm thấy đơn với mã: ' . htmlspecialchars($ems_code);
    } else {
        $tid = (int) $order['id'];
        $tStmt = $conn->prepare("
            SELECT t.status, t.note, t.created_at, u.full_name
            FROM emslss_tracking t
            LEFT JOIN emslss_users u ON t.created_by = u.id
            WHERE t.order_id = ?
            ORDER BY t.created_at DESC
        ");
        $tStmt->bind_param('i', $tid);
        $tStmt->execute();
        $timeline = $tStmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tra cứu đơn EMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../../templates/ems_topbar.php'; ?>

<div class="container py-4" style="max-width:720px">
    <h4 class="mb-3">Tra cứu theo mã EMS</h4>

    <form method="GET" class="card card-body shadow-sm mb-4">
        <div class="input-group">
            <input type="text" name="ems_code" class="form-control" placeholder="Nhập mã EMS (VD: EMS123456)"
                   value="<?= htmlspecialchars($ems_code) ?>" required autofocus>
            <button type="submit" class="btn btn-primary">Tra cứu</button>
        </div>
        <small class="text-muted mt-2 d-block">Tra cứu nhanh trạng thái đơn đã push qua API</small>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($order): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= htmlspecialchars($order['ems_code']) ?></strong>
            <?= ems_status_badge($order['status']) ?>
        </div>
        <div class="card-body">
            <p class="mb-1"><b>Người nhận:</b> <?= htmlspecialchars($order['receiver_name'] ?? '') ?></p>
            <p class="mb-1"><b>Địa chỉ:</b> <?= htmlspecialchars($order['receiver_address'] ?? '') ?></p>
            <p class="mb-3"><b>Tạo lúc:</b> <?= htmlspecialchars($order['created_at']) ?></p>
            <a href="order_detail.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary">Xem chi tiết đầy đủ</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Lịch sử trạng thái</div>
        <ul class="list-group list-group-flush">
            <?php while ($t = $timeline->fetch_assoc()): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <span class="badge bg-secondary"><?= htmlspecialchars($t['status']) ?></span>
                    <small class="text-muted"><?= htmlspecialchars($t['created_at']) ?></small>
                </div>
                <?php if ($t['note']): ?><div class="small mt-1"><?= htmlspecialchars($t['note']) ?></div><?php endif; ?>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
