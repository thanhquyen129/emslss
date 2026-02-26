<?php
// public_html/booking/includes/functions.php

/**
 * Hàm làm sạch dữ liệu đầu vào để ngăn chặn các cuộc tấn công XSS và loại bỏ khoảng trắng dư thừa.
 *
 * @param string $data Chuỗi dữ liệu cần làm sạch.
 * @return string Chuỗi dữ liệu đã được làm sạch.
 */
function sanitize_input($data) {
    $data = trim($data); // Loại bỏ khoảng trắng (hoặc ký tự khác) từ đầu và cuối chuỗi
    $data = stripslashes($data); // Loại bỏ dấu gạch chéo ngược (backslash)
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Chuyển đổi các ký tự đặc biệt thành thực thể HTML
    return $data;
}

/**
 * Hàm tạo một mã tham chiếu (reference_code) duy nhất theo định dạng mới.
 * Format: [Mã đại lý (user_id)] + [Các chữ số ngẫu nhiên còn lại]
 * Tổng cộng 10 chữ số.
 *
 * @param PDO $pdo Đối tượng PDO để tương tác với database.
 * @param int $agencyId ID của đại lý (user_id) để làm tiền tố cho mã booking.
 * @return string Mã tham chiếu duy nhất gồm 10 chữ số.
 * @throws Exception Nếu không thể tạo mã duy nhất sau nhiều lần thử.
 */
function generateUniqueReferenceCode(PDO $pdo, int $agencyId) {
    $agencyCode = (string)$agencyId; // Chuyển ID đại lý thành chuỗi
    $agencyCodeLength = strlen($agencyCode);

    // Đảm bảo mã đại lý không quá dài để còn chỗ cho các số ngẫu nhiên
    if ($agencyCodeLength >= 10) {
        throw new Exception("Mã đại lý quá dài (" . $agencyCodeLength . " chữ số) để tạo mã booking 10 chữ số.");
    }

    $remainingDigitsLength = 10 - $agencyCodeLength; // Số chữ số ngẫu nhiên cần tạo
    $max_attempts = 10; // Số lần thử tối đa để tạo mã duy nhất

    for ($i = 0; $i < $max_attempts; $i++) {
        // Tạo các chữ số ngẫu nhiên còn lại
        $minRandom = ($remainingDigitsLength > 0) ? pow(10, $remainingDigitsLength - 1) : 0;
        $maxRandom = pow(10, $remainingDigitsLength) - 1;

        // Đảm bảo mt_rand không vượt quá giới hạn integer của PHP
        if ($minRandom < 0) $minRandom = 0;
        if ($maxRandom > mt_getrandmax()) $maxRandom = mt_getrandmax();

        // Xử lý trường hợp $remainingDigitsLength = 0 để tránh lỗi mt_rand(0, -1)
        if ($remainingDigitsLength == 0) {
            $randomNumber = '';
        } else {
            $randomNumber = str_pad(mt_rand($minRandom, $maxRandom), $remainingDigitsLength, '0', STR_PAD_LEFT);
        }

        $reference_code = $agencyCode . $randomNumber;

        // Kiểm tra xem mã này đã tồn tại trong database chưa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vmlbooking_orders WHERE reference_code = ?");
        $stmt->execute([$reference_code]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            // Mã chưa tồn tại, trả về mã này
            return $reference_code;
        }
        // Nếu mã đã tồn tại, vòng lặp sẽ tiếp tục để thử lại
    }

    // Nếu không thể tạo mã duy nhất sau số lần thử tối đa
    throw new Exception("Không thể tạo mã tham chiếu duy nhất sau " . $max_attempts . " lần thử cho đại lý ID " . $agencyId . ". Vui lòng thử lại sau.");
}

/**
 * Hàm kiểm tra người dùng đã đăng nhập hay chưa.
 *
 * @return bool True nếu đã đăng nhập, ngược lại False.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Hàm lấy thông tin người dùng từ database dựa trên ID.
 *
 * @param int $user_id ID của người dùng.
 * @return array|null Mảng thông tin người dùng hoặc null nếu không tìm thấy.
 */
function get_user_info($user_id) {
    global $pdo; // Sử dụng biến $pdo đã được khởi tạo từ db_config.php (hoặc includes/config.php)
    if (!$pdo) {
        error_log("Lỗi: Kết nối PDO không khả dụng trong get_user_info().");
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, role FROM vmlbooking_users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Lỗi database khi lấy thông tin người dùng: " . $e->getMessage());
        return null;
    }
}

/**
 * Hàm kiểm tra vai trò của người dùng có nằm trong danh sách các vai trò yêu cầu hay không.
 *
 * @param string $user_role Vai trò hiện tại của người dùng.
 * @param array $required_roles Mảng các vai trò được phép.
 * @return bool True nếu vai trò hợp lệ, ngược lại False.
 */
