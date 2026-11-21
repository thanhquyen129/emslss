-- Thêm role EMS và tài khoản mẫu (chạy một lần trên DB production)

INSERT INTO emslss_roles (role_code, role_name, description)
SELECT 'ems', 'EMS Partner', 'Đối tác EMS — xem đơn push qua API, tracking, callback logs'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM emslss_roles WHERE role_code = 'ems');

-- Nếu cột role là ENUM, mở rộng để chứa 'ems':
-- ALTER TABLE emslss_users MODIFY role ENUM('admin','dispatcher','shipper','operation','ems');

-- Tạo user EMS mẫu (đổi mật khẩu sau khi đăng nhập):
-- INSERT INTO emslss_users (username, password, full_name, phone, role_id, role, is_active)
-- SELECT 'ems_viewer', MD5('123456'), 'EMS Viewer', '', r.id, 'ems', 1
-- FROM emslss_roles r WHERE r.role_code = 'ems' LIMIT 1;
