<?php
require_once __DIR__ . '/init.php';
ems_require_role();

$source = trim($_GET['source'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$emsSources = ems_ems_api_sources();
$placeholders = implode(',', array_fill(0, count($emsSources), '?'));

$where = ["source IN ($placeholders)"];
$types = str_repeat('s', count($emsSources));
$params = $emsSources;

if ($source !== '' && in_array($source, $emsSources, true)) {
    $where = ['source = ?'];
    $types = 's';
    $params = [$source];
}

if ($keyword !== '') {
    $where[] = '(payload LIKE ? OR response LIKE ?)';
    $types .= 'ss';
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM emslss_api_logs' . $whereSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = $conn->prepare("
    SELECT id, source, payload, response, created_at
    FROM emslss_api_logs
    $whereSql
    ORDER BY id DESC
    LIMIT ? OFFSET ?
");
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$logs = $listStmt->get_result();

$inList = "'" . implode("','", array_map('addslashes', $emsSources)) . "'";
$sourceStats = $conn->query("
    SELECT source, COUNT(*) AS c FROM emslss_api_logs
    WHERE source IN ($inList)
    GROUP BY source ORDER BY c DESC
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EMS API Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f0f4f8; }
.log-payload { font-size: 11px; max-height: 120px; overflow: auto; white-space: pre-wrap; }
</style>
</head>
<body>
<?php include __DIR__ . '/../../templates/ems_topbar.php'; ?>

<div class="container-fluid py-4">
    <h4 class="mb-3">API Logs (EMS)</h4>
    <p class="text-muted small">Push đơn, hủy đơn, callback trả trạng thái về EMS</p>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php while ($s = $sourceStats->fetch_assoc()): ?>
            <a href="?source=<?= urlencode($s['source']) ?>" class="badge bg-secondary text-decoration-none">
                <?= htmlspecialchars($s['source']) ?>: <?= (int) $s['c'] ?>
            </a>
        <?php endwhile; ?>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="source" class="form-select form-select-sm">
                <option value="">Tất cả nguồn</option>
                <?php foreach ($emsSources as $src): ?>
                <option value="<?= htmlspecialchars($src) ?>" <?= $source === $src ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Tìm trong payload/response (mã EMS...)"
                   value="<?= htmlspecialchars($keyword) ?>">
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary btn-sm">Lọc</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>ID</th><th>Source</th><th>Thời gian</th><th>Payload</th><th>Response</th></tr>
                </thead>
                <tbody>
                <?php while ($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?= (int) $log['id'] ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($log['source']) ?></span></td>
                    <td><small><?= htmlspecialchars($log['created_at']) ?></small></td>
                    <td><pre class="log-payload mb-0"><?= htmlspecialchars($log['payload']) ?></pre></td>
                    <td><pre class="log-payload mb-0 text-muted"><?= htmlspecialchars($log['response']) ?></pre></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <small>Trang <?= $page ?> / <?= $totalPages ?> (<?= $total ?> bản ghi)</small>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
