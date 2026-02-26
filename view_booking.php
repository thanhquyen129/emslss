<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
require_once "includes/functions.php"; // Đảm bảo functions.php chứa hàm sanitize_input

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'agency'; // Mặc định là 'agency' nếu không có

$booking = null;
$message = '';
$booking_id = sanitize_input($_GET['id'] ?? '');

// --- Kiểm tra và xử lý booking_id hợp lệ ---
if (empty($booking_id) || !is_numeric($booking_id)) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Mã booking không hợp lệ.</p>";
    header("Location: index.php");
    exit;
}

try {
    // Đảm bảo $pdo được khởi tạo một lần và truyền vào các hàm nếu cần
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Thêm dòng này để luôn fetch dưới dạng mảng kết hợp
    }

    // --- Xử lý cập nhật cost/sales_price/note nếu là POST request từ Admin hoặc Accounting ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_cost_sales'])) {
        if ($user_role === 'admin' || $user_role === 'accounting') {
            $new_cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
            $new_sales_price = filter_input(INPUT_POST, 'sales_price', FILTER_VALIDATE_FLOAT);
            $new_note = sanitize_input($_POST['note'] ?? ''); // Lấy giá trị ghi chú

            if ($new_cost === false || $new_sales_price === false) {
                $message = "<p class='message error'>❌ Dữ liệu chi phí hoặc giá bán không hợp lệ.</p>";
            } else {
                $stmt_update = $pdo->prepare("UPDATE vmlbooking_orders SET cost = ?, sales_price = ?, note = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$new_cost, $new_sales_price, $new_note, $booking_id]);
                $message = "<p class='message success'>✅ Đã cập nhật chi phí, giá bán và ghi chú thành công!</p>";
                // Không cần redirect, dữ liệu sẽ được tải lại ngay bên dưới
            }
        } else {
            $message = "<p class='message error'>❌ Bạn không có quyền cập nhật chi phí, giá bán hoặc ghi chú.</p>";
        }
    }

    // --- Lấy thông tin booking ---
    // Thêm cột 'note' vào câu truy vấn SELECT
    $sql = "SELECT o.*, u.company_name as user_company_name FROM vmlbooking_orders o JOIN vmlbooking_users u ON o.user_id = u.id WHERE o.id = ?";
    $params = [$booking_id];

    // Kiểm tra quyền xem: Agency chỉ xem booking của họ, các vai trò khác xem tất cả
    if ($user_role === 'agency') {
        $sql .= " AND o.user_id = ?";
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $booking = $stmt->fetch(); // Đã thiết lập PDO::FETCH_ASSOC mặc định ở trên

    if (!$booking) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Không tìm thấy booking hoặc bạn không có quyền truy cập.</p>";
        header("Location: index.php");
        exit;
    }

    // --- Lấy tài liệu đính kèm cho booking này ---
    // Cần đảm bảo truy vấn này cũng lấy user_id của tài liệu để kiểm tra quyền xóa
    if (in_array($user_role, ['admin', 'viewer', 'accounting'])) {
        $stmt_docs = $pdo->prepare("SELECT id, file_name, unique_file_name, user_id FROM vmlbooking_documents WHERE booking_id = ? ORDER BY uploaded_at ASC");
        $stmt_docs->execute([$booking['id']]);
    } else { // Agency
        $stmt_docs = $pdo->prepare("SELECT id, file_name, unique_file_name, user_id FROM vmlbooking_documents WHERE booking_id = ? AND user_id = ? ORDER BY uploaded_at ASC");
        $stmt_docs->execute([$booking['id'], $user_id]);
    }
    $booking['documents'] = $stmt_docs->fetchAll();


} catch (PDOException $e) {
    error_log("PDOException in view_booking.php: " . $e->getMessage());
    $message = "<p class='message error'>Lỗi hệ thống. Vui lòng thử lại sau.</p>"; // Thông báo chung cho người dùng
} catch (Exception $e) {
    error_log("General Exception in view_booking.php: " . $e->getMessage());
    $message = "<p class='message error'>Lỗi hệ thống. Vui lòng thử lại sau.</p>"; // Thông báo chung cho người dùng
}

require_once "includes/header.php";
?>

