<?php
// he_thong/phan_quyen.php - Tab "Phân quyền truy cập": quản lý tài khoản, vai trò, khóa/mở khóa, đặt lại mật khẩu (chỉ Admin)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);
$userM = new User();

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'save') {
        if ($id === Auth::id() && (post('role') !== 'admin' || post('status') !== 'Hoạt động')) { flash('error', 'Không thể tự hạ quyền hoặc khóa tài khoản của chính mình'); }
        else { $userM->setRoleStatus($id, post('role'), post('status')) ? flash('success', 'Đã cập nhật phân quyền') : flash('error', 'Dữ liệu không hợp lệ'); }
    } elseif (post('action') === 'reset') {
        $pw = $userM->resetPassword($id);
        flash('success', 'Mật khẩu mới của tài khoản: ' . $pw . ' (hãy chuyển cho người dùng và yêu cầu đổi lại)');
    }
    redirect('he_thong/phan_quyen.php');
}
$pageTitle = 'Quản Lý Hệ Thống';
$pageSub = 'Phân quyền truy cập và chỉnh sửa yêu cầu hệ thống';
$active = 'he_thong';
require __DIR__ . '/../includes/header.php';
echo tabs(['pq' => ['Phân quyền truy cập', 'he_thong/phan_quyen.php'], 'cs' => ['Chỉnh sửa hệ thống', 'he_thong/chinh_sua.php'], 'nv' => ['Nhân viên kỹ thuật', 'he_thong/nhan_vien.php']], 'pq');
$users = $userM->search(qs('q'), qs('role'));
?>
<div class="card"><div class="card-h">Phân quyền truy cập người dùng <a class="btn" href="<?= h(url('he_thong/user_form.php')) ?>">+ Thêm người dùng</a></div><div class="card-b">
  <form class="filter" method="get"><div class="field"><label>Tìm kiếm</label><input name="q" value="<?= h(qs('q')) ?>" placeholder="Tên đăng nhập, họ tên"></div>
    <div class="field"><label>Vai trò</label><select name="role"><option value="">Tất cả</option><?php foreach (Auth::ROLES as $k => $v): ?><option value="<?= h($k) ?>" <?= qs('role') === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></div><button class="btn">Lọc</button></form>
  <div class="tw"><table class="t"><thead><tr><th>Tên đăng nhập</th><th>Họ tên</th><th>Vai trò</th><th>Trạng thái</th><th>Quyền hạn</th><th>Thao tác</th></tr></thead><tbody>
  <?php $perm = ['admin' => 'Toàn quyền hệ thống (vai trò cố định trong mã nguồn)', 'chutro' => 'Quản lý phòng, thu phí, thiết bị (vai trò cố định trong mã nguồn)', 'nguoithue' => 'Xem hóa đơn, báo lỗi, tạm trú', 'nhanvien' => 'Tiếp nhận và xử lý sửa chữa'];
  foreach ($users as $u): $fid = 'f' . (int) $u['id_user']; ?>
    <tr><td><b><?= h($u['username']) ?></b></td><td><?= h($u['full_name']) ?></td>
      <td><?php if (in_array($u['role'], ['admin', 'chutro'], true)): ?>
        <span class="badge purple"><?= h(Auth::ROLES[$u['role']]) ?> - cố định</span><input type="hidden" form="<?= $fid ?>" name="role" value="<?= h($u['role']) ?>">
      <?php else: ?>
        <select form="<?= $fid ?>" name="role" style="padding:4px;border:1px solid #cbd5e1;border-radius:6px"><?php foreach (Auth::ROLES as $k => $v): if (in_array($k, ['admin', 'chutro'], true)) continue; ?><option value="<?= h($k) ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
      <?php endif; ?></td>
      <td><select form="<?= $fid ?>" name="status" style="padding:4px;border:1px solid #cbd5e1;border-radius:6px"><?php foreach (['Hoạt động', 'Khóa'] as $s): ?><option <?= $u['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></td>
      <td class="t-muted"><?= h($perm[$u['role']]) ?></td>
      <td><div class="actions">
        <form id="<?= $fid ?>" class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $u['id_user'] ?>"><button class="btn sm">Lưu</button></form>
        <form class="inline" method="post" data-confirm="Đặt lại mật khẩu cho <?= h($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $u['id_user'] ?>"><button class="btn sm amber">Đặt lại MK</button></form>
      </div></td></tr>
  <?php endforeach; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
