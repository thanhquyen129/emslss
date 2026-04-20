# EMS-LSS API Test Checklist

Checklist test nhanh cho đội EMS theo tài liệu GitBook.

## 1) Chuẩn bị

- Base URL: `https://lsslogistics.vn/api`
- Header bắt buộc:
  - `Content-Type: application/json`
  - `X-API-KEY: <YOUR_API_KEY>`
- Dùng mã test:
  - `EMS_CODE_OK=EE123456789VN`
  - `EMS_CODE_NOT_FOUND=EE000000000VN`

## 2) Push Order

Endpoint: `POST /ems_push_order.php`

```bash
curl -X POST "https://lsslogistics.vn/api/ems_push_order.php" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: YOUR_API_KEY" \
  -d '{
    "ems_code": "EE123456789VN",
    "post_office_name": "Da Nang Post",
    "post_office_address": "Hai Chau",
    "holder_name": "Nguyen Van A",
    "holder_phone": "0901234567",
    "sender_name": "Cong ty A",
    "sender_phone": "0900000000",
    "sender_address": "Ha Noi",
    "receiver_name": "Tran Van B",
    "receiver_phone": "0912345678",
    "receiver_address": "Hoi An",
    "weight": 0.5,
    "cargo_type": "documents",
    "service_type": "door_to_door"
  }'
```

Expected:
- Lần đầu:
  - `status = success`
  - Có `ems_code`
- Gửi lại cùng `ems_code`:
  - `status = duplicate`
  - Có `current_status`

## 3) Tracking Query

Endpoint: `GET /ems_tracking.php?ems_code=...`

```bash
curl -X GET "https://lsslogistics.vn/api/ems_tracking.php?ems_code=EE123456789VN" \
  -H "X-API-KEY: YOUR_API_KEY"
```

Expected:
- `status = success`
- Có `ems_code`, `current_status`
- Có mảng `timeline[]`
- Mỗi item `timeline` có: `status`, `note`, `created_at`

## 4) Cancel Order

Endpoint: `POST /ems_cancel_order.php`

```bash
curl -X POST "https://lsslogistics.vn/api/ems_cancel_order.php" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: YOUR_API_KEY" \
  -d '{
    "ems_code": "EE123456789VN"
  }'
```

Expected:
- `status = success`

## 5) Auth/Validation Cases

### 5.1 Missing API key

```bash
curl -X GET "https://lsslogistics.vn/api/ems_tracking.php?ems_code=EE123456789VN"
```

Expected:
- HTTP `401`
- `status = error`

### 5.2 Wrong method

```bash
curl -X GET "https://lsslogistics.vn/api/ems_push_order.php" \
  -H "X-API-KEY: YOUR_API_KEY"
```

Expected:
- HTTP `405`
- `status = error`

### 5.3 Missing required field

```bash
curl -X POST "https://lsslogistics.vn/api/ems_push_order.php" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: YOUR_API_KEY" \
  -d '{
    "ems_code": "EE999999999VN"
  }'
```

Expected:
- HTTP `422`
- `status = error`

### 5.4 Tracking not found

```bash
curl -X GET "https://lsslogistics.vn/api/ems_tracking.php?ems_code=EE000000000VN" \
  -H "X-API-KEY: YOUR_API_KEY"
```

Expected:
- HTTP `404`
- `status = error`

## 6) Callback Verification (LSS -> EMS)

Phần này do đội LSS trigger từ nghiệp vụ nội bộ:
- Pickup callback payload:
  - `ems_code`
  - `status = picked_up`
  - `time`
- Delivery callback payload:
  - `ems_code`
  - `status = delivered`
  - `time`

Checklist với EMS:
- EMS endpoint nhận đúng payload trên.
- EMS trả HTTP 2xx khi nhận thành công.
- Khi EMS trả lỗi/non-2xx, phía LSS có cơ chế retry qua `GET /api/callback_retry.php`.

## 7) Go-live Smoke Test

- Push 1 đơn mới thành công.
- Query tracking thấy event `new_order`.
- Chạy flow nội bộ pickup/delivery.
- EMS nhận callback `picked_up`.
- EMS nhận callback `delivered`.
- Query tracking khớp timeline vận hành thực tế.
