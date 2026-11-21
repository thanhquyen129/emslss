<?php

function shipper_format_created_at(?string $createdAt): string
{
    if ($createdAt === null || $createdAt === '') {
        return '';
    }
    return date('d/m/Y H:i', strtotime($createdAt));
}

function shipper_order_code_html(array $row, string $detailUrl): string
{
    $code = htmlspecialchars($row['ems_code'] ?? '');
    $date = htmlspecialchars(shipper_format_created_at($row['created_at'] ?? ''));
    return '<a class="text-info" href="' . htmlspecialchars($detailUrl) . '">'
        . $code . '</a> <small class="text-secondary">(' . $date . ')</small>';
}

/** Pickup: chưa xử lý */
function shipper_pickup_pending_sql(): string
{
    return "pickup_shipper_id = ? AND status = 'assigned_pickup'";
}

/** Pickup: đã xử lý */
function shipper_pickup_done_sql(): string
{
    return "pickup_shipper_id = ? AND status <> 'assigned_pickup'";
}

/** Delivery: chưa xử lý */
function shipper_delivery_pending_sql(): string
{
    return "delivery_shipper_id = ? AND status IN ('assigned_delivery','in_transit','failed')";
}

/** Delivery: đã xử lý */
function shipper_delivery_done_sql(): string
{
    return "delivery_shipper_id = ? AND status IN ('delivered','cancelled')";
}
