<?php
// dich_vu/hoa_don_tao.php - Lập hóa đơn tháng: nhập chỉ số điện nước, chọn dịch vụ, hệ thống tự tính tổng
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$billM = new Bill(); $svcM = new Service(); $roomM = new Room();

$idRoom = (int) (is_post() ? post('id_room') : qs('room', '0'));
$month = is_post() ? post('month') : qs('month', date('Y-m'));
if (!valid_month($month)) { $month = date('Y-m'); }

if (is_post()) {
    csrf_check();
    $inputs = ['idx' => [], 'use' => []];
    foreach ((array) ($_POST['old'] ?? []) as $sid => $v) { $inputs['idx'][(int) $sid]['old'] = trim((string) $v); }
    foreach ((array) ($_POST['new'] ?? []) as $sid => $v) { $inputs['idx'][(int) $sid]['new'] = trim((string) $v); }
    foreach ((array) ($_POST['use'] ?? []) as $sid => $v) { $inputs['use'][(int) $sid] = 1; }
    try {
        $id = $billM->generateBill($idRoom, $month, $inputs, post('note'));
        flash('success', 'Đã lập hóa đơn tháng ' . month_label($month));
        redirect('dich_vu/hoa_don_xem.php?id=' . $id);
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
      catch (mysqli_sql_exception $e) { flash('error', mysql_error_text($e)); }
}

$rented = Database::get()->query("SELECT * FROM room WHERE status = 'Đã thuê' ORDER BY room_number");
$room = $idRoom ? $roomM->find($idRoom) : null;
$services = $svcM->active();
$pageTitle = 'Lập hóa đơn';
$pageSub = 'Nhập chỉ số điện nước và chọn dịch vụ sử dụng trong tháng';
$active = 'dich_vu';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-h">Chọn phòng và tháng</div><div class="card-b">
  <form class="filter" method="get">
    <div class="field"><label>Phòng đang thuê</label><select name="room" required><option value="">-- Chọn phòng --</option>
      <?php foreach ($rented as $r): ?><option value="<?= (int) $r['id_room'] ?>" <?= $idRoom == $r['id_room'] ? 'selected' : '' ?>><?= h($r['room_number']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Tháng</label><input type="month" name="month" value="<?= h($month) ?>" required></div>
    <button class="btn">Tiếp tục</button>
  </form></div></div>

<?php if ($room && $room['status'] === 'Đã thuê'): ?>
<form class="card" method="post" id="billForm" data-room-price="<?= h($room['price']) ?>">
  <div class="card-h">Hóa đơn phòng <?= h($room['room_number']) ?> &middot; tháng <?= h(month_label($month)) ?></div>
  <div class="card-b">
    <?= csrf_field() ?><input type="hidden" name="id_room" value="<?= (int) $idRoom ?>"><input type="hidden" name="month" value="<?= h($month) ?>">
    <div class="rowline"><span>Tiền phòng</span><b><?= h(money($room['price'])) ?></b></div>
    <h3 style="margin:14px 0 6px">Dịch vụ tính theo chỉ số</h3>
    <p class="meter-carry-note">Chỉ số cũ được tự động lấy từ chỉ số mới gần nhất đã lưu của phòng ở các hóa đơn trước. Ví dụ: tháng 10 ghi chỉ số mới 450 thì tháng 11 sẽ tự điền chỉ số cũ là 450. Chỉ lần ghi đầu tiên mới cần nhập chỉ số cũ ban đầu.</p>
    <div class="tw"><table class="t"><thead><tr><th>Dịch vụ</th><th>Đơn giá</th><th>Chỉ số cũ</th><th>Chỉ số mới</th><th>Thành tiền</th></tr></thead><tbody>
    <?php foreach ($services as $s): if ($s['type'] !== 'Theo chỉ số') { continue; }
        $prev = $billM->previousIndex($idRoom, (int) $s['id_service'], $month); ?>
      <tr class="meter" data-price="<?= h($s['unit_price']) ?>"><td><b><?= h($s['service_name']) ?></b></td><td><?= h(money($s['unit_price'])) ?>/<?= h($s['unit']) ?></td>
        <td><input class="old" type="number" min="0" name="old[<?= (int) $s['id_service'] ?>]" value="<?= h($prev ?? 0) ?>" <?= $prev !== null ? 'readonly' : '' ?> style="width:110px;padding:6px;border:1px solid #cbd5e1;border-radius:6px"></td>
        <td><input class="new" type="number" min="0" name="new[<?= (int) $s['id_service'] ?>]" placeholder="để trống nếu không dùng" style="width:170px;padding:6px;border:1px solid #cbd5e1;border-radius:6px"></td>
        <td class="amt">—</td></tr>
    <?php endforeach; ?></tbody></table></div>
    <h3 style="margin:14px 0 6px">Dịch vụ cố định hàng tháng</h3>
    <?php foreach ($services as $s): if ($s['type'] !== 'Cố định') { continue; } ?>
      <label class="rowline fixed" data-price="<?= h($s['unit_price']) ?>" style="cursor:pointer"><span><input type="checkbox" name="use[<?= (int) $s['id_service'] ?>]" value="1" <?= $s['code'] !== 'maygiat' ? 'checked' : '' ?>> <?= h($s['service_name']) ?></span><b><?= h(money($s['unit_price'])) ?>/<?= h($s['unit']) ?></b></label>
    <?php endforeach; ?>
    <div class="field" style="margin-top:12px"><label>Ghi chú</label><input name="note" placeholder="Ví dụ: Giảm 50.000đ do mất nước 2 ngày"></div>
    <p class="total">Tổng tạm tính: <span id="billTotal"><?= h(money($room['price'])) ?></span></p>
    <button class="btn green">Lập hóa đơn</button> <a class="btn gray" href="<?= h(url('dich_vu/index.php')) ?>">Hủy</a>
  </div>
</form>
<?php elseif ($idRoom): ?><div class="alert warn">Phòng chưa có người thuê nên không thể lập hóa đơn.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
