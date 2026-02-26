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
$user_role = $_SESSION['user_role'] ?? 'agency'; // Lấy vai trò của người dùng, mặc định là 'agency'
$company_name = $_SESSION['company_name'] ?? ''; // Lấy tên công ty của user agency

$message = '';

if (isset($_SESSION['upload_message'])) {
    $message = $_SESSION['upload_message'];
    unset($_SESSION['upload_message']);
}

if (isset($_SESSION['booking_message'])) {
    $message = $_SESSION['booking_message'];
    unset($_SESSION['booking_message']);
}

// Logic để pre-fill form khi có yêu cầu copy
$copied_booking_data = [];
if (isset($_SESSION['copy_booking_data'])) {
    $copied_booking_data = $_SESSION['copy_booking_data'];
    unset($_SESSION['copy_booking_data']); // Xóa dữ liệu sau khi đã sử dụng
    $message = "<p class='message info'>Thông tin booking đã được điền sẵn vào form tạo đơn hàng mới. Vui lòng kiểm tra lại!</p>";
}

// Danh sách các quốc gia phổ biến (có thể mở rộng thêm)
$countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria",
    "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan",
    "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia",
    "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo (Brazzaville)", "Congo (Kinshasa)",
    "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czechia", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador",
    "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France",
    "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau",
    "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland",
    "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan",
    "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar",
    "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia",
    "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar (Burma)", "Namibia", "Nauru", "Nepal",
    "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan",
    "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar",
    "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia",
    "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa",
    "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan",
    "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
    "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
    "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
];


