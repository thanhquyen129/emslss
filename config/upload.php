<?php

/**
 * Upload helper — mọi file ảnh lưu dưới uploads/{pickup|delivery|signatures|misc}
 * DB lưu đường dẫn web: /uploads/{subdir}/filename
 */

function emslss_upload_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $system = require __DIR__ . '/system.php';
        $cfg = $system['upload'] ?? [];
        $cfg += [
            'root_path' => null,
            'web_path' => '/uploads',
            'public_base_url' => '',
            'subdirs' => [
                'pickup' => 'pickup',
                'delivery' => 'delivery',
                'signatures' => 'signatures',
                'misc' => 'misc',
            ],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_bytes' => 10485760,
        ];
    }
    return $cfg;
}

function emslss_upload_root_fs(): string
{
    $cfg = emslss_upload_config();
    $root = $cfg['root_path'];
    if ($root === null || $root === '') {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    }
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;
}

function emslss_upload_web_prefix(): string
{
    return rtrim(emslss_upload_config()['web_path'] ?? '/uploads', '/');
}

function emslss_upload_subdir_name(string $type): string
{
    $subdirs = emslss_upload_config()['subdirs'] ?? [];
    return $subdirs[$type] ?? $type;
}

function emslss_upload_fs_dir(string $type): string
{
    return emslss_upload_root_fs() . emslss_upload_subdir_name($type) . DIRECTORY_SEPARATOR;
}

function emslss_upload_ensure_dir(string $type): string
{
    $dir = emslss_upload_fs_dir($type);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $dir);
    }
    return $dir;
}

function emslss_upload_web_path(string $type, string $filename): string
{
    return emslss_upload_web_prefix() . '/' . emslss_upload_subdir_name($type) . '/' . $filename;
}

function emslss_upload_validate_extension(string $filename): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = emslss_upload_config()['allowed_extensions'] ?? [];
    return $ext !== '' && in_array($ext, $allowed, true);
}

function emslss_upload_validate_size(int $bytes): bool
{
    $max = (int) (emslss_upload_config()['max_bytes'] ?? 10485760);
    return $bytes > 0 && $bytes <= $max;
}

function emslss_upload_safe_basename(string $originalName): string
{
    $base = basename($originalName);
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?: 'file.bin';
    if (strlen($base) > 80) {
        $ext = pathinfo($base, PATHINFO_EXTENSION);
        $stem = substr(pathinfo($base, PATHINFO_FILENAME), 0, 64) ?: 'file';
        $base = $ext !== '' ? ($stem . '.' . $ext) : $stem;
    }
    return $base;
}

function emslss_upload_generate_filename(string $originalName, ?string $suffix = null): string
{
    $safe = emslss_upload_safe_basename($originalName);
    $token = time() . '_' . bin2hex(random_bytes(4));
    if ($suffix !== null && $suffix !== '') {
        $token .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $suffix);
    }
    return $token . '_' . $safe;
}

/**
 * Lưu file upload (multipart). Trả về web path hoặc null nếu lỗi.
 */
function emslss_upload_save_file(string $type, string $tmpPath, string $originalName, ?string $suffix = null): ?string
{
    if (!is_uploaded_file($tmpPath) && !file_exists($tmpPath)) {
        return null;
    }
    if (!emslss_upload_validate_extension($originalName)) {
        return null;
    }
    $size = @filesize($tmpPath);
    if ($size === false || !emslss_upload_validate_size((int) $size)) {
        return null;
    }

    $dir = emslss_upload_ensure_dir($type);
    $filename = emslss_upload_generate_filename($originalName, $suffix);
    $targetFs = $dir . $filename;

    $ok = is_uploaded_file($tmpPath)
        ? move_uploaded_file($tmpPath, $targetFs)
        : @rename($tmpPath, $targetFs);

    if (!$ok || !is_file($targetFs) || filesize($targetFs) <= 0) {
        if (is_file($targetFs)) {
            @unlink($targetFs);
        }
        return null;
    }

    @chmod($targetFs, 0644);

    return emslss_upload_web_path($type, $filename);
}

