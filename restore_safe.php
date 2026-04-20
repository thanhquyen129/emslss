<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/config/db.php';

$backupDir = __DIR__ . '/backups';
$mode = $_POST['mode'] ?? ($_GET['mode'] ?? 'form');
$selectedFile = trim($_POST['file'] ?? ($_GET['file'] ?? ''));

function listSqlBackups($backupDir)
{
    if (!is_dir($backupDir)) {
        return [];
    }
    $files = glob($backupDir . '/*.sql');
    if (!$files) {
        return [];
    }
    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    return array_map('basename', $files);
}

function restoreSqlFile($conn, $fullPath)
{
    $templine = '';
    $ok = 0;
    $errors = [];

    $lines = file($fullPath);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $templine .= $line;
        if (substr($trimmed, -1) === ';') {
            if (!$conn->query($templine)) {
                $errors[] = $conn->error;
            } else {
                $ok++;
            }
            $templine = '';
        }
    }

    return [
        'ok' => $ok,
        'errors' => $errors
    ];
}

if ($mode === 'execute') {
    if ($selectedFile === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $selectedFile) !== 1) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid file name'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fullPath = realpath($backupDir . '/' . $selectedFile);
    $realBackupDir = realpath($backupDir);

    if (!$fullPath || !$realBackupDir || !str_starts_with($fullPath, $realBackupDir . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Backup file not found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = restoreSqlFile($conn, $fullPath);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => count($result['errors']) === 0 ? 'success' : 'partial',
        'file' => basename($fullPath),
        'executed_queries' => $result['ok'],
        'error_count' => count($result['errors']),
        'errors' => array_slice($result['errors'], 0, 10)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$files = listSqlBackups($backupDir);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Safe</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 30px auto; padding: 0 16px; }
        .box { border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
        .warn { color: #b00020; font-weight: bold; }
        .muted { color: #555; font-size: 14px; }
        button { padding: 8px 14px; }
        select { min-width: 100%; padding: 8px; }
    </style>
</head>
<body>
    <h3>Restore Database (Safe)</h3>
    <p class="warn">Cảnh báo: thao tác này ghi đè dữ liệu hiện tại.</p>
    <p class="muted">Chỉ restore file `.sql` nằm trong thư mục `backups/`.</p>

    <div class="box">
        <?php if (count($files) === 0): ?>
            <p>Không có file backup nào trong thư mục <code>backups/</code>.</p>
        <?php else: ?>
            <form method="POST" action="restore_safe.php" onsubmit="return confirm('Xác nhận restore file đã chọn?');">
                <input type="hidden" name="mode" value="execute">
                <label for="file">Chọn file backup:</label><br><br>
                <select id="file" name="file" required>
                    <?php foreach ($files as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <button type="submit">Restore ngay</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
