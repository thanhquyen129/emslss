CREATE DATABASE emslss;
USE emslss;

CREATE TABLE emslss_users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50),
 password VARCHAR(255),
 full_name VARCHAR(100),
 role ENUM("admin","dispatcher","shipper","operation"),
 phone VARCHAR(20),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO emslss_users(username,password,full_name,role,phone)
VALUES(
"admin",
MD5("123456"),
"Administrator",
"admin",
"0900000000"
);

CREATE TABLE emslss_orders (
 id INT AUTO_INCREMENT PRIMARY KEY,
 ems_code VARCHAR(50) UNIQUE,
 service_type VARCHAR(50),
 pickup_name VARCHAR(100),
 pickup_phone VARCHAR(20),
 pickup_address TEXT,
 receiver_name VARCHAR(100),
 receiver_phone VARCHAR(20),
 receiver_address TEXT,
 note TEXT,
 status VARCHAR(30) DEFAULT "new_order",
 shipper_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emslss_tracking (
 id INT AUTO_INCREMENT PRIMARY KEY,
 order_id INT,
 status VARCHAR(30),
 note TEXT,
 created_by INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emslss_images (
 id INT AUTO_INCREMENT PRIMARY KEY,
 order_id INT,
 image_path VARCHAR(255),
 uploaded_by INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emslss_api_logs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 source VARCHAR(50),
 payload TEXT,
 response TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);




CREATE TABLE emslss_roles (
 id INT AUTO_INCREMENT PRIMARY KEY,
 role_code VARCHAR(50) UNIQUE,
 role_name VARCHAR(100),
 description TEXT
);


CREATE TABLE vn_provinces (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(150),
    type ENUM('province','municipality')
);

CREATE TABLE vn_wards (
    code VARCHAR(10) PRIMARY KEY,
    province_code VARCHAR(10),
    name VARCHAR(150),

    old_district VARCHAR(150),
    old_ward VARCHAR(150),

    FOREIGN KEY (province_code) REFERENCES vn_provinces(code)
);

