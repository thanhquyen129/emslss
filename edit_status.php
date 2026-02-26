<?php
session_start(); // Bắt đầu phiên làm việc

// Bật báo cáo lỗi để dễ dàng gỡ lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình cơ sở dữ liệu
// Đường dẫn đã được điều chỉnh dựa trên cấu trúc thư mục bạn cung cấp: includes/config.php
require_once 'includes/config.php';
// Bao gồm file functions.php để sử dụng hàm redirect()
require_once 'includes/functions.php'; // Thêm dòng này nếu chưa có

// --- Xử lý xác thực và phân quyền truy cập ---
if (!isset($_SESSION['user_id'])) {
    // Đường dẫn đã được điều chỉnh: login.php nằm cùng cấp với file này
    redirect('login.php', 'error', 'Bạn cần đăng nhập để truy cập trang này.');
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['user_role'] ?? 'guest';
$allowedRoles = ['admin', 'accounting'];

if (!in_array($currentUserRole, $allowedRoles)) {
    // Đường dẫn đã được điều chỉnh: index.php (dashboard) nằm cùng cấp với file này
    redirect('index.php', 'error', 'Bạn không có quyền truy cập trang này.');
}
// --- Kết thúc xử lý xác thực và phân quyền ---

$statusLog = null; // Biến để lưu trữ thông tin log trạng thái cần chỉnh sửa
$errorMessage = ''; // Biến để lưu trữ thông báo lỗi
$bookingId = null; // Biến để lưu trữ ID của booking liên quan

// Kiểm tra xem có ID của log trạng thái và ID booking được truyền qua URL không
if (isset($_GET['id']) && !empty($_GET['id']) && isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $statusLogId = $_GET['id']; // ID của log trạng thái cần chỉnh sửa
    $bookingId = $_GET['order_id']; // ID của booking liên quan

    try {
        // Chuẩn bị câu lệnh SQL để lấy thông tin chi tiết của log trạng thái
        $stmt = $pdo->prepare("SELECT * FROM vmlbooking_status_logs WHERE id = :id AND order_id = :order_id");
        $stmt->bindParam(':id', $statusLogId, PDO::PARAM_INT);
        $stmt->bindParam(':order_id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();

        // Lấy kết quả truy vấn
        $statusLog = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra nếu không tìm thấy log trạng thái với ID đã cho
        if (!$statusLog) {
            // Sử dụng hàm redirect() để chuyển hướng và truyền thông báo lỗi
            redirect('update_booking_status.php?id=' . htmlspecialchars($bookingId), 'error', "Không tìm thấy log trạng thái với ID: " . htmlspecialchars($statusLogId) . " cho booking ID: " . htmlspecialchars($bookingId));
        }

    } catch (PDOException $e) {
        // Xử lý lỗi cơ sở dữ liệu và chuyển hướng
        redirect('update_booking_status.php?id=' . htmlspecialchars($bookingId), 'error', "Lỗi cơ sở dữ liệu khi tải log trạng thái: " . $e->getMessage());
    }
} else {
    // Thông báo lỗi và chuyển hướng nếu không có ID log hoặc ID booking
    redirect('index.php', 'error', "Vui lòng cung cấp ID log trạng thái và ID booking để chỉnh sửa.");
}

// Lấy tên người dùng hiện tại để hiển thị
$currentUserName = 'Người dùng không xác định';
try {
    $stmtUser = $pdo->prepare("SELECT username FROM vmlbooking_users WHERE id = :id");
    $stmtUser->bindParam(':id', $currentUserId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userResult = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userResult) {
        $currentUserName = $userResult['username'];
    }
} catch (PDOException $e) {
    // Nếu có lỗi khi tải thông tin người dùng, ghi vào session
    $_SESSION['error_message'] = ($_SESSION['error_message'] ?? '') . " Lỗi khi tải thông tin người dùng hiện tại: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Log Trạng thái Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        h1 {
            color: #1a202c;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }
        .form-select, .form-textarea, .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 1rem;
            color: #2d3748;
            background-color: #edf2f7;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-select:focus, .form-textarea:focus, .form-input:focus {
            outline: none;
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: #4299e1;
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background-color 0.3s ease, transform 0.1s ease;
            box-shadow: 0 4px 10px rgba(66, 153, 225, 0.3);
        }
        .btn-submit:hover {
            background-color: #3182ce;
            transform: translateY(-2px);
        }
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background-color: #a0aec0; /* Gray */
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin-top: 20px;
            text-align: center;
        }
        .btn-back:hover {
            background-color: #718096;
        }
        /* Thông báo lỗi */
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sửa Log Trạng thái Booking</h1>

        <?php
        // Kiểm tra và hiển thị thông báo lỗi từ session (nếu có)
        if (isset($_SESSION['error_message'])) {
            echo '<div class="error-message">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']); // Xóa thông báo sau khi hiển thị
        }
        ?>

        <?php if ($errorMessage): // Nếu có lỗi trong quá trình tải dữ liệu log ?>
            <div class="error-message">
                <?php echo $errorMessage; ?>
            </div>
            <div class="text-center">
                <a href="update_booking_status.php<?php echo $bookingId ? '?id=' . htmlspecialchars($bookingId) : ''; ?>" class="btn-back">Quay lại</a>
            </div>
        <?php elseif ($statusLog): ?>
            <form method="POST" action="edit_status_process.php">
                <input type="hidden" name="status_log_id" value="<?php echo htmlspecialchars($statusLog['id']); ?>">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($bookingId); ?>">
                <input type="hidden" name="current_user_id_hidden" value="<?php echo htmlspecialchars($currentUserId); ?>">

                <div class="form-group">
                    <label class="form-label">Người thực hiện:</label>
                    <span class="detail-value font-bold text-blue-700"><?php echo htmlspecialchars($currentUserName); ?> (ID: <?php echo htmlspecialchars($currentUserId); ?>)</span>
                    <p class="text-sm text-gray-500 mt-1">
                        *Thông tin người dùng được lấy tự động từ phiên đăng nhập.
                    </p>
                </div>

                <div class="form-group">
                    <label for="event_datetime" class="form-label">Ngày giờ xảy ra sự kiện:</label>
                    <!-- Định dạng lại datetime từ DB để phù hợp với input type="datetime-local" -->
                    <input type="datetime-local" name="event_datetime" id="event_datetime" class="form-input rounded-md"
                           value="<?php echo date('Y-m-d\TH:i', strtotime($statusLog['created_at'])); ?>">
                    <p class="text-sm text-gray-500 mt-1">
                        Thời gian này sẽ được dùng làm cột 'created_at' trong database.
                    </p>
                </div>

                <div class="form-group">
                    <label for="detailed_status_type" class="form-label">Loại Trạng thái:</label>
                    <select name="detailed_status_type" id="detailed_status_type" class="form-select rounded-md">
                        <?php
                        $statusTypes = [
                            'pending' => 'Pending (Đang chờ)',
                            'confirmed' => 'Confirmed (Đã xác nhận)',
                            'in transit' => 'In Transit (Đang vận chuyển)',
                            'departed' => 'Departed (Đã khởi hành)',
                            'arrived' => 'Arrived (Đã đến)',
                            'delivered' => 'Delivered (Đã giao)',
                            'holded' => 'Holded (Đang giữ)',
                            'cancelled' => 'Cancelled (Đã hủy)'
                        ];
                        foreach ($statusTypes as $value => $label) {
                            $selected = ($statusLog['status_type'] == $value) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="detailed_status_text" class="form-label">Ghi chú Trạng thái:</label>
                    <textarea name="detailed_status_text" id="detailed_status_text" rows="5" class="form-textarea rounded-md" placeholder="Ví dụ: Shipment departed from SGN at 10:00 AM"><?php echo htmlspecialchars($statusLog['status_text']); ?></textarea>
                </div>

                <button type="submit" name="edit_status_log" class="btn-submit rounded-md bg-blue-500 hover:bg-blue-600">Cập nhật Trạng thái</button>
                <a href="update_booking_status.php?id=<?php echo htmlspecialchars($bookingId); ?>" class="btn-back rounded-md mt-4">Hủy và Quay lại</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>