// Xử lý tạo booking (Chỉ Agency mới có quyền tạo)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Chỉ xử lý POST nếu đây là form tạo booking
    // Dựa vào việc có trường 'shipper_contact' để phân biệt với các POST request khác nếu có
    if (isset($_POST['shipper_contact'])) {
        if ($user_role !== 'agency') {
            $message = "<p class='message error'>❌ Bạn không có quyền tạo đơn hàng.</p>";
        } else {
            $shipper_agency_name = $company_name; // Lấy tên công ty từ session của Agency
            $shipper_contact = sanitize_input($_POST['shipper_contact'] ?? '');
            $shipper_address = sanitize_input($_POST['shipper_address'] ?? '');
            $shipper_phone = sanitize_input($_POST['shipper_phone'] ?? '');
            $shipper_id_tax = sanitize_input($_POST['shipper_id_tax'] ?? '');
            $shipper_email = sanitize_input($_POST['shipper_email'] ?? '');
            $shipper_country = sanitize_input($_POST['shipper_country'] ?? 'Vietnam'); // Lấy từ dropdown

            $service_type = sanitize_input($_POST['service_type'] ?? '');
            $warehouse = sanitize_input($_POST['warehouse'] ?? '');
            $shipment_type = sanitize_input($_POST['shipment_type'] ?? 'PACK');
            $gross_weight = floatval($_POST['gross_weight'] ?? 0);

            $number_of_packages = intval($_POST['number_of_packages'] ?? 1);
            $dimensions_text = sanitize_input($_POST['dimensions_text'] ?? '');

            $receiver_country = sanitize_input($_POST['receiver_country'] ?? ''); // Lấy từ dropdown
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

            if (empty($shipper_contact) || empty($receiver_contact) || empty($receiver_phone) || $gross_weight <= 0 || empty($service_type) || empty($receiver_address1)) {
                $message = "<p class='message error'>Vui lòng điền đầy đủ các thông tin bắt buộc và đảm bảo khối lượng lớn hơn 0.</p>";
            } else {
                try {
                    if (!isset($pdo) || $pdo === null) {
                        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    }

                    // THAY ĐỔI DÒNG NÀY: Truyền $user_id vào hàm generateUniqueReferenceCode
                    $reference_code = generateUniqueReferenceCode($pdo, $user_id);

                    $stmt = $pdo->prepare("INSERT INTO vmlbooking_orders
                        (user_id, shipper_agency_name, shipper_contact, shipper_address, shipper_phone, shipper_id_tax, shipper_email, shipper_country,
                        service_type, warehouse, reference_code, shipment_type, gross_weight, number_of_packages, dimensions_text,
                        receiver_country, receiver_company, receiver_contact, receiver_phone, receiver_tax_id, receiver_email, receiver_postal_code,
                        receiver_city, receiver_state, receiver_address1, receiver_address2, receiver_address3)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->execute([
                        $user_id,
                        $shipper_agency_name,
                        $shipper_contact,
                        $shipper_address,
                        $shipper_phone,
                        $shipper_id_tax,
                        $shipper_email,
                        $shipper_country,
                        $service_type,
                        $warehouse,
                        $reference_code,
                        $shipment_type,
                        $gross_weight,
                        $number_of_packages,
                        $dimensions_text,
                        $receiver_country,
                        $receiver_company,
                        $receiver_contact,
                        $receiver_phone,
                        $receiver_tax_id,
                        $receiver_email,
                        $receiver_postal_code,
                        $receiver_city,
                        $receiver_state,
                        $receiver_address1,
                        $receiver_address2,
                        $receiver_address3
                    ]);

                    $_SESSION['booking_message'] = "<p class='message success'>✅ Đã tạo booking thành công! Mã đơn hàng: " . htmlspecialchars($reference_code) . "</p>";
                    header("Location: index.php");
                    exit;

                } catch (PDOException $e) {
                    $message = "<p class='message error'>Lỗi khi tạo booking: " . htmlspecialchars($e->getMessage()) . "</p>";
                } catch (Exception $e) {
                    $message = "<p class='message error'>Lỗi hệ thống: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
    }
}

$bookings = [];
$total_bookings = 0;
$total_pages = 1;
$limit = 10; // Số lượng booking trên mỗi trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$filter_conditions = [];
$filter_params = [];

// Lấy các giá trị lọc từ URL và sanitize
$filter_reference_code = sanitize_input($_GET['reference_code'] ?? '');
$filter_start_date = sanitize_input($_GET['start_date'] ?? '');
$filter_end_date = sanitize_input($_GET['end_date'] ?? '');
$filter_service_type = sanitize_input($_GET['service_type'] ?? '');
$filter_receiver_country = sanitize_input($_GET['country'] ?? ''); // Tên biến trong GET là 'country'
$filter_agency_name = sanitize_input($_GET['agency'] ?? ''); // Tên biến trong GET là 'agency'

// Xây dựng điều kiện lọc
if (!empty($filter_reference_code)) {
    $filter_conditions[] = "o.reference_code LIKE ?";
    $filter_params[] = '%' . $filter_reference_code . '%';
}
if (!empty($filter_start_date)) {
    $filter_conditions[] = "DATE(o.created_at) >= ?";
    $filter_params[] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $filter_conditions[] = "DATE(o.created_at) <= ?";
    $filter_params[] = $filter_end_date; // Đã sửa lỗi: $filter_end_date
}
if (!empty($filter_service_type)) {
    $filter_conditions[] = "o.service_type LIKE ?";
    $filter_params[] = '%' . $filter_service_type . '%';
}
if (!empty($filter_receiver_country)) {
    $filter_conditions[] = "o.receiver_country LIKE ?";
    $filter_params[] = '%' . $filter_receiver_country . '%';
}

// Điều kiện lọc theo Agency Name chỉ áp dụng cho Admin, Viewer và Accounting
if (in_array($user_role, ['admin', 'viewer', 'accounting']) && !empty($filter_agency_name)) {
    $filter_conditions[] = "u.company_name LIKE ?";
    $filter_params[] = '%' . $filter_agency_name . '%';
}

// Điều kiện riêng cho từng vai trò
// CHÚ Ý: Đã thêm cost và sales_price vào SELECT
$base_query_select = "SELECT o.id, o.user_id, o.reference_code, o.shipper_contact, o.receiver_contact, o.receiver_country, o.created_at, o.updated_at, o.shipper_agency_name, o.gross_weight, o.service_type, o.status as booking_status, o.cost, o.sales_price, o.note, o.number_of_packages, o.dimensions_text, o.shipment_type, o.warehouse, o.shipper_phone, o.shipper_email, o.shipper_id_tax, o.shipper_address, o.shipper_country, o.receiver_company, o.receiver_contact, o.receiver_phone, o.receiver_tax_id, o.receiver_email, o.receiver_postal_code, o.receiver_city, o.receiver_state, o.receiver_address1, o.receiver_address2, o.receiver_address3, u.company_name as user_company_name FROM vmlbooking_orders o JOIN vmlbooking_users u ON o.user_id = u.id";
$base_query_count = "SELECT COUNT(o.id) FROM vmlbooking_orders o JOIN vmlbooking_users u ON o.user_id = u.id";

if ($user_role === 'agency') {
    $filter_conditions[] = "o.user_id = ?";
    $filter_params[] = $user_id;
}

$where_clause = '';
if (!empty($filter_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $filter_conditions);
}

$sql = $base_query_select . $where_clause . " ORDER BY o.created_at DESC LIMIT " . $limit . " OFFSET " . $offset;
$count_sql = $base_query_count . $where_clause;


try {
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Lấy tổng số booking (cho phân trang)
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($filter_params);
    $total_bookings = $stmt_count->fetchColumn();
    $total_pages = ceil($total_bookings / $limit);

    // Lấy booking cho trang hiện tại
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter_params);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy tài liệu đính kèm cho từng booking
    foreach ($bookings as &$booking) {
        // ADMIN, VIEWER, ACCOUNTING CÓ THỂ XEM TẤT CẢ TÀI LIỆU CỦA MỌI BOOKING
        if (in_array($user_role, ['admin', 'viewer', 'accounting'])) {
            $stmt_docs = $pdo->prepare("SELECT id, file_name, unique_file_name FROM vmlbooking_documents WHERE booking_id = ? ORDER BY uploaded_at ASC");
            $stmt_docs->execute([$booking['id']]);
        } else { // AGENCY CHỈ THẤY TÀI LIỆU CỦA BOOKING THUỘC VỀ HỌ
            $stmt_docs = $pdo->prepare("SELECT id, file_name, unique_file_name FROM vmlbooking_documents WHERE booking_id = ? AND user_id = ? ORDER BY uploaded_at ASC");
            $stmt_docs->execute([$booking['id'], $user_id]);
        }
        $booking['documents'] = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($booking); // Hủy tham chiếu cuối cùng

} catch (PDOException $e) {
    $message = "<p class='message error'>Lỗi khi tải danh sách booking: " . htmlspecialchars($e->getMessage()) . "</p>";
}

require_once "includes/header.php";
?>

<style>
/* Reset CSS (bạn có thể đã có hoặc thêm vào global stylesheet) */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    background-color: #f4f7f6;
    color: #333;
}
.container {
    max-width: 1200px;
    margin: 20px auto;
    background-color: #ffffff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
h2 {
    color: #0056b3;
    margin-top: 30px;
    margin-bottom: 20px;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

/* Messages */
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
    background-color: #e0f7fa;
    color: #007bff;
    border: 1px solid #00bcd4;
}
.message.warning { /* Thêm style cho thông báo cảnh báo */
    background-color: #fff3cd;
    color: #856404;
    border-color: #ffeeba;
}

/* Form Styles (Tạo Booking) */
.form-section-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}
.form-column {
    flex: 1;
    min-width: 300px;
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.form-column h3 {
    margin-top: 0;
    color: #0056b3;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.form-column label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
    font-size: 0.95em;
}
.form-column input[type="text"],
.form-column input[type="email"],
.form-column input[type="number"],
.form-column select {
    width: calc(100% - 22px); /* Account for padding and border */
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box; /* Include padding and border in the element's total width and height */
    font-size: 1em;
}
.read-only-field {
    background-color: #e9ecef;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    margin-bottom: 15px;
    color: #495057;
    font-weight: normal; /* Not bold */
}
.form-actions {
    text-align: center;
    margin-top: 20px;
}
.form-actions button {
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    margin: 0 10px;
    transition: background-color 0.3s ease;
}
.form-actions button:hover {
    background-color: #0056b3;
}

/* Filter Form Styles */
.filter-form-wrapper {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background-color: #f9f9f9;
}
.filter-form-wrapper form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.filter-form-wrapper label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    font-size: 0.9em;
}
.filter-form-wrapper input[type="text"],
.filter-form-wrapper input[type="date"],
.filter-form-wrapper select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9em;
    width: 100%; /* Flexible width */
    box-sizing: border-box;
}
.filter-form-wrapper button,
.filter-form-wrapper a.button {
    padding: 8px 15px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
    text-decoration: none;
    text-align: center;
    line-height: 1.2;
    transition: background-color 0.3s ease;
}
.filter-form-wrapper button:hover,
.filter-form-wrapper a.button:hover {
    opacity: 0.9;
}
.filter-form-wrapper button[type="button"] { /* For "Clear Filter" button */
    background-color: #6c757d;
}

/* Table Styles - Cập nhật cho thanh cuộn ngang trên/dưới */
.table-wrapper {
    overflow-x: hidden; /* Ban đầu không cuộn, chỉ cái .table-responsive bên trong cuộn */
    margin-top: 20px;
}

.horizontal-scroll-top {
    height: 15px; /* Chiều cao của thanh cuộn giả */
    overflow-x: scroll; /* Kích hoạt cuộn ngang cho thanh cuộn giả */
    overflow-y: hidden; /* Ẩn cuộn dọc */
    margin-bottom: -1px; /* Để sát với bảng */
    background-color: #f9f9f9; /* Nền của thanh cuộn trên */
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.horizontal-scroll-top > div {
    width: 100%; /* Chiều rộng ban đầu bằng 100% của container */
    height: 1px; /* Chiều cao tối thiểu để thanh cuộn hiển thị */
}

.table-responsive {
    overflow-x: auto; /* Kích hoạt cuộn ngang cho bảng chính */
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); /* Giữ bóng đổ cho bảng */
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    background-color: #fff;
}

table {
    width: 100%;
    border-collapse: collapse;
    /* margin-top: 20px; - Đã chuyển margin lên .table-wrapper */
    /* box-shadow: 0 2px 8px rgba(0,0,0,0.1); - Đã chuyển lên .table-responsive */
    background-color: #fff;
    min-width: 1200px; /* Đặt chiều rộng tối thiểu lớn hơn để đảm bảo cuộn ngang xuất hiện */
}
table th, table td {
    padding: 12px 15px;
    border: 1px solid #e9ecef;
    text-align: left;
    vertical-align: top;
    white-space: nowrap; /* Ngăn nội dung ô bị ngắt dòng */
}
table th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #343a40;
}
table tbody tr:nth-child(even) {
    background-color: #fefefe;
}
table tbody tr:hover {
    background-color: #e2f2ff; /* Light blue on hover */
}
.document-links a {
    display: inline-block;
    color: #007bff;
    text-decoration: none;
    margin-bottom: 3px;
    word-break: break-all; /* Ngắt từ nếu tên file quá dài */
    white-space: normal; /* Cho phép tên file dài xuống dòng */
}
.document-links a:hover {
    text-decoration: underline;
}
.document-links .button-delete-doc {
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 3px;
    padding: 2px 5px;
    cursor: pointer;
    font-size: 0.8em;
    line-height: 1;
    vertical-align: middle;
}
.document-links .button-delete-doc:hover {
    background-color: #c82333;
}

