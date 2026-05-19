<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

function ems_require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /modules/login.php');
        exit;
    }
}

function ems_require_role(): void
{
    ems_require_login();
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'ems' && $role !== 'admin') {
        http_response_code(403);
        die('Access denied');
    }
}

function ems_status_badge(string $status): string
{
    $map = [
        'new_order' => ['secondary', 'Mới (EMS push)'],
        'assigned_pickup' => ['primary', 'Đã gán pickup'],
        'picked_up' => ['info', 'Đã lấy hàng'],
        'in_transit' => ['warning', 'Đang vận chuyển'],
        'assigned_delivery' => ['dark', 'Đã gán giao'],
        'delivered' => ['success', 'Đã giao'],
        'failed' => ['danger', 'Giao thất bại'],
        'cancelled' => ['danger', 'Đã hủy'],
    ];
    $item = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $item[0] . '">' . htmlspecialchars($item[1]) . '</span>';
}

function ems_allowed_statuses(): array
{
    return [
        'new_order',
        'assigned_pickup',
        'picked_up',
        'in_transit',
        'assigned_delivery',
        'delivered',
        'failed',
        'cancelled',
    ];
}

function ems_ems_api_sources(): array
{
    return [
        'EMS_PUSH',
        'EMS_PUSH_DUPLICATE',
        'EMS_CANCEL',
        'CALLBACK_PICKUP',
        'CALLBACK_DELIVERY',
        'CALLBACK_FAIL',
        'CALLBACK_DEAD',
    ];
}
