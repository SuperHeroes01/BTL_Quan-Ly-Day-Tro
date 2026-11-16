<?php
// thiet_bi/form.php - Thêm / sửa thiết bị, gán thiết bị vào phòng
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$devM = new Device();
$id = (int) qs('id', '0');
$dev = $id ? $devM->find($id) : null;
if ($id && !$dev) { flash('error', 'Không tìm thấy thiết bị'); redirect('thiet_bi/index.php'); }
$errors = [];
$d = $dev ?: ['device_name' => '', 'id_room' => '', 'status' => 'Hoạt động', 'last_maintenance' => '', 'quantity' => 1, 'note' => ''];
if (is_post()) {
    csrf_check();
    $d = ['device_name' => post('device_name'), 'id_room' => post('id_room'), 'status' => post('status'),
          'last_maintenance' => post('last_maintenance'), 'quantity' => post('quantity', '1'), 'note' => post('note')];
    if ($d['device_name'] === '') { $errors[] = 'Vui lòng nhập tên thiết bị'; }
    if (!ctype_digit($d['quantity']) || (int) $d['quantity'] < 1) { $errors[] = 'Số lượng phải là số nguyên dương'; }
    if (!in_array($d['status'], Device::STATUSES, true)) { $errors[] = 'Trạng thái không hợp lệ'; }
    if ($d['last_maintenance'] !== '' && !valid_date($d['last_maintenance'])) { $errors[] = 'Ngày bảo trì không hợp lệ'; }
    if ($d['id_room'] !== '' && !(new Room())->find((int) $d['id_room'])) { $errors[] = 'Phòng không tồn tại'; }
    if (!$errors) {
        $devM->save(['device_name' => $d['device_name'], 'id_room' => $d['id_room'] === '' ? null : (int) $d['id_room'], 'status' => $d['status'],
                     'last_maintenance' => $d['last_maintenance'] === '' ? null : $d['last_maintenance'], 'quantity' => (int) $d['quantity'], 'note' => $d['note']], $id ?: null);
        flash('success', $id ? 'Đã cập nhật thiết bị' : 'Đã thêm thiết bị');
        redirect('thiet_bi/index.php');
    }
}
$rooms = (new Room())->all('room_number');
$pageTitle = $id ? 'Sửa thiết bị' : 'Thêm thiết bị';
$pageSub = 'Thông tin thiết bị và vị trí đặt';
$active = 'thiet_bi';
require __DIR__ . '/../includes/header.php';
?>
<form class="card" method="post" data-validate><div class="card-h"><?= h($pageTitle) ?></div><div class="card-b"><?= csrf_field() ?>
  <?php foreach ($errors as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>
  <div class="form-row">
    <div class="field"><label>Tên thiết bị *</label><input name="device_name" value="<?= h($d['device_name']) ?>" required data-label="Tên thiết bị"></div>
    <div class="field"><label>Phòng đặt thiết bị</label><select name="id_room"><option value="">Khu chung / kho</option>
      <?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id_room'] ?>" <?= (string) $d['id_room'] === (string) $r['id_room'] ? 'selected' : '' ?>><?= h($r['room_number']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Số lượng</label><input type="number" name="quantity" min="1" value="<?= h($d['quantity']) ?>" data-label="Số lượng"></div>
    <div class="field"><label>Trạng thái</label><select name="status"><?php foreach (Device::STATUSES as $s): ?><option <?= $d['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Bảo dưỡng gần nhất</label><input type="date" name="last_maintenance" value="<?= h($d['last_maintenance']) ?>"></div>
    <div class="field full"><label>Ghi chú</label><textarea name="note" rows="2"><?= h($d['note']) ?></textarea></div>
  </div>
  <button class="btn">Lưu thiết bị</button> <a class="btn gray" href="<?= h(url('thiet_bi/index.php')) ?>">Hủy</a>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