/* Action Buttons */
.action-buttons-top {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-start;
}
.action-buttons-top .button {
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-size: 1em;
    transition: background-color 0.3s ease;
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

/* Table action buttons */
.actions-buttons-group {
    white-space: nowrap;
}
.actions-buttons-group .button.small {
    display: block;
    width: fit-content;
    margin-bottom: 5px;
    padding: 6px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85em;
    text-align: center;
    transition: background-color 0.3s ease;
}
.button.small.view {
    background-color: #17a2b8;
    color: white;
}
.button.small.view:hover {
    background-color: #138496;
}
.button.small.edit {
    background-color: #ffc107;
    color: #333;
}
.button.small.edit:hover {
    background-color: #e0a800;
}
.button.small.delete {
    background-color: #dc3545;
    color: white;
}
.button.small.delete:hover {
    background-color: #c82333;
}
/* New styles for Cancel and Copy buttons */
.button.small.cancel {
    background-color: #fd7e14; /* Orange */
    color: white;
}
.button.small.cancel:hover {
    background-color: #e66a00;
}
.button.small.copy {
    background-color: #6f42c1; /* Purple */
    color: white;
}
.button.small.copy:hover {
    background-color: #5f36a4;
}
/* Style for disabled links/buttons */
.button.small.disabled-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none; /* Prevents click events */
}


