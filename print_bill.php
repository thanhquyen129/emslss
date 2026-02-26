<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php';

require_once 'includes/tcpdf/tcpdf.php'; // Đảm bảo đường dẫn này đúng

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// LẤY VAI TRÒ CỦA NGƯỜI DÙNG TỪ SESSION
// Đảm bảo lấy đúng biến session chứa vai trò
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'agency';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Booking ID không hợp lệ.");
}

$booking_id = (int)$_GET['id'];

try {
    // Đảm bảo $pdo đã được khởi tạo từ includes/config.php
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $sql = "SELECT * FROM vmlbooking_orders WHERE id = ?";
    $params = [$booking_id];

    // ĐIỀU CHỈNH ĐIỀU KIỆN TRUY VẤN DỰA TRÊN VAI TRÒ
    // Admin, Viewer, và ACCOUNTING có thể xem bất kỳ booking nào.
    if ($user_role === 'admin' || $user_role === 'viewer' || $user_role === 'accounting') {
        // Không thêm điều kiện user_id, cho phép xem tất cả booking chỉ dựa vào ID.
    } else {
        // Chỉ cho phép xem booking do user hiện tại tạo.
        $sql .= " AND user_id = ?";
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kiểm tra nếu không tìm thấy booking hoặc không có quyền truy cập
    if (!$booking) {
        // Cung cấp thông báo lỗi rõ ràng hơn dựa trên vai trò
        if ($user_role === 'admin' || $user_role === 'viewer' || $user_role === 'accounting') {
            die("Không tìm thấy Booking với ID: " . htmlspecialchars($booking_id));
        } else {
            die("Không tìm thấy Booking hoặc bạn không có quyền truy cập Booking này.");
        }
    }

    // --- BẮT ĐẦU PHẦN TẠO PDF VỚI LAYOUT A4 NGANG (GIỮ NGUYÊN NHƯ PHIÊN BẢN TRƯỚC) ---

    // Cấu hình TCPDF cho A4 ngang
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('VML Express');
    $pdf->SetTitle('Air Waybill - ' . $booking['reference_code']);
    $pdf->SetSubject('Shipment Air Waybill');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetAutoPageBreak(TRUE, 5);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 7); // Font mặc định
    $pdf->AddPage();

    // --- ĐỊNH NGHĨA BIẾN LAYOUT TRƯỚC KHI SỬ DỤNG ---
    $page_width_a4 = 297; // Chiều rộng của A4 ngang
    $page_height_a4 = 210; // Chiều cao của A4 ngang

    $print_area_width = $page_height_a4 / 2; // Chiều rộng tương đương A5 dọc (105mm)
    $print_area_height = $page_width_a4 - 2 * PDF_MARGIN_TOP; // Chiều cao tối đa của vùng A5 trên A4 ngang

    // Canh lề cho toàn bộ vùng bill (giả lập A5 trên A4 ngang)
    $margin_side = 5; // Lề trái, phải của vùng A5 ảo trên trang A4 ngang
    $margin_top_bottom = 5; // Lề trên, dưới của vùng A5 ảo trên trang A4 ngang
    
    $offset_x = $margin_side; // Vị trí bắt đầu X của vùng in (từ lề trái A4)
    $offset_y = $margin_top_bottom; // Vị trí bắt đầu Y của vùng in (từ lề trên A4)

    // Cấu hình border style (dày hơn và màu vàng nhạt)
    $border_width = 0.4; // Độ dày của đường viền (điểm).
    $border_color_rgb = array(180, 180, 0); // Màu vàng nhạt (RGB)

    // --- VẼ KHUNG CHÍNH CHO TOÀN BỘ BILL (VÙNG A5) ---
    // Thiết lập style cho khung chính
    $pdf->SetLineStyle(array('width' => 0.6, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $offset_y, $print_area_width, $print_area_height - ($margin_top_bottom * 2), 'D'); // Vẽ khung chính

    // Đặt lại màu và độ dày mặc định cho các khung con
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);

    $box_padding = 2; // Padding bên trong các box con

    // --- DÒNG CHỮ "SHIPMENT HAWB" TRÊN CÙNG ---
    $currentY = $offset_y + $box_padding; // Bắt đầu từ lề trên của khung chính
    $pdf->SetFont('dejavusans', 'B', 16); // Font lớn và đậm
    $hawb_text = 'SHIPMENT HAWB';
    $hawb_text_width = $pdf->GetStringWidth($hawb_text, 'dejavusans', 'B', 16);
    $hawb_x = $offset_x + ($print_area_width - $hawb_text_width) / 2; // Căn giữa
    $pdf->Text($hawb_x, $currentY, $hawb_text);
    $currentY += 10; // Di chuyển xuống để chừa chỗ cho tiêu đề

    // --- TOP SECTION (Logo, Barcode, Booking Code) ---
    $currentY += $box_padding; // Thêm padding sau tiêu đề

    // VML Logo (góc trên bên trái của vùng in A5)
    $logo_path = 'images/vml_logo.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, $offset_x + $box_padding, $currentY, 20, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Text($offset_x + $box_padding, $currentY, 'VML EXPRESS');
        $pdf->SetFont('dejavusans', '', 7);
    }

    // Barcode và Mã đơn hàng (Góc trên bên phải của vùng A5)
    $style1D = array(
        'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
        'border' => 0, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => array(0,0,0), 'bgcolor' => false,
        'text' => false, // text: false để không in mã bên dưới barcode tự động (chúng ta sẽ in thủ công)
        'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 4
    );
    $barcode_width = 60;
    $barcode_height = 10;
    
    $barcode_x_top = $offset_x + $print_area_width - $barcode_width - $box_padding +18;
    $pdf->write1DBarcode($booking['reference_code'], 'C128', $barcode_x_top, $currentY + 1, $barcode_width, $barcode_height, 0.4, $style1D, 'N');
    
    // Mã đơn hàng dưới barcode
    $pdf->SetFont('dejavusans', 'B', 9);
    $booking_code_text = htmlspecialchars($booking['reference_code']);
    $booking_code_width = $pdf->GetStringWidth($booking_code_text, 'dejavusans', 'B', 9);
    $pdf->Text($barcode_x_top + ($barcode_width - $booking_code_width) / 2 -8, $currentY + $barcode_height + 1, $booking_code_text);
    $pdf->SetFont('dejavusans', '', 7);


    $currentY += 20; // Di chuyển xuống sau phần logo/barcode

    // Hàng chứa Date, Origin Code, Destination Code
    $box_height_date_codes = 10;
    
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $currentY, $print_area_width, $box_height_date_codes, 'D');

    // Ngày tạo bill (Date) - Căn trái trong box
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->Text($offset_x + $box_padding, $currentY + $box_padding, date('d/m/Y H:i', strtotime($booking['created_at'])));

    // Origin Country Code / Destination Code - Căn phải trong box
    $origin_code = htmlspecialchars(strtoupper($booking['shipper_country'] ?? 'VN'));
    $destination_code = htmlspecialchars(strtoupper($booking['receiver_country']));
    $codes_text = $origin_code . ' / ' . $destination_code;

    $pdf->SetFont('dejavusans', 'B', 14);
    $codes_text_width = $pdf->GetStringWidth($codes_text, 'dejavusans', 'B', 14);
    $x_codes = $offset_x + $print_area_width - $codes_text_width - $box_padding;
    $pdf->Text($x_codes, $currentY + $box_padding, $codes_text);
    
    $currentY += $box_height_date_codes + 5;

    // --- SHIPPER DETAILS ---
    $shipper_box_height = 35;
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $currentY, $print_area_width, $shipper_box_height, 'D');
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($offset_x + $box_padding, $currentY + $box_padding, 'Shipper Details:');
    $pdf->SetFont('dejavusans', '', 7);

    $detail_start_x = $offset_x + $box_padding + 2;
    $detail_current_y = $currentY + $box_padding + 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Shipper\'s account:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 30, $detail_current_y, htmlspecialchars($_SESSION['agency_username'] ?? '')); // Lấy từ session
    $detail_current_y += 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Company name/Contact:');
    $pdf->SetFont('dejavusans', '', 7);
    $shipper_name = !empty($booking['shipper_agency_name']) ? $booking['shipper_agency_name'] : $booking['shipper_contact'];
    $pdf->Text($detail_start_x + 40, $detail_current_y, htmlspecialchars($shipper_name ?? ''));
    $detail_current_y += 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Phone:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 15, $detail_current_y, htmlspecialchars($booking['shipper_phone'] ?? ''));
    $detail_current_y += 5;
    
    // Email (nếu có)
    if (!empty($booking['shipper_email'])) {
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->Text($detail_start_x, $detail_current_y, 'Email:');
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->Text($detail_start_x + 15, $detail_current_y, htmlspecialchars($booking['shipper_email'] ?? ''));
    }

    $currentY += $shipper_box_height + 5;

    // --- RECEIVER DETAILS ---
    $receiver_box_height = 40; // Chiều cao tối ưu cho Receiver Details
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $currentY, $print_area_width, $receiver_box_height, 'D');
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($offset_x + $box_padding, $currentY + $box_padding, 'Receiver Details:');
    $pdf->SetFont('dejavusans', '', 7);

    $detail_start_x = $offset_x + $box_padding + 2;
    $detail_current_y = $currentY + $box_padding + 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Company name:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 28, $detail_current_y, htmlspecialchars($booking['receiver_company'] ?? ''));
    $detail_current_y += 5; // Giảm khoảng cách dòng

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Address:');
    $pdf->SetFont('dejavusans', '', 7);
    $address_lines_rec = [];
    if (!empty($booking['receiver_address1'])) $address_lines_rec[] = htmlspecialchars($booking['receiver_address1']);
    if (!empty($booking['receiver_address2'])) $address_lines_rec[] = htmlspecialchars($booking['receiver_address2']);
    if (!empty($booking['receiver_address3'])) $address_lines_rec[] = htmlspecialchars($booking['receiver_address3']);
    
    $full_address = implode(', ', $address_lines_rec);
    if (!empty($booking['receiver_city'])) $full_address .= ', ' . htmlspecialchars($booking['receiver_city']);
    if (!empty($booking['receiver_state'])) $full_address .= ', ' . htmlspecialchars($booking['receiver_state']);
    if (!empty($booking['receiver_postal_code'])) $full_address .= ', ' . htmlspecialchars($booking['receiver_postal_code']);

    // Sử dụng MultiCell cho địa chỉ để xuống dòng nếu dài
    $pdf->MultiCell(
        $print_area_width - $box_padding * 2 - 18, // Chiều rộng cho MultiCell
        0, // Chiều cao tự động
        $full_address,
        0, // Không viền
        'L', // Căn trái
        0, // Không tô màu
        1, // Xuống dòng sau khi in
        $detail_start_x + 18, // Vị trí X bắt đầu cho nội dung địa chỉ
        $detail_current_y, // Vị trí Y
        true, // Kéo giãn
        0, false, true, 0, 'T', false
    );
    $detail_current_y = $pdf->GetY(); // Cập nhật Y sau MultiCell, để các dòng tiếp theo không bị chồng chéo

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y + 1, 'Country:'); // +1mm để căn chỉnh tốt hơn
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 18, $detail_current_y + 1, htmlspecialchars($booking['receiver_country'] ?? ''));
    $detail_current_y += 5; // Giảm khoảng cách dòng

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Contact person:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 28, $detail_current_y, htmlspecialchars($booking['receiver_contact'] ?? ''));
    $detail_current_y += 5; // Giảm khoảng cách dòng

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Phone:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 15, $detail_current_y, htmlspecialchars($booking['receiver_phone'] ?? '') . ' / ' . htmlspecialchars($booking['receiver_email'] ?? '')); // Kết hợp phone và email như layout trước
    $detail_current_y += 5; // Giảm khoảng cách dòng


    // Di chuyển Y sau khi kết thúc Receiver Details.
    // Nếu bạn muốn Receiver box khớp hoàn hảo với nội dung,
    // có thể cần điều chỉnh $receiver_box_height dựa trên GetY() cuối cùng của nội dung
    $currentY += $receiver_box_height + 5;

    // --- SHIPMENT DETAILS ---
    $shipment_box_height = 30;
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $currentY, $print_area_width, $shipment_box_height, 'D');
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($offset_x + $box_padding, $currentY + $box_padding, 'Shipment Details:');
    $pdf->SetFont('dejavusans', '', 7);

    $detail_start_x = $offset_x + $box_padding + 2;
    $detail_current_y = $currentY + $box_padding + 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Số lượng kiện:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 25, $detail_current_y, htmlspecialchars($booking['number_of_packages'] ?? 0));
    $detail_current_y += 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Cân nặng:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 25, $detail_current_y, htmlspecialchars($booking['gross_weight'] ?? 0) . ' kg');
    $detail_current_y += 5;

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Text($detail_start_x, $detail_current_y, 'Kích thước:');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->Text($detail_start_x + 25, $detail_current_y, htmlspecialchars($booking['dimensions_text'] ?? 'N/A'));
    $detail_current_y += 5;

    $currentY += $shipment_box_height + 5;

    // --- BARCODE VÀ SỐ BILL DƯỚI CÙNG ---
    $barcode_bottom_height = 20;
    $pdf->SetLineStyle(array('width' => $border_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $border_color_rgb));
    $pdf->SetDrawColor($border_color_rgb[0], $border_color_rgb[1], $border_color_rgb[2]);
    $pdf->Rect($offset_x, $currentY, $print_area_width, $barcode_bottom_height, 'D');

    $style1D_bottom = array(
        'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
        'border' => 0, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => array(0,0,0), 'bgcolor' => false,
        'text' => false, // text: false để không in mã bên dưới barcode tự động (chúng ta sẽ in thủ công)
        'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 4
    );
    $barcode_width_bottom = 80;
    $barcode_height_bottom = 10;

    $barcode_x_bottom_centered = $offset_x + ($print_area_width - $barcode_width_bottom) +6 ;
    $pdf->write1DBarcode($booking['reference_code'], 'C128', $barcode_x_bottom_centered, $currentY + 1, $barcode_width_bottom, $barcode_height_bottom, 0.4, $style1D_bottom, 'N');

    $pdf->SetFont('dejavusans', 'B', 10);
    $booking_code_text_bottom = 'Bill No: ' . htmlspecialchars($booking['reference_code']);
    $booking_code_width_bottom = $pdf->GetStringWidth($booking_code_text_bottom, 'dejavusans', 'B', 10);
    $text_x_bottom_centered = $offset_x + ($print_area_width - $booking_code_width_bottom) / 2;
    $pdf->Text($text_x_bottom_centered, $currentY + $barcode_height_bottom + 1, $booking_code_text_bottom);


    // Output PDF
    $pdf->Output('AirWaybill_' . $booking['reference_code'] . '.pdf', 'I');

} catch (PDOException $e) {
    ob_clean(); // Xóa bất kỳ output nào trước đó để tránh lỗi header PDF
    error_log("Lỗi database khi lấy dữ liệu booking: " . $e->getMessage()); // Ghi log lỗi
    die("Lỗi database khi lấy dữ liệu booking: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    ob_clean(); // Xóa bất kỳ output nào trước đó để tránh lỗi header PDF
    error_log("Lỗi hệ thống: " . $e->getMessage()); // Ghi log lỗi
    die("Lỗi hệ thống: " . htmlspecialchars($e->getMessage()));
}
?>