<?php
// phong/hop_dong_xem.php - In hợp đồng theo mẫu PDF do người dùng cung cấp.
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nguoithue']);
$id = (int) qs('id', '0');
$renterM = new Renter();
$contract = $renterM->findFull($id);
if (!$contract) { http_response_code(404); die('Không tìm thấy hợp đồng.'); }
if (Auth::is('nguoithue') && (int) $contract['id_user'] !== Auth::id()) {
    http_response_code(403);
    die('Bạn không có quyền xem hợp đồng của người thuê khác.');
}

$contractCode = 'HD' . str_pad((string) $contract['id_renter'], 4, '0', STR_PAD_LEFT);
$address = Setting::get('dia_chi', '');
$owner = [
    'name' => Setting::get('chu_tro_ho_ten', ''),
    'birth' => Setting::get('chu_tro_ngay_sinh', ''),
    'address' => Setting::get('chu_tro_ho_khau', ''),
    'identity' => Setting::get('chu_tro_cccd', ''),
    'issued' => Setting::get('chu_tro_ngay_cap', ''),
    'issue_place' => Setting::get('chu_tro_noi_cap', ''),
    'phone' => (Setting::get('chu_tro_dien_thoai') !== '' ? Setting::get('chu_tro_dien_thoai') : Setting::get('dien_thoai', '')),
];
$electricity = Database::get()->one("SELECT unit_price, unit FROM service WHERE code = 'dien' ORDER BY id_service LIMIT 1", []);
// Mẫu PDF ghi tiền nước theo đầu người (đ/người), khác với dịch vụ nước theo m³ đang dùng để lập hóa đơn; giữ dòng trống theo mẫu để hai bên tự điền, không quy đổi sai đơn giá.
$display = function ($value, $dots = 24) {
    $value = trim((string) $value);
    return $value !== '' ? h($value) : '<span class="contract-blank">' . str_repeat('.', $dots) . '</span>';
};
$displayDate = function ($value, $dots = 12) use ($display) {
    return !empty($value) ? h(dmy($value)) : $display('', $dots);
};
$displayRate = function ($row, $defaultUnit, $dots = 10) use ($display) {
    $rate = $row && isset($row['unit_price']) ? money($row['unit_price']) : '';
    $unit = $row && !empty($row['unit']) ? $row['unit'] : $defaultUnit;
    return $rate !== '' ? h($rate) . '/' . h($unit) : $display('', $dots) . ' đ/' . h($unit);
};
$pageTitle = 'Hợp đồng thuê phòng ' . $contractCode;
$pageSub = 'Bản xem và in theo mẫu hợp đồng thuê phòng trọ';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
?>
<div class="contract-toolbar noprint">
  <a class="btn gray" href="<?= h(url('phong/hop_dong.php')) ?>">← Quay lại danh sách hợp đồng</a>
  <button class="btn" type="button" onclick="window.print()">In hợp đồng</button>
