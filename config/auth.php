<?php

function emslss_hash_password(string $password): string
{
    return md5(trim($password));
}

function emslss_verify_password(string $password, string $hash): bool
{
    $plain = trim($password);
    if ($hash === md5($plain)) {
        return true;
    }
    if (strlen($hash) >= 60 && password_verify($plain, $hash)) {
        return true;
    }
    return false;
}

function emslss_ensure_user_roles_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS emslss_user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, role_id),
            KEY idx_user_roles_role (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("
        INSERT IGNORE INTO emslss_user_roles (user_id, role_id)
        SELECT id, role_id FROM emslss_users
        WHERE role_id IS NOT NULL AND role_id > 0
    ");
    $done = true;
}

function emslss_ensure_roles_seed(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS emslss_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_code VARCHAR(50) UNIQUE,
            role_name VARCHAR(100),
            description TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $defaults = [
        ['admin', 'Administrator', 'Quản trị hệ thống'],
        ['dispatcher', 'Dispatcher', 'Điều phối đơn hàng'],
        ['shipper', 'Shipper', 'Nhân viên pickup/delivery'],
        ['operation', 'Operation', 'Vận hành kho/bưu cục'],
        ['ems', 'EMS', 'Đối tác EMS xem đơn'],
    ];
    $stmt = $conn->prepare('
        INSERT IGNORE INTO emslss_roles (role_code, role_name, description) VALUES (?, ?, ?)
    ');
    foreach ($defaults as $row) {
        $stmt->bind_param('sss', $row[0], $row[1], $row[2]);
        $stmt->execute();
    }
    $done = true;
}

/** @return array<int, array<string, mixed>> */
function emslss_fetch_all_roles(mysqli $conn): array
{
    emslss_ensure_roles_seed($conn);
    $list = [];
    $res = $conn->query('SELECT id, role_code, role_name, description FROM emslss_roles ORDER BY role_name ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function emslss_resolve_user_role(array $user, mysqli $conn): string
{
    $codes = emslss_user_role_codes($conn, (int) ($user['id'] ?? 0));
    if ($codes !== []) {
        return emslss_pick_primary_role_code($codes);
    }
    if (!empty($user['role'])) {
        return (string) $user['role'];
    }
    if (empty($user['role_id'])) {
        return '';
    }
    $stmt = $conn->prepare('SELECT role_code FROM emslss_roles WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $user['role_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['role_code'] ?? '';
}

/** @return int[] */
function emslss_user_role_ids(mysqli $conn, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    emslss_ensure_user_roles_table($conn);
    $stmt = $conn->prepare('
        SELECT ur.role_id
        FROM emslss_user_roles ur
        INNER JOIN emslss_roles r ON r.id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY r.role_name ASC
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $ids = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['role_id'];
    }
    if ($ids === []) {
        $fallback = $conn->prepare('SELECT role_id FROM emslss_users WHERE id=? LIMIT 1');
        $fallback->bind_param('i', $userId);
        $fallback->execute();
        $u = $fallback->get_result()->fetch_assoc();
        if (!empty($u['role_id'])) {
            $ids[] = (int) $u['role_id'];
        }
    }
    return $ids;
}

/** @return string[] */
function emslss_user_role_codes(mysqli $conn, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    emslss_ensure_user_roles_table($conn);
    $stmt = $conn->prepare('
        SELECT r.role_code
        FROM emslss_user_roles ur
        INNER JOIN emslss_roles r ON r.id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY r.role_name ASC
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $codes = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $code = trim((string) ($row['role_code'] ?? ''));
        if ($code !== '') {
            $codes[] = $code;
        }
    }
    if ($codes === []) {
        $fallback = $conn->prepare('SELECT role FROM emslss_users WHERE id=? LIMIT 1');
        $fallback->bind_param('i', $userId);
        $fallback->execute();
        $u = $fallback->get_result()->fetch_assoc();
        if (!empty($u['role'])) {
            $codes[] = (string) $u['role'];
        }
    }
    return array_values(array_unique($codes));
}

function emslss_user_has_role_code(mysqli $conn, int $userId, string $roleCode): bool
{
    return in_array($roleCode, emslss_user_role_codes($conn, $userId), true);
}

/** @param string[] $roleCodes */
function emslss_pick_primary_role_code(array $roleCodes): string
{
    $priority = ['admin', 'dispatcher', 'operation', 'shipper', 'ems'];
    foreach ($priority as $code) {
        if (in_array($code, $roleCodes, true)) {
            return $code;
        }
    }
    return $roleCodes[0] ?? '';
}

/** @param int[] $roleIds */
function emslss_save_user_roles(mysqli $conn, int $userId, array $roleIds): void
{
    emslss_ensure_user_roles_table($conn);
    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
    if ($roleIds === []) {
        throw new InvalidArgumentException('Chọn ít nhất một role');
    }

    $del = $conn->prepare('DELETE FROM emslss_user_roles WHERE user_id=?');
    $del->bind_param('i', $userId);
    $del->execute();

    $ins = $conn->prepare('INSERT INTO emslss_user_roles (user_id, role_id) VALUES (?, ?)');
    foreach ($roleIds as $rid) {
        $chk = $conn->prepare('SELECT id FROM emslss_roles WHERE id=? LIMIT 1');
        $chk->bind_param('i', $rid);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            continue;
        }
        $ins->bind_param('ii', $userId, $rid);
        $ins->execute();
    }

    $codes = [];
    foreach ($roleIds as $rid) {
        $stmt = $conn->prepare('SELECT role_code FROM emslss_roles WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!empty($row['role_code'])) {
            $codes[] = $row['role_code'];
        }
    }
    $primaryCode = emslss_pick_primary_role_code($codes);
    $primaryId = $roleIds[0];
    foreach ($roleIds as $rid) {
        $stmt = $conn->prepare('SELECT role_code FROM emslss_roles WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (($row['role_code'] ?? '') === $primaryCode) {
            $primaryId = $rid;
            break;
        }
    }

    $up = $conn->prepare('UPDATE emslss_users SET role_id=?, role=? WHERE id=?');
    $up->bind_param('isi', $primaryId, $primaryCode, $userId);
    $up->execute();
}

/** @return array<int, array{id:int, full_name:string}> */
function emslss_fetch_active_users_by_role(mysqli $conn, string $roleCode): array
{
    emslss_ensure_user_roles_table($conn);
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.full_name
        FROM emslss_users u
        LEFT JOIN emslss_user_roles ur ON ur.user_id = u.id
        LEFT JOIN emslss_roles r ON r.id = ur.role_id
        WHERE u.is_active = 1
          AND (u.role = ? OR r.role_code = ?)
        ORDER BY u.full_name ASC
    ");
    $stmt->bind_param('ss', $roleCode, $roleCode);
    $stmt->execute();
    $users = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = ['id' => (int) $row['id'], 'full_name' => (string) $row['full_name']];
    }
    return $users;
}

function emslss_role_login_redirect(string $roleCode): string
{
    return match ($roleCode) {
        'shipper' => 'shipper/shipper_dashboard.php',
        'ems' => 'ems/dashboard.php',
        'operation' => 'operation/dashboard.php',
        default => 'admin/dashboard.php',
    };
}

function emslss_user_roles_label(mysqli $conn, int $userId, ?array $userRow = null): string
{
    $codes = emslss_user_role_codes($conn, $userId);
    if ($codes === [] && $userRow !== null && !empty($userRow['role'])) {
        $codes = [(string) $userRow['role']];
    }
    if ($codes === []) {
        return '-';
    }
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $conn->prepare("SELECT role_code, role_name FROM emslss_roles WHERE role_code IN ($placeholders)");
    $types = str_repeat('s', count($codes));
    $stmt->bind_param($types, ...$codes);
    $stmt->execute();
    $names = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $names[] = $row['role_name'] ?: $row['role_code'];
    }
    return $names !== [] ? implode(', ', $names) : implode(', ', $codes);
}
