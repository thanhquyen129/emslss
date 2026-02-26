<?php
session_start();
ini_set('display_errors', 0); // Tắt hiển thị lỗi trên trình duyệt cho môi trường production
error_reporting(E_ALL); // Ghi log tất cả lỗi

// Bao gồm file cấu hình database của bạn
require_once 'includes/config.php'; 
require_once 'includes/functions.php'; // Nếu bạn có hàm sanitize_input hay các hàm tiện ích khác

// --- Kiểm tra phân quyền truy cập ---
// Chỉ cho phép admin tải xuống backup
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '') !== 'admin') {
    die("Bạn không có quyền truy cập chức năng này.");
}

// Tên file backup sẽ được tạo
$backup_file_name = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';

// Thiết lập header để trình duyệt tải file xuống
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $backup_file_name . '"');

try {
    // Lấy danh sách tất cả các bảng trong database
    $stmt_tables = $pdo->query('SHOW TABLES');
    $tables = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);

    $output = "";

    foreach ($tables as $table) {
        // Lấy cấu trúc bảng (CREATE TABLE statement)
        $stmt_create = $pdo->query("SHOW CREATE TABLE `" . $table . "`");
        $row_create = $stmt_create->fetch(PDO::FETCH_NUM);
        $output .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
        $output .= $row_create[1] . ";\n\n";

        // Lấy dữ liệu của bảng (INSERT statements)
        $stmt_data = $pdo->query("SELECT * FROM `" . $table . "`");
        $columns = [];
        for ($i = 0; $i < $stmt_data->columnCount(); $i++) {
            $col_meta = $stmt_data->getColumnMeta($i);
            $columns[] = "`" . $col_meta['name'] . "`";
        }
        $column_names = implode(", ", $columns);

        while ($row_data = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
            $output .= "INSERT INTO `" . $table . "` (" . $column_names . ") VALUES (";
            $values = [];
            foreach ($row_data as $key => $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    // Escape các ký tự đặc biệt và bọc trong dấu nháy đơn
                    $values[] = $pdo->quote($value);
                }
            }
            $output .= implode(", ", $values) . ");\n";
        }
        $output .= "\n"; // Thêm dòng trống giữa các bảng cho dễ đọc
    }

    // In ra nội dung backup
    echo $output;

} catch (PDOException $e) {
    // Ghi log lỗi thay vì hiển thị trực tiếp cho người dùng
    error_log("Lỗi khi backup database: " . $e->getMessage());
    // Xóa bất kỳ output nào đã được gửi trước đó để tránh lỗi header
    ob_clean(); 
    // Thiết lập lại header để thông báo lỗi thay vì tải file
    header('Content-Type: text/plain');
    header('Content-disposition: inline'); // Hiển thị lỗi trên trình duyệt
    die("Đã xảy ra lỗi trong quá trình tạo bản sao lưu. Vui lòng kiểm tra log lỗi.");
} catch (Exception $e) {
    error_log("Lỗi hệ thống khi backup database: " . $e->getMessage());
    ob_clean();
    header('Content-Type: text/plain');
    header('Content-disposition: inline');
    die("Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.");
}

exit; // Đảm bảo không có mã nào khác chạy sau khi file được gửi
?>