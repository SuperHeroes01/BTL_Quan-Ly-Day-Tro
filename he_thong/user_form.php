<?php
// he_thong/user_form.php - Thêm tài khoản người dùng mới (admin, chủ trọ, nhân viên kỹ thuật, người thuê)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);
$userM = new User();
$errors = [];
$availableRoles = Auth::ROLES; unset($availableRoles['admin'], $availableRoles['chutro']);
$d = ['username' => '', 'full_name' => '', 'role' => 'nhanvien'];
if (is_post()) {
    csrf_check();
    $d = ['username' => post('username'), 'full_name' => post('full_name'), 'role' => post('role')];
    if (!preg_match('/^[a-zA-Z0-9_.]{4,30}$/', $d['username'])) { $errors[] = 'Tên đăng nhập gồm 4-30 ký tự chữ, số, dấu chấm hoặc gạch dưới'; }
    elseif ($userM->usernameExists($d['username'])) { $errors[] = 'Tên đăng nhập đã tồn tại'; }
    if ($d['full_name'] === '') { $errors[] = 'Vui lòng nhập họ tên'; }
    if (!isset($availableRoles[$d['role']])) { $errors[] = 'Vai trò không hợp lệ'; }
    if (strlen(post('password')) < 6) { $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự'; }
    if (!$errors) {
        $userM->insert(['username' => $d['username'], 'password' => $_POST['password'], 'role' => $d['role'], 'full_name' => $d['full_name'], 'status' => 'Hoạt động']);
        flash('success', 'Đã tạo tài khoản ' . $d['username']);
        redirect('he_thong/phan_quyen.php');
    }
}
$pageTitle = 'Thêm người dùng';
$pageSub = 'Tạo tài khoản và gán vai trò';
$active = 'he_thong';
require __DIR__ . '/../includes/header.php';
?>
<form class="card" method="post" data-validate><div class="card-h">Thông tin tài khoản</div><div class="card-b"><?= csrf_field() ?>
  <?php foreach ($errors as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>
  <div class="form-row">
    <div class="field"><label>Tên đăng nhập *</label><input name="username" value="<?= h($d['username']) ?>" required data-label="Tên đăng nhập"></div>
    <div class="field"><label>Họ và tên *</label><input name="full_name" value="<?= h($d['full_name']) ?>" required data-label="Họ và tên"></div>
    <div class="field"><label>Mật khẩu *</label><input type="password" name="password" required data-label="Mật khẩu"></div>
    <div class="field"><label>Vai trò</label><select name="role"><?php foreach ($availableRoles as $k => $v): ?><option value="<?= h($k) ?>" <?= $d['role'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
      <small>Vai trò Admin và Chủ trọ là vai trò cố định, không thể tạo hoặc thay đổi tại giao diện; chỉ được cấu hình trong mã nguồn/SQL triển khai. Người thuê nên được tạo ở mục Quản lý phòng trọ &rsaquo; Người thuê để gắn với phòng.</small></div>
  </div>
  <button class="btn">Tạo tài khoản</button> <a class="btn gray" href="<?= h(url('he_thong/phan_quyen.php')) ?>">Hủy</a>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
