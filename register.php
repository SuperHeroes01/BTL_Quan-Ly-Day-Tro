<?php
// register.php - Đăng ký tài khoản người thuê. Tài khoản sẽ chờ Admin/Chủ trọ gán phòng.
require_once __DIR__ . '/includes/bootstrap.php';
if (Auth::check()) { redirect('home.php'); }

$error = '';
$d = ['full_name' => '', 'username' => '', 'phone' => '', 'identity_card' => ''];
if (is_post()) {
    csrf_check();
    foreach ($d as $k => $v) { $d[$k] = post($k); }
    $password = $_POST['password'] ?? '';
    $again = $_POST['password_again'] ?? '';
    if ($password !== $again) { $error = 'Mật khẩu nhập lại không khớp'; }
    else {
        try {
            $result = (new User())->registerTenantAccount($d['username'], $password, $d['full_name'], $d['phone'], $d['identity_card']);
            if (is_string($result)) { $error = $result; }
            else {
                flash('success', 'Đăng ký tài khoản thành công. Vui lòng đăng nhập và liên hệ Chủ trọ để được gán phòng.');
                redirect('login.php');
            }
        } catch (mysqli_sql_exception $e) { $error = mysql_error_text($e); }
    }
}
$brand = Setting::get('ten_day_tro', APP_NAME);
?>
<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng ký tài khoản - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head><body class="auth-page">
<div class="auth-layout">
  <section class="auth-visual">
    <div class="auth-brand"><span class="auth-mark">⌂</span><span><b><?= h(APP_NAME) ?></b><small><?= h($brand) ?></small></span></div>
    <div class="auth-copy"><span class="eyebrow">CHÀO MỪNG BẠN</span><h1>Tạo tài khoản thuê trọ dễ dàng hơn.</h1><p>Quản lý thông tin phòng, hợp đồng, hóa đơn và yêu cầu hỗ trợ ngay trên một nền tảng.</p></div>
    <img src="<?= h(url('assets/images/login-hero.svg')) ?>" alt="Minh họa phòng trọ hiện đại" class="auth-art">
    <div class="auth-footnote">Thông tin đăng ký chỉ dùng để tạo tài khoản người thuê và xác minh khi cần khôi phục mật khẩu.</div>
  </section>
  <section class="auth-form-wrap">
    <form class="auth-form" method="post" data-validate>
      <?= csrf_field() ?>
      <div class="auth-heading"><span class="auth-tag">TÀI KHOẢN NGƯỜI THUÊ</span><h2>Đăng ký tài khoản</h2><p>Nhập thông tin của bạn để bắt đầu.</p></div>
      <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
      <div class="field"><label>Họ và tên *</label><input name="full_name" value="<?= h($d['full_name']) ?>" required autocomplete="name" placeholder="Nguyễn Văn A"></div>
      <div class="field"><label>Tên đăng nhập *</label><input name="username" value="<?= h($d['username']) ?>" required autocomplete="username" placeholder="Ví dụ: nguyenvana"></div>
      <div class="form-row">
        <div class="field"><label>Số điện thoại *</label><input name="phone" value="<?= h($d['phone']) ?>" required inputmode="numeric" placeholder="09xxxxxxxx"></div>
        <div class="field"><label>CCCD / CMND *</label><input name="identity_card" value="<?= h($d['identity_card']) ?>" required inputmode="numeric" placeholder="Số giấy tờ đã đăng ký"></div>
      </div>
      <div class="field"><label>Mật khẩu *</label><input type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự"></div>
      <div class="field"><label>Nhập lại mật khẩu *</label><input type="password" name="password_again" required minlength="6" autocomplete="new-password" placeholder="Nhập lại mật khẩu"></div>
      <button class="btn auth-submit">Tạo tài khoản</button>
      <div class="auth-links"><span>Đã có tài khoản?</span><a href="<?= h(url('login.php')) ?>">Đăng nhập</a></div>
      <p class="auth-note">Sau khi đăng ký, Chủ trọ/Admin cần gán tài khoản của bạn với phòng thuê để hiển thị thông tin phòng và hợp đồng.</p>
    </form>
  </section>
</div>
</body></html>
