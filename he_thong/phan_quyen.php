<?php
// he_thong/phan_quyen.php - Tab "Phân quyền truy cập": quản lý tài khoản, vai trò, khóa/mở khóa, đặt lại mật khẩu (chỉ Admin)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);
$userM = new User();

if (is_post()) {
    // Kiểm tra CSRF trước khi xử lý dữ liệu POST
    csrf_check();

    // Danh sách giá trị hợp lệ theo Auth::ROLES trong project
    $allowedRoles = ['admin', 'chutro', 'nguoithue', 'nhanvien'];
    $allowedStatuses = ['Hoạt động', 'Khóa'];
    $activeStatus = 'Hoạt động';
    $allowedActions = ['save', 'delete', 'reset'];

    $id = (int) post('id');
    $action = (string) post('action');
    $selfId = (int) Auth::id();

    try {
        // Kiểm tra ID tài khoản
        if ($id <= 0) {
            flash('error', 'ID tài khoản không hợp lệ.');

        } elseif (!in_array($action, $allowedActions, true)) {
            flash('error', 'Thao tác không hợp lệ.');

        } elseif ($id === $selfId && $action !== 'save') {
            // Không cho Admin tự xóa hoặc tự đặt lại mật khẩu
            flash('error', 'Không thể thực hiện thao tác này trên chính tài khoản của bạn.');

        } else {
            switch ($action) {

                // 1. Cập nhật vai trò và trạng thái
                case 'save':
                    $role = (string) post('role');
                    $status = (string) post('status');

                    // Kiểm tra dữ liệu đầu vào
                    if (
                        !in_array($role, $allowedRoles, true)
                        || !in_array($status, $allowedStatuses, true)
                    ) {
                        flash('error', 'Dữ liệu vai trò hoặc trạng thái không hợp lệ.');
                        break;
                    }

                    // Không cho Admin tự hạ quyền hoặc khóa chính mình
                    if (
                        $id === $selfId
                        && ($role !== 'admin' || $status !== $activeStatus)
                    ) {
                        flash(
                            'error',
                            'Không thể tự hạ quyền hoặc khóa tài khoản của chính mình.'
                        );
                        break;
                    }

                    if ($userM->setRoleStatus($id, $role, $status)) {
                        error_log(sprintf(
                            '[Audit][Phan quyen] admin=%d cap nhat user=%d role=%s status=%s',
                            $selfId,
                            $id,
                            $role,
                            $status
                        ));

                        flash('success', 'Đã cập nhật phân quyền thành công.');
                    } else {
                        flash(
                            'error',
                            'Không thể cập nhật tài khoản. Vui lòng kiểm tra tài khoản và dữ liệu.'
                        );
                    }
                    break;

                // 2. Xóa tài khoản người thuê
                case 'delete':
                    $result = $userM->deleteTenantAccount($id);

                    if ($result === true) {
                        error_log(sprintf(
                            '[Audit][Phan quyen] admin=%d xoa user=%d',
                            $selfId,
                            $id
                        ));

                        flash('success', 'Đã xóa tài khoản người thuê.');
                    } else {
                        flash(
                            'error',
                            is_string($result) ? $result : 'Không thể xóa tài khoản.'
                        );
                    }
                    break;

                // 3. Đặt lại mật khẩu
                case 'reset':
                    $password = $userM->resetPassword($id);

                    if ($password === false) {
                        flash(
                            'error',
                            'Không thể đặt lại mật khẩu tài khoản Admin hoặc Chủ trọ. '
                            . 'Các tài khoản này chỉ tự đổi mật khẩu tại mục Tài khoản của tôi.'
                        );
                    } else {
                        // Không ghi mật khẩu vào log
                        error_log(sprintf(
                            '[Audit][Phan quyen] admin=%d reset mat khau user=%d',
                            $selfId,
                            $id
                        ));

                        flash(
                            'success',
                            'Mật khẩu mới của tài khoản: ' . h($password)
                            . ' (hãy chuyển cho người dùng và yêu cầu đổi lại).'
                        );
                    }
                    break;
            }
        }

    } catch (Throwable $e) {
        // Ghi lỗi chi tiết vào log, không hiển thị lỗi hệ thống cho người dùng
        error_log(sprintf(
            '[Phan quyen] admin=%d target=%d action=%s: %s',
            $selfId,
            $id,
            $action,
            $e->getMessage()
        ));

        flash('error', 'Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại.');
    }

    // Post/Redirect/Get
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
                <?php if (in_array($u['role'], ['admin', 'chutro'], true)): ?>
          <span class="t-muted" title="Admin và Chủ trọ không thể đặt lại mật khẩu trực tiếp, chỉ tự đổi mật khẩu tại Tài khoản của tôi">Chỉ tự đổi MK</span>
        <?php else: ?>
        <form class="inline" method="post" data-confirm="Đặt lại mật khẩu cho <?= h($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $u['id_user'] ?>"><button class="btn sm amber">Đặt lại MK</button></form>
        <?php endif; ?>
        <?php if ($u['role'] === 'nguoithue'): ?>
        <form class="inline" method="post" data-confirm="Xóa vĩnh viễn tài khoản <?= h($u['username']) ?>? Chỉ xóa được khi người này không còn thuê phòng."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id_user'] ?>"><button class="btn sm red">Xóa</button></form>
        <?php endif; ?>
      </div></td></tr>
  <?php endforeach; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
