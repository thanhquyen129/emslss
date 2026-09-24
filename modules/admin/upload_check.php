<?php
// TRANG CHẨN ĐOÁN TẠM THỜI — kiểm tra đường dẫn upload thực tế trên server.
// Sau khi xác định xong nguyên nhân 404 ảnh, hãy XÓA file này.
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied');
}

/**
 * Fallback khi server chưa deploy config/upload.php mới (thiếu emslss_upload_fs_path).
 */
function upload_check_resolve_fs(?string $storedPath): ?string
{
    if (function_exists('emslss_upload_fs_path')) {
        return emslss_upload_fs_path($storedPath);
    }
    if ($storedPath === null || $storedPath === '') {
        return null;
    }
    $path = trim($storedPath);
    if (preg_match('#^https?://#i', $path)) {
        return null;
    }
    $prefix = emslss_upload_web_prefix();
    if (str_starts_with($path, $prefix . '/')) {
        $relative = ltrim(substr($path, strlen($prefix)), '/');
        return emslss_upload_root_fs() . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
    if (str_starts_with($path, '/')) {
        $relative = ltrim(substr($path, strlen($prefix . '/')), '/');
        if ($relative === $path) {
            $relative = ltrim($path, '/');
        }
        return emslss_upload_root_fs() . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
    if (str_starts_with($path, 'uploads/')) {
        return emslss_upload_root_fs() . str_replace('/', DIRECTORY_SEPARATOR, substr($path, strlen('uploads/')));
    }
    if (str_starts_with($path, 'modules/shipper/uploads/')) {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
    return null;
}

function upload_check_http_status(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code > 0 ? (string) $code : 'curl_err';
    }
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8]]);
    $headers = @get_headers($url, true, $ctx);
    if (!is_array($headers) || !isset($headers[0])) {
        return 'head_err';
    }
    if (preg_match('/\s(\d{3})\s/', (string) $headers[0], $m)) {
        return $m[1];
    }
    return 'unknown';
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== EMS-LSS UPLOAD DIAGNOSTIC ===\n\n";

if (function_exists('emslss_upload_repair_all_misplaced_files')) {
    $repaired = emslss_upload_repair_all_misplaced_files();
    echo "auto-repair misplaced : " . $repaired . " file(s) moved to correct subdir\n\n";
}

echo "__FILE__               : " . __FILE__ . "\n";
echo "DOCUMENT_ROOT          : " . ($_SERVER['DOCUMENT_ROOT'] ?? '(n/a)') . "\n";
echo "HTTP_HOST              : " . ($_SERVER['HTTP_HOST'] ?? '(n/a)') . "\n";
echo "upload.php version     : " . (function_exists('emslss_upload_fs_path') ? 'mới (có fs_path)' : 'CŨ — cần deploy config/upload.php') . "\n\n";

echo "upload web prefix      : " . emslss_upload_web_prefix() . "\n";
$root = emslss_upload_root_fs();
echo "upload root (fs)       : " . $root . "\n";
echo "  exists?              : " . (is_dir($root) ? 'YES' : 'NO') . "\n";
echo "  writable?            : " . (is_writable($root) ? 'YES' : 'NO') . "\n";
echo "  .htaccess uploads?   : " . (is_file($root . '.htaccess') ? 'YES' : 'NO') . "\n";

$rootFlatFiles = array_filter(glob($root . '*') ?: [], 'is_file');
$misplacedRoot = array_values(array_filter($rootFlatFiles, static function (string $f): bool {
    return (bool) preg_match('/^(pickup|delivery|signatures)\d/', basename($f));
}));
echo "  file lưu nhầm (root): " . count($misplacedRoot) . "\n";
if ($misplacedRoot !== []) {
    echo "      ví dụ          : " . implode(', ', array_slice(array_map('basename', $misplacedRoot), 0, 5)) . "\n";
}
echo "\n";

