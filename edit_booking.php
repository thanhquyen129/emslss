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

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'agency'; // Lấy vai trò của người dùng, mặc định là 'agency'

// Định nghĩa biến này để kiểm soát quyền cập nhật trạng thái (cho nút chuyển hướng)
$can_update_status = in_array($current_user_role, ['admin', 'accounting']);

$message = '';
$booking = null;

// Kiểm tra quyền truy cập chỉnh sửa tổng thể
// Admin có thể chỉnh sửa mọi thứ
// Agency chỉ có thể chỉnh sửa booking của chính họ
// Accounting chỉ có thể chỉnh sửa cost/sales_price (logic này sẽ được xử lý riêng)
// Viewer không được chỉnh sửa bất cứ gì
if (!in_array($current_user_role, ['admin', 'agency', 'accounting'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn không có quyền chỉnh sửa đơn hàng này.</p>";
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['booking_message'] = "<p class='message error'>❌ ID booking không hợp lệ.</p>";
    header("Location: index.php");
    exit;
}

$booking_id = (int)$_GET['id'];

try {
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Đảm bảo fetch dạng mảng kết hợp
    }

    // Lấy thông tin booking để hiển thị và kiểm tra quyền
    // Thêm cột 'note' vào câu truy vấn SELECT
    $stmt = $pdo->prepare("SELECT * FROM vmlbooking_orders WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(); // Đã thiết lập FETCH_ASSOC mặc định

    if (!$booking) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Booking không tồn tại.</p>";
        header("Location: index.php");
        exit;
    }

    // Kiểm tra quyền truy cập chi tiết sau khi lấy được booking
    // Admin có thể chỉnh sửa bất kỳ booking nào
    // Agency chỉ có thể chỉnh sửa booking của chính họ
    // Accounting chỉ được phép chỉnh sửa cost/sales_price (sẽ kiểm tra cụ thể hơn khi xử lý POST)
    // Nếu Accounting cố gắng truy cập trang này để chỉnh sửa toàn bộ (không phải cost/sales_price), sẽ không cho phép
    if ($current_user_role === 'agency' && $booking['user_id'] !== $current_user_id) {
        $_SESSION['booking_message'] = "<p class='message error'>❌ Bạn không có quyền chỉnh sửa đơn hàng này.</p>";
        header("Location: index.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $update_fields = [];
        $update_params = [];
        $allowed_to_update_all = in_array($current_user_role, ['admin', 'agency']); // Admin & Agency có thể chỉnh sửa các trường chung

        // Các trường chung mà Admin và Agency có thể chỉnh sửa
        if ($allowed_to_update_all) {
            $shipper_agency_name = sanitize_input($_POST['shipper_agency_name'] ?? '');
            $shipper_contact = sanitize_input($_POST['shipper_contact'] ?? '');
            $shipper_address = sanitize_input($_POST['shipper_address'] ?? '');
            $shipper_phone = sanitize_input($_POST['shipper_phone'] ?? '');
            $shipper_id_tax = sanitize_input($_POST['shipper_id_tax'] ?? '');
            $shipper_email = sanitize_input($_POST['shipper_email'] ?? '');
            $shipper_country = sanitize_input($_POST['shipper_country'] ?? '');

            $service_type = sanitize_input($_POST['service_type'] ?? '');
            $warehouse = sanitize_input($_POST['warehouse'] ?? '');
            $shipment_type = sanitize_input($_POST['shipment_type'] ?? '');
            $gross_weight = filter_input(INPUT_POST, 'gross_weight', FILTER_VALIDATE_FLOAT);
            $number_of_packages = filter_input(INPUT_POST, 'number_of_packages', FILTER_VALIDATE_INT);
            $dimensions_text = sanitize_input($_POST['dimensions_text'] ?? '');

            $receiver_country = sanitize_input($_POST['receiver_country'] ?? '');
            $receiver_company = sanitize_input($_POST['receiver_company'] ?? '');
            $receiver_contact = sanitize_input($_POST['receiver_contact'] ?? '');
            $receiver_phone = sanitize_input($_POST['receiver_phone'] ?? '');
            $receiver_tax_id = sanitize_input($_POST['receiver_tax_id'] ?? '');
            $receiver_email = sanitize_input($_POST['receiver_email'] ?? '');
            $receiver_postal_code = sanitize_input($_POST['receiver_postal_code'] ?? '');
            $receiver_city = sanitize_input($_POST['receiver_city'] ?? '');
            $receiver_state = sanitize_input($_POST['receiver_state'] ?? '');
            $receiver_address1 = sanitize_input($_POST['receiver_address1'] ?? '');
            $receiver_address2 = sanitize_input($_POST['receiver_address2'] ?? '');
            $receiver_address3 = sanitize_input($_POST['receiver_address3'] ?? '');

            // Kiểm tra các trường bắt buộc
            if (empty($shipper_contact) || empty($receiver_contact) || empty($receiver_phone) || $gross_weight === false || $gross_weight <= 0 || empty($service_type) || empty($receiver_address1)) {
                $message = "<p class='message error'>Vui lòng điền đầy đủ các thông tin bắt buộc và đảm bảo khối lượng lớn hơn 0.</p>";
            } else {
                // Chỉ Admin mới có thể chỉnh sửa shipper_agency_name nếu nó được hiển thị
                if ($current_user_role === 'admin') {
                    $update_fields[] = "shipper_agency_name = ?";
                    $update_params[] = $shipper_agency_name;
                } else {
                    // Nếu không phải admin, đảm bảo giá trị vẫn là giá trị hiện tại của booking
                    // Không cần thêm vào update_fields vì nó không thay đổi
                }

                $update_fields[] = "shipper_contact = ?"; $update_params[] = $shipper_contact;
                $update_fields[] = "shipper_address = ?"; $update_params[] = $shipper_address;
                $update_fields[] = "shipper_phone = ?"; $update_params[] = $shipper_phone;
                $update_fields[] = "shipper_id_tax = ?"; $update_params[] = $shipper_id_tax;
                $update_fields[] = "shipper_email = ?"; $update_params[] = $shipper_email;
                $update_fields[] = "shipper_country = ?"; $update_params[] = $shipper_country;
                $update_fields[] = "service_type = ?"; $update_params[] = $service_type;
                $update_fields[] = "warehouse = ?"; $update_params[] = $warehouse;
                $update_fields[] = "shipment_type = ?"; $update_params[] = $shipment_type;
                $update_fields[] = "gross_weight = ?"; $update_params[] = $gross_weight;
                $update_fields[] = "number_of_packages = ?"; $update_params[] = $number_of_packages;
                $update_fields[] = "dimensions_text = ?"; $update_params[] = $dimensions_text;
                $update_fields[] = "receiver_country = ?"; $update_params[] = $receiver_country;
                $update_fields[] = "receiver_company = ?"; $update_params[] = $receiver_company;
                $update_fields[] = "receiver_contact = ?"; $update_params[] = $receiver_contact;
                $update_fields[] = "receiver_phone = ?"; $update_params[] = $receiver_phone;
                $update_fields[] = "receiver_tax_id = ?"; $update_params[] = $receiver_tax_id;
                $update_fields[] = "receiver_email = ?"; $update_params[] = $receiver_email;
                $update_fields[] = "receiver_postal_code = ?"; $update_params[] = $receiver_postal_code;
                $update_fields[] = "receiver_city = ?"; $update_params[] = $receiver_city;
                $update_fields[] = "receiver_state = ?"; $update_params[] = $receiver_state;
                $update_fields[] = "receiver_address1 = ?"; $update_params[] = $receiver_address1;
                $update_fields[] = "receiver_address2 = ?"; $update_params[] = $receiver_address2;
                $update_fields[] = "receiver_address3 = ?"; $update_params[] = $receiver_address3;
            }
        }

        // Xử lý trường cost, sales_price và note (chỉ Admin và Accounting mới có thể chỉnh sửa)
        // Các trường này không bị ảnh hưởng bởi $allowed_to_update_all
        if (in_array($current_user_role, ['admin', 'accounting'])) {
            $new_cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
            $new_sales_price = filter_input(INPUT_POST, 'sales_price', FILTER_VALIDATE_FLOAT);
            $new_note = sanitize_input($_POST['note'] ?? ''); // Lấy giá trị ghi chú

            if ($new_cost !== false) {
                // Chỉ cập nhật nếu giá trị mới khác giá trị hiện tại
                if ($new_cost !== (float)($booking['cost'] ?? 0)) {
                    $update_fields[] = "cost = ?";
                    $update_params[] = $new_cost;
                }
            }
            if ($new_sales_price !== false) {
                // Chỉ cập nhật nếu giá trị mới khác giá trị hiện tại
                if ($new_sales_price !== (float)($booking['sales_price'] ?? 0)) {
                    $update_fields[] = "sales_price = ?";
                    $update_params[] = $new_sales_price;
                }
            }
            // Luôn cập nhật note nếu là admin/accounting và có thay đổi
            if ($new_note !== ($booking['note'] ?? '')) {
                $update_fields[] = "note = ?";
                $update_params[] = $new_note;
            }
        }

        // Thực hiện UPDATE nếu có trường nào đó được thay đổi
        if (!empty($update_fields) && empty($message)) { // Chỉ cập nhật nếu có trường thay đổi và không có lỗi validation
            $sql_update = "UPDATE vmlbooking_orders SET " . implode(", ", $update_fields) . ", updated_at = NOW() WHERE id = ?";
            $update_params[] = $booking_id; // Thêm booking_id vào cuối params

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute($update_params);

            $_SESSION['booking_message'] = "<p class='message success'>✅ Đã cập nhật booking " . htmlspecialchars($booking['reference_code']) . " thành công.</p>";
            header("Location: index.php");
            exit;
        } else if (empty($update_fields) && empty($message)) {
            $message = "<p class='message info'>Không có thay đổi nào để lưu.</p>";
        }
    }

} catch (PDOException $e) {
    error_log("PDOException in edit_booking.php: " . $e->getMessage());
    $message = "<p class='message error'>Lỗi hệ thống. Vui lòng thử lại sau.</p>"; // Thông báo chung cho người dùng
} catch (Exception $e) {
    error_log("General Exception in edit_booking.php: " . $e->getMessage());
    $message = "<p class='message error'>Lỗi hệ thống. Vui lòng thử lại sau.</p>"; // Thông báo chung cho người dùng
}

require_once "includes/header.php";
?>

<div class="booking-detail-container">
    <h2>Chỉnh sửa Booking: <?= htmlspecialchars($booking['reference_code'] ?? 'N/A') ?></h2>

    <?php if (!empty($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <?php if ($booking): ?>
        <form method="post">
            <div class="form-section-wrapper">
                <div class="form-column">
                    <h3>Thông tin người gửi (Shipper)</h3>
                    <?php if ($current_user_role === 'admin'): // Chỉ Admin mới có thể chỉnh sửa tên Agency ?>
                        <label for="shipper_agency_name">Tên Agency:</label>
                        <input type="text" id="shipper_agency_name" name="shipper_agency_name" value="<?= htmlspecialchars($booking['shipper_agency_name'] ?? '') ?>" placeholder="Tên Agency">
                    <?php else: // Agency và các vai trò khác chỉ xem ?>
                        <label>Tên Agency:</label>
                        <p class="read-only-field"><?= htmlspecialchars($booking['shipper_agency_name'] ?? '') ?></p>
                        <input type="hidden" name="shipper_agency_name" value="<?= htmlspecialchars($booking['shipper_agency_name'] ?? '') ?>">
                    <?php endif; ?>

                    <label for="shipper_contact">Người LH (contact name) (*):</label>
                    <input type="text" id="shipper_contact" name="shipper_contact" value="<?= htmlspecialchars($booking['shipper_contact'] ?? '') ?>" placeholder="Tên người gửi" required>

                    <label for="shipper_address">Địa chỉ:</label>
                    <input type="text" id="shipper_address" name="shipper_address" value="<?= htmlspecialchars($booking['shipper_address'] ?? '') ?>" placeholder="Địa chỉ người gửi">

                    <label for="shipper_phone">ĐT (tel):</label>
                    <input type="text" id="shipper_phone" name="shipper_phone" value="<?= htmlspecialchars($booking['shipper_phone'] ?? '') ?>" placeholder="Số điện thoại người gửi">

                    <label for="shipper_id_tax">Mã số thuế/CCCD/CMND:</label>
                    <input type="text" id="shipper_id_tax" name="shipper_id_tax" value="<?= htmlspecialchars($booking['shipper_id_tax'] ?? '') ?>" placeholder="Mã số thuế/CCCD/CMND">
                    
                    <label for="shipper_email">Email:</label>
                    <input type="email" id="shipper_email" name="shipper_email" value="<?= htmlspecialchars($booking['shipper_email'] ?? '') ?>" placeholder="Email người gửi">
                    
                    <label for="shipper_country">Quốc gia:</label>
                    <input type="text" id="shipper_country" name="shipper_country" value="<?= htmlspecialchars($booking['shipper_country'] ?? '') ?>" placeholder="Quốc gia người gửi">

                    <h3>Thông tin đơn hàng (Info Shipment)</h3>
                    <label for="gross_weight">Cân nặng (gross weight) (kg) (*):</label>
                    <input type="number" step="0.01" id="gross_weight" name="gross_weight" value="<?= htmlspecialchars($booking['gross_weight'] ?? '') ?>" placeholder="Ví dụ: 1.5, 10.25" required>
                    
                    <label for="number_of_packages">Số lượng kiện (No. of packages):</label>
                    <input type="number" id="number_of_packages" name="number_of_packages" value="<?= htmlspecialchars($booking['number_of_packages'] ?? '') ?>" min="1" placeholder="Số lượng kiện hàng">

                    <label for="dimensions_text">Kích thước (Dài x Rộng x Cao) (cm):</label>
                    <input type="text" id="dimensions_text" name="dimensions_text" value="<?= htmlspecialchars($booking['dimensions_text'] ?? '') ?>" placeholder="Ví dụ: D10R20C30 (1 kiện), D15R25C35 (2 kiện)">
                    <small style="display: block; margin-top: -5px; margin-bottom: 10px; color: #666;">Nhập theo định dạng ví dụ để dễ đọc.</small>

                    <label for="service_type">Loại hình dịch vụ (Service Type) (*):</label>
                    <input type="text" id="service_type" name="service_type" value="<?= htmlspecialchars($booking['service_type'] ?? '') ?>" placeholder="Ví dụ: Express, Economy, Standard" required>
                    
                    <label for="shipment_type">Loại (type):</label>
                    <select id="shipment_type" name="shipment_type">
                        <option value="PACK" <?= (($booking['shipment_type'] ?? '') == 'PACK') ? 'selected' : '' ?>>PACK</option>
                        <option value="DOC" <?= (($booking['shipment_type'] ?? '') == 'DOC') ? 'selected' : '' ?>>DOC</option>
                    </select>
                    
                    <label for="warehouse">Kho hàng:</label>
                    <input type="text" id="warehouse" name="warehouse" value="<?= htmlspecialchars($booking['warehouse'] ?? '') ?>" placeholder="Tên kho hàng (ví dụ: HCM Warehouse)">
                </div>

                <div class="form-column">
                    <h3>Thông tin người nhận (Receiver)</h3>
                    <label for="receiver_country">Nước đến (country) (*):</label>
                    <input type="text" id="receiver_country" name="receiver_country" value="<?= htmlspecialchars($booking['receiver_country'] ?? '') ?>" placeholder="Quốc gia người nhận" required>

                    <label for="receiver_company">Cty (company name):</label>
                    <input type="text" id="receiver_company" name="receiver_company" value="<?= htmlspecialchars($booking['receiver_company'] ?? '') ?>" placeholder="Tên công ty (nếu có)">

                    <label for="receiver_contact">Người LH (contact name) (*):</label>
                    <input type="text" id="receiver_contact" name="receiver_contact" value="<?= htmlspecialchars($booking['receiver_contact'] ?? '') ?>" placeholder="Tên người nhận" required>

                    <label for="receiver_phone">ĐT (tel) (*):</label>
                    <input type="text" id="receiver_phone" name="receiver_phone" value="<?= htmlspecialchars($booking['receiver_phone'] ?? '') ?>" placeholder="Số điện thoại người nhận" required>

                    <label for="receiver_tax_id">Tax ID:</label>
                    <input type="text" id="receiver_tax_id" name="receiver_tax_id" value="<?= htmlspecialchars($booking['receiver_tax_id'] ?? '') ?>" placeholder="Mã số thuế người nhận">

                    <label for="receiver_email">Email:</label>
                    <input type="email" id="receiver_email" name="receiver_email" value="<?= htmlspecialchars($booking['receiver_email'] ?? '') ?>" placeholder="Email người nhận">
                    
                    <label for="receiver_postal_code">Postal code:</label>
                    <input type="text" id="receiver_postal_code" name="receiver_postal_code" value="<?= htmlspecialchars($booking['receiver_postal_code'] ?? '') ?>" placeholder="Mã bưu chính (Postal code)">

                    <label for="receiver_city">Thành phố (city):</label>
                    <input type="text" id="receiver_city" name="receiver_city" value="<?= htmlspecialchars($booking['receiver_city'] ?? '') ?>" placeholder="Thành phố">

                    <label for="receiver_state">Tỉnh (State):</label>
                    <input type="text" id="receiver_state" name="receiver_state" value="<?= htmlspecialchars($booking['receiver_state'] ?? '') ?>" placeholder="Tỉnh/Bang">

                    <label for="receiver_address1">Địa chỉ 1 (address 1) (*):</label>
                    <input type="text" id="receiver_address1" name="receiver_address1" value="<?= htmlspecialchars($booking['receiver_address1'] ?? '') ?>" placeholder="Số nhà, tên đường, thôn, xóm..." required>

                    <label for="receiver_address2">Địa chỉ 2 (address 2):</label>
                    <input type="text" id="receiver_address2" name="receiver_address2" value="<?= htmlspecialchars($booking['receiver_address2'] ?? '') ?>" placeholder="Phường, xã, thị trấn...">

                    <label for="receiver_address3">Địa chỉ 3 (address 3):</label>
                    <input type="text" id="receiver_address3" name="receiver_address3" value="<?= htmlspecialchars($booking['receiver_address3'] ?? '') ?>" placeholder="Quận, huyện, thị xã...">

                    </div>
            </div>

            <?php if (in_array($current_user_role, ['admin', 'accounting', 'viewer'])): ?>
            <div class="form-section-wrapper">
                <div class="form-column full-width">
                    <h3>Thông tin tài chính</h3>
                    <label for="cost">Chi phí (Cost):</label>
                    <input type="number" step="1" id="cost" name="cost" value="<?= htmlspecialchars($booking['cost'] ?? 0) ?>" placeholder="Chi phí"
                        <?= (!in_array($current_user_role, ['admin', 'accounting'])) ? 'readonly' : '' ?>>

                    <label for="sales_price">Giá bán (Sales Price):</label>
                    <input type="number" step="1" id="sales_price" name="sales_price" value="<?= htmlspecialchars($booking['sales_price'] ?? 0) ?>" placeholder="Giá bán"
                        <?= (!in_array($current_user_role, ['admin', 'accounting'])) ? 'readonly' : '' ?>>

                    <label for="note">Ghi chú:</label>
                    <textarea id="note" name="note" rows="4" placeholder="Nhập ghi chú tại đây..."
                        <?= (!in_array($current_user_role, ['admin', 'accounting', 'viewer'])) ? 'readonly' : '' ?>><?= htmlspecialchars($booking['note'] ?? '') ?></textarea>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="button primary">Cập nhật Booking</button>
                <button type="button" class="button secondary" onclick="window.location.href='index.php'">Quay lại</button>
            </div>
        </form>
    <?php else: ?>
        <p class="message error">Không tìm thấy booking.</p>
        <a href="index.php" class="button secondary">Quay lại danh sách</a>
    <?php endif; ?>
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

    /* Badge cho trạng thái */
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: bold;
        color: white;
        text-transform: uppercase;
        min-width: 80px; /* Đảm bảo độ rộng tối thiểu */
        text-align: center;
    }
    .status-pending { background-color: #ffc107; color: #343a40; } /* Vàng */
    .status-confirmed { background-color: #007bff; } /* Xanh dương */
    .status-in-transit { background-color: #17a2b8; } /* Xanh ngọc */
    .status-delivered { background-color: #28a745; } /* Xanh lá cây */
    .status-cancelled { background-color: #dc3545; } /* Đỏ */
</style>