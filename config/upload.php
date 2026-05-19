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
    return emslss_upload_root_fs() . emslss_upload_subdir_name($type);
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

    if (!$ok) {
        return null;
    }

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
    $stmt = $conn->prepare('
        INSERT INTO emslss_images (order_id, image_path, uploaded_by, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->bind_param('isi', $orderId, $webPath, $userId);
    return $stmt->execute();
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
