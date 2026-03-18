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

// shipper / admin / operation đều có thể vào nếu cần test
if (!in_array($role, ['shipper', 'admin', 'operation'])) {
    die("Access denied");
}

// filter
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// build sql
$sql = "
SELECT *
FROM emslss_orders
WHERE delivery_shipper_id = ?
AND status IN ('assigned_delivery','in_transit','failed')
";

$params = [$user_id];
$types = "i";

if ($status_filter != 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (
        ems_code LIKE ?
        OR receiver_name LIKE ?
        OR receiver_phone LIKE ?
    )";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();

// counters
$count_sql = "
SELECT 
SUM(status='assigned_delivery') as assigned_delivery,
SUM(status='in_transit') as in_transit,
SUM(status='failed') as failed
FROM emslss_orders
WHERE delivery_shipper_id = $user_id
";
$count_result = $conn->query($count_sql)->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Delivery Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background:#f5f7fa;
}
.card-box{
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
.badge-status{
    font-size:13px;
    padding:6px 10px;
}
</style>
</head>
<body>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4>🚚 Xin chào <?= htmlspecialchars($full_name) ?></h4>
            <small>Delivery Dashboard</small>
        </div>
        <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
    </div>

    <div class="row mb-4">

        <div class="col-md-4">
            <div class="card card-box p-3">
                <h6>📦 Chờ giao</h6>
                <h3><?= $count_result['assigned_delivery'] ?? 0 ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-box p-3">
                <h6>🚚 Đang giao</h6>
                <h3><?= $count_result['in_transit'] ?? 0 ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-box p-3">
                <h6>⚠️ Giao thất bại</h6>
                <h3><?= $count_result['failed'] ?? 0 ?></h3>
            </div>
        </div>

    </div>

    <form method="GET" class="row mb-3">

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="all">Tất cả</option>
                <option value="assigned_delivery" <?= $status_filter=='assigned_delivery'?'selected':'' ?>>Chờ giao</option>
                <option value="in_transit" <?= $status_filter=='in_transit'?'selected':'' ?>>Đang giao</option>
                <option value="failed" <?= $status_filter=='failed'?'selected':'' ?>>Thất bại</option>
            </select>
        </div>

        <div class="col-md-4">
            <input type="text" name="search" class="form-control"
                   placeholder="Mã EMS / Người nhận / SĐT"
                   value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary">Lọc</button>
        </div>

    </form>

    <div class="card card-box">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>Mã EMS</th>
                        <th>Người nhận</th>
                        <th>Điện thoại</th>
                        <th>Địa chỉ</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php while($row = $orders->fetch_assoc()): ?>

                    <tr>
                        <td><strong><?= $row['ems_code'] ?></strong></td>
                        <td><?= htmlspecialchars($row['receiver_name']) ?></td>
                        <td><?= htmlspecialchars($row['receiver_phone']) ?></td>
                        <td><?= htmlspecialchars($row['receiver_address']) ?></td>
                        <td>

                            <?php
                            if($row['status']=='assigned_delivery'){
                                echo '<span class="badge bg-warning badge-status">Chờ giao</span>';
                            }elseif($row['status']=='in_transit'){
                                echo '<span class="badge bg-primary badge-status">Đang giao</span>';
                            }else{
                                echo '<span class="badge bg-danger badge-status">Thất bại</span>';
                            }
                            ?>

                        </td>

                        <td>
                            <a href="delivery_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                Xử lý
                            </a>
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