Admin:
- quản lý user
- quản lý role
- xem toàn hệ thống
- API log

Dispatcher:
- đơn mới từ EMS
- gán pickup shipper
- gán delivery shipper
- theo dõi trạng thái

Shipper pickup:
- đơn được giao pickup
- người giữ thư
- số điện thoại
- bưu cục
- scan mã EMS
- upload ảnh

Pickup scan:
scan EMS
→ status picked_up
→ tracking insert
→ callback EMS

Delivery dashboard:
- đơn giao phát
- người nhận
- sđt
- địa chỉ
- scan giao thành công
- ảnh chứng từ

tracking:
- new_order
- assigned_pickup
- picked_up
- arrived_hub
- assigned_delivery
- delivered
- failed

Note:
- Khách vắng nhà. Hẹn mai giao
- Bưu cục chưa bàn giao
- Thiếu chứng từ