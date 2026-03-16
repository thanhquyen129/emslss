# EMS-LSS SYSTEM MAP

## 1. Tổng quan hệ thống

EMS-LSS là hệ thống trung gian tiếp nhận đơn từ EMS, điều phối vận hành pickup / delivery, cập nhật tracking và callback trạng thái về EMS.

Luồng tổng thể:

```text
EMS
 ↓
API nhận đơn
 ↓
LSS Database
 ↓
Dispatcher phân công shipper
 ↓
Pickup Shipper scan nhận thư
 ↓
Operation / Hub xử lý trung gian
 ↓
Delivery Shipper scan phát thư
 ↓
Callback trạng thái về EMS
```

---

# 2. Cấu trúc source hiện tại

```text
emslss/
│
├── api/
├── config/
├── cron/
├── modules/
├── templates/
│
├── index.php
├── logout.php
├── install.sql
├── test_ems_push.php
```

---

# 3. Các module chính cần có

## api/

Chứa các endpoint giao tiếp hệ ngoài.

```text
api/
├── ems_receive.php
├── ems_callback.php
├── ems_tracking.php
```

### ems_receive.php

EMS gửi đơn sang LSS.

### ems_callback.php

LSS gửi trạng thái đơn về EMS.

### ems_tracking.php

EMS truy vấn tracking đơn.

---

## config/

```text
config/
├── db.php
├── config.php
```

### db.php

Kết nối MySQL.

### config.php

Chứa API key, timezone, system config.

---

## modules/

Các module nghiệp vụ.

```text
modules/
├── admin/
├── dispatcher/
├── pickup/
├── delivery/
├── operation/
```

---

# 4. Dashboard theo vai trò

## Admin Dashboard

Chức năng:

* Quản lý user
* Quản lý role
* Xem toàn bộ đơn
* Xem API log
* Xem tracking toàn hệ thống

---

## Dispatcher Dashboard

Chức năng:

* Nhận đơn mới từ EMS
* Gán pickup shipper
* Gán delivery shipper
* Theo dõi trạng thái đơn

---

## Pickup Dashboard

Chức năng:

* Xem đơn pickup được giao
* Scan mã EMS
* Upload ảnh pickup
* Cập nhật trạng thái picked_up

---

## Delivery Dashboard

Chức năng:

* Xem đơn giao phát
* Scan mã EMS
* Upload ảnh giao hàng
* Cập nhật trạng thái delivered

---

## Operation Dashboard

Chức năng:

* Quản lý hàng tại hub
* Chuyển trạng thái in_transit
* Kiểm tra đơn lỗi

---

# 5. Database chính

## emslss_orders

Chứa đơn hàng chính.

### trạng thái chuẩn:

```text
new_order
assigned_pickup
picked_up
in_transit
assigned_delivery
delivered
failed
cancelled
```

---

## emslss_tracking

Lưu lịch sử tracking.

Mỗi lần scan tạo 1 record mới.

---

## emslss_images

Lưu ảnh chứng từ.

---

## emslss_api_logs

Lưu request / response API.

---

# 6. Flow vận hành thực tế

## Pickup Flow

```text
EMS push order
↓
Dispatcher assign pickup
↓
Pickup shipper scan
↓
Status = picked_up
↓
Tracking insert
↓
Callback EMS
```

---

## Delivery Flow

```text
Dispatcher assign delivery
↓
Delivery shipper scan
↓
Status = delivered
↓
Tracking insert
↓
Callback EMS
```

---

# 7. File nên phát triển tiếp ngay

```text
dispatcher_dashboard.php
pickup_dashboard.php
pickup_scan.php
delivery_dashboard.php
delivery_scan.php
ems_callback.php
```

---

# 8. Khuyến nghị production

* dùng transaction khi update order + tracking
* chuẩn hóa callback EMS
* tách role rõ ràng
* scan barcode bằng camera mobile
* log đầy đủ API request/response

---

# 9. Mức hoàn thiện hiện tại

```text
DB: 90%
API: 75%
Dashboard: 60%
Tracking: 85%
Scan: 50%
Callback: 60%
```

---

# 10. Mục tiêu phase tiếp theo

Hoàn thiện full logistics workflow production.
