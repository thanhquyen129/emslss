<?php
// public_html/vietma/booking/admin/status/add_status_process.php

session_start();

// Bật báo cáo lỗi để dễ dàng gỡ lỗi trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình và hàm
// Đảm bảo đường dẫn đúng đến file config.php và functions.php
require_once 'includes/config.php';
require_once 'includes/functions.php'; // Bao gồm file functions.php

// Kiểm tra xem người dùng đã đăng nhập và có vai trò 'admin' hoặc 'accounting' hay không
if (!is_logged_in() || !has_role(get_user_info($_SESSION['user_id'])['role'], ['admin', 'accounting'])) {
    redirect('../../login.php', 'error', 'Bạn không có quyền truy cập trang này.'); // Điều chỉnh đường dẫn
}

// Xử lý logic khi form thêm log trạng thái chi tiết được gửi (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_status_log'])) {
    $bookingIdToUpdate = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $detailedStatusText = trim(filter_input(INPUT_POST, 'detailed_status_text', FILTER_SANITIZE_STRING));
    $detailedStatusType = filter_input(INPUT_POST, 'detailed_status_type', FILTER_SANITIZE_STRING);
    $eventDateTime = filter_input(INPUT_POST, 'event_datetime', FILTER_SANITIZE_STRING); // Lấy giá trị datetime-local

    $currentUserId = $_SESSION['user_id']; // Lấy ID người dùng hiện tại từ session

    // Kiểm tra dữ liệu đầu vào
    if (!$bookingIdToUpdate || empty($detailedStatusType) || empty($detailedStatusText)) {
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Dữ liệu không hợp lệ. Vui lòng điền đầy đủ thông tin.");
    }

    try {
        $pdo->beginTransaction();

        // Ghi log chi tiết vào bảng vmlbooking_status_logs
        $stmtInsertDetailedLog = $pdo->prepare("INSERT INTO vmlbooking_status_logs (order_id, status_text, status_type, created_by_user_id, created_at) VALUES (:order_id, :status_text, :status_type, :created_by_user_id, :created_at)");

        // Gán giá trị created_at
        $createdAtValue = empty($eventDateTime) ? date('Y-m-d H:i:s') : $eventDateTime;

        $stmtInsertDetailedLog->bindParam(':order_id', $bookingIdToUpdate, PDO::PARAM_INT);
        $stmtInsertDetailedLog->bindParam(':status_text', $detailedStatusText, PDO::PARAM_STR);
        $stmtInsertDetailedLog->bindParam(':status_type', $detailedStatusType, PDO::PARAM_STR);
        $stmtInsertDetailedLog->bindParam(':created_by_user_id', $currentUserId, PDO::PARAM_INT);
        $stmtInsertDetailedLog->bindParam(':created_at', $createdAtValue, PDO::PARAM_STR);

        $stmtInsertDetailedLog->execute();

        // Cập nhật trạng thái chính của booking dựa trên log mới nhất
        // Gọi hàm updateMainBookingStatus từ functions.php
        if (!updateMainBookingStatus($pdo, $bookingIdToUpdate)) {
            throw new Exception("Không thể cập nhật trạng thái chính của booking.");
        }

        $pdo->commit();
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'success', "Đã thêm trạng thái thành công!");

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Lỗi database khi thêm trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Lỗi database: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Lỗi khi thêm trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Lỗi: " . $e->getMessage());
    }
} else {
    // Nếu không phải là yêu cầu POST hợp lệ, chuyển hướng về trang quản lý trạng thái hoặc trang chi tiết booking
    redirect('../../dashboard.php', 'error', 'Truy cập không hợp lệ.'); // Điều chỉnh đường dẫn
}
?>