<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/tcpdf/tcpdf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'agency';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Mã booking không hợp lệ.");
}

$booking_id = (int)$_GET['id'];

try {
    if (!isset($pdo) || $pdo === null) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $stmt = null;
    if ($user_role === 'admin' || $user_role === 'viewer' || $user_role === 'accounting') {
        $stmt = $pdo->prepare("SELECT * FROM vmlbooking_orders WHERE id = ?");
        $stmt->execute([$booking_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM vmlbooking_orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$booking_id, $user_id]);
    }

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Booking không tồn tại hoặc bạn không có quyền truy cập.");
    }

    $reference_code = htmlspecialchars($booking['reference_code']);
    $number_of_packages = (int)($booking['number_of_packages'] ?? 1);
    if ($number_of_packages < 1) $number_of_packages = 1;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('VML Express');
    $pdf->SetTitle('Shipping Marks - ' . $reference_code);
    $pdf->SetSubject('Shipping Marks for shipment');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $page_margin = 15;
    $pdf->SetMargins($page_margin, $page_margin, $page_margin, $page_margin);
    $pdf->SetAutoPageBreak(TRUE, $page_margin);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 8);

    $pdf->AddPage();

    $style1D = array(
        'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
        'border' => 0, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => array(0,0,0), 'bgcolor' => false,
        'text' => false, 'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 4
    );

    $sm_width = 120;
    $sm_height = 80;
    $available_width = 297 - (2 * $page_margin);
    $available_height = 210 - (2 * $page_margin);

    $gap_x = ($available_width - (2 * $sm_width)) / 1;
    if ($gap_x < 0) $gap_x = 0;

    $gap_y = ($available_height - (2 * $sm_height)) / 1;
    if ($gap_y < 0) $gap_y = 0;

    $x1 = $page_margin;
    $x2 = $page_margin + $sm_width + $gap_x;
    $y1 = $page_margin;
    $y2 = $page_margin + $sm_height + $gap_y;

    $sm_positions = [
        ['x' => $x1, 'y' => $y1],
        ['x' => $x2, 'y' => $y1],
        ['x' => $x1, 'y' => $y2],
        ['x' => $x2, 'y' => $y2]
    ];

    for ($i = 1; $i <= $number_of_packages; $i++) {
        $sm_index_on_page = ($i - 1) % 4;
        if ($i > 1 && $sm_index_on_page === 0) {
            $pdf->AddPage();
        }

        $current_sm_x = $sm_positions[$sm_index_on_page]['x'];
        $current_sm_y = $sm_positions[$sm_index_on_page]['y'];

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineStyle(array('width' => 0.4, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0,0,0)));
        $pdf->Rect($current_sm_x, $current_sm_y, $sm_width, $sm_height, 'DF');

        $pcs_text = 'Pcs: ' . $i . ' / ' . $number_of_packages;
        $pdf->SetFont('dejavusans', 'B', 30);
        $pdf->Text($current_sm_x + 5, $current_sm_y + 4, $pcs_text);

        $pdf->SetFont('dejavusans', 'B', 14);
        $shipping_mark_text_label = 'SHIPPING MARK';
        $shipping_mark_text_width = $pdf->GetStringWidth($shipping_mark_text_label, 'dejavusans', 'B', 14);
        $pdf->Text($current_sm_x + $sm_width - $shipping_mark_text_width - 2, $current_sm_y + 4, $shipping_mark_text_label);

        $pdf->Line($current_sm_x + 2, $current_sm_y + 18, $current_sm_x + $sm_width - 2, $current_sm_y + 18);

        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Text($current_sm_x + 5, $current_sm_y + 26, 'HAWB: ' . $reference_code);

        $pdf->Line($current_sm_x + 2, $current_sm_y + 35, $current_sm_x + $sm_width - 2, $current_sm_y + 35);

        $barcode_width = 80;
        $barcode_height = 12;
        $barcode_x = $current_sm_x + ($sm_width - $barcode_width) / 2;
        $barcode_y = $current_sm_y + 37;
        $pdf->write1DBarcode($reference_code, 'C128', $barcode_x, $barcode_y, $barcode_width, $barcode_height, 0.4, $style1D, 'N');

        $shipping_mark_id_text = $reference_code . '-' . $i;
        $pdf->SetFont('dejavusans', 'B', 10);
        $sm_id_text_width = $pdf->GetStringWidth($shipping_mark_id_text, 'dejavusans', 'B', 10);
        $sm_id_text_x = $barcode_x + ($barcode_width - $sm_id_text_width) / 2;
        $sm_id_text_y = $barcode_y + $barcode_height + 2;
        $pdf->Text($sm_id_text_x, $sm_id_text_y, $shipping_mark_id_text);

        $destination_country_code = htmlspecialchars(strtoupper($booking['receiver_country'] ?? ''));
        $pdf->SetFont('dejavusans', 'B', 14);
        $country_code_width = $pdf->GetStringWidth($destination_country_code, 'dejavusans', 'B', 14);
        $country_code_x = $current_sm_x + $sm_width - $country_code_width - 2;
        $country_code_y = $current_sm_y + 51;
        $pdf->Text($country_code_x, $country_code_y, $destination_country_code);

        $pdf->Line($current_sm_x + 2, $current_sm_y + 60, $current_sm_x + $sm_width - 2, $current_sm_y + 60);

        $sender_consignee_y_start = $current_sm_y + 61;
        $cell_width = $sm_width / 2 - 1.5;

        $pdf->SetFillColor(190, 207, 169);
        $pdf->Rect($current_sm_x + 1, $sender_consignee_y_start, $cell_width, 3, 'F');
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->Text($current_sm_x + 3, $sender_consignee_y_start + 0.5, 'Sender:');

        $pdf->SetFont('dejavusans', '', 5);
        $current_line_y = $sender_consignee_y_start + 4;

        $pdf->SetXY($current_sm_x + 2, $current_line_y);
        $pdf->Cell(0, 0, 'Company: ' . htmlspecialchars($booking['shipper_agency_name'] ?? ''));
        $current_line_y += 2;

        $pdf->SetXY($current_sm_x + 2, $current_line_y);
        $pdf->Cell(0, 0, 'Contact name: ' . htmlspecialchars($booking['shipper_contact'] ?? ''));
        $current_line_y += 2;

        $pdf->SetXY($current_sm_x + 2, $current_line_y);
        $pdf->Cell(0, 0, 'Country: ' . htmlspecialchars($booking['shipper_country'] ?? ''));

        $pdf->SetFillColor(190, 207, 169);
        $pdf->Rect($current_sm_x + $sm_width / 2 + 1, $sender_consignee_y_start, $cell_width, 3, 'F');
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->Text($current_sm_x + $sm_width / 2 + 3, $sender_consignee_y_start + 0.5, 'Consignee:');

        $pdf->SetFont('dejavusans', '', 5);
        $current_line_y_consignee = $sender_consignee_y_start + 4;

        $pdf->SetXY($current_sm_x + $sm_width / 2 + 2, $current_line_y_consignee);
        $pdf->Cell(0, 0, 'Company: ' . htmlspecialchars($booking['receiver_company'] ?? ''));
        $current_line_y_consignee += 2;

        $receiver_address_text = 'Address: ' . htmlspecialchars($booking['receiver_address1'] ?? '') . (!empty($booking['receiver_address2']) ? ' - ' . htmlspecialchars($booking['receiver_address2']) : '') . (!empty($booking['receiver_address3']) ? ' - ' . htmlspecialchars($booking['receiver_address3']) : '');
        $pdf->SetXY($current_sm_x + $sm_width / 2 + 2, $current_line_y_consignee);
        $pdf->Cell(0, 0, $receiver_address_text);
        $current_line_y_consignee += 2;

        $pdf->SetXY($current_sm_x + $sm_width / 2 + 2, $current_line_y_consignee);
        $pdf->Cell(0, 0, htmlspecialchars($booking['receiver_city'] ?? '') . ', ' . htmlspecialchars($booking['receiver_state'] ?? '') . ' ' . htmlspecialchars($booking['receiver_postal_code'] ?? ''));
        $current_line_y_consignee += 2;

        $pdf->SetXY($current_sm_x + $sm_width / 2 + 2, $current_line_y_consignee);
        $pdf->Cell(0, 0, htmlspecialchars($booking['receiver_country'] ?? ''));
    }

    $pdf->Output('ShippingMarks_' . $reference_code . '.pdf', 'I');

} catch (PDOException $e) {
    error_log("Error generating shipping marks: " . $e->getMessage());
    die("Lỗi database khi tạo shipping marks: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    error_log("General error generating shipping marks: " . $e->getMessage());
    die("Lỗi hệ thống khi tạo shipping marks: " . htmlspecialchars($e->getMessage()));
}
?>