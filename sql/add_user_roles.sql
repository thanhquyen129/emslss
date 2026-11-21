-- Chạy một lần: hỗ trợ nhiều role / user

INSERT IGNORE INTO emslss_roles (role_code, role_name, description) VALUES
('admin', 'Administrator', 'Quản trị hệ thống'),
('dispatcher', 'Dispatcher', 'Điều phối đơn hàng'),
('shipper', 'Shipper', 'Nhân viên pickup/delivery'),
('operation', 'Operation', 'Vận hành kho/bưu cục'),
('ems', 'EMS', 'Đối tác EMS xem đơn');

CREATE TABLE IF NOT EXISTS emslss_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    KEY idx_user_roles_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO emslss_user_roles (user_id, role_id)
SELECT id, role_id FROM emslss_users
WHERE role_id IS NOT NULL AND role_id > 0;