</div>
<article class="contract-document">
  <header class="contract-official-heading">
    <div class="contract-country">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
    <div class="contract-motto">Độc lập – Tự do – Hạnh phúc</div>
    <div class="contract-separator">----------o0o----------</div>
    <h1>HỢP ĐỒNG THUÊ PHÒNG TRỌ</h1>
  </header>

  <p class="contract-opening">Hôm nay ngày <b><?= date('d') ?></b> tháng <b><?= date('m') ?></b> năm <b><?= date('Y') ?></b>; tại địa chỉ: <?= $display($address, 32) ?></p>
  <p class="contract-intro"><b>Chúng tôi gồm:</b></p>

  <section class="contract-legal-section">
    <h2>I. BÊN CHO THUÊ PHÒNG TRỌ (Bên A):</h2>
    <p>Ông/bà: <?= $display($owner['name'], 32) ?> <span class="contract-inline-gap"></span> Sinh ngày: <?= $displayDate($owner['birth']) ?></p>
    <p>Nơi đăng ký HK: <?= $display($owner['address'], 45) ?></p>
    <p>CMND số: <?= $display($owner['identity'], 18) ?> cấp ngày <?= $displayDate($owner['issued'], 10) ?> tại: <?= $display($owner['issue_place'], 22) ?></p>
    <p>Số điện thoại: <?= $display($owner['phone'], 25) ?></p>
  </section>

  <section class="contract-legal-section">
    <h2>II. BÊN THUÊ PHÒNG TRỌ (Bên B):</h2>
    <p>Ông/bà: <?= $display($contract['full_name'], 32) ?> <span class="contract-inline-gap"></span> Sinh ngày: <?= $displayDate($contract['birth_date'] ?? '') ?></p>
    <p>Nơi đăng ký HK thường trú: <?= $display($contract['registered_address'] ?? '', 38) ?></p>
    <p>Số CMND: <?= $display($contract['identity_card'], 18) ?> cấp ngày <?= $displayDate($contract['identity_issue_date'] ?? '', 10) ?> tại: <?= $display($contract['identity_issue_place'] ?? '', 22) ?></p>
    <p>Số điện thoại: <?= $display($contract['phone'], 25) ?></p>
  </section>

  <p class="contract-agreement"><b>Sau khi bàn bạc trên tinh thần dân chủ, hai bên cùng có lợi, cùng thống nhất như sau:</b></p>
  <section class="contract-legal-section contract-rental-terms">
    <p>Bên A đồng ý cho bên B thuê 01 phòng ở tại địa chỉ: <?= $display($address, 28) ?>, phòng <?= h($contract['room_number']) ?>.</p>
    <p>Diện tích: <?= $display($contract['area'] ? $contract['area'] . ' m²' : '', 8) ?>. Giá thuê: <?= h(money($contract['rent_price'] ?: $contract['price'])) ?> đ/tháng. Hình thức thanh toán: <?= $display($contract['payment_method'] ?? '', 16) ?></p>
    <p>Tiền điện <?= $displayRate($electricity, 'kWh') ?> tính theo chỉ số công tơ, thanh toán vào cuối các tháng.</p>
    <p>Tiền nước: <?= $display('', 12) ?> đ/người thanh toán vào đầu các tháng.</p>
    <p>Tiền đặt cọc: <?= h(money($contract['deposit'])) ?>.</p>
    <p>Hợp đồng có giá trị kể từ ngày <?= h(dmy($contract['start_date'])) ?> đến ngày <?= $displayDate($contract['end_date'], 16) ?>.</p>
  </section>

  <section class="contract-legal-section contract-responsibilities">
    <h2>III. TRÁCH NHIỆM CỦA CÁC BÊN</h2>
    <h3>* Trách nhiệm của bên A:</h3>
    <p>1. Tạo mọi điều kiện thuận lợi để bên B thực hiện theo hợp đồng.</p>
    <p>2. Cung cấp nguồn điện, nước, wifi cho bên B sử dụng.</p>

    <h3>* Trách nhiệm của bên B:</h3>
    <p>1. Thanh toán đầy đủ các khoản tiền theo đúng thỏa thuận.</p>
    <p>2. Bảo quản các trang thiết bị và cơ sở vật chất của bên A trang bị cho ban đầu (làm hỏng phải sửa, mất phải đền).</p>
    <p>3. Không được tự ý sửa chữa, cải tạo cơ sở vật chất khi chưa được sự đồng ý của bên A.</p>
    <p>4. Giữ gìn vệ sinh trong và ngoài khuôn viên của phòng trọ.</p>
    <p>5. Bên B phải chấp hành mọi quy định của pháp luật Nhà nước và quy định của địa phương.</p>
    <p>6. Nếu bên B cho khách ở qua đêm thì phải báo và được sự đồng ý của chủ nhà đồng thời phải chịu trách nhiệm về các hành vi vi phạm pháp luật của khách trong thời gian ở lại.</p>
  </section>

  <section class="contract-legal-section contract-common-responsibilities">
    <h2>IV. TRÁCH NHIỆM CHUNG</h2>
    <p>1. Hai bên phải tạo điều kiện cho nhau thực hiện hợp đồng.</p>
    <p>2. Trong thời gian hợp đồng còn hiệu lực nếu bên nào vi phạm các điều khoản đã thỏa thuận thì bên còn lại có quyền đơn phương chấm dứt hợp đồng; nếu sự vi phạm hợp đồng đó gây tổn thất cho bên bị vi phạm hợp đồng thì bên vi phạm hợp đồng phải bồi thường thiệt hại.</p>
    <p>3. Một trong hai bên muốn chấm dứt hợp đồng trước thời hạn thì phải báo trước cho bên kia ít nhất 30 ngày và hai bên phải có sự thống nhất.</p>
    <p>4. Bên A phải trả lại tiền đặt cọc cho bên B.</p>
    <p>5. Bên nào vi phạm điều khoản chung thì phải chịu trách nhiệm trước pháp luật.</p>
    <p>6. Hợp đồng được lập thành 02 bản có giá trị pháp lý như nhau, mỗi bên giữ một bản.</p>
  </section>

  <footer class="contract-signatures">
    <div><b>ĐẠI DIỆN BÊN A</b><em>(Ký, ghi rõ họ tên)</em><span class="contract-sign-space"></span><?= $display($owner['name'], 22) ?></div>
    <div><b>ĐẠI DIỆN BÊN B</b><em>(Ký, ghi rõ họ tên)</em><span class="contract-sign-space"></span><?= h($contract['full_name']) ?></div>
  </footer>
  <div class="contract-internal-code">Mã hồ sơ nội bộ: <?= h($contractCode) ?> · Trạng thái: <?= h(Renter::contractStatus($contract)) ?></div>
