-- Bảng dữ liệu chức năng Bảng kê (độc lập EMS-LSS)
-- Tự tạo khi mở bangke.php; file này chỉ để tham chiếu/manual.

CREATE TABLE IF NOT EXISTS bangke_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_date DATE NOT NULL,
  route_leg VARCHAR(255) NOT NULL DEFAULT '',
  flight_no VARCHAR(100) NOT NULL DEFAULT '',
  airway_bill VARCHAR(120) NOT NULL DEFAULT '',
  vin_no VARCHAR(120) NOT NULL DEFAULT '',
  serial_no VARCHAR(120) NOT NULL DEFAULT '',
  package_count INT NOT NULL DEFAULT 0,
  weight DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
