<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php"; // Đảm bảo functions.php chứa hàm sanitize_input

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn cần đăng nhập để thực hiện thao tác này.</p>";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'agency';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];

    try {
        if (!isset($pdo) || $pdo === null) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        // Truy vấn booking để lấy dữ liệu
        // Đảm bảo chỉ agency của booking đó hoặc admin mới có thể copy
        $sql = "SELECT * FROM vmlbooking_orders WHERE id = ?";
        $params = [$booking_id];

        if ($user_role === 'agency') {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $booking_to_copy = $stmt->fetch();

        if ($booking_to_copy) {
            // Loại bỏ các trường không muốn copy
            unset($booking_to_copy['id']);
            unset($booking_to_copy['user_id']);
            unset($booking_to_copy['reference_code']);
            unset($booking_to_copy['created_at']);
            unset($booking_to_copy['updated_at']);
            unset($booking_to_copy['booking_status']); // Trạng thái luôn là pending khi tạo mới
            unset($booking_to_copy['cost']); // Không copy chi phí
            unset($booking_to_copy['sales_price']); // Không copy giá bán
            unset($booking_to_copy['note']); // Không copy ghi chú
            unset($booking_to_copy['gross_weight']); // Không copy cân nặng
            unset($booking_to_copy['number_of_packages']); // Không copy số lượng kiện
            unset($booking_to_copy['dimensions_text']); // Không copy kích thước

            // Lưu dữ liệu vào session để index.php có thể đọc và pre-fill form
            $_SESSION['copy_booking_data'] = $booking_to_copy;
            $_SESSION['booking_message'] = "<p class='message info'>✅ Đã copy thông tin booking vào form tạo đơn hàng mới. Vui lòng kiểm tra lại các trường và điền thông tin về cân nặng, số kiện, kích thước!</p>";
        } else {
            $_SESSION['booking_message'] = "<p class='message error'>❌ Không tìm thấy booking để copy hoặc bạn không có quyền.</p>";
        }

    } catch (PDOException $e) {
        $_SESSION['booking_message'] = "<p class='message error'>Lỗi database khi copy booking: " . htmlspecialchars($e->getMessage()) . "</p>";
    } catch (Exception $e) {
        $_SESSION['booking_message'] = "<p class='message error'>Lỗi hệ thống: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Yêu cầu không hợp lệ để copy booking.</p>";
}

header("Location: index.php");
exit;
?>