</article>
<style>
.contract-document{max-width:900px;margin:0 auto 24px;padding:42px 56px;background:#fff;border:1px solid #e2e8f0;color:#171717;font-family:"Times New Roman",serif;font-size:16px;line-height:1.48;box-shadow:0 8px 28px rgba(15,23,42,.06)}
.contract-official-heading{text-align:center;margin-bottom:26px}.contract-country{font-weight:700;font-size:18px}.contract-motto{font-weight:700;text-decoration:underline;text-underline-offset:5px;margin-top:7px}.contract-separator{margin:16px 0 20px;font-weight:700;letter-spacing:1px}.contract-official-heading h1{font-size:23px;margin:0;font-weight:700}
.contract-opening{font-style:italic;margin-bottom:18px}.contract-intro{font-weight:700;font-style:italic;margin-bottom:16px}.contract-legal-section{margin:14px 0 18px}.contract-legal-section h2{font-size:17px;font-weight:700;margin:12px 0 10px}.contract-legal-section h3{font-size:16px;font-weight:700;margin:12px 0 7px}.contract-legal-section p{margin:7px 0;text-align:justify}.contract-inline-gap{display:inline-block;min-width:10px}.contract-blank{letter-spacing:.5px;color:#333;overflow-wrap:anywhere}.contract-agreement{font-style:italic;margin:16px 0 8px;text-align:justify}.contract-rental-terms{margin-top:8px}.contract-responsibilities p,.contract-common-responsibilities p{padding-left:0;text-indent:0}.contract-signatures{display:grid;grid-template-columns:1fr 1fr;gap:36px;text-align:center;margin-top:34px;break-inside:avoid}.contract-signatures div{display:flex;flex-direction:column;align-items:center}.contract-signatures b{font-size:16px}.contract-signatures em{margin-top:4px}.contract-sign-space{height:76px}.contract-internal-code{text-align:right;color:#777;font:11px Arial,sans-serif;margin-top:26px}
@media(max-width:700px){.contract-document{padding:24px 18px;font-size:15px}.contract-country{font-size:16px}.contract-official-heading h1{font-size:20px}.contract-signatures{gap:10px}.contract-sign-space{height:55px}}
@media print{@page{size:A4;margin:17mm 19mm}body{background:#fff!important}.sidebar,.topbar,.contract-toolbar{display:none!important}.app,.main,.content{display:block!important;width:auto!important;max-width:none!important;margin:0!important;padding:0!important}.contract-document{max-width:none;margin:0;padding:0;border:0;box-shadow:none;font-size:12.5pt;line-height:1.35}.contract-country{font-size:14pt}.contract-official-heading h1{font-size:17pt}.contract-legal-section{margin:10px 0 12px}.contract-legal-section p{margin:5px 0}.contract-signatures{break-inside:avoid}.contract-internal-code{font-size:8pt}.contract-document *{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>
