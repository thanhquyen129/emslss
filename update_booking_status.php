<?php
// public_html/vietma/booking/update_booking_status.php

// Bắt đầu phiên làm việc để truy cập các biến session
// Đảm bảo dòng này nằm ở đầu file, trước bất kỳ output HTML nào
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Bật báo cáo lỗi để dễ dàng gỡ lỗi trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file cấu hình cơ sở dữ liệu để kết nối đến database
// Đường dẫn đã được điều chỉnh dựa trên cấu trúc thư mục bạn cung cấp: includes/config.php
require_once 'includes/config.php';
// Bao gồm file functions.php chứa hàm updateMainBookingStatus và hàm redirect()
// Đường dẫn đã được điều chỉnh dựa trên cấu trúc thư mục bạn cung cấp: includes/functions.php
require_once 'includes/functions.php';

// --- Xử lý xác thực và phân quyền truy cập ---
// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (!isset($_SESSION['user_id'])) {
    // Nếu người dùng chưa đăng nhập, chuyển hướng họ đến trang đăng nhập
    // Đường dẫn đã được điều chỉnh dựa trên cấu trúc thư mục bạn cung cấp: login.php nằm cùng cấp với file này
    redirect('login.php', 'error', 'Bạn cần đăng nhập để truy cập trang này.');
}

// Lấy ID người dùng hiện tại từ session
$currentUserId = $_SESSION['user_id'];
// Lấy vai trò của người dùng hiện tại từ session, mặc định là 'guest' nếu không có
$currentUserRole = $_SESSION['user_role'] ?? 'guest';

// Định nghĩa các vai trò được phép truy cập trang này
$allowedRoles = ['admin', 'accounting']; // Giả định rằng chỉ 'admin' và 'accounting' mới có quyền

// Kiểm tra xem vai trò của người dùng hiện tại có nằm trong danh sách các vai trò được phép không
if (!in_array($currentUserRole, $allowedRoles)) {
    // Nếu người dùng không có vai trò hợp lệ, chuyển hướng họ hoặc hiển thị thông báo lỗi
    // Đường dẫn đã được điều chỉnh dựa trên cấu trúc thư mục bạn cung cấp: index.php (dashboard) nằm cùng cấp với file này
    redirect('index.php', 'error', 'Bạn không có quyền truy cập trang này.');
}
// --- Kết thúc xử lý xác thực và phân quyền ---

$bookingDetails = null; // Biến để lưu trữ thông tin chi tiết của booking được truy vấn
$statusLogs = []; // Biến để lưu trữ lịch sử các thay đổi trạng thái của booking
$bookingId = null; // Biến để lưu trữ ID booking thực tế từ DB (sau khi tìm kiếm)

// Lấy tên người dùng hiện tại để hiển thị trên giao diện
$currentUserName = 'Người dùng không xác định';
try {
    // Đã sửa user_name thành username theo yêu cầu của bạn
    $stmtUser = $pdo->prepare("SELECT username FROM vmlbooking_users WHERE id = :id");
    $stmtUser->bindParam(':id', $currentUserId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userResult = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userResult) {
        $currentUserName = $userResult['username'];
    }
} catch (PDOException $e) {
    // Lưu lỗi vào session và hiển thị trực tiếp trên trang nếu lỗi tải thông tin người dùng
    $_SESSION['error_message'] = "Lỗi khi tải thông tin người dùng hiện tại: " . $e->getMessage();
}

// --- Xử lý logic lấy ID booking từ URL hoặc form tìm kiếm theo MÃ THAM CHIẾU ---
$bookingIdentifier = null; // Sẽ chứa ID hoặc Mã tham chiếu
$isSearchById = false; // Cờ để xác định loại tìm kiếm

// Ưu tiên tìm kiếm bằng mã tham chiếu từ ô nhập liệu mới
if (isset($_GET['booking_reference_code_search']) && !empty($_GET['booking_reference_code_search'])) {
    $bookingIdentifier = sanitize_input($_GET['booking_reference_code_search']); // Làm sạch chuỗi
    $isSearchById = false; // Đây là mã tham chiếu
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    // Nếu không có mã tham chiếu, kiểm tra tham số 'id' cũ (chỉ chấp nhận số nguyên)
    $tempId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($tempId) {
        $bookingIdentifier = $tempId;
        $isSearchById = true; // Đây là ID
    }
}

