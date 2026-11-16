<?php
// tai_khoan.php - Hồ sơ cá nhân và đổi mật khẩu (dùng chung cho mọi vai trò)
require_once __DIR__ . '/includes/bootstrap.php';
Auth::guard();

$userM = new User();
$renterM = new Renter();
$renter = Auth::is('nguoithue') ? $renterM->findByUser(Auth::id()) : null;

if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'profile') {
        $r = $userM->updateProfile(Auth::id(), post('full_name'));
        if ($r === true && $renter) { $r = $renterM->updateContact((int) $renter['id_renter'], post('phone'), post('hometown')); }
        if ($r === true) { $_SESSION['user']['full_name'] = post('full_name'); flash('success', 'Đã cập nhật thông tin cá nhân'); }
        else { flash('error', $r); }
    } elseif ($act === 'password') {
        if (post('new') !== post('again')) { flash('error', 'Mật khẩu nhập lại không khớp'); }
        else {
            $r = $userM->changePassword(Auth::id(), $_POST['old'] ?? '', $_POST['new'] ?? '');
            $r === true ? flash('success', 'Đổi mật khẩu thành công') : flash('error', $r);
        }
    }
    redirect('tai_khoan.php');
}

$me = $userM->find(Auth::id());
$pageTitle = 'Tài khoản của tôi';
$pageSub = 'Cập nhật thông tin cá nhân và đổi mật khẩu';
$active = 'tai_khoan';
require __DIR__ . '/includes/header.php';
?>
<div class="grid g2">
  <form class="card" method="post" data-validate>
    <div class="card-h">Thông tin cá nhân</div><div class="card-b">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="field"><label>Tên đăng nhập</label><input value="<?= h($me['username']) ?>" disabled></div>
      <div class="field"><label>Vai trò</label><input value="<?= h(Auth::ROLES[$me['role']]) ?>" disabled></div>
      <div class="field"><label>Họ và tên</label><input name="full_name" value="<?= h($me['full_name']) ?>" required data-label="Họ và tên"></div>
      <?php if ($renter): ?>
        <div class="field"><label>Số điện thoại</label><input name="phone" value="<?= h($renter['phone']) ?>" required data-label="Số điện thoại"></div>
        <div class="field"><label>Quê quán</label><input name="hometown" value="<?= h($renter['hometown']) ?>"></div>
      <?php endif; ?>
      <button class="btn">Lưu thay đổi</button>
    </div>
  </form>
  <form class="card" method="post" data-validate>
    <div class="card-h">Đổi mật khẩu</div><div class="card-b">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label>Mật khẩu hiện tại</label><input type="password" name="old" required data-label="Mật khẩu hiện tại"></div>
      <div class="field"><label>Mật khẩu mới (tối thiểu 6 ký tự)</label><input type="password" name="new" required data-label="Mật khẩu mới"></div>
      <div class="field"><label>Nhập lại mật khẩu mới</label><input type="password" name="again" required data-label="Nhập lại mật khẩu"></div>
      <button class="btn amber">Đổi mật khẩu</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
