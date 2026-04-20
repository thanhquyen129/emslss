# EMS-LSS Routes Map (Canonical)

Use these URLs as canonical routes after cleanup.

## Auth

- Login: `/modules/login.php`
- Logout: `/logout.php`

## Admin

- Dashboard realtime: `/modules/admin/admin_dashboard_realtime.php`
- Orders entry: `/modules/admin/dashboard.php`
- Order detail: `/modules/admin/admin_order_detail.php?id={order_id}`
- Users: `/modules/admin/admin_users.php`
- Roles: `/modules/admin/admin_roles.php`
- Reports: `/modules/admin/admin_reports.php`
- Callback monitor: `/modules/admin/callback_monitor.php`
- API logs: `/modules/admin/API_logs.php`

## Operation

- Operation dashboard: `/modules/operation/dashboard.php`
- Receive from pickup: `/modules/operation/receive.php?id={order_id}`
- Assign delivery API: `/modules/operation/assign_delivery.php` (POST)

## Shipper

- Shipper dashboard: `/modules/shipper/shipper_dashboard.php`
- Pickup detail: `/modules/shipper/shipper_order_detail.php?id={order_id}`
- Pickup scan: `/modules/shipper/shipper_scan.php?id={order_id}`
- Pickup complete: `/modules/shipper/shipper_complete.php?id={order_id}`
- Delivery dashboard: `/modules/shipper/delivery_dashboard.php`
- Delivery detail: `/modules/shipper/delivery_detail.php?id={order_id}`
- Delivery scan: `/modules/shipper/delivery_scan.php?id={order_id}`
- Delivery complete: `/modules/shipper/delivery_complete.php?id={order_id}`

## API

- Push order: `/api/ems_push_order.php`
- Tracking query: `/api/ems_tracking.php?ems_code={ems_code}`
- Cancel order: `/api/ems_cancel_order.php`
- Callback retry: `/api/callback_retry.php`

## Notes

- Keep legacy wrapper files only if external links still depend on old paths.
- For Linux hosting, route/file names are case-sensitive.
