<?php
/**
 * Test EMS push order + OneSignal.
 * Chỉ admin. Xóa hoặc bảo vệ thêm khi lên production công khai.
 *
 * URL: /test_ems_push.php
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/onesignal.php';

$_system = require __DIR__ . '/config/system.php';

if (empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    http_response_code(403);
    echo 'Chỉ admin được dùng trang này. <a href="/modules/login.php">Đăng nhập</a>';
    exit;
}

$apiKey = (string)($_system['security']['api_key'] ?? '');
$baseUrl = rtrim((string)($_system['onesignal']['open_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$apiUrl = $baseUrl . '/api/ems_push_order.php';

$result = null;
$error = null;
$action = '';

function emslss_test_http_post_json(string $url, string $apiKey, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'X-API-KEY: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $code,
        'curl_error' => $errno ? $err : null,
        'raw' => $raw,
        'json' => json_decode((string)$raw, true),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'push_order') {
        $emsCode = strtoupper(trim((string)($_POST['ems_code'] ?? '')));
        if ($emsCode === '') {
            $emsCode = 'TEST' . date('ymdHis') . sprintf('%02d', random_int(0, 99)) . 'VN';
        }

        $payload = [
            'ems_code' => $emsCode,
            'post_office_name' => 'BUU CUC TEST',
            'post_office_address' => 'Da Nang',
            'holder_name' => 'Nguyen Van Test',
            'holder_phone' => '0901000001',
            'sender_name' => 'Cong ty Test',
            'sender_phone' => '0901000002',
            'sender_address' => 'Ha Noi',
            'receiver_name' => 'Tran Van Nhan',
            'receiver_phone' => '0901000003',
            'receiver_address' => 'Hoi An, Quang Nam',
            'weight' => 0.5,
            'cargo_type' => 'documents',
            'service_type' => 'door_to_door',
        ];

        $result = [
            'mode' => 'ems_push_order',
            'request' => $payload,
            'api_url' => $apiUrl,
            'response' => emslss_test_http_post_json($apiUrl, $apiKey, $payload),
        ];

        // Đọc lại kết quả push từ api_logs (API không trả push trong JSON response)
        $like = '%' . $emsCode . '%';
        $logStmt = $conn->prepare("
            SELECT id, response, created_at
            FROM emslss_api_logs
            WHERE source IN ('EMS_PUSH', 'EMS_PUSH_DUPLICATE')
              AND response LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $logStmt->bind_param('s', $like);
        $logStmt->execute();
        $logRow = $logStmt->get_result()->fetch_assoc();
        if ($logRow) {
            $result['api_log'] = [
                'id' => (int)$logRow['id'],
                'created_at' => $logRow['created_at'],
                'response_decoded' => json_decode((string)$logRow['response'], true) ?: $logRow['response'],
            ];
        }
    } elseif ($action === 'push_only') {
        $emsCode = strtoupper(trim((string)($_POST['ems_code'] ?? '')));
        if ($emsCode === '') {
            $emsCode = 'PUSHONLY' . date('His');
        }
        $notify = emslss_notify_admins_new_order($conn, $emsCode, 0);
        $result = [
            'mode' => 'onesignal_only',
            'ems_code' => $emsCode,
            'onesignal_enabled' => emslss_onesignal_enabled(),
            'admin_external_ids' => emslss_admin_external_ids($conn),
            'notify' => $notify,
        ];
    } else {
        $error = 'Action không hợp lệ';
    }
}

$osEnabled = emslss_onesignal_enabled();
$adminIds = emslss_admin_external_ids($conn);
$defaultCode = 'TEST' . date('ymdHis') . 'VN';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Test EMS Push + OneSignal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:820px">
  <h1 class="h4 mb-3">Test EMS API + thông báo OneSignal</h1>
  <p class="text-muted small mb-4">
    Đăng nhập admin trên <strong>app Android</strong>, bật quyền thông báo, rồi dùng trang này để tạo đơn / gửi push.
  </p>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 small">
        <div class="col-md-6"><strong>OneSignal:</strong>
          <?= $osEnabled ? '<span class="text-success">enabled</span>' : '<span class="text-danger">disabled (thiếu app_id/rest_api_key)</span>' ?>
        </div>
        <div class="col-md-6"><strong>Admin external_id:</strong>
          <?= $adminIds !== [] ? htmlspecialchars(implode(', ', $adminIds)) : '<span class="text-warning">không có user admin</span>' ?>
        </div>
        <div class="col-12"><strong>API:</strong> <code><?= htmlspecialchars($apiUrl) ?></code></div>
      </div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">1) Tạo đơn qua EMS API (có push nếu đơn mới)</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="push_order">
        <div class="col-md-8">
          <label class="form-label">ems_code (để trống = tự sinh mã mới)</label>
          <input type="text" name="ems_code" class="form-control" placeholder="<?= htmlspecialchars($defaultCode) ?>">
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100" type="submit">Gọi ems_push_order</button>
        </div>
      </form>
      <p class="form-text mb-0 mt-2">Mỗi lần test nên dùng mã mới. Gửi trùng → <code>duplicate</code> (không push lại).</p>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">2) Chỉ gửi push OneSignal (không tạo đơn)</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="push_only">
        <div class="col-md-8">
          <label class="form-label">Mã hiển thị trên thông báo</label>
          <input type="text" name="ems_code" class="form-control" value="TESTPUSH">
        </div>
        <div class="col-md-4">
          <button class="btn btn-success w-100" type="submit">Gửi push thử</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($result !== null): ?>
    <div class="card border-dark">
      <div class="card-header">Kết quả (<?= htmlspecialchars((string)$result['mode']) ?>)</div>
      <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded small mb-0" style="white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
      </div>
    </div>
  <?php endif; ?>

  <p class="text-muted small mt-4 mb-0">
    File test nội bộ — không để public nếu không cần. Checklist: <code>docs/EMS_API_TEST_CHECKLIST.md</code>
  </p>
</div>
</body>
</html>
