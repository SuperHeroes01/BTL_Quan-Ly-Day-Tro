<?php
// he_thong/nhan_vien.php - Tab "Nhân viên kỹ thuật": danh sách nhân viên, thêm/sửa, liên kết tài khoản đăng nhập (chỉ Admin)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);
$staffM = new Staff();
$id = (int) qs('id', '0');
$edit = $id ? $staffM->find($id) : null;

if (is_post()) {
    csrf_check();
    $eid = (int) post('id');
    if (post('action') === 'delete') {
        try { $staffM->delete($eid); flash('success', 'Đã xóa nhân viên'); } catch (mysqli_sql_exception $e) { flash('error', 'Nhân viên đã xử lý yêu cầu sửa chữa nên chỉ có thể chuyển sang trạng thái Nghỉ'); }
    } else {
        $d = ['staff_name' => post('staff_name'), 'specialty' => post('specialty'), 'phone' => post('phone'),
              'email' => post('email') === '' ? null : post('email'), 'status' => post('status') === 'Nghỉ' ? 'Nghỉ' : 'Đang làm',
              'id_user' => post('id_user') === '' ? null : (int) post('id_user')];
        $accountError = '';
        if ($d['id_user'] !== null) {
            $account = Database::get()->one("SELECT id_user, role, status FROM `user` WHERE id_user = ?", [$d['id_user']]);
            if (!$account || $account['role'] !== 'nhanvien' || $account['status'] !== 'Hoạt động') {
                $accountError = 'Chỉ được liên kết tài khoản Nhân viên kỹ thuật đang Hoạt động. Tài khoản bị khóa hoặc sai vai trò không thể nhận việc.';
            } else {
                $used = (int) Database::get()->value(
                    "SELECT COUNT(*) FROM staff WHERE id_user = ? AND id_staff <> ?",
                    [$d['id_user'], $eid]
                );
                if ($used > 0) { $accountError = 'Tài khoản này đã được liên kết với một nhân viên kỹ thuật khác.'; }
            }
        }
        if ($d['staff_name'] === '') { flash('error', 'Vui lòng nhập tên nhân viên'); }
        elseif ($d['phone'] !== '' && !preg_match('/^[0-9]{9,11}$/', $d['phone'])) { flash('error', 'Số điện thoại phải gồm 9-11 chữ số'); }
        elseif ($accountError !== '') { flash('error', $accountError); }
        else {
            try { $staffM->save($d, $eid ?: null); flash('success', $eid ? 'Đã cập nhật nhân viên' : 'Đã thêm nhân viên'); }
            catch (mysqli_sql_exception $e) { flash('error', mysql_error_text($e)); }
        }
    }
    redirect('he_thong/nhan_vien.php');
}
$pageTitle = 'Quản Lý Hệ Thống';
$pageSub = 'Nhân viên kỹ thuật thực hiện sửa chữa, bảo trì';
$active = 'he_thong';
require __DIR__ . '/../includes/header.php';
echo tabs(['pq' => ['Phân quyền truy cập', 'he_thong/phan_quyen.php'], 'cs' => ['Chỉnh sửa hệ thống', 'he_thong/chinh_sua.php'], 'nv' => ['Nhân viên kỹ thuật', 'he_thong/nhan_vien.php']], 'nv');
$list = Database::get()->query("SELECT s.*, u.username, u.status AS account_status, u.role AS account_role
    FROM staff s LEFT JOIN `user` u ON u.id_user = s.id_user ORDER BY s.staff_name");
$currentEditId = $edit ? (int) $edit['id_staff'] : 0;
$accounts = Database::get()->query("SELECT u.id_user, u.username, u.full_name, u.status
    FROM `user` u
    WHERE u.role = 'nhanvien' AND (u.status = 'Hoạt động' OR u.id_user = ?)
      AND NOT EXISTS (SELECT 1 FROM staff sx WHERE sx.id_user = u.id_user AND sx.id_staff <> ?)
    ORDER BY u.username", [$edit ? (int) ($edit['id_user'] ?? 0) : 0, $currentEditId]);
$e = $edit ?: ['staff_name' => '', 'specialty' => '', 'phone' => '', 'email' => '', 'status' => 'Đang làm', 'id_user' => ''];
?>
<form class="card" method="post" data-validate><div class="card-h"><?= $edit ? 'Sửa nhân viên' : 'Thêm nhân viên kỹ thuật' ?></div><div class="card-b"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="alert info">“Trạng thái nhân sự” (Đang làm/Nghỉ) mô tả tình trạng công việc. Tài khoản đăng nhập là thông tin riêng: nhân viên chưa được liên kết tài khoản sẽ không thể đăng nhập hệ thống.</div>
  <div class="form-row">
    <div class="field"><label>Họ tên *</label><input name="staff_name" value="<?= h($e['staff_name']) ?>" required data-label="Họ tên"></div>
    <div class="field"><label>Chuyên môn</label><input name="specialty" value="<?= h($e['specialty']) ?>" placeholder="Điện lạnh, điện nước..."></div>
    <div class="field"><label>Điện thoại</label><input name="phone" value="<?= h($e['phone']) ?>"></div>
    <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($e['email']) ?>"></div>
    <div class="field"><label>Tài khoản đăng nhập</label><select name="id_user"><option value="">Chưa có</option>
      <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id_user'] ?>" <?= (string) $e['id_user'] === (string) $a['id_user'] ? 'selected' : '' ?>><?= h($a['username']) ?> (<?= h($a['full_name']) ?>)</option><?php endforeach; ?></select>
      <small>Chỉ chọn tài khoản Nhân viên kỹ thuật đang Hoạt động và chưa liên kết với nhân viên khác.</small></div>
    <div class="field"><label>Trạng thái nhân sự</label><select name="status"><?php foreach (['Đang làm', 'Nghỉ'] as $s): ?><option <?= $e['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
  </div><button class="btn">Lưu</button> <?php if ($edit): ?><a class="btn gray" href="<?= h(url('he_thong/nhan_vien.php')) ?>">Hủy</a><?php endif; ?></div></form>
<div class="card"><div class="card-h">Danh sách nhân viên kỹ thuật</div><div class="tw"><table class="t"><thead><tr><th>Họ tên</th><th>Chuyên môn</th><th>Điện thoại</th><th>Email</th><th>Tài khoản</th><th>Trạng thái nhân sự</th><th>Quyền truy cập</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($list as $s):
  $accessLabel = empty($s['id_user']) ? 'Chưa cấp tài khoản'
      : (($s['account_role'] ?? '') !== 'nhanvien' ? 'Sai vai trò tài khoản'
      : (($s['account_status'] ?? '') !== 'Hoạt động' ? 'Tài khoản bị khóa' : 'Có thể đăng nhập'));
  $accessClass = $accessLabel === 'Có thể đăng nhập' ? 'green' : ($accessLabel === 'Chưa cấp tài khoản' ? 'amber' : 'red');
?>
  <tr><td><b><?= h($s['staff_name']) ?></b></td><td><?= h($s['specialty']) ?></td><td><?= h($s['phone']) ?></td><td><?= h($s['email']) ?></td><td><?= h($s['username'] ?: '—') ?></td><td><?= status_badge($s['status']) ?></td><td><?= badge($accessLabel, $accessClass) ?></td>
    <td><div class="actions"><a class="btn sm gray" href="<?= h(url('he_thong/nhan_vien.php?id=' . (int) $s['id_staff'])) ?>">Sửa</a>
      <form class="inline" method="post" data-confirm="Xóa nhân viên này?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id_staff'] ?>"><button class="btn sm red">Xóa</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
