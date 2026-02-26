<?php
// Báo lỗi PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Đảm bảo đường dẫn này đúng tới file tcpdf.php
require_once __DIR__ . '/includes/tcpdf/tcpdf.php';

if (isset($_GET['code'])) {
    $code = htmlspecialchars($_GET['code']); // Mã booking hoặc shipping mark
    $height = $_GET['height'] ?? 60; // Chiều cao của barcode
    $width_factor = $_GET['width_factor'] ?? 2; // Độ rộng của các vạch

    // Tạo một đối tượng TCPDF tạm thời
    // Kích thước tài liệu nhỏ, chỉ để tạo barcode
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(100, (int)$height + 20), true, 'UTF-8', false);
    $pdf->SetAutoPageBreak(false, 0); // Không tự động ngắt trang
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Style cho mã 1D barcode
    $style1D = array(
        'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
        'border' => 0, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => array(0,0,0), 'bgcolor' => false,
        'text' => false, 'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 4
    );

    // Lấy thông tin barcode dưới dạng hình ảnh
    // write1DBarcode sẽ vẽ lên trang PDF, sau đó ta trích xuất ảnh
    // Để lấy ảnh trực tiếp, TCPDF cung cấp phương thức getBarcodePNG()
    // Hoặc getBarcodeSVG() nếu bạn muốn SVG
    
    // Lưu ý: TCPDF không có hàm getBarcodePNG trực tiếp ngoài class Barcode.
    // Chúng ta sẽ dùng cách sau để tạo ảnh: vẽ lên PDF, rồi xuất PDF ra ảnh hoặc dùng một helper function nếu có.
    // Cách đơn giản nhất là dùng chính method của TCPDF để xuất ra luồng ảnh.

    // Tạo một đối tượng Barcode 1D
    $barcodeobj = new TCPDF_Barcode1D($code, 'C128'); // Sử dụng CODE128
    
    // Render barcode và xuất ra hình ảnh PNG
    header('Content-Type: image/png');
    echo $barcodeobj->get     ($width_factor * 10, $height, 'PNG'); // width_factor * 10 để có độ rộng hợp lý
    
    // Exit để không có gì khác được in ra
    exit;

} else {
    header("HTTP/1.0 400 Bad Request");
    echo "Missing 'code' parameter.";
}
?>