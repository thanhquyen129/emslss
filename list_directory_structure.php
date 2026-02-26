<?php
// public_html/vietma/booking/list_directory_structure.php
// Hoặc bất kỳ thư mục nào bạn muốn đặt file này để chạy

// Bật báo cáo lỗi để dễ dàng gỡ lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Hàm này liệt kê cấu trúc cây thư mục và các file bên trong một thư mục gốc.
 *
 * @param string $path Đường dẫn đến thư mục gốc bạn muốn liệt kê.
 * @return string HTML đã định dạng chứa cấu trúc cây thư mục.
 */
function listDirectoryStructure($path) {
    // Kiểm tra xem đường dẫn có tồn tại và là một thư mục không
    if (!is_dir($path)) {
        return "<p style='color: red;'>Lỗi: Đường dẫn không hợp lệ hoặc không phải là thư mục: " . htmlspecialchars($path) . "</p>";
    }

    $output = "<pre style='background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 8px; font-family: monospace; white-space: pre-wrap; word-wrap: break-word;'>";
    $output .= "Cấu trúc thư mục cho: " . htmlspecialchars($path) . "\n\n";

    try {
        // Tạo một RecursiveDirectoryIterator để duyệt qua các thư mục con
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Lấy cấp độ lồng nhau hiện tại
            $depth = $iterator->getDepth();
            // Tạo thụt lề dựa trên cấp độ
            $indent = str_repeat("    ", $depth); // 4 dấu cách cho mỗi cấp

            if ($item->isDir()) {
                // Nếu là thư mục, thêm dấu gạch chéo và màu xanh
                $output .= $indent . "<span style='color: #007bff; font-weight: bold;'>&#x1F4C1; " . htmlspecialchars($item->getBasename()) . "/</span>\n";
            } else {
                // Nếu là file, thêm biểu tượng và màu đen
                $output .= $indent . "<span style='color: #333;'>&#x1F4C4; " . htmlspecialchars($item->getBasename()) . "</span>\n";
            }
        }
    } catch (Exception $e) {
        $output .= "<p style='color: red;'>Lỗi khi duyệt thư mục: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    $output .= "</pre>";
    return $output;
}

// --- Cấu hình đường dẫn thư mục gốc ---
// Đặt đường dẫn đến thư mục mà bạn muốn liệt kê.
// Ví dụ:
// __DIR__ là thư mục hiện tại của file list_directory_structure.php
// Nếu file này nằm trong vietma/booking/, và bạn muốn liệt kê thư mục 'vietma', thì đường dẫn là:
// $baseDirectory = __DIR__ . '/../';
// Nếu bạn muốn liệt kê thư mục 'booking' (nơi file này đang nằm), thì đường dẫn là:
// $baseDirectory = __DIR__;
// Nếu bạn muốn liệt kê thư mục 'vietma' (từ hình ảnh bạn gửi, nó là thư mục gốc của dự án web của bạn)
// Và file này (list_directory_structure.php) nằm trong vietma/booking/, thì đường dẫn sẽ là:
$baseDirectory = __DIR__ . '/../'; // Lùi ra khỏi 'booking' để đến 'vietma'

// --- Hiển thị cấu trúc thư mục ---
echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liệt Kê Cấu Trúc Thư Mục</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f0f0; }
        .container { background-color: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        h1 { color: #333; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Liệt Kê Cấu Trúc Thư Mục</h1>
        ' . listDirectoryStructure($baseDirectory) . '
    </div>
</body>
</html>';
?>