// Nếu có một định danh hợp lệ (ID hoặc Mã tham chiếu), tiến hành tải dữ liệu
if ($bookingIdentifier) {
    try {
        if ($isSearchById) {
            // Tìm kiếm bằng ID
            $stmt = $pdo->prepare("SELECT * FROM vmlbooking_orders WHERE id = :identifier");
            $stmt->bindParam(':identifier', $bookingIdentifier, PDO::PARAM_INT);
        } else {
            // Tìm kiếm bằng Mã tham chiếu
            $stmt = $pdo->prepare("SELECT * FROM vmlbooking_orders WHERE reference_code = :identifier");
            $stmt->bindParam(':identifier', $bookingIdentifier, PDO::PARAM_STR);
        }
        $stmt->execute();
        $bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingDetails) {
            // Điều chỉnh thông báo lỗi dựa trên loại tìm kiếm
            $errorMessage = $isSearchById ?
                "Không tìm thấy thông tin booking với ID: " . htmlspecialchars($bookingIdentifier) :
                "Không tìm thấy thông tin booking với Mã tham chiếu: " . htmlspecialchars($bookingIdentifier);
            redirect('index.php', 'error', $errorMessage);
        } else {
            // Nếu tìm thấy, cập nhật $bookingId với ID thực tế từ DB
            // Điều này quan trọng vì các log trạng thái được liên kết bằng ID booking, không phải mã tham chiếu.
            $bookingId = $bookingDetails['id'];
            // Sau đó, lấy lịch sử trạng thái bằng ID booking này
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
            $stmtLogs->bindParam(':order_id', $bookingId, PDO::PARAM_INT);
            $stmtLogs->execute();
            $statusLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        redirect('index.php', 'error', "Lỗi cơ sở dữ liệu khi tải chi tiết booking: " . $e->getMessage());
    }
} else {
    // Nếu không có định danh nào được cung cấp từ cả hai nguồn, trang sẽ hiển thị form tìm kiếm trống
    // và thông báo hướng dẫn. Không cần redirect ở đây.
}

