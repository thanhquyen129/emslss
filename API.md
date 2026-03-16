# API.md

# EMS-LSS API Specification

## 1. Mục đích

API dùng để kết nối giữa hệ thống EMS và hệ thống LSS trong quá trình tiếp nhận, xử lý và cập nhật trạng thái đơn hàng.

Luồng tổng thể:

```text id="r1v1x2"
EMS → gửi đơn → LSS
LSS → phản hồi tiếp nhận ngay
LSS → callback trạng thái vận hành → EMS
EMS → truy vấn tracking → LSS
```

---

# 2. Authentication

Tất cả API sử dụng API Key.

## Header chuẩn

```http id="w1v9p4"
X-API-KEY: your_secret_key
Content-Type: application/json
```

---

# 3. API EMS gửi đơn sang LSS

## Endpoint

```text id="e8j3n7"
POST /api/ems_receive.php
```

---

## Mục đích

EMS gửi thông tin đơn sang LSS để LSS tiếp nhận và đưa vào vận hành.

---

## Request JSON

```json id="p3c7x5"
{
  "ems_code": "EE123456789VN",
  "post_office_name": "Bưu cục Tân Bình",
  "post_office_address": "12 Nguyễn Thái Bình, Tân Bình",

  "holder_name": "Chị Thảo",
  "holder_phone": "0909123456",

  "sender_name": "Nguyễn Văn A",
  "sender_phone": "0911222333",
  "sender_address": "Quận 1, TP.HCM",

  "receiver_name": "Trần Văn B",
  "receiver_phone": "0988777666",
  "receiver_address": "Hải Châu, Đà Nẵng",

  "weight": 0.5,
  "cargo_type": "documents",
  "service_type": "door_to_door"
}
```

---

## Response thành công

```json id="k9m2f1"
{
  "status": "success",
  "message": "Order received successfully",
  "ems_code": "EE123456789VN"
}
```

---

## Giải thích

### status

```text id="b4z6u8"
success
error
```

---

### message

Thông điệp phản hồi kết quả xử lý.

---

### ems_code

Trả lại đúng mã EMS do EMS gửi sang.

---

## Response lỗi

### Duplicate EMS code

```json id="x7p1r6"
{
  "status": "error",
  "code": 1001,
  "message": "Duplicate EMS code"
}
```

---

### Invalid API key

```json id="m8q4t2"
{
  "status": "error",
  "code": 1002,
  "message": "Unauthorized"
}
```

---

### Missing required field

```json id="f6w3y9"
{
  "status": "error",
  "code": 1003,
  "message": "Missing required field"
}
```

---

## Nguyên tắc

Không trả về:

```text id="q2a8n5"
order_id
```

vì đây là khóa nội bộ LSS.

---

# 4. API callback trạng thái vận hành từ LSS về EMS

## Endpoint

```text id="t4k9m1"
POST /api/ems_callback.php
```

---

## Mục đích

Sau khi đơn được xử lý ngoài thực địa, LSS callback trạng thái về EMS.

---

## Request JSON

```json id="n5r8u3"
{
  "ems_code": "EE123456789VN",
  "status": "picked_up",
  "note": "Pickup successful",
  "updated_at": "2026-03-16 10:30:00"
}
```

---

## Status callback chuẩn

```text id="u7c2w4"
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

## Response EMS trả lại

```json id="j3x7b6"
{
  "status": "success"
}
```

---

# 5. API EMS truy vấn tracking đơn

## Endpoint

```text id="g8m4p2"
GET /api/ems_tracking.php?ems_code=EE123456789VN
```

---

## Response

```json id="h2v9k1"
{
  "ems_code": "EE123456789VN",
  "current_status": "in_transit",
  "tracking": [
    {
      "status": "new_order",
      "note": "Order created",
      "time": "2026-03-16 09:00:00"
    },
    {
      "status": "picked_up",
      "note": "Picked by shipper",
      "time": "2026-03-16 10:20:00"
    }
  ]
}
```

---

# 6. API phân công shipper nội bộ

## Endpoint

```text id="z6p3r8"
POST /api/assign_shipper.php
```

---

## Request JSON

```json id="v1k7m5"
{
  "order_id": 125,
  "shipper_id": 8,
  "type": "pickup"
}
```

---

## type

```text id="s4n2c9"
pickup
delivery
```

---

## Response

```json id="w9m6x2"
{
  "status": "success"
}
```

---

# 7. API scan cập nhật trạng thái nội bộ

## Endpoint

```text id="r3q8p7"
POST /api/scan_update.php
```

---

## Request JSON

```json id="c5v1n4"
{
  "ems_code": "EE123456789VN",
  "status": "picked_up",
  "user_id": 12,
  "note": "Received from holder"
}
```

---

## Response

```json id="y8k2m6"
{
  "status": "success"
}
```

---

# 8. Logging

Mọi request API phải lưu vào bảng:

```text id="d4m7w1"
emslss_api_logs
```

---

## Nội dung log

* source
* payload
* response
* created_at

---

# 9. Error Code chuẩn

| Code | Meaning                |
| ---- | ---------------------- |
| 1001 | Duplicate EMS code     |
| 1002 | Unauthorized           |
| 1003 | Missing required field |

---

# 10. Production recommendation

* dùng transaction khi update order + tracking
* callback retry nếu EMS timeout
* log đầy đủ request / response
* timezone Asia/Ho_Chi_Minh
* validate đầy đủ ems_code trước insert

---

# 11. Future API

```text id="m2x7p9"
api/upload_image.php
api/order_detail.php
api/retry_callback.php
api/dashboard_summary.php