td form button[type="submit"] {
    background-color: #28a745;
    color: white;
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85em;
    margin-top: 5px;
    transition: background-color 0.3s ease;
}
td form button[type="submit"]:hover {
    background-color: #218838;
}
td .button {
    background-color: #6f42c1;
    color: white;
}
td .button:hover {
    background-color: #5f36a4;
}
td .button[onclick*="Invoice"] {
    background-color: #007bff;
}
td .button[onclick*="Invoice"]:hover {
    background-color: #0056b3;
}
td .button[href*="shipping_mark"] {
    background-color: #20c997;
}
td .button[href*="shipping_mark"]:hover {
    background-color: #17a689;
}

/* Pagination Styles */
.pagination {
    margin-top: 20px;
    text-align: center;
    padding-bottom: 20px;
}
.pagination a {
    display: inline-block;
    padding: 8px 16px;
    margin: 0 4px;
    border: 1px solid #ddd;
    color: #007bff;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s ease, color 0.3s ease;
}
.pagination a.active {
    background-color: #007bff;
    color: white;
    border: 1px solid #007bff;
}
.pagination a:hover:not(.active) {
    background-color: #f2f2f2;
}

/* Excel Upload Form Styles */
.excel-upload-section {
    background-color: #e6f7ff; /* Light blue background */
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #91d5ff; /* Blue border */
    margin-bottom: 25px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: flex-start;
}

.excel-upload-section h3 {
    margin-top: 0;
    color: #0056b3;
    border-bottom: 1px solid #a6d9f6;
    padding-bottom: 10px;
    width: 100%;
}

.excel-upload-section .form-group {
    display: flex;
    flex-direction: column;
    width: 100%; /* Take full width */
    max-width: 400px; /* Limit width for better appearance */
}

.excel-upload-section label {
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
    font-size: 1em;
}

.excel-upload-section input[type="file"] {
    padding: 8px;
    border: 1px solid #a6d9f6;
    border-radius: 4px;
    background-color: #ffffff;
    cursor: pointer;
    font-size: 0.95em;
}

.excel-upload-section .button.upload {
    background-color: #1890ff; /* Blue for upload */
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    border: none;
    transition: background-color 0.3s ease;
}

.excel-upload-section .button.upload:hover {
    background-color: #096dd9;
}

.excel-upload-section .button.download-template {
    background-color: #52c41a; /* Green for download */
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    text-decoration: none;
    display: inline-block;
    margin-top: 10px; /* Space above download button */
    border: none;
    transition: background-color 0.3s ease;
}

.excel-upload-section .button.download-template:hover {
    background-color: #389e08;
}

</style>

<div class="container">
<?php if (!empty($message)): ?>
    <?php echo $message; ?>
<?php endif; ?>

<?php // CHỈ HIỂN THỊ FORM UPLOAD EXCEL NẾU LÀ VAI TRÒ 'ADMIN' HOẶC 'AGENCY' ?>
<?php if (in_array($user_role, ['admin', 'agency'])): ?>
    <h2>Tải lên Booking bằng Excel</h2>
    <div class="excel-upload-section">
        <h3>Tải lên file Excel Booking</h3>
        <form action="upload_bookings_excel.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Chọn file Excel (.xls hoặc .xlsx):</label>
                <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx" required>
            </div>
            <button type="submit" class="button upload">Tải lên Booking</button>
        </form>
        <a href="download_excel_template.php" class="button download-template">Tải xuống File Excel Template</a>
        <p style="font-size: 0.9em; color: #666; margin-top: 15px;">
            Vui lòng tải xuống file template và điền thông tin booking vào đó trước khi tải lên.
            Đảm bảo các cột bắt buộc được điền đầy đủ để tránh lỗi.
        </p>
    </div>
