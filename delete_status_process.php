<?php
// public_html/vietma/booking/admin/status/delete_status_process.php

session_start();

// Bật báo cáo lỗi để dễ dàng gỡ lỗi trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình và hàm
require_once '../../includes/config.php';
require_once '../../includes/functions.php'; // Bao gồm file functions.php

// Kiểm tra xem người dùng đã đăng nhập và có vai trò 'admin' hay 'accounting' hay không
if (!is_logged_in() || !has_role(get_user_info($_SESSION['user_id'])['role'], ['admin', 'accounting'])) {
    redirect('../../login.php', 'error', 'Bạn không có quyền truy cập trang này.');
}

// Xử lý logic khi yêu cầu xóa log trạng thái được gửi (GET hoặc POST request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $statusLogId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $bookingId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT); // Lấy order_id từ URL

    if (!$statusLogId || !$bookingId) {
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "ID trạng thái hoặc ID booking không hợp lệ.");
    }

    try {
        $pdo->beginTransaction();

        // Xóa log chi tiết từ bảng vmlbooking_status_logs
        $stmtDeleteDetailedLog = $pdo->prepare("DELETE FROM vmlbooking_status_logs WHERE id = :id AND order_id = :order_id");
        $stmtDeleteDetailedLog->bindParam(':id', $statusLogId, PDO::PARAM_INT);
        $stmtDeleteDetailedLog->bindParam(':order_id', $bookingId, PDO::PARAM_INT);
        $stmtDeleteDetailedLog->execute();

        // Kiểm tra xem có hàng nào bị ảnh hưởng không
        if ($stmtDeleteDetailedLog->rowCount() === 0) {
            throw new Exception("Không tìm thấy log trạng thái để xóa hoặc bạn không có quyền.");
        }

        // Cập nhật trạng thái chính của booking dựa trên log còn lại
        // Gọi hàm updateMainBookingStatus từ functions.php
        if (!updateMainBookingStatus($pdo, $bookingId)) {
            throw new Exception("Không thể cập nhật trạng thái chính của booking sau khi xóa log.");
        }

        $pdo->commit();
        redirect('update_booking_status.php?id=' . $bookingId, 'success', "Đã xóa trạng thái thành công!");

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Lỗi database khi xóa trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "Lỗi database: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Lỗi khi xóa trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingId, 'error', "Lỗi: " . $e->getMessage());
    }
} else {
    // Nếu không phải là yêu cầu GET hợp lệ, chuyển hướng về trang quản lý trạng thái hoặc dashboard
    $bookingId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT) ?? null;
    if ($bookingId) {
        redirect('update_booking_status.php?id=' . $bookingId, 'error', 'Truy cập không hợp lệ.');
    } else {
        redirect('../../dashboard.php', 'error', 'Truy cập không hợp lệ.');
    }
}
?>