/**
 * Lưu chữ ký base64 (data:image/png;base64,...).
 */
function emslss_upload_save_base64_image(string $type, string $dataUrl): ?string
{
    $parts = explode(',', $dataUrl, 2);
    if (count($parts) < 2) {
        return null;
    }

    $dir = emslss_upload_ensure_dir($type);
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '_sig.png';
    $targetFs = $dir . $filename;
    $binary = base64_decode($parts[1], true);
    if ($binary === false) {
        return null;
    }
    if (!emslss_upload_validate_size(strlen($binary))) {
        return null;
    }
    if (file_put_contents($targetFs, $binary) === false) {
        return null;
    }

    return emslss_upload_web_path($type, $filename);
}

/**
 * Xử lý $_FILES[field] (đơn hoặc mảng).
 *
 * @return string[] danh sách web path đã lưu
 */
function emslss_upload_save_multipart_field(string $type, array $fileField, ?string $suffix = null): array
{
    $paths = [];
    if (empty($fileField['name'])) {
        return $paths;
    }

    if (!is_array($fileField['name'])) {
        if (($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $paths;
        }
        $path = emslss_upload_save_file($type, $fileField['tmp_name'], $fileField['name'], $suffix);
        if ($path !== null) {
            $paths[] = $path;
        }
        return $paths;
    }

    foreach ($fileField['tmp_name'] as $key => $tmp) {
        if (($fileField['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        if (empty($fileField['name'][$key])) {
            continue;
        }
        $path = emslss_upload_save_file(
            $type,
            $tmp,
            $fileField['name'][$key],
            $suffix !== null ? $suffix . '_' . $key : (string) $key
        );
        if ($path !== null) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function emslss_upload_insert_image(mysqli $conn, int $orderId, string $webPath, int $userId): bool
{
    $fs = emslss_upload_fs_path($webPath);
    if ($fs === null || !is_file($fs)) {
        return false;
    }

    $stmt = $conn->prepare('
        INSERT INTO emslss_images (order_id, image_path, uploaded_by, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->bind_param('isi', $orderId, $webPath, $userId);
    return $stmt->execute();
}

/**
 * Xóa các file vật lý đã lưu (dùng khi rollback transaction).
 */
function emslss_upload_cleanup_paths(array $webPaths): void
{
    foreach ($webPaths as $path) {
        if (is_string($path) && $path !== '') {
            emslss_upload_delete_file($path);
        }
    }
}

function emslss_upload_insert_images(mysqli $conn, int $orderId, array $webPaths, int $userId): int
{
    $count = 0;
    foreach ($webPaths as $path) {
        if ($path !== '' && emslss_upload_insert_image($conn, $orderId, $path, $userId)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Chuẩn hóa path cũ trong DB thành URL hiển thị (bắt đầu bằng /).
 */
function emslss_upload_url(?string $storedPath): string
{
    if ($storedPath === null || $storedPath === '') {
        return '';
    }
    $path = trim($storedPath);
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $prefix = emslss_upload_web_prefix();

    if (str_starts_with($path, $prefix . '/')) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }

    // uploads/delivery/x.jpg hoặc uploads/x.jpg
    if (str_starts_with($path, 'uploads/')) {
        return '/' . $path;
    }

    // assets/uploads/legacy
    if (str_starts_with($path, 'assets/uploads/')) {
        $name = substr($path, strlen('assets/uploads/'));
        return emslss_upload_web_path('misc', basename($name));
    }

    // path cũ shipper module (file vẫn nằm modules/shipper/uploads/...)
    if (str_starts_with($path, 'modules/shipper/uploads/')) {
        return '/' . $path;
    }

    // chỉ tên file
    if (strpos($path, '/') === false) {
        return emslss_upload_web_path('misc', $path);
    }

    return $prefix . '/' . ltrim($path, '/');
}

/**
 * URL tuyệt đối (callback EMS, email...).
 */
function emslss_upload_absolute_url(?string $storedPath): string
{
    $relative = emslss_upload_url($storedPath);
    if ($relative === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }

    $base = trim(emslss_upload_config()['public_base_url'] ?? '');
    if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    if ($base === '') {
        return $relative;
    }

    return rtrim($base, '/') . $relative;
}

/**
 * Path file bị lưu nhầm do thiếu dấu / giữa subdir và tên file.
 * VD: uploads/pickup1780628543_xxx.jpg thay vì uploads/pickup/1780628543_xxx.jpg
 */
function emslss_upload_misplaced_fs_path(string $relativeWebPath): ?string
{
    if (!preg_match('#^(pickup|delivery|signatures|misc)/(.+)$#', $relativeWebPath, $m)) {
        return null;
    }

    $misplaced = emslss_upload_root_fs() . $m[1] . $m[2];
    return is_file($misplaced) ? $misplaced : null;
}

/**
 * Quét và chuyển toàn bộ file dính liền subdir trong uploads/ về đúng thư mục.
 */
function emslss_upload_repair_all_misplaced_files(): int
{
    $count = 0;
    foreach (glob(emslss_upload_root_fs() . '*') ?: [] as $fullPath) {
        if (!is_file($fullPath)) {
            continue;
        }
        $base = basename($fullPath);
        if (!preg_match('/^(pickup|delivery|signatures|misc)(\d.+)$/', $base, $m)) {
            continue;
        }
        $destDir = emslss_upload_ensure_dir($m[1]);
        $dest = $destDir . $m[2];
        if (!is_file($dest) && @rename($fullPath, $dest)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Chuyển file lưu nhầm về đúng thư mục con.
 */
function emslss_upload_repair_misplaced_file(string $relativeWebPath): ?string
{
    if (!preg_match('#^(pickup|delivery|signatures|misc)/(.+)$#', $relativeWebPath, $m)) {
        return null;
    }

    $misplaced = emslss_upload_misplaced_fs_path($relativeWebPath);
    if ($misplaced === null) {
        return null;
    }

    $destDir = emslss_upload_ensure_dir($m[1]);
    $dest = $destDir . $m[2];
    if (!is_file($dest)) {
        @rename($misplaced, $dest);
    }

    if (is_file($dest)) {
        return $dest;
    }

    return $misplaced;
}

/**
 * Đường dẫn tuyệt đối trên filesystem từ path lưu DB.
 */
function emslss_upload_fs_path(?string $storedPath): ?string
{
    if ($storedPath === null || $storedPath === '') {
        return null;
    }

    $path = trim($storedPath);
    if (str_starts_with($path, 'modules/shipper/uploads/')) {
        $legacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        return is_file($legacy) ? $legacy : null;
    }

    $url = emslss_upload_url($storedPath);
    $prefix = emslss_upload_web_prefix();
    if (!str_starts_with($url, $prefix . '/')) {
        return null;
    }

    $relative = ltrim(substr($url, strlen($prefix)), '/');
    $normal = emslss_upload_root_fs() . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($normal)) {
        return $normal;
    }

    return emslss_upload_repair_misplaced_file($relative);
}

/**
 * Xóa file vật lý (nếu tồn tại). Trả về true nếu không còn file hoặc đã xóa.
 */
function emslss_upload_delete_file(?string $storedPath): bool
{
    $fs = emslss_upload_fs_path($storedPath);
    if ($fs === null) {
        return false;
    }
    if (!is_file($fs)) {
        return true;
    }
    return @unlink($fs);
}

/**
 * Xóa ảnh trong DB và file trên host.
 */
function emslss_upload_delete_image(mysqli $conn, int $imageId, int $orderId): bool
{
    $stmt = $conn->prepare('SELECT image_path FROM emslss_images WHERE id=? AND order_id=? LIMIT 1');
    $stmt->bind_param('ii', $imageId, $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return false;
    }

    $del = $conn->prepare('DELETE FROM emslss_images WHERE id=? AND order_id=?');
    $del->bind_param('ii', $imageId, $orderId);
    if (!$del->execute()) {
        return false;
    }

    emslss_upload_delete_file($row['image_path']);
    return true;
}
