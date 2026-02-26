<?php
// public_html/vietma/booking/track_booking.php

// Bắt đầu phiên làm việc để truy cập các biến session (vẫn cần để hiển thị thông báo lỗi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Bật báo cáo lỗi để dễ dàng gỡ lỗi trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình cơ sở dữ liệu
require_once 'includes/config.php';
// Bao gồm file functions.php chứa các hàm tiện ích
require_once 'includes/functions.php';

// --- Hủy bỏ yêu cầu đăng nhập ---
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserRole = $_SESSION['user_role'] ?? 'guest';

$bookingDetails = null;
$statusLogs = [];
$referenceCodeSearch = '';

if (isset($_GET['reference_code']) && !empty($_GET['reference_code'])) {
    $referenceCodeSearch = sanitize_input($_GET['reference_code']);
    try {
        $sql = "SELECT * FROM vmlbooking_orders WHERE reference_code = :reference_code";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':reference_code', $referenceCodeSearch, PDO::PARAM_STR);
        $stmt->execute();
        $bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingDetails) {
            $_SESSION['error_message'] = "Không tìm thấy thông tin booking với Mã tham chiếu: " . htmlspecialchars($referenceCodeSearch) . ". Vui lòng kiểm tra lại mã.";
        } else {
            $stmtLogs = $pdo->prepare("
                SELECT
                    vsl.*,
                    vu.username AS created_by_username
                FROM
                    vmlbooking_status_logs vsl
                LEFT JOIN
                    vmlbooking_users vu ON vsl.created_by_user_id = vu.id
                WHERE
                    vsl.order_id = :order_id
                ORDER BY
                    vsl.created_at DESC
            ");
            $stmtLogs->bindParam(':order_id', $bookingDetails['id'], PDO::PARAM_INT);
            $stmtLogs->execute();
            $statusLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Lỗi cơ sở dữ liệu khi tải chi tiết booking: " . $e->getMessage();
    }
}

$pageTitle = "Theo dõi Trạng thái Booking";
require_once 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Trạng thái Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            max-width: 900px;
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
        .detail-item {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            width: 180px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #2d3748;
            flex-grow: 1;
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
        .message.error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #ef4444;
        }
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #34d399;
        }
        .message.info {
            background-color: #e0f2fe;
            color: #1e40af;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #60a5fa;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .status-pending { background-color: #f6ad55; }
        .status-confirmed { background-color: #4299e1; }
        .status-in-transit { background-color: #38a169; }
        .status-departed { background-color: #008000; }
        .status-arrived { background-color: #805ad5; }
        .status-delivered { background-color: #006400; }
        .status-holded { background-color: #e53e3e; }
        .status-cancelled { background-color: #c53030; }

        .status-logs table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .status-logs th, .status-logs td {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .status-logs th {
            background-color: #edf2f7;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .status-logs tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .status-logs tr:hover {
            background-color: #ebf4ff;
        }
        .status-logs th:nth-child(1),
        .status-logs td:nth-child(1) {
            width: 22%;
            min-width: 150px;
        }
        .status-logs th:nth-child(2),
        .status-logs td:nth-child(2) {
            width: 18%;
            text-align: center;
            min-width: 120px;
        }
        .status-logs th:nth-child(3),
        .status-logs td:nth-child(3) {
            width: 60%;
            min-width: 200px;
        }
    </style>
</head>
<body>
	<div class="container">
    <div class="search-form-section bg-gray-100 p-6 rounded-lg shadow-inner mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Theo dõi Booking</h2>
        <form method="GET" action="track_booking.php" class="flex flex-col sm:flex-row gap-4 items-center">
            <div class="flex-grow w-full sm:w-auto">
                <label for="reference_code" class="sr-only">Nhập Mã Bill:</label>
                <input type="text" id="reference_code" name="reference_code"
                       class="form-input rounded-md w-full"
                       placeholder="Nhập Mã Bill"
                       value="<?= htmlspecialchars($referenceCodeSearch) ?>" required>
            </div>
            <button type="submit" class="btn-submit bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-md w-full sm:w-auto">
                Tracking
            </button>
        </form>
    </div>

    <?php if ($bookingDetails): ?>
        <div class="booking-details mb-8 p-6 bg-gray-50 rounded-lg border border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b pb-3">Chi tiết Booking</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="detail-item">
                    <span class="detail-label">Mã bill:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['reference_code'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Trạng thái hiện tại:</span>
                    <span class="detail-value">
                        <?php
                            $statusClass = 'status-' . strtolower(str_replace(' ', '-', $bookingDetails['status']));
                            echo '<span class="status-badge ' . $statusClass . '">' . htmlspecialchars($bookingDetails['status'] ?? 'N/A') . '</span>';
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Quốc gia người gửi:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['shipper_country'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Quốc gia người nhận:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['receiver_country'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tên người gửi:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['shipper_agency_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tên người nhận:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['receiver_company'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ngày tạo Booking:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['created_at'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cập nhật Booking cuối:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['updated_at'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Phần hiển thị lịch sử trạng thái -->
        <div class="status-logs mt-10 pt-6 border-t border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b pb-3">Lịch sử Trạng thái</h2>
            <?php if (!empty($statusLogs)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Thời gian sự kiện</th>
                                <th>Trạng thái</th>
                                <th>Chi tiết lộ trình</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td>
                                        <?php
                                            $logStatusClass = 'status-' . strtolower(str_replace(' ', '-', $log['status_type']));
                                            echo '<span class="status-badge ' . $logStatusClass . '">' . htmlspecialchars($log['status_type']) . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($log['status_text'])); ?></td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-4">Chưa có lịch sử trạng thái nào cho booking này.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="text-center p-8 bg-white rounded-lg shadow-md">
            <p class="text-gray-700 text-lg mb-4">Vui lòng nhập Mã Bill hàng vào ô tìm kiếm ở trên để xem chi tiết trạng thái.</p>
            <p class="text-gray-500 text-sm">Bạn có thể tìm thấy Mã Bill trong email xác nhận hoặc trên phần Quản lý Booking của mình.</p>
        </div>
    <?php endif; ?>
    </div>
</body>
</html>
<?php
// Bao gồm footer.php (chứa các thẻ đóng HTML)
require_once 'includes/footer.php';
?>