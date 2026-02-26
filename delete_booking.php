<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php"; // Đảm bảo hàm sanitize_input có sẵn

// Kiểm tra đăng nhập và vai trò (chỉ Admin mới được xóa booking)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn không có quyền thực hiện hành động này.</p>";
    header("Location: index.php");
    exit;
}

$target_dir = "downloads/"; // Thư mục chứa file tài liệu

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $booking_id = sanitize_input($_GET['id']);

    if (empty($booking_id) || !is_numeric($booking_id)) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Lỗi: ID booking không hợp lệ.</p>";
        header("Location: index.php");
        exit;
    }

    try {
        $pdo->beginTransaction(); // Bắt đầu giao dịch

        // 1. Lấy danh sách các file đính kèm của booking này
        $stmt_docs = $pdo->prepare("SELECT id, unique_file_name FROM vmlbooking_documents WHERE booking_id = ?");
        $stmt_docs->execute([$booking_id]);
        $documents_to_delete = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

        // 2. Xóa các file vật lý
        foreach ($documents_to_delete as $doc) {
            $file_path = $target_dir . $doc['unique_file_name'];
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    // Nếu xóa file thất bại, ghi log và rollback
                    error_log("Lỗi: Không thể xóa file vật lý: " . $file_path);
                    $_SESSION['booking_message'] = "<p class='message error'>❌ Lỗi: Không thể xóa một số file đính kèm. Vui lòng kiểm tra quyền thư mục hoặc xóa thủ công.</p>";
                    $pdo->rollBack(); // Hoàn tác giao dịch
                    header("Location: index.php");
                    exit;
                }
            } else {
                error_log("Cảnh báo: File vật lý không tồn tại nhưng có trong DB: " . $file_path);
            }
        }

        // 3. Xóa các bản ghi tài liệu khỏi database
        $stmt_delete_docs = $pdo->prepare("DELETE FROM vmlbooking_documents WHERE booking_id = ?");
        $stmt_delete_docs->execute([$booking_id]);

        // 4. Xóa bản ghi booking khỏi database
        $stmt_delete_booking = $pdo->prepare("DELETE FROM vmlbooking_orders WHERE id = ?");
        $stmt_delete_booking->execute([$booking_id]);

        $pdo->commit(); // Hoàn tất giao dịch nếu mọi thứ thành công
        $_SESSION['booking_message'] = "<p class='message success'>✅ Booking và tất cả tài liệu đính kèm đã được xóa thành công.</p>";

    } catch (PDOException $e) {
        $pdo->rollBack(); // Hoàn tác giao dịch nếu có lỗi database
        $_SESSION['booking_message'] = "<p class='message error'>❌ Lỗi database khi xóa booking: " . htmlspecialchars($e->getMessage()) . "</p>";
        error_log("Lỗi PDO khi xóa booking ID " . $booking_id . ": " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack(); // Hoàn tác giao dịch nếu có lỗi khác
        $_SESSION['booking_message'] = "<p class='message error'>❌ Lỗi hệ thống khi xóa booking: " . htmlspecialchars($e->getMessage()) . "</p>";
        error_log("Lỗi hệ thống khi xóa booking ID " . $booking_id . ": " . $e->getMessage());
    }

} else {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Truy cập không hợp lệ.</p>";
}

header("Location: index.php");
exit;
?>