<?php
/**
 * Công cụ thực thi lệnh SQL đơn giản qua giao diện web.
 * Hỗ trợ các lệnh SELECT, INSERT, UPDATE, DELETE.
 * Tác giả: Gemini (Google)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// *******************************************************************
// 1. CẤU HÌNH KẾT NỐI DATABASE (BẠN CẦN THAY ĐỔI CÁC GIÁ TRỊ NÀY)
// *******************************************************************
$servername = "localhost";    // Địa chỉ host
$username = "posacb_db";   // Tên người dùng database
$password = "837e7d126f1088"; // Mật khẩu database
$dbname = "posacb_db";     // Tên database
// *******************************************************************

$conn = null;
$error_message = "";
$success_message = "";
$results_html = "";

// 2. XỬ LÝ KẾT NỐI VÀ THỰC THI QUERY
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["sql_query"])) {
    $sql_query = trim($_POST["sql_query"]);
    
    // Tạo kết nối
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error_message = "Lỗi Kết Nối Database: " . $conn->connect_error;
    } else if (empty($sql_query)) {
        $error_message = "Vui lòng nhập câu lệnh SQL.";
    } else {
        // Xử lý câu lệnh SQL
        $start_time = microtime(true);
//        $result = $conn->query($sql_query);


$queries = array_filter(array_map('trim', explode(';', $sql_query)));

$results_html .= "<div class='space-y-6'>";

foreach ($queries as $index => $query) {

    if(empty($query)) continue;

    $start_time = microtime(true);
    $result = $conn->query($query);
    $end_time = microtime(true);

    $execution_time = round(($end_time - $start_time) * 1000, 2);

    $results_html .= "<div class='border rounded-lg p-4 shadow bg-white'>";
    $results_html .= "<div class='mb-2 text-sm text-gray-500'>Query #".($index+1)."</div>";
    $results_html .= "<pre class='bg-gray-100 p-2 rounded text-xs overflow-x-auto'>".htmlspecialchars($query)."</pre>";

    if ($result === TRUE) {

        $affected_rows = $conn->affected_rows;

        $results_html .= "<div class='text-green-600 font-medium mt-2'>✅ OK - $affected_rows dòng | {$execution_time} ms</div>";

    } elseif ($result === FALSE) {

        $results_html .= "<div class='text-red-600 font-medium mt-2'>❌ ERROR: ".$conn->error."</div>";

    } else {

        if ($result->num_rows > 0) {

            $results_html .= "<div class='mt-3 overflow-x-auto'>";
            $results_html .= "<table class='min-w-full border text-sm'>";

            // header
            $results_html .= "<tr class='bg-gray-200'>";
            $fields = [];
            while ($field = $result->fetch_field()) {
                $fields[] = $field->name;
                $results_html .= "<th class='px-3 py-2 border'>".$field->name."</th>";
            }
            $results_html .= "</tr>";

            // data
            while ($row = $result->fetch_assoc()) {
                $results_html .= "<tr>";
                foreach ($fields as $f) {
                    $results_html .= "<td class='px-3 py-2 border'>".htmlspecialchars($row[$f] ?? '')."</td>";
                }
                $results_html .= "</tr>";
            }

            $results_html .= "</table></div>";

        } else {
            $results_html .= "<div class='text-gray-500 mt-2'>No rows</div>";
        }

        $result->free();
    }

    $results_html .= "</div>";
}

$results_html .= "</div>";



        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2); // ms

        if ($result === TRUE) {
            // INSERT, UPDATE, DELETE thành công
            $affected_rows = $conn->affected_rows;
            $success_message = "✅ Lệnh SQL thực thi thành công. (**" . $affected_rows . "** dòng bị ảnh hưởng) | Thời gian: " . $execution_time . "ms";
        } elseif ($result === FALSE) {
            // Lỗi cú pháp hoặc lỗi thực thi
            $error_message = "❌ Lỗi thực thi SQL: " . $conn->error . "<br>Lệnh SQL: **" . htmlspecialchars($sql_query) . "**";
        } else {
            // SELECT thành công (MySQLi_Result Object)
            if ($result->num_rows > 0) {
                // Xây dựng bảng kết quả
                $results_html .= "<h3 class='text-xl font-semibold mb-3'>Kết quả (".$result->num_rows." dòng)</h3>";
                $results_html .= "<div class='overflow-x-auto'>";
                $results_html .= "<table class='min-w-full divide-y divide-gray-200 shadow-lg rounded-xl'>";
                
                // Tiêu đề cột
                $results_html .= "<thead class='bg-gray-50'>";
                $results_html .= "<tr>";
                $field_names = [];
                while ($field = $result->fetch_field()) {
                    $results_html .= "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>". htmlspecialchars($field->name) . "</th>";
                    $field_names[] = $field->name;
                }
                $results_html .= "</tr>";
                $results_html .= "</thead>";

                // Dữ liệu
                $results_html .= "<tbody class='bg-white divide-y divide-gray-200'>";
                while($row = $result->fetch_assoc()) {
                    $results_html .= "<tr class='hover:bg-indigo-50 transition duration-150'>";
                    foreach($field_names as $name) {
                         // Sử dụng nl2br và htmlspecialchars để hiển thị nội dung an toàn
                        $display_value = htmlspecialchars($row[$name] ?? 'NULL');
                        $results_html .= "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-700'>". nl2br($display_value) . "</td>";
                    }
                    $results_html .= "</tr>";
                }
                $results_html .= "</tbody>";
                $results_html .= "</table>";
                $results_html .= "</div>";
                
                $success_message = "✅ Lệnh SQL thực thi thành công. (Tìm thấy **".$result->num_rows."** dòng) | Thời gian: " . $execution_time . "ms";
            } else {
                $success_message = "✅ Lệnh SQL thực thi thành công. (Không tìm thấy dòng nào) | Thời gian: " . $execution_time . "ms";
            }
            $result->free(); // Giải phóng bộ nhớ
        }
    }
}

// Đóng kết nối nếu nó được mở thành công
if ($conn !== null && !$conn->connect_error) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Công Cụ Thực Thi SQL Đơn Giản</title>
    <!-- Tải Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Đảm bảo font Inter được sử dụng */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* light gray background */
        }
        /* Custom scrollbar for better look */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-thumb {
            background: #9ca3af;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #e5e7eb;
        }
        textarea {
            resize: vertical;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <!--header class="mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Trình Thực Thi SQL Đơn Giản</h1>
            <p class="text-lg text-gray-600">Nhập câu lệnh SQL của bạn vào ô bên dưới và nhấn **Thực Thi**.</p>
        </header-->

        <!-- CẢNH BÁO AN TOÀN -->
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6" role="alert">
 <!--            <p class="font-bold">⚠️ CẢNH BÁO BẢO MẬT</p>
            <p class="text-sm mt-1">Công cụ này có khả năng thực thi **MỌI lệnh SQL**. KHÔNG sử dụng trong môi trường Production mà không có xác thực và bảo mật nghiêm ngặt. Bất kỳ lỗi nào cũng có thể làm hỏng dữ liệu.</p>-->
            <p class="text-sm mt-1">Xem cấu trúc bảng: DESCRIBE ten_bang.</p>
            <p class="text-sm mt-1">SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA 'tên_database_của_bạn';</p>
            <p class="text-sm mt-1">UPDATE users SET username = LOWER(REPLACE(hovaten, ' ', ''));</p>
            <p class="text-sm mt-1">UPDATE emslss_users SET username = CONCAT(LOWER('dis'),'_',LOWER(REPLACE(full_name, ' ', ''))) where role = 'dispatcher'</p>
        </div>

        <!-- HIỂN THỊ THÔNG BÁO LỖI HOẶC THÀNH CÔNG -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6 shadow-md font-medium" role="alert">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-500 text-white p-4 rounded-lg mb-6 shadow-md font-medium" role="alert">
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <!-- FORM NHẬP SQL -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-2xl">
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="sql_query" class="block text-lg font-medium text-gray-700 mb-2">Câu Lệnh SQL</label>
                    <textarea 
                        id="sql_query" 
                        name="sql_query" 
                        rows="10" 
                        class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-4 text-gray-900 font-mono focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                        placeholder="Ví dụ: SELECT * FROM users LIMIT 10; hoặc UPDATE users SET status = 0 WHERE id = 1;"
                    ><?= isset($_POST["sql_query"]) ? htmlspecialchars($_POST["sql_query"]) : '' ?></textarea>
                </div>
                
                <button 
                    type="submit" 
                    class="w-full md:w-auto px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out transform hover:scale-[1.02]"
                >
                    <span class="mr-2">🚀</span> Thực Thi SQL
                </button>
            </form>
        </div>

        <!-- KHU VỰC HIỂN THỊ KẾT QUẢ -->
        <?php if (!empty($results_html)): ?>
            <div class="mt-8 bg-white p-6 md:p-8 rounded-xl shadow-2xl">
                <?= $results_html ?>
            </div>
        <?php endif; ?>

        <!-- CHÂN TRANG THÔNG TIN KẾT NỐI -->
        <footer class="mt-12 text-center text-sm text-gray-500">
            <p>Đang kết nối đến: Host: **<?= htmlspecialchars($servername) ?>** | DB: **<?= htmlspecialchars($dbname) ?>**</p>
            <p>Vui lòng cập nhật các biến `$servername`, `$username`, `$password`, `$dbname` trong mã PHP để sử dụng.</p>
        </footer>
    </div>
</body>
</html>