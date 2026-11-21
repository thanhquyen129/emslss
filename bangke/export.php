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
    $sttShow = (int) (!empty($r['_stt']) ? $r['_stt'] : (!empty($r['stt']) ? $r['stt'] : ($i + 1)));
    [$from, $to] = bangke_parse_route((string) $r['route_leg']);
    if ($from === '') { $from = 'Hà Nội'; }
    if ($to === '') { $to = 'Hồ Chí Minh'; }
    $senderAirport = bangke_airport_info($from, 'sender');
    $receiverAirport = bangke_airport_info($to, 'receiver');
    $dateShow = date('d/m/Y', strtotime($r['request_date']));
    $vinSerial = trim(trim((string) $r['vin_no']) . ' ' . trim((string) $r['serial_no']));
    if ($vinSerial === '') {
        $vinSerial = '';
    }
    $weightShow = bangke_fmt_weight($r['weight']) . '(kg)';
?>
  <div class="page">
    <div style="font-size:12px;font-weight:bold;margin-bottom:4px;text-align:left">STT: <strong><?= $sttShow ?></strong></div>
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
            <div class="val"><?= bangke_h($senderAirport['province']) ?></div>
          </td>
          <td colspan="2">
            <div class="lbl">Tỉnh nhận (To):</div>
            <div class="val"><?= bangke_h($receiverAirport['province']) ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="text-align:center">
            <div class="awb"><?= bangke_h($r['airway_bill'] !== '' ? $r['airway_bill'] : '—') ?></div>
          </td>
          <td colspan="2">
            <div class="title">ROAD WAYBILL</div>
            <div class="flight-lbl">SỐ HIỆU CHUYẾN BAY</div>
            <div class="flight-val"><?= bangke_h(bangke_format_flight_no($r['flight_no'], $from, $to)) ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div class="sec-title">Thông tin gửi/Sender</div>
            <div class="lbl">Họ tên người gửi/Sender:</div>
            <div class="line filled">Công Ty EMS Logistics</div>
            <div class="lbl">Địa chỉ/Address:</div>
            <div class="line">&nbsp;</div>
            <div class="lbl">Quận/huyện (District):</div>
            <div class="line filled"><?= bangke_h($senderAirport['name']) ?></div>
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
            <div class="line">&nbsp;</div>
            <div class="lbl">Quận/huyện (District):</div>
            <div class="line filled"><?= bangke_h($receiverAirport['name']) ?></div>
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
 * In bill theo mẫu EMS Logistics (A4 landscape).
 * @param array<int,array<string,mixed>> $rows
 */