<?php endif; ?>


<?php // CHỈ HIỂN THỊ FORM TẠO BOOKING NẾU LÀ VAI TRÒ 'AGENCY' ?>
<?php if ($user_role === 'agency'): ?>
    <h2>Tạo Đơn Hàng Mới (Bill Online)</h2>
    <form method="post">
        <div class="form-section-wrapper">
            <div class="form-column">
                <h3>Thông tin người gửi (Shipper)</h3>
                <label>Tên Agency:</label>
                <p class="read-only-field"><?= htmlspecialchars($company_name) ?></p>
                <input type="hidden" name="shipper_agency_name_hidden" value="<?= htmlspecialchars($company_name) ?>">

                <label for="shipper_contact">Người LH (contact name) (*):</label>
                <input type="text" id="shipper_contact" name="shipper_contact" placeholder="Tên người gửi" required value="<?= htmlspecialchars($copied_booking_data['shipper_contact'] ?? '') ?>">

                <label for="shipper_address">Địa chỉ:</label>
                <input type="text" id="shipper_address" name="shipper_address" placeholder="Địa chỉ người gửi" value="<?= htmlspecialchars($copied_booking_data['shipper_address'] ?? '') ?>">

                <label for="shipper_phone">ĐT (tel):</label>
                <input type="text" id="shipper_phone" name="shipper_phone" placeholder="Số điện thoại người gửi" value="<?= htmlspecialchars($copied_booking_data['shipper_phone'] ?? '') ?>">

                <label for="shipper_id_tax">Mã số thuế/CCCD/CMND:</label>
                <input type="text" id="shipper_id_tax" name="shipper_id_tax" placeholder="Mã số thuế/CCCD/CMND" value="<?= htmlspecialchars($copied_booking_data['shipper_id_tax'] ?? '') ?>">

                <label for="shipper_email">Email:</label>
                <input type="email" id="shipper_email" name="shipper_email" placeholder="Email người gửi" value="<?= htmlspecialchars($copied_booking_data['shipper_email'] ?? '') ?>">

                <label for="shipper_country">Quốc gia:</label>
                <select id="shipper_country" name="shipper_country">
                    <?php
                    $selected_shipper_country = $copied_booking_data['shipper_country'] ?? 'Vietnam'; // Mặc định là Vietnam
                    foreach ($countries as $country) {
                        $selected = ($country == $selected_shipper_country) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($country) . "\" " . $selected . ">" . htmlspecialchars($country) . "</option>";
                    }
                    ?>
                </select>

                <h3>Thông tin đơn hàng (Info Shipment)</h3>
                <label for="gross_weight">Cân nặng (gross weight) (kg) (*):</label>
                <input type="number" step="0.01" id="gross_weight" name="gross_weight" placeholder="Ví dụ: 1.5, 10.25" required value=""> <!-- KHÔNG COPY -->

                <label for="number_of_packages">Số lượng kiện (No. of packages):</label>
                <input type="number" id="number_of_packages" name="number_of_packages" value="1" min="1" placeholder="Số lượng kiện hàng"> <!-- KHÔNG COPY -->

                <label for="dimensions_text">Kích thước (Dài x Rộng x Cao) (cm):</label>
                <input type="text" id="dimensions_text" name="dimensions_text" placeholder="Ví dụ: D10R20C30 (1 kiện), D15R25C35 (2 kiện)" value=""> <!-- KHÔNG COPY -->
                <small style="display: block; margin-top: -5px; margin-bottom: 10px; color: #666;">Nhập theo định dạng ví dụ để dễ đọc.</small>

                <label for="service_type">Loại hình dịch vụ (Service Type) (*):</label>
                <input type="text" id="service_type" name="service_type" placeholder="Ví dụ: Express, Economy, Standard" required value="<?= htmlspecialchars($copied_booking_data['service_type'] ?? '') ?>">

                <label for="shipment_type">Loại (type):</label>
                <select id="shipment_type" name="shipment_type">
                    <option value="PACK" <?= (isset($copied_booking_data['shipment_type']) && $copied_booking_data['shipment_type'] == 'PACK') ? 'selected' : '' ?>>PACK</option>
                    <option value="DOC" <?= (isset($copied_booking_data['shipment_type']) && $copied_booking_data['shipment_type'] == 'DOC') ? 'selected' : '' ?>>DOC</option>
                </select>

                <label for="warehouse">Kho hàng:</label>
                <input type="text" id="warehouse" name="warehouse" placeholder="Tên kho hàng (ví dụ: HCM Warehouse)" value="<?= htmlspecialchars($copied_booking_data['warehouse'] ?? '') ?>">
            </div>

            <div class="form-column">
                <h3>Thông tin người nhận (Receiver)</h3>
                <label for="receiver_country">Nước đến (country) (*):</label>
                <select id="receiver_country" name="receiver_country" required>
                    <option value="">-- Chọn quốc gia --</option>
                    <?php
                    $selected_receiver_country = $copied_booking_data['receiver_country'] ?? '';
                    foreach ($countries as $country) {
                        $selected = ($country == $selected_receiver_country) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($country) . "\" " . $selected . ">" . htmlspecialchars($country) . "</option>";
                    }
                    ?>
                </select>

                <label for="receiver_company">Cty (company name):</label>
                <input type="text" id="receiver_company" name="receiver_company" placeholder="Tên công ty (nếu có)" value="<?= htmlspecialchars($copied_booking_data['receiver_company'] ?? '') ?>">

                <label for="receiver_contact">Người LH (contact name) (*):</label>
                <input type="text" id="receiver_contact" name="receiver_contact" placeholder="Tên người nhận" required value="<?= htmlspecialchars($copied_booking_data['receiver_contact'] ?? '') ?>">

                <label for="receiver_phone">ĐT (tel) (*):</label>
                <input type="text" id="receiver_phone" name="receiver_phone" placeholder="Số điện thoại người nhận" required value="<?= htmlspecialchars($copied_booking_data['receiver_phone'] ?? '') ?>">

                <label for="receiver_tax_id">Tax ID:</label>
                <input type="text" id="receiver_tax_id" name="receiver_tax_id" placeholder="Mã số thuế người nhận" value="<?= htmlspecialchars($copied_booking_data['receiver_tax_id'] ?? '') ?>">

                <label for="receiver_email">Email:</label>
                <input type="email" id="receiver_email" name="receiver_email" placeholder="Email người nhận" value="<?= htmlspecialchars($copied_booking_data['receiver_email'] ?? '') ?>">

                <label for="receiver_postal_code">Postal code:</label>
                <input type="text" id="receiver_postal_code" name="receiver_postal_code" placeholder="Mã bưu chính (Postal code)" value="<?= htmlspecialchars($copied_booking_data['receiver_postal_code'] ?? '') ?>">

                <label for="receiver_city">Thành phố (city):</label>
                <input type="text" id="receiver_city" name="receiver_city" placeholder="Thành phố" value="<?= htmlspecialchars($copied_booking_data['receiver_city'] ?? '') ?>">

                <label for="receiver_state">Tỉnh (State):</label>
                <input type="text" id="receiver_state" name="receiver_state" placeholder="Tỉnh/Bang" value="<?= htmlspecialchars($copied_booking_data['receiver_state'] ?? '') ?>">

                <label for="receiver_address1">Địa chỉ 1 (address 1) (*):</label>
                <input type="text" id="receiver_address1" name="receiver_address1" placeholder="Số nhà, tên đường, thôn, xóm..." required value="<?= htmlspecialchars($copied_booking_data['receiver_address1'] ?? '') ?>">

                <label for="receiver_address2">Địa chỉ 2 (address 2):</label>
                <input type="text" id="receiver_address2" name="receiver_address2" placeholder="Phường, xã, thị trấn..." value="<?= htmlspecialchars($copied_booking_data['receiver_address2'] ?? '') ?>">

                <label for="receiver_address3">Địa chỉ 3 (address 3):</label>
                <input type="text" id="receiver_address3" name="receiver_address3" placeholder="Quận, huyện, thị xã..." value="<?= htmlspecialchars($copied_booking_data['receiver_address3'] ?? '') ?>">

                <p style="font-size: 0.9em; color: #888; margin-top: 20px;">
                    Hệ thống Kiểm tra VSVX chỉ mang tính chất tham khảo. Vui lòng tự kiểm tra VSVX với hàng trước khi gửi hàng!
                </p>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit">Tạo Booking</button>
        </div>
    </form>