// Xử lý logic khi form thêm log trạng thái chi tiết được gửi (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_status_log'])) {
    $bookingIdToUpdate = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $detailedStatusText = trim(filter_input(INPUT_POST, 'detailed_status_text', FILTER_SANITIZE_STRING));
    $detailedStatusType = filter_input(INPUT_POST, 'detailed_status_type', FILTER_SANITIZE_STRING);
    $eventDateTimeInput = filter_input(INPUT_POST, 'event_datetime', FILTER_SANITIZE_STRING); // Lấy giá trị datetime-local từ input

    $currentUserId = $_SESSION['user_id']; // Lấy ID người dùng hiện tại từ session

    if (!$bookingIdToUpdate || empty($detailedStatusType) || empty($detailedStatusText)) {
        redirect('update_booking_status.php?id=' . ($bookingIdToUpdate ?: ''), 'error', "Dữ liệu không hợp lệ. Vui lòng điền đầy đủ thông tin.");
    }

    // Xử lý và định dạng lại eventDateTime
    $eventDateTime = null;
    if (!empty($eventDateTimeInput)) {
        try {
            $dateTimeObj = new DateTime($eventDateTimeInput);
            $eventDateTime = $dateTimeObj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log("Lỗi định dạng ngày giờ từ input: " . $eventDateTimeInput . " - " . $e->getMessage());
            redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Định dạng ngày giờ không hợp lệ. Vui lòng kiểm tra lại.");
        }
    } else {
        $eventDateTime = date('Y-m-d H:i:s');
    }

    try {
        $pdo->beginTransaction();

        // Ghi log chi tiết vào bảng vmlbooking_status_logs
        $stmtInsertDetailedLog = $pdo->prepare("INSERT INTO vmlbooking_status_logs (order_id, status_text, status_type, created_by_user_id, created_at) VALUES (:order_id, :status_text, :status_type, :created_by_user_id, :created_at)");

        $stmtInsertDetailedLog->bindParam(':order_id', $bookingIdToUpdate, PDO::PARAM_INT);
        $stmtInsertDetailedLog->bindParam(':status_text', $detailedStatusText, PDO::PARAM_STR);
        $stmtInsertDetailedLog->bindParam(':status_type', $detailedStatusType, PDO::PARAM_STR);
        $stmtInsertDetailedLog->bindParam(':created_by_user_id', $currentUserId, PDO::PARAM_INT);
        $stmtInsertDetailedLog->bindParam(':created_at', $eventDateTime, PDO::PARAM_STR);

        $stmtInsertDetailedLog->execute();

        // Cập nhật trạng thái chính của booking dựa trên log mới nhất
        if (!updateMainBookingStatus($pdo, $bookingIdToUpdate)) {
            throw new Exception("Không thể cập nhật trạng thái chính của booking.");
        }

        $pdo->commit();
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'success', "Đã thêm trạng thái thành công!");

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Lỗi database khi thêm trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Lỗi database: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Lỗi khi thêm trạng thái: " . $e->getMessage());
        redirect('update_booking_status.php?id=' . $bookingIdToUpdate, 'error', "Lỗi: " . $e->getMessage());
    }
}
require_once "includes/header.php";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Trạng thái Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome cho các icon Sửa/Xóa -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Thiết lập font và màu nền chung cho body */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }
        /* Container chính của trang, căn giữa và có đổ bóng */
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        /* Tiêu đề chính của trang */
        h1 {
            color: #1a202c;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }
        /* Định dạng cho mỗi mục chi tiết trong phần booking details */
        .detail-item {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0; /* Đường kẻ đứt nét */
        }
        .detail-item:last-child {
            border-bottom: none; /* Không có đường kẻ ở mục cuối cùng */
        }
        /* Nhãn cho các mục chi tiết */
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            width: 180px; /* Chiều rộng cố định cho nhãn */
            flex-shrink: 0;
        }
        /* Giá trị của các mục chi tiết */
        .detail-value {
            color: #2d3748;
            flex-grow: 1;
        }
        /* Nhóm form (label + input/select/textarea) */
        .form-group {
            margin-bottom: 20px;
        }
        /* Nhãn của form */
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }
        /* Thiết lập chung cho select và textarea */
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
        /* Hiệu ứng focus cho select và textarea */
        .form-select:focus, .form-textarea:focus, .form-input:focus {
            outline: none;
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
        /* Nút submit */
        .btn-submit {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: #4299e1; /* Màu xanh dương */
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
            background-color: #3182ce; /* Màu xanh đậm hơn khi hover */
            transform: translateY(-2px); /* Hiệu ứng nhấc nhẹ lên */
        }
        /* Thông báo lỗi */
        .message.error {
            background-color: #fee2e2; /* Nền đỏ nhạt */
            color: #991b1b; /* Chữ đỏ đậm */
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #ef4444;
        }
        /* Thông báo thành công */
        .message.success {
            background-color: #d1fae5; /* Nền xanh nhạt */
            color: #065f46; /* Chữ xanh đậm */
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #34d399;
        }
        /* Thông báo thông tin */
        .message.info {
            background-color: #e0f2fe; /* Nền xanh nhạt */
            color: #1e40af; /* Chữ xanh đậm */
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #60a5fa;
        }
        /* Badge hiển thị trạng thái hiện tại */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 9999px; /* Bo tròn hoàn toàn */
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        /* Các màu sắc khác nhau cho từng trạng thái */
        .status-pending { background-color: #f6ad55; } /* Cam */
        .status-confirmed { background-color: #4299e1; } /* Xanh dương */
        .status-in-transit { background-color: #38a169; } /* Xanh lá cây */
        .status-departed { background-color: #008000; } /* Xanh lá cây đậm */
        .status-arrived { background-color: #805ad5; } /* Tím */
        .status-delivered { background-color: #006400; } /* Xanh lá cây rất đậm */
        .status-holded { background-color: #e53e3e; } /* Đỏ */
        .status-cancelled { background-color: #c53030; } /* Đỏ đậm */


        /* Styles cho phần Lịch sử trạng thái */
        .status-logs table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .status-logs th, .status-logs td {
            padding: 12px 15px;
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
        .status-logs .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center; /* Căn giữa các nút */
        }
        .status-logs .action-buttons a,
        .status-logs .action-buttons button {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease, transform 0.1s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .status-logs .action-buttons .edit-btn {
            background-color: #f6ad55; /* Orange */
            color: white;
        }
        .status-logs .action-buttons .edit-btn:hover {
            background-color: #ed8936;
            transform: translateY(-1px);
        }
        .status-logs .action-buttons .delete-btn {
            background-color: #e53e3e; /* Red */
            color: white;
        }
        .status-logs .action-buttons .delete-btn:hover {
            background-color: #c53030;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản lý Trạng thái Booking</h1>

        <?php
        // Kiểm tra và hiển thị thông báo thành công từ session
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']); // Xóa thông báo sau khi hiển thị
        }

        // Kiểm tra và hiển thị thông báo lỗi từ session
        if (isset($_SESSION['error_message'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']); // Xóa thông báo sau khi hiển thị
        }
        ?>
        <div class="search-form-section bg-gray-100 p-6 rounded-lg shadow-inner mb-8">
            
            <form method="GET" action="update_booking_status.php" class="flex flex-col sm:flex-row gap-4 items-center">
                <div class="flex-grow w-full sm:w-auto">
                    <label for="booking_reference_code_search" class="sr-only">Nhập Mã tham chiếu Booking:</label>
                    <input type="text" id="booking_reference_code_search" name="booking_reference_code_search"
                           class="form-input rounded-md w-full"
                           placeholder="Nhập Mã Bill"
                           value="<?= htmlspecialchars($_GET['booking_reference_code_search'] ?? ($_GET['id'] ?? '')) ?>">
                </div>
                <button type="submit" class="btn-submit bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-md w-full sm:w-auto">
                    Tìm kiếm
                </button>
            </form>
          </div>

        <?php if ($bookingDetails): ?>
            <div class="booking-details mb-8 p-6 bg-gray-50 rounded-lg border border-gray-200">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b pb-3">Chi tiết Booking #<?php echo htmlspecialchars($bookingDetails['reference_code']); ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="detail-item">
                        <span class="detail-label">Mã tham chiếu:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['reference_code'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Loại dịch vụ:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['service_type'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Trạng thái hiện tại:</span>
                        <span class="detail-value">
                            <?php
                                // Tạo class CSS động dựa trên trạng thái để hiển thị màu sắc phù hợp
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
                        <span class="detail-label">Tên người gửi:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['shipper_agency_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Điện thoại người gửi:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['shipper_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quốc gia người nhận:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['receiver_country'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tên người nhận:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['receiver_company'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Điện thoại người nhận:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bookingDetails['receiver_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Địa chỉ người nhận:</span>
                        <span class="detail-value">
                            <?php
                                // Xây dựng địa chỉ đầy đủ từ các trường khác nhau
                                $address = [];
                                if (!empty($bookingDetails['receiver_address1'])) $address[] = $bookingDetails['receiver_address1'];
                                if (!empty($bookingDetails['receiver_address2'])) $address[] = $bookingDetails['receiver_address2'];
                                if (!empty($bookingDetails['receiver_address3'])) $address[] = $bookingDetails['receiver_address3'];
                                if (!empty($bookingDetails['receiver_city'])) $address[] = $bookingDetails['receiver_city'];
                                if (!empty($bookingDetails['receiver_state'])) $address[] = $bookingDetails['receiver_state'];
                                if (!empty($bookingDetails['receiver_postal_code'])) $address[] = $bookingDetails['receiver_postal_code'];
                                echo htmlspecialchars(implode(', ', $address));
                            ?>
                        </span>
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

            <!-- Form để thêm trạng thái mới vào lịch sử -->
            <div class="mt-10 pt-6 border-t border-gray-200">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b pb-3">Thêm Trạng thái Mới</h2>
                <form method="POST" action="">
                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($bookingDetails['id']); ?>">
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
                        <input type="datetime-local" name="event_datetime" id="event_datetime" class="form-input rounded-md" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        <p class="text-sm text-gray-500 mt-1">
                            Mặc định là thời gian hiện tại. Bạn có thể thay đổi nếu sự kiện xảy ra trong quá khứ.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="detailed_status_type" class="form-label">Loại Trạng thái:</label>
                        <select name="detailed_status_type" id="detailed_status_type" class="form-select rounded-md">
                            <option value="pending">Pending (Đang chờ)</option>
                            <option value="confirmed">Confirmed (Đã xác nhận)</option>
                            <option value="in transit">In Transit (Đang vận chuyển)</option>
                            <option value="departed">Departed (Đã khởi hành)</option>
                            <option value="arrived">Arrived (Đã đến)</option>
                            <option value="delivered">Delivered (Đã giao)</option>
                            <option value="holded">Holded (Đang giữ)</option>
                            <option value="cancelled">Cancelled (Đã hủy)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="detailed_status_text" class="form-label">Ghi chú Trạng thái:</label>
                        <textarea name="detailed_status_text" id="detailed_status_text" rows="3" class="form-textarea rounded-md" placeholder="Ví dụ: Shipment departed from SGN at 10:00 AM"></textarea>
                    </div>

                    <button type="submit" name="add_status_log" class="btn-submit rounded-md bg-green-500 hover:bg-green-600">Thêm Trạng thái</button>
                </form>
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
                                    <th>Loại Trạng thái</th>
                                    <th>Ghi chú</th>
                                    <th>Người tạo</th>
                                    <th>Cập nhật cuối</th>
                                    <th style="width: 120px;">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statusLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td>
                                            <?php
                                                // Tạo class CSS động dựa trên trạng thái để hiển thị màu sắc phù hợp
                                                $logStatusClass = 'status-' . strtolower(str_replace(' ', '-', $log['status_type']));
                                                echo '<span class="status-badge ' . $logStatusClass . '">' . htmlspecialchars($log['status_type']) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($log['status_text'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['created_by_username'] ?? 'ID: ' . $log['created_by_user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['updated_at'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_status.php?id=<?php echo htmlspecialchars($log['id']); ?>&order_id=<?php echo htmlspecialchars($bookingDetails['id']); ?>"
                                                   class="edit-btn">
                                                    <i class="fas fa-edit"></i> Sửa
                                                </a>
                                                <button type="button" onclick="confirmDelete(<?php echo htmlspecialchars($log['id']); ?>, <?php echo htmlspecialchars($bookingDetails['id']); ?>)"
                                                               class="delete-btn">
                                                    <i class="fas fa-trash-alt"></i> Xóa
                                                </button>
                                            </div>
                                        </td>
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
                <p class="text-gray-700 text-lg mb-4">Vui lòng nhập Mã Bill vào ô tìm kiếm ở trên để xem chi tiết trạng thái.</p>
                <p class="text-gray-500 text-sm">Bạn có thể tìm thấy Mã Bill hàng trong danh sách đơn hàng.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Hàm này sẽ hiển thị một modal tùy chỉnh thay vì alert() hoặc confirm()
        // Bạn cần tự xây dựng phần HTML/CSS cho modal này
        function showCustomModal(message, type, onConfirm = null) {
            // Đây là một ví dụ đơn giản, bạn có thể mở rộng để tạo modal đẹp hơn
            const modalHtml = `
                <div id="customModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full">
                        <h3 class="text-lg font-bold mb-4 ${type === 'error' ? 'text-red-600' : 'text-green-600'}">
                            ${type === 'error' ? 'Lỗi!' : 'Xác nhận'}
                        </h3>
                        <p class="text-gray-700 mb-6">${message}</p>
                        <div class="flex justify-end space-x-4">
                            ${onConfirm ? `<button id="confirmBtn" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Xác nhận</button>` : ''}
                            <button id="closeModalBtn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Đóng</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            document.getElementById('closeModalBtn').addEventListener('click', () => {
                document.getElementById('customModal').remove();
            });

            if (onConfirm) {
                document.getElementById('confirmBtn').addEventListener('click', () => {
                    onConfirm();
                    document.getElementById('customModal').remove();
                });
            }
        }

        function confirmDelete(statusLogId, bookingId) {
            showCustomModal("Bạn có chắc chắn muốn xóa log trạng thái này không?", "info", () => {
                // Chuyển hướng đến file xử lý xóa với ID log và ID booking
                // Đường dẫn này không đổi vì chuyển hướng đến file trong cùng thư mục
                window.location.href = "delete_status_process.php?id=" + statusLogId + "&order_id=" + bookingId;
            });
        }
    </script>
</body>
</html>