<?php
// public_html/vietma/booking/cancel_booking.php

// Bắt đầu phiên làm việc
session_start();

// Bật báo cáo lỗi để gỡ lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình và hàm tiện ích
require_once "includes/config.php";
require_once "includes/functions.php"; // Đảm bảo functions.php có hàm redirect

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    redirect('login.php', 'error', 'Bạn cần đăng nhập để thực hiện thao tác này.');
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'agency';

// Kiểm tra xem yêu cầu có phải là POST và có booking_id không
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];

    try {
        // Lấy thông tin booking để kiểm tra quyền và trạng thái
        $stmt_check = $pdo->prepare("SELECT user_id, booking_status, reference_code FROM vmlbooking_orders WHERE id = ?");
        $stmt_check->execute([$booking_id]);
        $booking = $stmt_check->fetch();

        if (!$booking) {
            redirect('index.php', 'error', 'Booking không tồn tại.');
        }

        // Kiểm tra quyền hủy:
        // Admin có thể hủy bất kỳ booking nào
        // Agency chỉ có thể hủy booking của chính họ
        // Sửa lỗi so sánh kiểu dữ liệu: đổi `!==` thành `!=`
        if ($user_role === 'agency' && $booking['user_id'] != $user_id) {
            redirect('index.php', 'error', 'Bạn không có quyền hủy booking này.');
        }

        // Kiểm tra trạng thái booking, chỉ cho phép hủy khi đang ở trạng thái 'Pending'
        // Kiểm tra cả hai trường hợp 'Pending' và 'pending' để đề phòng lỗi nhập liệu
        if (strtolower($booking['booking_status']) !== 'pending') {
            redirect('index.php', 'error', 'Booking ' . htmlspecialchars($booking['reference_code']) . ' không thể hủy vì trạng thái hiện tại là "' . htmlspecialchars($booking['booking_status']) . '".');
        }

        // Cập nhật trạng thái booking thành 'Cancelled'
        $stmt_update = $pdo->prepare("UPDATE vmlbooking_orders SET booking_status = 'Cancelled', updated_at = NOW() WHERE id = ?");
        $stmt_update->execute([$booking_id]);

        // Thêm log vào bảng status_logs để ghi lại lịch sử
        $stmt_log = $pdo->prepare("INSERT INTO vmlbooking_status_logs (order_id, status_type, status_text, created_by_user_id) VALUES (?, ?, ?, ?)");
        $status_text = "Booking đã được hủy bởi " . ($_SESSION['username'] ?? 'Người dùng không xác định') . ".";
        $stmt_log->execute([$booking_id, 'Cancelled', $status_text, $user_id]);

        // Chuyển hướng về index.php với thông báo thành công
        redirect('index.php', 'success', 'Booking ' . htmlspecialchars($booking['reference_code']) . ' đã được hủy thành công.');

    } catch (PDOException $e) {
        redirect('index.php', 'error', 'Lỗi database khi hủy booking: ' . $e->getMessage());
    } catch (Exception $e) {
        redirect('index.php', 'error', 'Lỗi hệ thống: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'error', 'Yêu cầu không hợp lệ để hủy booking.');
}