<?php endif; ?>

<?php // Hiển thị bộ lọc cho Admin, Viewer, Accounting, Agency ?>
<?php if (in_array($user_role, ['admin', 'viewer', 'accounting', 'agency'])): ?>
    <h2>Bộ lọc Booking</h2>
    <div class="filter-form-wrapper">
        <form method="get" action="index.php">
            <div>
                <label for="filter_reference_code">Mã đơn hàng:</label>
                <input type="text" id="filter_reference_code" name="reference_code" value="<?= htmlspecialchars($filter_reference_code) ?>" placeholder="Mã đơn hàng">
            </div>
            <div>
                <label for="filter_start_date">Ngày tạo (Từ):</label>
                <input type="date" id="filter_start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
            </div>
            <div>
                <label for="filter_end_date">Ngày tạo (Đến):</label>
                <input type="date" id="filter_end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
            </div>
            <div>
                <label for="filter_service_type">Loại hình DV:</label>
                <input type="text" id="filter_service_type" name="service_type" value="<?= htmlspecialchars($filter_service_type) ?>" placeholder="Loại dịch vụ">
            </div>
            <div>
                <label for="filter_country">Nước đến:</label>
                <input type="text" id="filter_country" name="country" value="<?= htmlspecialchars($filter_receiver_country) ?>" placeholder="Quốc gia">
            </div>
            <?php if (in_array($user_role, ['admin', 'viewer', 'accounting'])): // Chỉ hiển thị cho Admin, Viewer, Accounting ?>
            <div>
                <label for="filter_agency">Tên Agency:</label>
                <input type="text" id="filter_agency" name="agency" value="<?= htmlspecialchars($filter_agency_name) ?>" placeholder="Tên Agency">
            </div>
            <?php endif; ?>
            <button type="submit">Lọc</button>
            <button type="button" onclick="window.location.href='index.php'">Xóa lọc</button>
            
            <?php
            // Xây dựng URL cho nút xuất CSV với tất cả các tham số lọc hiện tại
            $export_params = $_GET; // Lấy tất cả các tham số GET hiện tại
            // Đảm bảo không có tham số nào liên quan đến phân trang nếu có
            unset($export_params['page']);
            $export_url = 'export_bookings.php?' . http_build_query($export_params);
            ?>
            <a href="<?= htmlspecialchars($export_url) ?>" class="button success" style="margin-left: 10px;">Tải xuống CSV</a>
        </form>
    </div>