function has_role($user_role, $required_roles) {
    return in_array($user_role, $required_roles);
}

/**
 * Hàm chuyển hướng trình duyệt đến một URL khác và có thể truyền thông báo qua session.
 *
 * @param string $url URL đích để chuyển hướng.
 * @param string|null $message_type Loại thông báo (ví dụ: 'success', 'error', 'info').
 * @param string|null $message Nội dung thông báo.
 */
function redirect($url, $message_type = null, $message = null) {
    if ($message_type && $message) {
        // Lưu thông báo vào session để hiển thị trên trang đích
        $_SESSION[$message_type . '_message'] = $message;
    }
    header("Location: " . $url);
    exit();
}

/**
 * Hàm định dạng chuỗi ngày giờ từ database sang định dạng dễ đọc.
 *
 * @param string $datetime_string Chuỗi ngày giờ từ database (ví dụ: 'YYYY-MM-DD HH:MM:SS').
 * @param string $format Định dạng mong muốn (mặc định 'd/m/Y H:i:s').
 * @return string Chuỗi ngày giờ đã định dạng hoặc 'N/A' nếu không hợp lệ.
 */
function format_date($datetime_string, $format = 'd/m/Y H:i:s') {
    if (empty($datetime_string) || $datetime_string == '0000-00-00 00:00:00' || $datetime_string == '0000-00-00') {
        return 'N/A';
    }
    try {
        $date = new DateTime($datetime_string);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Lỗi định dạng ngày: " . $e->getMessage() . " - Input: " . $datetime_string);
        return $datetime_string; // Trả về nguyên bản nếu có lỗi
    }
}

/**
 * Hàm để cập nhật trạng thái chính của booking dựa trên log mới nhất.
 * Hàm này sẽ được gọi từ các file xử lý trạng thái.
 *
 * @param PDO $pdo Đối tượng PDO để tương tác với database.
 * @param int $bookingId ID của booking cần cập nhật.
 * @return bool True nếu cập nhật thành công, false nếu có lỗi.
 */
function updateMainBookingStatus(PDO $pdo, int $bookingId) {
    error_log("DEBUG: Bắt đầu updateMainBookingStatus cho Booking ID: " . $bookingId);
    try {
        // Lấy log trạng thái mới nhất cho booking này
        $stmtLatestLog = $pdo->prepare("SELECT status_type, created_at FROM vmlbooking_status_logs WHERE order_id = :order_id ORDER BY created_at DESC LIMIT 1");
        $stmtLatestLog->bindParam(':order_id', $bookingId, PDO::PARAM_INT);
        $stmtLatestLog->execute();
        $latestLog = $stmtLatestLog->fetch(PDO::FETCH_ASSOC);

        $latestStatusType = null;
        if ($latestLog) {
            $latestStatusType = $latestLog['status_type'];
            error_log("DEBUG: Log trạng thái mới nhất tìm thấy cho Booking ID " . $bookingId . ": " . $latestStatusType . " (Created At: " . $latestLog['created_at'] . ")");
        } else {
            error_log("DEBUG: Không tìm thấy log trạng thái nào cho Booking ID " . $bookingId . ".");
        }

        // Nếu không còn log nào (hoặc log mới nhất là null), đặt trạng thái là 'pending'
        $newMainStatus = $latestStatusType ?: 'pending';

        error_log("DEBUG: Trạng thái chính mới sẽ được đặt cho Booking ID " . $bookingId . ": " . $newMainStatus);

        // Cập nhật trạng thái chính trong bảng vmlbooking_orders
        $stmtUpdateOrder = $pdo->prepare("UPDATE vmlbooking_orders SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmtUpdateOrder->bindParam(':status', $newMainStatus, PDO::PARAM_STR);
        $stmtUpdateOrder->bindParam(':id', $bookingId, PDO::PARAM_INT);
        $stmtUpdateOrder->execute();

        // Kiểm tra số hàng bị ảnh hưởng
        if ($stmtUpdateOrder->rowCount() > 0) {
            error_log("DEBUG: Trạng thái chính của booking ID " . $bookingId . " đã được cập nhật thành: " . $newMainStatus . " thành công.");
            return true;
        } else {
            error_log("DEBUG: Không có hàng nào được cập nhật cho Booking ID " . $bookingId . ". Có thể trạng thái đã giống nhau hoặc ID không tồn tại.");
            return false; // Trả về false nếu không có hàng nào bị ảnh hưởng
        }
    } catch (PDOException $e) {
        error_log("Lỗi PDO khi cập nhật trạng thái chính của booking ID " . $bookingId . ": " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Lỗi chung khi cập nhật trạng thái chính của booking ID " . $bookingId . ": " . $e->getMessage());
        return false;
    }
}
?>