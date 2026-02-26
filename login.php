<?php
session_start(); // Luôn bắt đầu session đầu tiên
// Bật báo lỗi để dễ debug trong môi trường phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php"; // Chứa hàm sanitize_input() nếu cần

$error_message = ''; // Biến để lưu thông báo lỗi
$username_input = ''; // Biến để giữ lại username đã nhập

// Nếu người dùng đã đăng nhập, chuyển hướng đến index.php ngay lập tức
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Khởi tạo biến $pdo ở phạm vi toàn cục trước khi khối if ($_SERVER["REQUEST_METHOD"] == "POST")
// Điều này đảm bảo $pdo có sẵn cho khối try-catch bên dưới
$pdo = null; // Khởi tạo với giá trị null

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form, sử dụng ?? để tránh lỗi nếu key không tồn tại
    // Sử dụng sanitize_input nếu bạn muốn làm sạch dữ liệu trước khi xử lý
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $username_input = htmlspecialchars($username); // Giữ lại giá trị username để hiển thị lại

    // Kiểm tra các trường bắt buộc
    if (empty($username) || empty($password)) {
        $error_message = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
        try {
            // Thiết lập kết nối PDO (nếu chưa có)
            if ($pdo === null) {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }

            // Sử dụng prepared statement để tránh SQL Injection
            // Đã đổi agency_name thành company_name
            $stmt = $pdo->prepare("SELECT id, username, password, company_name, is_active, role FROM vmlbooking_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch dưới dạng mảng kết hợp

            // Kiểm tra xem có người dùng không, tài khoản có hoạt động không và mật khẩu có đúng không
            if ($user) {
                // Kiểm tra trạng thái is_active
                if ($user['is_active'] == 0) {
                    $error_message = "Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.";
                } elseif (password_verify($password, $user['password'])) {
                    // Đăng nhập thành công, lưu thông tin vào session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['company_name'] = $user['company_name']; // Đã đổi tên thành company_name
                    $_SESSION['user_role'] = $user['role']; // Lưu vai trò người dùng
                    $_SESSION['is_active'] = $user['is_active']; // Lưu trạng thái hoạt động

                    // Chuyển hướng đến trang chính
                    header("Location: index.php");
                    exit; // Quan trọng: dừng script sau khi chuyển hướng
                } else {
                    $error_message = "Sai tên đăng nhập hoặc mật khẩu.";
                }
            } else {
                $error_message = "Sai tên đăng nhập hoặc mật khẩu.";
            }
        } catch (PDOException $e) {
            $error_message = "Đã xảy ra lỗi hệ thống: " . $e->getMessage();
            // Trong môi trường dev, bạn có thể echo $e->getMessage();
            error_log("Lỗi đăng nhập: " . $e->getMessage()); // Ghi log lỗi chi tiết hơn
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập đại lý - VML Booking</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* CSS riêng cho trang đăng nhập, ghi đè hoặc bổ sung từ style.css nếu cần */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #e9ecef; /* Màu nền nhẹ hơn */
        }
        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 380px;
            text-align: center;
            box-sizing: border-box; /* Đảm bảo padding không làm tăng width */
        }
        .login-container h2 {
            margin-bottom: 30px;
            color: #0056b3;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
            font-size: 15px;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box; /* Đảm bảo padding không làm tăng width */
        }
        .form-group input[type="submit"] {
            background-color: #28a745; /* Màu xanh lá cây */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }
        .form-group input[type="submit"]:hover {
            background-color: #218838;
        }
        .error-message {
            margin-top: 15px;
            color: #dc3545; /* Màu đỏ đậm hơn */
            font-weight: bold;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Đăng nhập đại lý</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Tên đăng nhập:</label>
                <input type="text" id="username" name="username" value="<?php echo $username_input; ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <input type="submit" value="Đăng nhập">
            </div>
        </form>
    </div>
</body>
</html>