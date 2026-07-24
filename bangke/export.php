<?php
/**
 * In bill theo mẫu ROAD WAYBILL LSS Logistics.
 * @param array<int,array<string,mixed>> $rows
 */
function bangke_render_print(array $rows): void
{
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>ROAD WAYBILL — LSS Logistics</title>
<style>
  @page { size: A4; margin: 8mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: Arial, "Segoe UI", sans-serif;
    color: #111;
    background: #eceff3;
    font-size: 12px;
  }
  .toolbar {
    position: sticky; top: 0; z-index: 20;
    display: flex; gap: 10px; align-items: center;
    padding: 10px 14px;
    background: #0c2340;
    color: #fff;
  }
  .toolbar button {
    border: 0; border-radius: 6px; padding: 8px 14px;
    background: #f0a202; color: #122033; font-weight: 700; cursor: pointer;
  }
  .toolbar a { color: #cfe7ff; margin-left: auto; text-decoration: none; }

  .page {
    position: relative;
    width: 210mm;
    min-height: 297mm;
    margin: 12px auto;
    background: #fff;
    padding: 8mm 7mm 7mm;
    page-break-after: always;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
  }
  .page:last-child { page-break-after: auto; }
  .wm {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 96px; font-weight: 700; color: rgba(0,0,0,.06);
    pointer-events: none; z-index: 0;
  }
  .bill { position: relative; z-index: 1; border: 2px solid #111; }

  table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.grid td, table.grid th {
    border: 1px solid #111;
    padding: 5px 7px;
    vertical-align: top;
  }
  .logo {
    font-size: 18px; font-weight: 800; letter-spacing: .04em;
    line-height: 1.15;
  }
  .logo small { display: block; font-size: 10px; font-weight: 600; color: #444; }
  .title {
    text-align: center; font-size: 22px; font-weight: 800;
    letter-spacing: .06em; line-height: 1.2;
  }
  .lbl { font-size: 11px; color: #222; }
  .val { font-weight: 700; font-size: 13px; margin-top: 2px; word-break: break-word; }
  .val.red { color: #c00000; font-size: 15px; }
  .awb {
    text-align: center; font-size: 16px; font-weight: 800;
    letter-spacing: .03em; padding: 4px 0;
  }
  .flight-lbl { font-size: 10px; font-weight: 700; text-align: center; }
  .flight-val { text-align: center; font-weight: 800; font-size: 14px; margin-top: 2px; }
  .sec-title { font-weight: 700; font-size: 12px; margin-bottom: 4px; }
  .line { border-bottom: 1px dotted #666; min-height: 16px; margin: 2px 0 6px; }
  .line.filled { border-bottom-color: transparent; font-weight: 700; }
  .chk { display: inline-block; margin-right: 14px; white-space: nowrap; }
  .box {
    display: inline-block; width: 12px; height: 12px;
    border: 1px solid #111; margin-right: 4px; vertical-align: -1px;
  }
  .commit { font-size: 10.5px; line-height: 1.35; color: #222; }
  .commit a { color: #0645ad; }
  .sign-box { text-align: center; height: 92px; }
  .sign-box .who { font-weight: 700; font-size: 12px; }
  .sign-box .hint { font-size: 10px; color: #444; margin-top: 4px; }
  .sign-box .space { height: 48px; }
  .muted { color: #444; font-size: 11px; }

  @media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    .page {
      margin: 0; width: auto; min-height: auto;
      box-shadow: none; padding: 0;
    }
  }
</style>
</head>
<body>
<div class="toolbar">
  <strong>Xem trước ROAD WAYBILL</strong>
  <button type="button" onclick="window.print()">In ngay</button>
  <a href="bangke.php">← Quay lại</a>
</div>
<?php if (!$rows): ?>
  <p style="padding:24px;text-align:center">Không có dòng nào để in.</p>
<?php endif; ?>
<?php foreach ($rows as $i => $r):
    [$from, $to] = bangke_parse_route((string) $r['route_leg']);
    if ($from === '') { $from = 'Hà Nội'; }
    if ($to === '') { $to = 'Hồ Chí Minh'; }
    $dateShow = date('d/m/Y', strtotime($r['request_date']));
    $vinSerial = trim(trim((string) $r['vin_no']) . ' ' . trim((string) $r['serial_no']));
    if ($vinSerial === '') {
        $vinSerial = '';
    }
    $weightShow = bangke_fmt_weight($r['weight']) . '(kg)';
    $pageNo = $i + 1;
?>
  <div class="page">
    <div class="wm">Page <?= (int) $pageNo ?></div>
    <div class="bill">
      <table class="grid">
        <tr>
          <td rowspan="2" style="width:22%">
            <div class="logo">LSS LOGISTICS<small>lsslogistics.vn</small></div>
          </td>
          <td style="width:28%">
            <div class="lbl">Ngày yêu cầu/Booking Date:</div>
            <div class="val"><?= bangke_h($dateShow) ?></div>
          </td>
          <td colspan="2" style="width:50%">
            <div class="lbl">Điện thoại liên hệ/Hotline:</div>
            <div class="line">&nbsp;</div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="lbl">Tỉnh gửi (From):</div>
            <div class="val"><?= bangke_h($from) ?></div>
          </td>
          <td colspan="2">
            <div class="lbl">Tỉnh nhận (To):</div>
            <div class="val"><?= bangke_h($to) ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="text-align:center">
            <div class="awb"><?= bangke_h($r['airway_bill'] !== '' ? $r['airway_bill'] : '—') ?></div>
          </td>
          <td colspan="2">
            <div class="title">ROAD WAYBILL</div>
            <div class="flight-lbl">SỐ HIỆU CHUYẾN BAY</div>
            <div class="flight-val"><?= bangke_h($r['flight_no'] !== '' ? $r['flight_no'] : '—') ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div class="sec-title">Thông tin gửi/Sender</div>
            <div class="lbl">Họ tên người gửi/Sender:</div>
            <div class="line filled">Công Ty EMS Logistics</div>
            <div class="lbl">Địa chỉ/Address:</div>
            <div class="line filled">Sân bay Nội Bài</div>
            <div class="lbl">Quận/huyện (District):</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Tỉnh/ Thành phố (Province):</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Điện thoại/Phone:</div>
            <div class="line">&nbsp;</div>
          </td>
          <td colspan="2">
            <div class="sec-title">Thông tin nhận/Receiver</div>
            <div class="lbl">Họ tên người nhận/Receiver:</div>
            <div class="line filled">Công Ty LSS Logistics</div>
            <div class="lbl">Địa chỉ/Address:</div>
            <div class="line filled">Sân bay Tân Sơn Nhất</div>
            <div class="lbl">Quận/huyện (District):</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Tỉnh/ Thành phố (Province):</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Điện thoại/Phone:</div>
            <div class="line">&nbsp;</div>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div class="sec-title">Hình thức vận chuyển/Delivery Type</div>
            <div style="margin:8px 0 10px">
              <span class="chk"><span class="box"></span>Hàng lẻ</span>
              <span class="chk"><span class="box"></span>Nguyên chuyến</span>
            </div>
            <div class="lbl">Tải trọng xe: <span class="muted">........................</span></div>
            <div class="lbl" style="margin-top:8px">BKS: <span class="muted">........................</span></div>
          </td>
          <td colspan="2">
            <div class="sec-title">Hình thức đóng gói/Packing</div>
            <div style="margin-top:10px; line-height:1.9">
              <span class="chk"><span class="box"></span>Pallet</span>
              <span class="chk"><span class="box"></span>Wood case</span><br>
              <span class="chk"><span class="box"></span>Plastic case</span>
              <span class="chk"><span class="box"></span>Carton</span>
            </div>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div class="sec-title">Thông tin hàng/Order Information</div>
            <table style="width:100%;border-collapse:collapse;margin-top:4px">
              <tr>
                <td style="border:0;padding:2px 0;width:50%">
                  <div class="lbl">Số kiện</div>
                  <div class="val"><?= (int) $r['package_count'] ?></div>
                </td>
                <td style="border:0;padding:2px 0;width:50%">
                  <div class="lbl">K.lượng/Weight</div>
                  <div class="val red"><?= bangke_h($weightShow) ?></div>
                </td>
              </tr>
            </table>
            <div class="lbl" style="margin-top:6px">Số sản phẩm/Quantity</div>
            <div class="val" style="min-height:18px"><?= bangke_h($vinSerial !== '' ? $vinSerial : ' ') ?></div>
            <div class="lbl" style="margin-top:6px">Số CBM: <span class="muted">................ CBM</span></div>
            <div class="lbl" style="margin-top:6px">Dịch vụ cộng thêm/Services:</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Nội dung hàng/Content:</div>
            <div class="line">&nbsp;</div>
          </td>
          <td colspan="2">
            <div class="sec-title">Cam kết của người gửi/Sender's Commitment</div>
            <div class="commit">
              Tôi đồng ý với các quy định về cung cấp dịch vụ của LSS tại website
              <a href="https://lsslogistics.vn/">https://lsslogistics.vn/</a>
              và chịu trách nhiệm về sự trung thực của các nội dung kê khai.<br><br>
              I agree to the LSS's regulations of service provision at website
              <a href="https://lsslogistics.vn/">https://lsslogistics.vn/</a>
              and take responsibility for the truthfulness of the declared contents.
            </div>
          </td>
        </tr>
        <tr>
          <td class="sign-box" style="width:25%">
            <div class="who">Người gửi (Sender)</div>
            <div class="hint">(Ký và ghi rõ họ tên)</div>
            <div class="space"></div>
          </td>
          <td class="sign-box" style="width:25%">
            <div class="who">Bên vận chuyển</div>
            <div class="hint">(Ký và ghi rõ họ tên)</div>
            <div class="space"></div>
          </td>
          <td class="sign-box" style="width:25%">
            <div class="who">Người nhận (Receiver)</div>
            <div class="hint">(Ký và ghi rõ họ tên)</div>
            <div class="space"></div>
          </td>
          <td class="sign-box" style="width:25%">
            <div class="who">Bên vận chuyển</div>
            <div class="hint">(Ký và ghi rõ họ tên)</div>
            <div class="space"></div>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div class="lbl">Ngày giờ thu gom: (Date, time of pickup)</div>
            <div class="line" style="margin-top:18px">&nbsp;</div>
          </td>
          <td colspan="2">
            <div class="lbl">Ngày giờ giao hàng: (Date, time of delivery)</div>
            <div class="line" style="margin-top:18px">&nbsp;</div>
          </td>
        </tr>
      </table>
    </div>
  </div>
<?php endforeach; ?>
</body>
</html>
    <?php
}

/**
 * Xuất Excel (SpreadsheetML .xls) — mở được bằng Excel/Google Sheets.
 * @param array<int,array<string,mixed>> $rows
 */
function bangke_export_excel(array $rows, string $filenameBase = 'bang_ke'): void
{
    $filename = $filenameBase . '_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Title">
   <Font ss:Bold="1" ss:Size="14"/>
   <Alignment ss:Horizontal="Center"/>
  </Style>
  <Style ss:ID="Sub">
   <Font ss:Size="10"/>
   <Alignment ss:Horizontal="Center"/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0C2340" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="Cell">
   <Alignment ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C9D4E0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C9D4E0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C9D4E0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C9D4E0"/>
   </Borders>
  </Style>
  <Style ss:ID="Num" ss:Parent="Cell">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Bang ke">
  <Table>
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="120"/>
   <Column ss:Width="100"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="70"/>
   <Column ss:Width="80"/>
   <Row>
    <Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">BẢNG KÊ HÀNG HÓA — LSS LOGISTICS</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="8" ss:StyleID="Sub"><Data ss:Type="String">lsslogistics.vn · Xuất <?= bangke_h(date('d/m/Y H:i')) ?></Data></Cell>
   </Row>
   <Row></Row>
   <Row>
    <Cell ss:StyleID="Header"><Data ss:Type="String">STT</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Ngày yêu cầu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Chặng</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Số hiệu chuyến bay</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Airway Bill</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Số VIN</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Số Serial</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Số kiện</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Khối lượng (kg)</Data></Cell>
   </Row>
<?php
    $stt = 0;
    $sumPkg = 0;
    $sumW = 0.0;
    foreach ($rows as $r) {
        $stt++;
        $sumPkg += (int) $r['package_count'];
        $sumW += (float) $r['weight'];
        $dateShow = date('d/m/Y', strtotime($r['request_date']));
        ?>
   <Row>
    <Cell ss:StyleID="Num"><Data ss:Type="Number"><?= $stt ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($dateShow) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($r['route_leg']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($r['flight_no']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($r['airway_bill']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($r['vin_no']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= bangke_h($r['serial_no']) ?></Data></Cell>
    <Cell ss:StyleID="Num"><Data ss:Type="Number"><?= (int) $r['package_count'] ?></Data></Cell>
    <Cell ss:StyleID="Num"><Data ss:Type="Number"><?= bangke_h(number_format((float) $r['weight'], 2, '.', '')) ?></Data></Cell>
   </Row>
        <?php
    }
    ?>
   <Row></Row>
   <Row>
    <Cell ss:MergeAcross="6" ss:StyleID="Cell"><Data ss:Type="String">TỔNG CỘNG (<?= $stt ?> dòng)</Data></Cell>
    <Cell ss:StyleID="Num"><Data ss:Type="Number"><?= $sumPkg ?></Data></Cell>
    <Cell ss:StyleID="Num"><Data ss:Type="Number"><?= bangke_h(number_format($sumW, 2, '.', '')) ?></Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
    <?php
}
