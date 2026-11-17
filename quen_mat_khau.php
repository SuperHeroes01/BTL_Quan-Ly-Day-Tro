<?php
// quen_mat_khau.php - Khôi phục mật khẩu tài khoản người thuê bằng thông tin đã đăng ký.
require_once __DIR__ . '/includes/bootstrap.php';
if (Auth::check()) { redirect('home.php'); }
$error = '';
$success = false;
$d = ['username' => '', 'phone' => '', 'identity_card' => ''];
if (is_post()) {
    csrf_check();
    foreach ($d as $k => $v) { $d[$k] = post($k); }
    $password = $_POST['password'] ?? '';
    $again = $_POST['password_again'] ?? '';
    if ($password !== $again) { $error = 'Mật khẩu nhập lại không khớp'; }
    else {
        try {
            $result = (new User())->recoverPassword($d['username'], $d['phone'], $d['identity_card'], $password);
            if ($result === true) { $success = true; }
            else { $error = $result; }
        } catch (Throwable $e) { $error = mysql_error_text($e); }
    }
}
$brand = Setting::get('ten_day_tro', APP_NAME);
?>
<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quên mật khẩu - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head><body class="auth-page">
<div class="auth-layout">
  <section class="auth-visual">
    <div class="auth-brand"><span class="auth-mark">⌂</span><span><b><?= h(APP_NAME) ?></b><small><?= h($brand) ?></small></span></div>
    <div class="auth-copy"><span class="eyebrow">HỖ TRỢ TÀI KHOẢN</span><h1>Khôi phục quyền truy cập an toàn.</h1><p>Xác minh thông tin đã đăng ký để thiết lập mật khẩu mới.</p></div>
    <img src="<?= h(url('assets/images/login-hero.svg')) ?>" alt="Minh họa phòng trọ" class="auth-art">
    <div class="auth-footnote">Bản demo xác minh bằng tên đăng nhập, số điện thoại và CCCD. Khi triển khai thực tế nên bổ sung mã OTP qua SMS hoặc email.</div>
  </section>
  <section class="auth-form-wrap">
    <form class="auth-form" method="post" data-validate>
      <?= csrf_field() ?>
      <div class="auth-heading"><span class="auth-tag">KHÔI PHỤC MẬT KHẨU</span><h2>Quên mật khẩu?</h2><p>Nhập đúng thông tin đã dùng khi đăng ký.</p>
         <p class="auth-note">Chức năng này chỉ áp dụng cho tài khoản Người thuê. Tài khoản Admin và Chủ trọ không thể đặt lại mật khẩu trên web, chỉ đổi được mật khẩu sau khi đăng nhập (mục Tài khoản của tôi). Nhân viên kỹ thuật liên hệ Admin để được hỗ trợ.</p>
      <?php if ($success): ?>
        <div class="alert success">Đã đổi mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.</div>
        <a class="btn auth-submit" href="<?= h(url('login.php')) ?>">Quay lại đăng nhập</a>
      <?php else: ?>
        <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
        <div class="field"><label>Tên đăng nhập *</label><input name="username" value="<?= h($d['username']) ?>" required autocomplete="username"></div>
        <div class="field"><label>Số điện thoại đã đăng ký *</label><input name="phone" value="<?= h($d['phone']) ?>" required inputmode="numeric"></div>
        <div class="field"><label>Số CCCD / CMND đã đăng ký *</label><input name="identity_card" value="<?= h($d['identity_card']) ?>" required inputmode="numeric"></div>
        <div class="field"><label>Mật khẩu mới *</label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
        <div class="field"><label>Nhập lại mật khẩu mới *</label><input type="password" name="password_again" required minlength="6" autocomplete="new-password"></div>
        <button class="btn auth-submit">Xác nhận đổi mật khẩu</button>
        <div class="auth-links"><a href="<?= h(url('login.php')) ?>">← Quay lại đăng nhập</a></div>
      <?php endif; ?>
    </form>
  </section>
</div>
</body></html>
