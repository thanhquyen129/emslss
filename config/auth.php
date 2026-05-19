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

function emslss_resolve_user_role(array $user, mysqli $conn): string
{
    if (!empty($user['role'])) {
        return $user['role'];
    }
    if (empty($user['role_id'])) {
        return '';
    }
    $stmt = $conn->prepare("SELECT role_code FROM emslss_roles WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user['role_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['role_code'] ?? '';
}
