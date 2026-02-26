<?php
// public_html/vietma/booking/edit_status_process.php

session_start();

// Bật báo cáo lỗi để dễ dàng gỡ lỗi trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình và hàm
require_once 'includes/config.php';
require_once 'includes/functions.php'; // Bao gồm file functions.php

// Hàm redirect đã được định nghĩa trong functions.php, không cần định nghĩa lại ở đây

// Kiểm tra xem người dùng đã đăng nhập và có vai trò 'admin' hoặc 'accounting' hay không
if (!is_logged_in() || !has_role(get_user_info($_SESSION['user_id'])['role'], ['admin', 'accounting'])) {
    redirect('login.php', 'error', 'Bạn không có quyền truy cập trang này.');
}

// Xử lý logic khi form chỉnh sửa log trạng thái được gửi (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_status_log'])) {
    $statusLogId = filter_input(INPUT_POST, 'status_log_id', FILTER_VALIDATE_INT);
    $bookingId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT); // Lấy order_id từ form
    $detailedStatusText = trim(filter_input(INPUT_POST, 'detailed_status_text', FILTER_SANITIZE_STRING));
    $detailedStatusType = filter_input(INPUT_POST, 'detailed_status_type', FILTER_SANITIZE_STRING);
    $eventDateTimeInput = filter_input(INPUT_POST, 'event_datetime', FILTER_SANITIZE_STRING); // Lấy giá trị datetime-local từ input

    $currentUserId = $_SESSION['user_id']; // Lấy ID người dùng hiện tại từ session

    // Kiểm tra dữ liệu đầu vào
    if (!$statusLogId || !$bookingId || empty($detailedStatusType) || empty($detailedStatusText)) {
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "Dữ liệu không hợp lệ. Vui lòng điền đầy đủ thông tin.");
    }

    // Xử lý và định dạng lại eventDateTime
    // Input từ datetime-local có định dạng "YYYY-MM-DDTHH:MM"
    // Cần chuyển đổi sang "YYYY-MM-DD HH:MM:SS" cho MySQL
    $eventDateTime = null;
    if (!empty($eventDateTimeInput)) {
        try {
            $dateTimeObj = new DateTime($eventDateTimeInput);
            $eventDateTime = $dateTimeObj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Nếu có lỗi khi parse ngày giờ, sử dụng thời gian hiện tại hoặc báo lỗi
            error_log("Lỗi định dạng ngày giờ từ input: " . $eventDateTimeInput . " - " . $e->getMessage());
            // Có thể chọn redirect với lỗi hoặc dùng thời gian hiện tại
            redirect('update_booking_status.php?id=' . $bookingId, 'error', "Định dạng ngày giờ không hợp lệ. Vui lòng kiểm tra lại.");
        }
    } else {
        // Nếu input rỗng, sử dụng thời gian hiện tại
        $eventDateTime = date('Y-m-d H:i:s');
    }


    try {
        $pdo->beginTransaction();

        // Cập nhật log chi tiết trong bảng vmlbooking_status_logs
        $stmtUpdateDetailedLog = $pdo->prepare("UPDATE vmlbooking_status_logs SET status_text = :status_text, status_type = :status_type, created_by_user_id = :created_by_user_id, created_at = :created_at, updated_at = NOW() WHERE id = :id AND order_id = :order_id");

        $stmtUpdateDetailedLog->bindParam(':status_text', $detailedStatusText, PDO::PARAM_STR);
        $stmtUpdateDetailedLog->bindParam(':status_type', $detailedStatusType, PDO::PARAM_STR);
        $stmtUpdateDetailedLog->bindParam(':created_by_user_id', $currentUserId, PDO::PARAM_INT); // Cập nhật người sửa
        $stmtUpdateDetailedLog->bindParam(':created_at', $eventDateTime, PDO::PARAM_STR); // Gán giá trị đã được định dạng
        $stmtUpdateDetailedLog->bindParam(':id', $statusLogId, PDO::PARAM_INT);
        $stmtUpdateDetailedLog->bindParam(':order_id', $bookingId, PDO::PARAM_INT);

        $stmtUpdateDetailedLog->execute();

        // Cập nhật trạng thái chính của booking dựa trên log mới nhất
        // Gọi hàm updateMainBookingStatus từ functions.php
        if (!updateMainBookingStatus($pdo, $bookingId)) {
            throw new Exception("Không thể cập nhật trạng thái chính của booking sau khi chỉnh sửa log.");
        }

        $pdo->commit();
        redirect('update_booking_status.php?id=' . $bookingId, 'success', "Đã cập nhật trạng thái thành công!");

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Lỗi database khi cập nhật trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "Lỗi database: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Lỗi khi cập nhật trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "Lỗi: " . $e->getMessage());
    }
} else {
    // Nếu không phải là yêu cầu POST hợp lệ, chuyển hướng về trang quản lý trạng thái hoặc trang chi tiết booking
    // Cố gắng lấy order_id từ GET nếu có để chuyển hướng đúng
    $bookingId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT) ?? null;
    if ($bookingId) {
        redirect('update_booking_status.php?id=' . $bookingId, 'error', 'Truy cập không hợp lệ.');
    } else {
        redirect('index.php', 'error', 'Truy cập không hợp lệ.');
    }
}
?>