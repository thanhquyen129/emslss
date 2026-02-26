<?php
// public_html/vietma/booking/includes/header.php

// Đảm bảo session được bắt đầu ở các file chính (ví dụ: index.php, login.php) trước khi include header.php
// session_start(); // Không cần nếu đã gọi ở file chính

// Bao gồm file functions.php để sử dụng các hàm tiện ích nếu bạn có
// require_once 'includes/functions.php';

// Kiểm tra trạng thái đăng nhập và vai trò người dùng
$loggedIn = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? 'guest';
$current_page = basename($_SERVER['PHP_SELF']);

// Hỗ trợ hàm has_role đơn giản nếu bạn chưa có file functions.php
if (!function_exists('has_role')) {
    function has_role($current_role, $allowed_roles) {
        return in_array($current_role, $allowed_roles);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'VML Booking Online') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* CSS cho thanh điều hướng (menu) */
        .main-nav {
            background-color: #f2f2f2; /* Màu nền nhẹ cho menu */
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-top: 10px; /* Khoảng cách với header phía trên */
            text-align: center; /* Căn giữa các nút */
        }

        .main-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: inline-block; /* Để ul có thể được căn giữa */
        }

        .main-nav ul li {
            display: inline-block; /* Hiển thị các mục menu trên cùng một hàng */
            margin: 0 10px; /* Khoảng cách giữa các nút */
        }

        .main-nav ul li a {
            text-decoration: none;
            color: #333; /* Màu chữ mặc định */
            font-weight: bold;
            padding: 8px 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: background-color 0.3s ease, color 0.3s ease;
            white-space: nowrap; /* Ngăn không cho nút bị ngắt dòng */
        }

        .main-nav ul li a:hover {
            background-color: #e0e0e0;
            color: #007bff;
        }

        .main-nav ul li a.active {
            background-color: #007bff; /* Nút active */
            color: white;
            border-color: #007bff;
        }

        /* Điều chỉnh cho header hiện có */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #007bff; /* Màu nền xanh cho header */
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 8px 8px 0 0; /* Bo góc trên cho header */
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
        }

        .header .user-info {
            font-size: 1em;
        }

        .header .user-info a {
            color: white; /* Màu link đăng xuất */
            text-decoration: underline;
            margin-left: 10px;
        }
        .header .user-info a:hover {
            color: #e0e0e0;
        }

        /* Container đã có sẵn, chỉ thêm margin-top để tạo khoảng cách với menu */
        .container {
            //width: 90%;
            max-width: 1200px;
            margin: 20px auto; /* Điều chỉnh lại margin-top nếu cần */
            background-color: #fff;
            padding: 20px;
            border-radius: 0 0 8px 8px; /* Bo góc dưới cho container */
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>VML Booking Online</h1>
        <div class="user-info">
            <?php
            // Lấy tên hiển thị: ưu tiên agency_name nếu có, nếu không thì dùng username
            $display_name = htmlspecialchars($_SESSION['agency_name'] ?? $_SESSION['username'] ?? 'Khách');
            $user_role_display = htmlspecialchars($_SESSION['user_role'] ?? '');

            if ($loggedIn) {
                echo "Chào, <strong>{$display_name}</strong>";
                if (!empty($user_role_display)) {
                    echo " ({$user_role_display})"; // Hiển thị vai trò
                }
                echo ' <a href="logout.php">Đăng xuất</a>';
            } else {
                echo '<a href="login.php">Đăng nhập</a>';
            }
            ?>
        </div>
    </div>

    <!-- Thanh menu này luôn hiển thị cho tất cả mọi người -->
    <div class="main-nav">
        <ul>
            <!-- Liên kết "Theo dõi Booking" luôn hiển thị cho mọi người -->
            <li><a href="track_booking.php" class="<?= ($current_page == 'track_booking.php') ? 'active' : '' ?>">Theo dõi Booking</a></li>

            <?php
            // Các liên kết dưới đây chỉ hiển thị nếu người dùng đã đăng nhập
            if ($loggedIn):
            ?>
            <li><a href="index.php" class="<?= ($current_page == 'index.php') ? 'active' : '' ?>">Dashboard</a></li>

            <?php if (has_role($user_role, ['agency'])): ?>
                <!-- Đây là nơi bạn có thể thêm các liên kết dành riêng cho agency -->
            <?php endif; ?>

            <?php if (has_role($user_role, ['admin'])): ?>
                <li><a href="admin/users/index.php" class="<?= ($current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/admin/users/') !== false) ? 'active' : '' ?>">Quản lý Người dùng</a></li>
                <li><a href="accounting_dashboard.php" class="<?= ($current_page == 'accounting_dashboard.php') ? 'active' : '' ?>">Quản lý Kế toán</a></li>
                <li><a href="update_booking_status.php" class="<?= ($current_page == 'update_booking_status.php') ? 'active' : '' ?>">Cập Nhật Trạng Thái</a></li>
            <?php endif; ?>

            <?php if (has_role($user_role, ['accounting'])): ?>
                <li><a href="accounting_dashboard.php" class="<?= ($current_page == 'accounting_dashboard.php') ? 'active' : '' ?>">Quản lý Kế toán</a></li>
            <?php endif; ?>

            <?php endif; // Kết thúc if ($loggedIn) ?>
        </ul>
    </div>