<?php endif; ?>


<h2>Danh sách Booking <?php
    if ($user_role === 'admin') echo '(Tất cả Booking trong hệ thống)';
    else if ($user_role === 'viewer' || $user_role === 'accounting') echo '(Tất cả Booking)';
    else echo 'của bạn'; // Đối với Agency
?></h2>

<div class="table-wrapper">
    <div class="horizontal-scroll-top">
        <div></div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>STT</th> <!-- Đã thay thế ID bằng STT -->
                    <?php if ($user_role !== 'agency'): ?>
                    <th>Agency</th>
                    <?php endif; ?>
                    <th>Mã đơn</th>
                    <th>Người gửi</th> <!-- Luôn hiển thị người gửi -->
                    <th>Người nhận</th>
                    <th>Nước nhận</th>
                    <th>Cân nặng (kg)</th>
                    <th>Loại dịch vụ</th> <!-- Luôn hiển thị loại dịch vụ -->
                    <th>Trạng thái</th>
                    <?php if (in_array($user_role, ['admin', 'accounting'])): ?>
                    <th>Chi phí</th>
                    <th>Giá bán</th>
                    <th>Ghi chú</th> <!-- Thêm cột ghi chú vào bảng -->
                    <?php endif; ?>
                    <th>Ngày tạo</th> <!-- Di chuyển Ngày tạo ra đây -->
                    <?php if ($user_role === 'admin'): ?>
                    <th>Ngày cập nhật</th>
                    <?php endif; ?>
                    <th>Tài liệu đính kèm</th>
                    <?php if ($user_role === 'agency'): ?>
                    <th>Upload</th>
                    <?php endif; ?>
                    <th>In chứng từ</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($bookings) > 0): ?>
                <?php $stt = $offset + 1; // Khởi tạo số thứ tự ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><?= $stt++ ?></td> <!-- Hiển thị số thứ tự và tăng lên -->
                    <?php if ($user_role !== 'agency'): ?>
                    <td><?= htmlspecialchars($b['user_company_name'] ?? $b['shipper_agency_name']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($b['reference_code']) ?></td>
                    <td><?= htmlspecialchars($b['shipper_contact']) ?></td> <!-- Luôn hiển thị người gửi -->
                    <td><?= htmlspecialchars($b['receiver_contact']) ?></td>
                    <td><?= htmlspecialchars($b['receiver_country']) ?></td>
                    <td><?= htmlspecialchars($b['gross_weight'] ?? '') ?></td>
                    <td><?= htmlspecialchars($b['service_type'] ?? '') ?></td> <!-- Luôn hiển thị loại dịch vụ -->
                    <td><?= htmlspecialchars($b['booking_status'] ?? 'Pending') ?></td>
                    <?php if (in_array($user_role, ['admin', 'accounting'])): ?>
                    <td><?= number_format($b['cost'] ?? 0, 2) ?></td>
                    <td><?= number_format($b['sales_price'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($b['note'] ?? '') ?></td> <!-- Hiển thị ghi chú -->
                    <?php endif; ?>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($b['created_at']))) ?></td> <!-- Hiển thị ngày tạo -->
                    <?php if ($user_role === 'admin'): ?>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($b['updated_at'] ?? $b['created_at']))) ?></td>
                    <?php endif; ?>
                    <td>
                        <div class="document-links">
                            <?php if (!empty($b['documents'])): ?>
                                <?php foreach ($b['documents'] as $doc): ?>
                                    <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 3px;">
                                        <a href="download_secure.php?doc_id=<?= htmlspecialchars($doc['id']) ?>" target="_blank" title="Tải về: <?= htmlspecialchars($doc['file_name']) ?>">
                                            📎 <?= htmlspecialchars($doc['file_name']) ?>
                                        </a>
                                        <?php if ($user_role === 'agency' || $user_role === 'admin'): ?>
                                        <form action="delete_document.php" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa file <?= htmlspecialchars($doc['file_name']) ?> này không?');">
                                            <input type="hidden" name="document_id" value="<?= htmlspecialchars($doc['id']) ?>">
                                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['id']) ?>">
                                            <button type="submit" class="button-delete-doc" title="Xóa file này">X</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                Không có tài liệu
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php if ($user_role === 'agency'): ?>
                    <td>
                        <form action="upload.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['id']) ?>">
                            <input type="file" name="file_upload" required style="width: 100px; font-size: 0.85em;">
                            <button type="submit" title="Tải lên chứng từ cho đơn hàng này" class="button small">Upload</button>
                        </form>
                    </td>
                    <?php endif; ?>
                    <td>
                        <a href="print_bill.php?id=<?= htmlspecialchars($b['id']) ?>" target="_blank" class="button small">In Bill</a><br>
                        <a href="generate_shipping_mark.php?id=<?= htmlspecialchars($b['id']) ?>" target="_blank" class="button small">In Shipping Mark</a><br>
                        <button type="button" onclick="alert('Chức năng In Invoice sẽ được triển khai sau.')" class="button small">In Invoice</button>
                    </td>
                    <td class="actions-buttons-group">
                        <a href="view_booking.php?id=<?= htmlspecialchars($b['id']) ?>" class="button view small">Xem</a>

                        <?php
                        $is_cancelled = ($b['booking_status'] === 'Cancelled');
                        $edit_link = $is_cancelled ? '#' : 'edit_booking.php?id=' . htmlspecialchars($b['id']);
                        $edit_class = $is_cancelled ? 'button edit small disabled-link' : 'button edit small';
                        $onclick_attr = $is_cancelled ? 'onclick="return false;"' : '';
                        ?>
                        <?php if ($user_role === 'admin' || ($user_role === 'agency' && $b['user_id'] === $user_id)): ?>
                            <a href="<?= $edit_link ?>" class="<?= $edit_class ?>" <?= $onclick_attr ?>>Sửa</a>
                        <?php endif; ?>

                        <?php // Nút HỦY - Chỉ hiển thị cho Agency (tạo booking đó) và Admin, nếu trạng thái là 'Pending' ?>
                        <?php if (($user_role === 'agency' && $b['user_id'] == $user_id && $b['booking_status'] === 'pending') || $user_role === 'admin'): ?>
                            <form action="cancel_booking.php" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn hủy booking <?= htmlspecialchars($b['reference_code']) ?> này không?');">
                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['id']) ?>">
                                <button type="submit" class="button small cancel">Hủy</button>
                            </form>
                        <?php endif; ?>

                        <?php // Nút COPY - Chỉ hiển thị cho Agency (tạo booking đó)?>
                        <?php if ($user_role === 'agency'): ?>
                            <form action="copy_booking.php" method="post">
                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['id']) ?>">
                                <button type="submit" class="button small copy">Copy</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($user_role === 'admin'): ?>
                            <a href="delete_booking.php?id=<?= htmlspecialchars($b['id']) ?>" class="button delete small" onclick="return confirm('Bạn có chắc chắn muốn xóa booking này? Đây là hành động không thể hoàn tác!');">Xóa</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="
                        <?php
                        $colspan_count = 0;
                        $colspan_count += 1; // STT
                        if ($user_role !== 'agency') { $colspan_count += 1; } // Agency
                        $colspan_count += 1; // Mã đơn
                        $colspan_count += 1; // Người gửi
                        $colspan_count += 1; // Người nhận
                        $colspan_count += 1; // Nước nhận
                        $colspan_count += 1; // Cân nặng
                        $colspan_count += 1; // Loại dịch vụ
                        $colspan_count += 1; // Trạng thái
                        if (in_array($user_role, ['admin', 'accounting'])) { $colspan_count += 3; } // Chi phí, Giá bán, Ghi chú
                        $colspan_count += 1; // Ngày tạo
                        if ($user_role === 'admin') { $colspan_count += 1; } // Ngày cập nhật
                        $colspan_count += 1; // Tài liệu đính kèm
                        if ($user_role === 'agency') { $colspan_count += 1; } // Upload
                        $colspan_count += 1; // In chứng từ
                        $colspan_count += 1; // Thao tác
                        echo $colspan_count;
                        ?>
                    " style="text-align: center;">Chưa có đơn hàng nào được tạo hoặc không tìm thấy theo bộ lọc.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>">Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => '']), '', '&', PHP_QUERY_RFC3986) ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET, '', '&', PHP_QUERY_RFC3986) ?>">Tiếp</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>
