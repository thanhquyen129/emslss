<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied");
}

$source = trim($_GET['source'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];

if ($source !== '') {
    $where[] = "source = ?";
    $types .= 's';
    $params[] = $source;
}

if ($keyword !== '') {
    $where[] = "(payload LIKE ? OR response LIKE ?)";
    $types .= 'ss';
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) AS total FROM emslss_api_logs" . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = intval(($countStmt->get_result()->fetch_assoc()['total'] ?? 0));
$totalPages = max(1, (int)ceil($total / $perPage));

$listSql = "
    SELECT id, source, payload, response, created_at
    FROM emslss_api_logs
    $whereSql
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";
$listStmt = $conn->prepare($listSql);
$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$logs = $listStmt->get_result();

$sourceRes = $conn->query("
    SELECT source, COUNT(*) AS c
    FROM emslss_api_logs
    GROUP BY source
    ORDER BY c DESC, source ASC
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>API Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f7fa; }
.card-box { border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: none; }
pre { white-space: pre-wrap; word-break: break-word; font-size: 12px; margin: 0; }
</style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">API Logs</h4>
        <a href="admin_dashboard_realtime.php" class="btn btn-secondary btn-sm">← Dashboard</a>
    </div>

    <div class="card card-box p-3 mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Source</label>
                <select name="source" class="form-select">
                    <option value="">-- tất cả --</option>
                    <?php while ($s = $sourceRes->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($s['source']) ?>" <?= $source === $s['source'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['source']) ?> (<?= intval($s['c']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">Keyword (ems_code / status / nội dung)</label>
                <input name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="EE123..., callback_fail, delivered...">
            </div>
            <div class="col-md-2 d-grid">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary">Lọc</button>
            </div>
        </form>
        <div class="small text-muted mt-2">Tổng: <?= $total ?> logs</div>
    </div>

    <div class="card card-box">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th style="width:180px;">Source</th>
                        <th>Payload</th>
                        <th>Response</th>
                        <th style="width:170px;">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                    <?php else: ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= intval($row['id']) ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($row['source']) ?></span></td>
                        <td><pre><?= htmlspecialchars($row['payload']) ?></pre></td>
                        <td><pre><?= htmlspecialchars($row['response']) ?></pre></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?source=<?= urlencode($source) ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $p ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

</body>
</html>
