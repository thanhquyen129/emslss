<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php"; // Đảm bảo hàm sanitize_input có sẵn

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$target_dir = "downloads/"; // Thư mục chứa file tải lên

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $document_id = sanitize_input($_POST['document_id'] ?? '');

    if (empty($document_id) || !is_numeric($document_id)) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi: ID tài liệu không hợp lệ.</p>";
        header("Location: index.php");
        exit;
    }

    try {
        // Lấy thông tin tài liệu từ database, đảm bảo người dùng có quyền xóa
        $stmt = $pdo->prepare("SELECT unique_file_name FROM vmlbooking_documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$document_id, $user_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document) {
            $file_path = $target_dir . $document['unique_file_name'];

            // 1. Xóa file vật lý khỏi server
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    // 2. Xóa bản ghi khỏi database
                    $stmt_delete = $pdo->prepare("DELETE FROM vmlbooking_documents WHERE id = ?");
                    $stmt_delete->execute([$document_id]);

                    $_SESSION['upload_message'] = "<p class='message success'>✅ File " . htmlspecialchars($document['unique_file_name']) . " đã được xóa thành công.</p>";
                } else {
                    $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi: Không thể xóa file vật lý " . htmlspecialchars($document['unique_file_name']) . ". Vui lòng kiểm tra quyền thư mục.</p>";
                }
            } else {
                // File vật lý không tồn tại nhưng vẫn có trong DB, chỉ xóa bản ghi DB
                $stmt_delete = $pdo->prepare("DELETE FROM vmlbooking_documents WHERE id = ?");
                $stmt_delete->execute([$document_id]);
                $_SESSION['upload_message'] = "<p class='message info'>ℹ️ File vật lý không tồn tại nhưng đã xóa bản ghi khỏi database.</p>";
            }
        } else {
            $_SESSION['upload_message'] = "<p class='message error'>❌ Tài liệu không tồn tại hoặc bạn không có quyền xóa.</p>";
        }
    } catch (PDOException $e) {
        $_SESSION['upload_message'] = "<p class='message error'>❌ Lỗi database khi xóa tài liệu: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    $_SESSION['upload_message'] = "<p class='message error'>❌ Truy cập không hợp lệ.</p>";
}

header("Location: index.php");
exit;
?>