function bangke_render_ems_print(array $rows): void
{
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>EMS Road Waybill — LSS Logistics</title>
<style>
@page { size: A4 landscape; margin: 8mm; }
* { box-sizing: border-box; }
body { margin: 0; background: #eee; font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; }

/* Toolbar (ẩn khi in) */
.toolbar {
  position: sticky; top: 0; z-index: 20;
  display: flex; gap: 10px; align-items: center;
  padding: 10px 14px;
  background: #0c2340; color: #fff;
}
.toolbar button {
  border: 0; border-radius: 6px; padding: 8px 14px;
  background: #f0a202; color: #122033; font-weight: 700; cursor: pointer;
}
.toolbar a { color: #cfe7ff; margin-left: auto; text-decoration: none; }

/* Sheet */
.sheet {
  width: 281mm; min-height: 194mm;
  margin: 12px auto;
  background: #fff;
  padding: 7mm 9mm 5mm;
  box-shadow: 0 2px 12px #999;
  page-break-after: always;
}
.sheet:last-child { page-break-after: auto; }

.header { display: grid; grid-template-columns: 1fr 1.1fr 1fr; align-items: center; margin-bottom: 3mm; }
.brand { text-align: center; display: flex; align-items: center; justify-content: center; }
.brand-logo { max-width: 250px; max-height: 50px; width: auto; height: auto; object-fit: contain; }
.waybill { text-align: right; font-weight: bold; font-size: 12px; line-height: 1.65; }
.waybill .awb-no { display: inline-block; font-weight: 700; margin-left: 6px; }
.meta { line-height: 1.65; font-weight: bold; }
.dots { display: inline-block; border-bottom: 1px dotted #333; min-width: 60px; vertical-align: baseline; }
.dots.filled { border-bottom-color: transparent; font-weight: 700; }

.grid { border: 1.5px solid #222; display: grid; grid-template-columns: 1fr 1fr; }
.cell { padding: 3mm; border-right: 1px solid #222; border-bottom: 1px solid #222; min-height: 37mm; }
.cell:nth-child(2n) { border-right: 0; }
.title { text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 2mm; }
.row { margin: 2px 0; }
.line { display: inline-block; border-bottom: 1px dotted #444; height: 12px; vertical-align: bottom; }
.line.filled { border-bottom-color: transparent; font-weight: 700; }
.wide { width: 70%; }
.medium { width: 45%; }
.delivery, .packing { min-height: 22mm; }
.checks { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 25px; margin-top: 3mm; }
.box { display: inline-block; width: 10px; height: 10px; border: 1px solid #111; margin-right: 6px; vertical-align: -1px; }
.order { grid-column: 1/3; min-height: 37mm; border-right: 0; }
.order-wrap { display: grid; grid-template-columns: 1.05fr .95fr; gap: 8mm; }
.order table { border-collapse: collapse; width: 100%; font-size: 11px; }
.order td { padding: 2px 0; }
.val-bold { font-weight: 700; font-size: 13px; }
.val-red { font-weight: 800; font-size: 14px; color: #c00000; }
.commit { font-size: 10.5px; line-height: 1.45; }
.signs { grid-column: 1/3; border-right: 0; border-bottom: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; }
.sign-half { display: grid; grid-template-columns: 1fr 1fr; min-height: 37mm; }
.sign-half:first-child { border-right: 1px solid #222; }
.signbox { padding: 2.5mm; text-align: center; font-weight: bold; position: relative; }
.signbox:first-child { border-right: 1px solid #222; }
.sub { font-weight: normal; font-style: italic; font-size: 10px; }
.date { position: absolute; left: 2.5mm; right: 2.5mm; bottom: 2mm; text-align: left; font-weight: bold; }
.date .en { font-weight: normal; font-size: 10px; }
.sheet-stt { font-size: 13px; font-weight: 800; margin-bottom: 2mm; color: #111; text-align: left; }

@media print {
  body { background: #fff; }
  .toolbar { display: none !important; }
  .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
}
</style>
</head>
<body>
<div class="toolbar">
  <strong>Xem trước EMS Road Waybill</strong>
  <button type="button" onclick="window.print()">In ngay</button>
  <a href="bangke.php">← Quay lại</a>
</div>
<?php
$logoFile = __DIR__ . '/ems_logo.png';
$logoSrc = file_exists($logoFile)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile))
    : 'bangke/ems_logo.png';
?>
<?php if (!$rows): ?>
  <p style="padding:24px;text-align:center">Không có dòng nào để in.</p>
<?php endif; ?>
<?php foreach ($rows as $idx => $r):
    $sttShow    = (int) (!empty($r['_stt']) ? $r['_stt'] : (!empty($r['stt']) ? $r['stt'] : ($idx + 1)));
    [$from, $to] = bangke_parse_route((string) $r['route_leg']);
    if ($from === '') { $from = 'Hà Nội'; }
    if ($to === '')   { $to   = 'Hồ Chí Minh'; }
    $senderAirport   = bangke_airport_info($from, 'sender');
    $receiverAirport = bangke_airport_info($to, 'receiver');
    $dateShow   = date('d/m/Y', strtotime($r['request_date']));
    $weightShow = bangke_fmt_weight($r['weight']) . ' (kg)';
    $awb        = trim((string) $r['airway_bill']);
    $vinSerial  = trim(trim((string) $r['vin_no']) . ' ' . trim((string) $r['serial_no']));
?>
<main class="sheet">
  <div class="sheet-stt">STT: <strong><?= $sttShow ?></strong></div>
  <header class="header">
    <div class="meta">
      Ngày yêu cầu/Booking Date: <span class="dots filled"><?= bangke_h($dateShow) ?></span><br>
      Điện thoại liên hệ/Hotline: <span class="dots"></span><br>
      Tỉnh gửi (From): <strong><?= bangke_h($senderAirport['province']) ?></strong>
    </div>
    <div class="brand">
      <img class="brand-logo" src="<?= $logoSrc ?>" alt="EMS LOGISTICS">
    </div>
    <div class="waybill">
      ROAD WAYBILL: <span class="awb-no"><?= bangke_h($awb !== '' ? $awb : '&lt;mã vận đơn&gt;') ?></span><br>
      Tỉnh nhận (To): <strong><?= bangke_h($receiverAirport['province']) ?></strong><br>
      Số hiệu chuyến bay: <strong><?= bangke_h(bangke_format_flight_no($r['flight_no'], $from, $to)) ?></strong>
    </div>
  </header>

  <section class="grid">
    <!-- Sender -->
    <div class="cell">
      <div class="title">Thông tin gửi/Sender</div>
      <div class="row">Họ tên người gửi/Sender: <span class="line medium filled">Công Ty EMS Logistics</span></div>
      <div class="row">Địa chỉ/Address: <span class="line wide"></span></div>
      <div class="row">Quận/huyện (District): <span class="line wide filled"><?= bangke_h($senderAirport['name']) ?></span></div>
      <div class="row">Tỉnh/Thành phố (Province): <span class="line wide"></span></div>
      <div class="row">Điện thoại/Phone: <span class="line wide"></span></div>
    </div>

    <!-- Receiver -->
    <div class="cell">
      <div class="title">Thông tin nhận/Receiver</div>
      <div class="row">Họ tên người nhận/Receiver: <span class="line medium filled">Công Ty LSS Logistics</span></div>
      <div class="row">Địa chỉ/Address: <span class="line wide"></span></div>
      <div class="row">Quận/huyện (District): <span class="line wide filled"><?= bangke_h($receiverAirport['name']) ?></span></div>
      <div class="row">Tỉnh/Thành phố (Province): <span class="line wide"></span></div>
      <div class="row">Điện thoại/Phone: <span class="line wide"></span></div>
    </div>

    <!-- Delivery type -->
    <div class="cell delivery">
      <div class="title">Hình thức vận chuyển/Delivery Type</div>
      <div class="checks">
        <div><span class="box"></span>Hàng lẻ</div>
        <div>Tải trọng xe: <span class="line medium"></span></div>
        <div><span class="box"></span>Nguyên chuyến</div>
        <div>BKS: <span class="line medium"></span></div>
      </div>
    </div>

    <!-- Packing -->
    <div class="cell packing">
      <div class="title">Hình thức đóng gói/Packing</div>
      <div class="checks">
        <div><span class="box"></span>Pallet</div>
        <div><span class="box"></span>Wood case</div>
        <div><span class="box"></span>Plastic case</div>
        <div><span class="box"></span>Carton</div>
      </div>
    </div>

    <!-- Order info -->
    <div class="cell order">
      <div class="order-wrap">
        <div>
          <div class="title">Thông tin hàng/Order Information</div>
          <table>
            <tr><td>Số kiện</td><td class="val-bold"><?= (int) $r['package_count'] ?></td></tr>
            <tr><td>K.lượng/Weight</td><td class="val-red"><?= bangke_h($weightShow) ?></td></tr>
            <tr><td>Số VIN</td><td><?= bangke_h(trim((string) $r['vin_no']) !== '' ? trim((string) $r['vin_no']) : '...') ?></td></tr>
            <tr><td>Số Serial</td><td><?= bangke_h(trim((string) $r['serial_no']) !== '' ? trim((string) $r['serial_no']) : '...') ?></td></tr>
            <tr><td>Số CBM</td><td>................. CBM</td></tr>
            <tr><td>Dịch vụ cộng thêm/Services</td><td>..........................</td></tr>
            <tr><td>Nội dung hàng/Content</td><td>: ..........................</td></tr>
          </table>
        </div>
        <div class="commit">
          <div class="title">Cam kết của người gửi/Sender's Commitment</div>
          Tôi đồng ý với các quy định của dịch vụ EMS hiện hành và cam kết nội dung đã kê khai là đúng sự thật.<br>
          I agree to the current EMS service regulations and guarantee that the declared content is true.<br>
          Tôi đồng ý chịu trách nhiệm trước pháp luật về nội dung bưu gửi.<br>
          I agree to be legally responsible for the content of the postal item.<br>
          Vui lòng truy cập <b>https://ems.com.vn/</b> để xem chi tiết điều khoản sử dụng dịch vụ EMS.<br>
          Please visit <b>https://ems.com.vn/</b> to see detailed terms of use of EMS services.
        </div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="cell signs">
      <div class="sign-half">
        <div class="signbox">
          Người gửi (Sender)<br><span class="sub">(Ký và ghi rõ họ tên)</span>
          <div class="date">Ngày giờ thu gom:<br><span class="en">(Date, time of pickup)</span></div>
        </div>
        <div class="signbox">Bên vận chuyển<br><span class="sub">(Ký và ghi rõ họ tên)</span></div>
      </div>
      <div class="sign-half">
        <div class="signbox">
          Người nhận (Receiver)<br><span class="sub">(Ký và ghi rõ họ tên)</span>
          <div class="date">Ngày giờ giao hàng:<br><span class="en">(Date, time of delivery)</span></div>
        </div>
        <div class="signbox">Bên vận chuyển<br><span class="sub">(Ký và ghi rõ họ tên)</span></div>
      </div>
    </div>
  </section>
</main>
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
    $idx = 0;
    $sumPkg = 0;
    $sumW = 0.0;
    foreach ($rows as $r) {
        $idx++;
        $stt = (int) (!empty($r['stt']) ? $r['stt'] : (!empty($r['_stt']) ? $r['_stt'] : $idx));
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
