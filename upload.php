<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$target_dir = "downloads/"; // Thư mục sẽ lưu file tải lên
$max_files_per_booking = 3; // Giới hạn số lượng file tối đa cho mỗi booking

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_id = sanitize_input($_POST['booking_id'] ?? '');

    if (empty($booking_id) || !is_numeric($booking_id)) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi: Mã booking không hợp lệ.</p>";
        header("Location: index.php");
        exit;
    }

    // --- KIỂM TRA SỐ LƯỢNG FILE HIỆN CÓ ---
    try {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM vmlbooking_documents WHERE booking_id = ? AND user_id = ?");
        $stmt_count->execute([$booking_id, $user_id]);
        $current_file_count = $stmt_count->fetchColumn();

        if ($current_file_count >= $max_files_per_booking) {
            $_SESSION['upload_message'] = "<p class='message error'>❌ Không thể tải lên: Mỗi booking chỉ được phép có tối đa " . $max_files_per_booking . " file đính kèm.</p>";
            header("Location: index.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi khi kiểm tra số lượng file: " . htmlspecialchars($e->getMessage()) . "</p>";
        header("Location: index.php");
        exit;
    }
    // --- KẾT THÚC KIỂM TRA SỐ LƯỢNG FILE ---

    if (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] != UPLOAD_ERR_OK) {
        $error_message = '';
        switch ($_FILES['file_upload']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'Kích thước file quá lớn.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File chỉ được tải lên một phần.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'Chưa chọn file nào để tải lên.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Thiếu thư mục tạm thời.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Không thể ghi file vào đĩa. Vui lòng kiểm tra quyền thư mục.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'PHP extension dừng tải file.';
                break;
            default:
                $error_message = 'Lỗi không xác định khi tải file.';
                break;
        }
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi upload file: " . htmlspecialchars($error_message) . "</p>";
        header("Location: index.php");
        exit;
    }

    $original_file_name = sanitize_input(basename($_FILES["file_upload"]["name"])); // Tên file gốc
    $file_type = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));

    $unique_file_name = $booking_id . '_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $file_type;
    $target_file = $target_dir . $unique_file_name;

    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Chỉ cho phép file JPG, JPEG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX.</p>";
        header("Location: index.php");
        exit;
    }

    if ($_FILES["file_upload"]["size"] > 5 * 1024 * 1024) { // 5MB
        $_SESSION['upload_message'] = "<p class='message error'>❌ Kích thước file quá lớn. Tối đa 5MB.</p>";
        header("Location: index.php");
        exit;
    }

    if (file_exists($target_file)) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi: Tên file duy nhất bị trùng. Vui lòng thử lại.</p>";
        header("Location: index.php");
        exit;
    }

    if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $target_file)) {
        try {
            // Chèn bản ghi mới vào bảng vmlbooking_documents
            $stmt = $pdo->prepare("INSERT INTO vmlbooking_documents (booking_id, user_id, file_name, unique_file_name, file_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$booking_id, $user_id, $original_file_name, $unique_file_name, $file_type]);

            $_SESSION['upload_message'] = "<p class='message success'>✅ File " . htmlspecialchars($original_file_name) . " đã được tải lên và liên kết thành công!</p>";
            
        } catch (PDOException $e) {
            $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi database khi cập nhật đường dẫn file: " . htmlspecialchars($e->getMessage()) . "</p>";
            // Xóa file đã upload nếu không lưu được vào DB
            unlink($target_file); 
        }
    } else {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Có lỗi xảy ra khi tải file lên server. Vui lòng kiểm tra quyền thư mục downloads/.</p>";
    }
} else {
    $_SESSION['upload_message'] = "<p class='message error'>❌ Truy cập không hợp lệ.</p>";
}

header("Location: index.php");
exit;
?>