<div class="booking-detail-container">
    <h2>Chi tiết Booking: <?= htmlspecialchars($booking['reference_code'] ?? 'N/A') ?></h2>

    <?php if (!empty($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <div class="action-buttons-top">
        <a href="index.php" class="button secondary">Quay lại danh sách</a>
        <?php
        // Chỉ cho phép chỉnh sửa toàn bộ booking nếu là admin HOẶC (là agency VÀ booking đó thuộc về agency đó)
        if ($user_role === 'admin' || ($user_role === 'agency' && ($booking['user_id'] ?? null) === $user_id)):
        ?>
            <a href="edit_booking.php?id=<?= htmlspecialchars($booking['id']) ?>" class="button primary">Chỉnh sửa toàn bộ Booking</a>
        <?php endif; ?>
    </div>


    <div class="form-section-wrapper">
        <div class="form-column">
            <h3>Thông tin người gửi (Shipper)</h3>
            <label>Tên Agency:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['user_company_name'] ?? $booking['shipper_agency_name'] ?? 'N/A') ?></p>

            <label>Người LH (contact name):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_contact'] ?? '') ?></p>

            <label>Địa chỉ:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_address'] ?? '') ?></p>

            <label>ĐT (tel):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_phone'] ?? '') ?></p>

            <label>Mã số thuế/CCCD/CMND:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_id_tax'] ?? '') ?></p>

            <label>Email:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_email'] ?? '') ?></p>

            <label>Quốc gia:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipper_country'] ?? '') ?></p>

            <h3>Thông tin đơn hàng (Info Shipment)</h3>
            <label>Mã tham chiếu:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['reference_code'] ?? 'N/A') ?></p>

            <label>Cân nặng (gross weight) (kg):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['gross_weight'] ?? '') ?></p>

            <label>Số lượng kiện (No. of packages):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['number_of_packages'] ?? '') ?></p>

            <label>Kích thước (Dài x Rộng x Cao) (cm):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['dimensions_text'] ?? '') ?></p>

            <label>Loại hình dịch vụ (Service Type):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['service_type'] ?? '') ?></p>

            <label>Loại (type):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['shipment_type'] ?? '') ?></p>

            <label>Kho hàng:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['warehouse'] ?? '') ?></p>

            <label>Trạng thái Booking:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['booking_status'] ?? 'Pending') ?></p>
        </div>

        <div class="form-column">
            <h3>Thông tin người nhận (Receiver)</h3>
            <label>Nước đến (country):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_country'] ?? '') ?></p>

            <label>Cty (company name):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_company'] ?? '') ?></p>

            <label>Người LH (contact name):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_contact'] ?? '') ?></p>

            <label>ĐT (tel):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_phone'] ?? '') ?></p>

            <label>Tax ID:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_tax_id'] ?? '') ?></p>

            <label>Email:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_email'] ?? '') ?></p>

            <label>Postal code:</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_postal_code'] ?? '') ?></p>

            <label>Thành phố (city):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_city'] ?? '') ?></p>

            <label>Tỉnh (State):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_state'] ?? '') ?></p>

            <label>Địa chỉ 1 (address 1):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_address1'] ?? '') ?></p>

            <label>Địa chỉ 2 (address 2):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_address2'] ?? '') ?></p>

            <label>Địa chỉ 3 (address 3):</label>
            <p class="read-only-field"><?= htmlspecialchars($booking['receiver_address3'] ?? '') ?></p>
        </div>
    </div>

    <?php if (in_array($user_role, ['admin', 'accounting', 'viewer'])): ?>
    <div class="form-section-wrapper">
        <div class="form-column full-width">
            <h3>Thông tin tài chính</h3>
            <?php if ($user_role === 'admin' || $user_role === 'accounting'): ?>
                <form method="post" action="view_booking.php?id=<?= htmlspecialchars($booking['id']) ?>">
                    <input type="hidden" name="update_cost_sales" value="1">
                    <label for="cost">Chi phí (Cost):</label>
                    <input type="number" step="1" id="cost" name="cost" value="<?= htmlspecialchars($booking['cost'] ?? 0) ?>">

                    <label for="sales_price">Giá bán (Sales Price):</label>
                    <input type="number" step="1" id="sales_price" name="sales_price" value="<?= htmlspecialchars($booking['sales_price'] ?? 0) ?>">
                    
                    <label for="note">Ghi chú:</label>
                    <textarea id="note" name="note" rows="4" placeholder="Nhập ghi chú tại đây..."><?= htmlspecialchars($booking['note'] ?? '') ?></textarea>

                    <button type="submit" class="button primary">Cập nhật Chi phí/Giá bán/Ghi chú</button>
                </form>
            <?php else: // Viewer chỉ xem ?>
                <label>Chi phí (Cost):</label>
                <p class="read-only-field"><?= number_format($booking['cost'] ?? 0, 2) ?></p>

                <label>Giá bán (Sales Price):</label>
                <p class="read-only-field"><?= number_format($booking['sales_price'] ?? 0, 2) ?></p>

                <label>Ghi chú:</label>
                <p class="read-only-field"><?= nl2br(htmlspecialchars($booking['note'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-section-wrapper">
        <div class="form-column full-width">
            <h3>Tài liệu đính kèm</h3>
            <?php if ($user_role === 'agency'): ?>
                <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']) ?>">
                    <label for="file_upload">Chọn file để Upload:</label>
                    <input type="file" name="file_upload" id="file_upload" required style="width: auto;">
                    <button type="submit" class="button primary small">Upload File</button>
                </form>
                <hr>
            <?php endif; ?>

            <h4>Danh sách tài liệu đã tải lên:</h4>
            <?php if (!empty($booking['documents'])): ?>
                <ul class="document-list">
                    <?php foreach ($booking['documents'] as $doc): ?>
                        <li>
                            <a href="download_secure.php?doc_id=<?= htmlspecialchars($doc['id']) ?>" target="_blank" title="Tải về: <?= htmlspecialchars($doc['file_name']) ?>">
                                📎 <?= htmlspecialchars($doc['file_name']) ?>
                            </a>
                            <?php
                            // Chỉ cho phép agency xóa tài liệu của chính họ HOẶC admin xóa bất kỳ tài liệu nào
                            // LƯU Ý: Truy vấn tài liệu trong view_booking.php cần SELECT thêm user_id của tài liệu
                            if ($user_role === 'admin' || ($user_role === 'agency' && ($doc['user_id'] ?? null) === $user_id)):
                            ?>
                                <form action="delete_document.php" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa file <?= htmlspecialchars($doc['file_name']) ?> này không?');" style="display:inline-block; margin-left: 10px;">
                                    <input type="hidden" name="document_id" value="<?= htmlspecialchars($doc['id']) ?>">
                                    <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']) ?>">
                                    <button type="submit" class="button-delete-doc" title="Xóa file này">X</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Chưa có tài liệu nào được đính kèm.</p>
            <?php endif; ?>
        </div>
    </div>


    <div class="form-section-wrapper">
        <div class="form-column full-width">
            <h3>Thời gian</h3>
            <label>Ngày tạo:</label>
            <p class="read-only-field"><?= htmlspecialchars(isset($booking['created_at']) ? date('d-m-Y H:i:s', strtotime($booking['created_at'])) : 'N/A') ?></p>

            <label>Cập nhật lúc:</label>
            <p class="read-only-field"><?= htmlspecialchars(isset($booking['updated_at']) ? date('d-m-Y H:i:s', strtotime($booking['updated_at'])) : (isset($booking['created_at']) ? date('d-m-Y H:i:s', strtotime($booking['created_at'])) : 'N/A')) ?></p>
        </div>
    </div>


</div>
<?php require_once "includes/footer.php"; ?>

<style>
    /* CSS hiện tại của bạn */
    /* Add to your existing stylesheet or at the end of this file */

    /* Container for the entire view page */
    .booking-detail-container {
        max-width: 900px;
        margin: 20px auto;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    h2 {
        color: #0056b3;
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }

    /* Message styles (already in your index.php, ensure they are global or copied here) */
    .message {
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        font-weight: bold;
    }
    .message.success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }
    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
    }
    .message.info {
        background-color: #cfe2ff;
        color: #052c65;
        border-color: #b6d4fe;
    }


    /* Form section wrapper and columns */
    .form-section-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 20px;
    }
    .form-column {
        flex: 1;
        min-width: 380px; /* Adjust min-width to prevent squishing */
        background-color: #fcfcfc; /* Slightly different background for clarity */
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .form-column.full-width {
        flex-basis: 100%; /* Makes column take full width */
        min-width: unset; /* Remove min-width restriction */
    }
    .form-column h3 {
        margin-top: 0;
        color: #0056b3;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    /* Labels and Read-only fields */
    .form-column label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
        font-size: 0.95em;
    }
    .read-only-field {
        background-color: #e9ecef;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        margin-bottom: 15px;
        color: #495057;
        font-weight: normal;
        word-wrap: break-word; /* Allow long text to wrap */
        white-space: normal;
    }

    /* Input fields */
    .form-column input[type="text"],
    .form-column input[type="number"],
    .form-column input[type="email"],
    .form-column select,
    .form-column textarea { /* Thêm textarea */
        width: calc(100% - 22px); /* Full width minus padding/border */
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 1em;
    }
    .form-column textarea {
        resize: vertical; /* Cho phép thay đổi kích thước theo chiều dọc */
        min-height: 80px; /* Chiều cao tối thiểu */
    }

    /* Buttons */
    .form-actions {
        margin-top: 30px;
        text-align: right;
        display: flex; /* Dùng flexbox */
        justify-content: flex-end; /* Căn phải */
        gap: 10px; /* Khoảng cách giữa các nút */
    }

    .button {
        display: inline-block;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 1em;
        text-align: center;
        transition: background-color 0.3s ease;
        border: none;
        cursor: pointer;
    }
    .button.primary {
        background-color: #007bff;
        color: white;
    }
    .button.primary:hover {
        background-color: #0056b3;
    }
    .button.secondary {
        background-color: #6c757d;
        color: white;
    }
    .button.secondary:hover {
        background-color: #5a6268;
    }
</style>