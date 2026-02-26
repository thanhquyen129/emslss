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
$user_role = $_SESSION['user_role'] ?? 'agency'; // Lấy vai trò của người dùng

if (!isset($_GET['doc_id']) || !is_numeric($_GET['doc_id'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ ID tài liệu không hợp lệ.</p>";
    header("Location: index.php");
    exit;
}

$document_id = (int)$_GET['doc_id'];

try {
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $stmt = null;
    // Admin và Viewer có thể tải bất kỳ tài liệu nào
    // Cần SELECT cả file_name (tên gốc) và unique_file_name (tên lưu trên đĩa)
    if ($user_role === 'admin' || $user_role === 'viewer') {
        $stmt = $pdo->prepare("SELECT file_name, unique_file_name FROM vmlbooking_documents WHERE id = ?");
        $stmt->execute([$document_id]);
    } else {
        // Agency chỉ có thể tải tài liệu của chính họ
        $stmt = $pdo->prepare("SELECT file_name, unique_file_name FROM vmlbooking_documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$document_id, $user_id]);
    }
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Tài liệu không tồn tại hoặc bạn không có quyền truy cập.</p>";
        header("Location: index.php");
        exit;
    }

    // ĐIỂM SỬA ĐỔI QUAN TRỌNG: SỬ DỤNG unique_file_name ĐỂ XÂY DỰNG ĐƯỜNG DẪN
    $file_on_disk_name = $document['unique_file_name'];
    $file_display_name = $document['file_name']; // Tên file hiển thị cho người dùng (tên gốc)

    $file_path = __DIR__ . '/downloads/' . $file_on_disk_name; // Sửa từ $document['file_name'] sang $file_on_disk_name

    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        // Sử dụng tên file gốc để hiển thị khi người dùng tải về
        header('Content-Disposition: attachment; filename="' . basename($file_display_name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $_SESSION['booking_message'] = "<p class='message error'>❌ File không tìm thấy trên server. Đường dẫn: " . htmlspecialchars($file_path) . "</p>"; // Thêm debug info
        header("Location: index.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['booking_message'] = "<p class='message error'>Lỗi hệ thống: " . htmlspecialchars($e->getMessage()) . "</p>";
    header("Location: index.php");
    exit;
}
?>