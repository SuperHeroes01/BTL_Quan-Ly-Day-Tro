<?php
// phong/nguoi_thue_form.php - Thêm/sửa người thuê hoặc gắn tài khoản tự đăng ký vào một phòng.
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$renterM = new Renter(); $userM = new User(); $roomM = new Room();
$id = (int) qs('id', '0');
$cur = $id ? $renterM->findFull($id) : null;
if ($id && !$cur) { flash('error', 'Không tìm thấy người thuê'); redirect('phong/nguoi_thue.php'); }

$errors = [];
$d = $cur ?: ['full_name' => '', 'username' => '', 'phone' => '', 'identity_card' => '', 'birth_date' => '', 'registered_address' => '', 'identity_issue_date' => '', 'identity_issue_place' => '', 'payment_method' => '', 'hometown' => '', 'id_room' => (int) qs('room', '0'),
              'start_date' => date('Y-m-d'), 'end_date' => '', 'deposit' => '0'];
if (is_post()) {
    csrf_check();
    $d = ['full_name' => post('full_name'), 'username' => post('username'), 'phone' => post('phone'),
          'identity_card' => post('identity_card'), 'birth_date' => post('birth_date'),
          'registered_address' => post('registered_address'), 'identity_issue_date' => post('identity_issue_date'),
          'identity_issue_place' => post('identity_issue_place'), 'payment_method' => post('payment_method'),
          'hometown' => post('hometown'), 'id_room' => (int) post('id_room'),
          'start_date' => post('start_date'), 'end_date' => post('end_date'), 'deposit' => post('deposit', '0')];
    if ($d['full_name'] === '') { $errors[] = 'Vui lòng nhập họ tên'; }
    if (!preg_match('/^[0-9]{9,11}$/', $d['phone'])) { $errors[] = 'Số điện thoại phải gồm 9-11 chữ số'; }
    if (!preg_match('/^[0-9]{9,12}$/', $d['identity_card'])) { $errors[] = 'CCCD/CMND phải gồm 9-12 chữ số'; }
    if (!valid_date($d['start_date'])) { $errors[] = 'Ngày bắt đầu không hợp lệ'; }
    if ($d['birth_date'] !== '' && !valid_date($d['birth_date'])) { $errors[] = 'Ngày sinh không hợp lệ'; }
    if ($d['identity_issue_date'] !== '' && !valid_date($d['identity_issue_date'])) { $errors[] = 'Ngày cấp CCCD/CMND không hợp lệ'; }
    if ($d['end_date'] !== '' && (!valid_date($d['end_date']) || $d['end_date'] <= $d['start_date'])) { $errors[] = 'Ngày trả phòng phải sau ngày bắt đầu'; }
    if (!is_numeric($d['deposit']) || $d['deposit'] < 0) { $errors[] = 'Tiền cọc không hợp lệ'; }
    $room = $roomM->find($d['id_room']);
    if (!$room) { $errors[] = 'Vui lòng chọn phòng'; }
    elseif ($room['status'] === 'Bảo trì') { $errors[] = 'Phòng đang bảo trì, không thể cho thuê'; }

    $mode = post('account_mode', 'new');
    $existingUserId = (int) post('registered_user_id', '0');
    if (!$id && $mode === 'existing') {
        $registered = Database::get()->one("SELECT u.id_user FROM `user` u LEFT JOIN renter t ON t.id_user = u.id_user\n            WHERE u.id_user = ? AND u.role = 'nguoithue' AND u.status = 'Hoạt động' AND t.id_renter IS NULL", [$existingUserId]);
        if (!$registered) { $errors[] = 'Tài khoản đăng ký trước đó không hợp lệ hoặc đã được gắn phòng'; }
    } elseif (!$id) {
        if (!preg_match('/^[a-zA-Z0-9_.]{4,30}$/', $d['username'])) { $errors[] = 'Tên đăng nhập gồm 4-30 ký tự chữ, số, dấu chấm hoặc gạch dưới'; }
        elseif ($userM->usernameExists($d['username'])) { $errors[] = 'Tên đăng nhập đã tồn tại'; }
        if (strlen($_POST['password'] ?? '') < 6) { $errors[] = 'Mật khẩu ban đầu phải có ít nhất 6 ký tự'; }
    }

    if (!$errors) {
        $info = ['id_room' => $d['id_room'], 'identity_card' => $d['identity_card'], 'phone' => $d['phone'], 'hometown' => $d['hometown'],
                 'birth_date' => $d['birth_date'] === '' ? null : $d['birth_date'],
                 'registered_address' => $d['registered_address'],
                 'identity_issue_date' => $d['identity_issue_date'] === '' ? null : $d['identity_issue_date'],
                 'identity_issue_place' => $d['identity_issue_place'], 'payment_method' => $d['payment_method'],
                 'start_date' => $d['start_date'], 'end_date' => $d['end_date'] === '' ? null : $d['end_date'], 'deposit' => (float) $d['deposit']];
        try {
            if ($id) {
                $oldRoom = (int) $cur['id_room'];
                $renterM->update($id, $info);
                $userM->update((int) $cur['id_user'], ['full_name' => $d['full_name'], 'recovery_phone' => $d['phone'], 'recovery_identity_card' => $d['identity_card']]);
                $roomM->refreshStatus($oldRoom); $roomM->refreshStatus($d['id_room']);
                flash('success', 'Đã cập nhật thông tin người thuê');
            } elseif ($mode === 'existing') {
                $renterM->registerExisting($existingUserId, $info, $d['full_name']);
                flash('success', 'Đã gắn tài khoản đã đăng ký vào phòng ' . $room['room_number']);
            } else {
                $renterM->register(['username' => $d['username'], 'password' => $_POST['password'], 'full_name' => $d['full_name']], $info);
                flash('success', 'Đã thêm người thuê và tạo tài khoản ' . $d['username']);
            }
            redirect('phong/nguoi_thue.php');
        } catch (Throwable $e) { $errors[] = mysql_error_text($e); }
    }
}
$rooms = $roomM->all('room_number');
$registeredUsers = !$id ? Database::get()->query("SELECT u.id_user, u.full_name, u.username, u.recovery_phone FROM `user` u\n    LEFT JOIN renter t ON t.id_user = u.id_user WHERE u.role = 'nguoithue' AND u.status = 'Hoạt động' AND t.id_renter IS NULL ORDER BY u.full_name") : [];
$pageTitle = $id ? 'Sửa người thuê' : 'Thêm người thuê';
$pageSub = 'Thông tin người thuê và hợp đồng';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
?>
<form class="card" method="post" data-validate>
  <div class="card-h"><?= h($pageTitle) ?></div><div class="card-b">
    <?= csrf_field() ?>
    <?php foreach ($errors as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>
    <div class="form-row">
      <div class="field"><label>Họ và tên *</label><input name="full_name" value="<?= h($d['full_name']) ?>" required data-label="Họ và tên"></div>
      <div class="field"><label>Số điện thoại *</label><input name="phone" value="<?= h($d['phone']) ?>" required data-label="Số điện thoại"></div>
      <div class="field"><label>CCCD / CMND *</label><input name="identity_card" value="<?= h($d['identity_card']) ?>" required data-label="CCCD"></div>
      <div class="field"><label>Ngày sinh</label><input type="date" name="birth_date" value="<?= h($d['birth_date'] ?? '') ?>"></div>
      <div class="field"><label>Nơi đăng ký hộ khẩu thường trú</label><input name="registered_address" value="<?= h($d['registered_address'] ?? '') ?>" placeholder="Địa chỉ thường trú theo giấy tờ"></div>
      <div class="field"><label>Ngày cấp CCCD/CMND</label><input type="date" name="identity_issue_date" value="<?= h($d['identity_issue_date'] ?? '') ?>"></div>
      <div class="field"><label>Nơi cấp CCCD/CMND</label><input name="identity_issue_place" value="<?= h($d['identity_issue_place'] ?? '') ?>"></div>
      <div class="field"><label>Hình thức thanh toán tiền thuê</label><input name="payment_method" value="<?= h($d['payment_method'] ?? '') ?>" placeholder="Ví dụ: Tiền mặt / Chuyển khoản"></div>
      <div class="field"><label>Quê quán</label><input name="hometown" value="<?= h($d['hometown']) ?>"></div>
      <div class="field"><label>Phòng *</label><select name="id_room" required data-label="Phòng"><option value="">-- Chọn phòng --</option>
        <?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id_room'] ?>" <?= (int) $d['id_room'] === (int) $r['id_room'] ? 'selected' : '' ?>><?= h($r['room_number']) ?> (<?= h($r['status']) ?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label>Tiền cọc (đ)</label><input type="number" name="deposit" min="0" step="1000" value="<?= h($d['deposit']) ?>" data-label="Tiền cọc"></div>
      <div class="field"><label>Ngày bắt đầu thuê *</label><input type="date" name="start_date" value="<?= h($d['start_date']) ?>" required data-label="Ngày bắt đầu"></div>
      <div class="field"><label>Ngày trả phòng (nếu đã xác định)</label><input type="date" name="end_date" value="<?= h($d['end_date']) ?>"></div>
    </div>
    <?php if (!$id): ?>
      <hr class="soft-rule">
      <div class="field"><label>Cách tạo tài khoản</label><select name="account_mode" id="accountMode"><option value="new">Tạo tài khoản mới tại đây</option><?php if ($registeredUsers): ?><option value="existing">Gắn tài khoản người thuê đã tự đăng ký</option><?php endif; ?></select></div>
      <?php if ($registeredUsers): ?>
      <div id="existingUserBox" class="account-choice" hidden><div class="field"><label>Chọn tài khoản đã đăng ký</label><select name="registered_user_id" id="registeredUser"><option value="">-- Chọn tài khoản --</option>
        <?php foreach ($registeredUsers as $ru): ?><option value="<?= (int) $ru['id_user'] ?>"><?= h($ru['full_name']) ?> · <?= h($ru['username']) ?> · <?= h($ru['recovery_phone']) ?></option><?php endforeach; ?></select></div><small>Tài khoản này sẽ được gắn vào phòng đang chọn, không tạo tài khoản trùng.</small></div>
      <?php endif; ?>
      <div id="newUserBox" class="account-choice"><div class="form-row">
        <div class="field"><label>Tên đăng nhập *</label><input name="username" value="<?= h($d['username']) ?>" id="newUsername" required data-label="Tên đăng nhập"></div>
        <div class="field"><label>Mật khẩu ban đầu *</label><input type="password" name="password" id="newPassword" required data-label="Mật khẩu"><small>Người thuê có thể tự đổi sau khi đăng nhập.</small></div>
      </div></div>
    <?php endif; ?>
    <button class="btn">Lưu thông tin</button> <a class="btn gray" href="<?= h(url('phong/nguoi_thue.php')) ?>">Hủy</a>
  </div>
</form>
<?php if (!$id && $registeredUsers): ?>
<script>
(function(){
  var mode=document.getElementById('accountMode'), existing=document.getElementById('existingUserBox'), fresh=document.getElementById('newUserBox');
  var pick=document.getElementById('registeredUser'), username=document.getElementById('newUsername'), password=document.getElementById('newPassword');
  function sync(){ var useExisting=mode.value==='existing'; existing.hidden=!useExisting; fresh.hidden=useExisting; pick.required=useExisting; pick.disabled=!useExisting; username.required=!useExisting; password.required=!useExisting; username.disabled=useExisting; password.disabled=useExisting; }
  mode.addEventListener('change',sync); sync();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