<?php require_once "includes/footer.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const horizontalScrollTop = document.querySelector('.horizontal-scroll-top');
    const tableResponsive = document.querySelector('.table-responsive');
    const table = document.querySelector('.table-responsive table');

    if (horizontalScrollTop && tableResponsive && table) {
        // Cài đặt chiều rộng của div bên trong thanh cuộn trên cùng bằng chiều rộng thực của bảng
        // Điều này đảm bảo thanh cuộn trên cùng có độ dài tương ứng với bảng
        const updateScrollWidth = () => {
            horizontalScrollTop.querySelector('div').style.width = table.scrollWidth + 'px';
        };

        // Cập nhật chiều rộng khi trang tải và khi cửa sổ được resize
        updateScrollWidth();
        window.addEventListener('resize', updateScrollWidth);

        // Đồng bộ hóa cuộn ngang từ thanh cuộn trên cùng xuống bảng
        horizontalScrollTop.addEventListener('scroll', function() {
            tableResponsive.scrollLeft = this.scrollLeft;
        });

        // Đồng bộ hóa cuộn ngang từ bảng lên thanh cuộn trên cùng
        tableResponsive.addEventListener('scroll', function() {
            horizontalScrollTop.scrollLeft = this.scrollLeft;
        });
    }
});
</script>