foreach (['pickup', 'delivery', 'signatures', 'misc'] as $sub) {
    if ($sub === 'misc' && !is_dir(emslss_upload_fs_dir($sub))) {
        @mkdir(emslss_upload_fs_dir($sub), 0755, true);
    }
    $dir = emslss_upload_fs_dir($sub);
    $files = is_dir($dir) ? (glob($dir . DIRECTORY_SEPARATOR . '*') ?: []) : [];
    $count = count($files);
    echo sprintf(
        "[%s] %s | exists=%s writable=%s files=%d perm=%s\n      webPath sample=%s\n",
        $sub,
        $dir,
        is_dir($dir) ? 'Y' : 'N',
        (is_dir($dir) && is_writable($dir)) ? 'Y' : 'N',
        $count,
        is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'n/a',
        emslss_upload_web_path($sub, 'sample.jpg')
    );
    if ($sub === 'delivery' && $count > 0) {
        $samples = array_slice(array_map('basename', $files), 0, 3);
        echo "      sample files     : " . implode(', ', $samples) . "\n";
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo "\n=== KIỂM TRA 5 ẢNH GẦN NHẤT TRONG DB ===\n";
$res = $conn->query("SELECT id, order_id, image_path, created_at FROM emslss_images ORDER BY id DESC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $stored = (string) $r['image_path'];
        $fs = upload_check_resolve_fs($stored);
        $webUrl = emslss_upload_url($stored);
        $absUrl = (preg_match('#^https?://#i', $webUrl) ? $webUrl : rtrim($hostBase, '/') . $webUrl);
        echo "\n#" . $r['id'] . " order=" . $r['order_id'] . " created=" . $r['created_at'] . "\n";
        echo "  DB image_path : " . $stored . "\n";
        echo "  web url       : " . $webUrl . "\n";
        echo "  resolved fs   : " . ($fs ?? '(không map được)') . "\n";
        echo "  fs tồn tại?   : " . ($fs && is_file($fs) ? 'YES' : 'NO') . "\n";
        if (!$fs || !is_file($fs)) {
            $relative = ltrim(parse_url($webUrl, PHP_URL_PATH) ?: '', '/');
            if (str_starts_with($relative, ltrim(emslss_upload_web_prefix(), '/') . '/')) {
                $relative = ltrim(substr($relative, strlen(ltrim(emslss_upload_web_prefix(), '/'))), '/');
            }
            $misplaced = function_exists('emslss_upload_misplaced_fs_path')
                ? emslss_upload_misplaced_fs_path($relative)
                : null;
            if ($misplaced) {
                echo "  misplaced fs  : " . $misplaced . " (YES — thiếu / giữa subdir và tên file)\n";
            }
            $basename = basename($stored);
            $legacyFs = dirname(__DIR__, 2) . '/modules/shipper/uploads/' . $basename;
            if (is_file($legacyFs)) {
                echo "  legacy fs     : " . $legacyFs . " (YES)\n";
            }
            $envRoot = getenv('EMSLSS_UPLOAD_ROOT');
            if ($envRoot) {
                echo "  EMLSS_UPLOAD_ROOT: " . $envRoot . "\n";
                $altFs = rtrim($envRoot, '/') . '/delivery/' . $basename;
                if (is_file($altFs)) {
                    echo "  alt env fs    : " . $altFs . " (YES)\n";
                }
            }
        }
        $resolvedFs = ($fs && is_file($fs)) ? $fs : null;
        if ($resolvedFs === null && function_exists('emslss_upload_repair_misplaced_file')) {
            $relative = ltrim(substr($webUrl, strlen(emslss_upload_web_prefix())), '/');
            $repaired = emslss_upload_repair_misplaced_file($relative);
            if ($repaired && is_file($repaired)) {
                $resolvedFs = $repaired;
                echo "  repaired fs   : " . $repaired . " (đã chuyển về đúng thư mục)\n";
            }
        }
        if ($resolvedFs && is_file($resolvedFs)) {
            echo "  file perm     : " . substr(sprintf('%o', fileperms($resolvedFs)), -4) . "\n";
            echo "  HTTP status   : " . upload_check_http_status($absUrl) . " (" . $absUrl . ")\n";
        }
    }
} else {
    echo "(không đọc được emslss_images)\n";
}

echo "\n=== HẾT ===\n";
