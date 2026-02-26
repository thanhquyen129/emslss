<?php
// Bật báo lỗi PHP để dễ debug (chỉ trong môi trường phát triển)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php'; // Đảm bảo đường dẫn đúng đến autoload của PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Tạo một Spreadsheet mới
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Booking Template');

// Định nghĩa các tiêu đề cột giống như trong upload_bookings_excel.php
$header_columns = [
    'Người LH (contact name) (Người gửi)',
    'Địa chỉ (Người gửi)',
    'ĐT (tel) (Người gửi)',
    'Mã số thuế/CCCD/CMND (Người gửi)',
    'Email (Người gửi)',
    'Quốc gia (Người gửi)',
    'Cân nặng (gross weight) (kg)',
    'Số lượng kiện (No. of packages)',
    'Kích thước (Dài x Rộng x Cao) (cm)',
    'Loại hình dịch vụ (Service Type)',
    'Loại (type)',
    'Kho hàng',
    'Nước đến (country)',
    'Cty (company name) (Người nhận)',
    'Người LH (contact name) (Người nhận)',
    'ĐT (tel) (Người nhận)',
    'Tax ID (Người nhận)',
    'Email (Người nhận)',
    'Postal code (Người nhận)',
    'Thành phố (city) (Người nhận)',
    'Tỉnh (State) (Người nhận)',
    'Địa chỉ 1 (address 1) (Người nhận)',
    'Địa chỉ 2 (address 2) (Người nhận)',
    'Địa chỉ 3 (address 3) (Người nhận)',
    'Chi phí (Cost)',
    'Giá bán (Sales Price)',
    'Ghi chú (Note)'
];

// Ghi tiêu đề vào hàng đầu tiên
$sheet->fromArray($header_columns, NULL, 'A1');

// Định dạng tiêu đề (tùy chọn)
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0'); // Light grey background

// Thiết lập chiều rộng cột tự động (tùy chọn)
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Thiết lập HTTP Headers để trình duyệt tải về file Excel
$filename = 'booking_template_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Tạo Writer và lưu file vào output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>