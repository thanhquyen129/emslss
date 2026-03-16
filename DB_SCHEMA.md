# DB_SCHEMA.md

# EMS-LSS Database Schema

## 1. Tổng quan

Database EMS-LSS dùng để quản lý:

* đơn hàng EMS chuyển sang LSS
* tracking vận hành
* phân công shipper
* ảnh chứng từ
* log API
* user và phân quyền

---

# 2. Bảng chính: emslss_orders

## Mục đích

Lưu toàn bộ đơn vận hành chính.

---

## Các field

| Field               | Type          | Meaning                     |
| ------------------- | ------------- | --------------------------- |
| id                  | int           | khóa nội bộ                 |
| ems_code            | varchar(50)   | mã vận đơn EMS              |
| post_office_name    | varchar(255)  | tên bưu cục                 |
| post_office_address | text          | địa chỉ bưu cục             |
| holder_name         | varchar(100)  | người giữ thư               |
| holder_phone        | varchar(20)   | số điện thoại người giữ thư |
| sender_name         | varchar(255)  | người gửi                   |
| sender_phone        | varchar(50)   | điện thoại người gửi        |
| sender_address      | text          | địa chỉ người gửi           |
| receiver_name       | varchar(255)  | người nhận                  |
| receiver_phone      | varchar(50)   | điện thoại người nhận       |
| receiver_address    | text          | địa chỉ người nhận          |
| weight              | decimal(10,2) | khối lượng                  |
| cargo_type          | varchar(255)  | loại hàng                   |
| service_type        | enum          | loại dịch vụ                |
| pickup_shipper_id   | int           | shipper pickup              |
| delivery_shipper_id | int           | shipper delivery            |
| status              | enum          | trạng thái đơn              |
| created_at          | datetime      | thời gian tạo               |
| updated_at          | datetime      | thời gian cập nhật          |

---

## service_type

```text id="8y2m4p"
door_to_door
door_to_hub
hub_to_door
```

---

## status chuẩn

```text id="f6p3v1"
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

## Ý nghĩa vận hành

### new_order

EMS vừa push đơn.

### assigned_pickup

Dispatcher đã gán shipper pickup.

### picked_up

Shipper pickup đã scan nhận thư.

### in_transit

Đơn đang vận chuyển nội bộ.

### assigned_delivery

Dispatcher đã gán shipper giao phát.

### delivered

Shipper giao thành công.

---

# 3. Bảng tracking: emslss_tracking

## Mục đích

Lưu toàn bộ lịch sử trạng thái đơn.

---

## Các field

| Field      | Type        | Meaning       |
| ---------- | ----------- | ------------- |
| id         | int         | khóa          |
| order_id   | int         | liên kết đơn  |
| status     | varchar(50) | trạng thái    |
| note       | text        | ghi chú       |
| created_by | int         | user thao tác |
| created_at | datetime    | thời gian     |

---

## Nguyên tắc

Mỗi lần scan hoặc đổi trạng thái:

→ tạo 1 record tracking mới.

---

# 4. Bảng ảnh: emslss_images

## Mục đích

Lưu ảnh chứng từ.

---

## Các field

| Field       | Type         | Meaning       |
| ----------- | ------------ | ------------- |
| id          | int          | khóa          |
| order_id    | int          | liên kết đơn  |
| image_path  | varchar(255) | đường dẫn ảnh |
| uploaded_by | int          | user upload   |
| created_at  | datetime     | thời gian     |

---

## Ứng dụng

* ảnh pickup
* ảnh giao hàng
* ảnh lỗi phát sinh

---

# 5. Bảng log API: emslss_api_logs

## Mục đích

Lưu log giao tiếp EMS ↔ LSS.

---

## Các field

| Field      | Type        | Meaning       |
| ---------- | ----------- | ------------- |
| id         | int         | khóa          |
| source     | varchar(50) | nguồn gọi     |
| payload    | text        | request data  |
| response   | text        | response data |
| created_at | datetime    | thời gian     |

---

## Ứng dụng

* kiểm tra lỗi API
* audit dữ liệu
* retry callback

---

# 6. Bảng metadata: emslss_order_meta

## Mục đích

Lưu dữ liệu mở rộng.

---

## Các field

| Field      | Type         | Meaning      |
| ---------- | ------------ | ------------ |
| id         | int          | khóa         |
| order_id   | int          | liên kết đơn |
| meta_key   | varchar(100) | tên key      |
| meta_value | text         | giá trị      |
| created_at | datetime     | thời gian    |

---

## Ví dụ meta_key

```text id="k4m2p8"
pickup_note
priority
hub_name
special_instruction
```

---

# 7. Bảng user: emslss_users

## Mục đích

Quản lý tài khoản hệ thống.

---

## Các field

| Field      | Type         | Meaning       |
| ---------- | ------------ | ------------- |
| id         | int          | khóa          |
| username   | varchar(50)  | tài khoản     |
| password   | varchar(255) | mật khẩu hash |
| full_name  | varchar(100) | họ tên        |
| role       | enum         | vai trò       |
| phone      | varchar(20)  | số điện thoại |
| role_id    | int          | role mapping  |
| is_active  | tinyint      | trạng thái    |
| created_at | timestamp    | tạo lúc       |

---

## role hiện tại

```text id="q3v7n1"
admin
dispatcher
shipper
operation
```

---

# 8. Bảng role: emslss_roles

## Mục đích

Phân quyền chi tiết.

---

## Các field

| Field       | Type         | Meaning  |
| ----------- | ------------ | -------- |
| id          | int          | khóa     |
| role_code   | varchar(50)  | mã role  |
| role_name   | varchar(100) | tên role |
| description | text         | mô tả    |

---

# 9. Quan hệ bảng

```text id="z8p1k4"
emslss_orders
   ↓
emslss_tracking
   ↓
emslss_images
   ↓
emslss_order_meta
```

---

## User relation

```text id="x6n2m7"
emslss_users
   ↓
pickup_shipper_id
delivery_shipper_id
created_by
uploaded_by
```

---

# 10. Production recommendation

* thêm foreign key cho order_id
* thêm index cho ems_code
* dùng transaction khi update nhiều bảng
* timezone Asia/Ho_Chi_Minh

---

# 11. Future schema extension

```text id="c7m4v9"
pickup_assigned_at
pickup_completed_at
delivery_assigned_at
delivery_completed_at
```

---

# 12. Core principle

## emslss_orders

lưu trạng thái hiện tại.

## emslss_tracking

lưu lịch sử toàn bộ.

## emslss_api_logs

lưu giao tiếp hệ ngoài.