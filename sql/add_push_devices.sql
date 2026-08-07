CREATE TABLE IF NOT EXISTS emslss_push_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    onesignal_subscription_id VARCHAR(64) DEFAULT NULL,
    platform VARCHAR(20) DEFAULT 'android',
    role_code VARCHAR(50) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_platform (user_id, platform),
    KEY idx_push_role (role_code),
    KEY idx_push_sub (onesignal_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
