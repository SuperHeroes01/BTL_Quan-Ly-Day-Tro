<?php
// he_thong/nhan_vien.php - Quản lý hồ sơ nhân viên kỹ thuật và tài khoản đăng nhập.
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);

$staffM = new Staff();
$userM = new User();
$db = Database::get();
$id = (int) qs('id', '0');
$edit = $id > 0 ? $staffM->find($id) : null;

if ($id > 0 && !$edit) {
    flash('error', 'Không tìm thấy hồ sơ nhân viên cần chỉnh sửa.');
    redirect('he_thong/nhan_vien.php');
}

if (is_post()) {
    csrf_check();
    $eid = (int) post('id');
    $action = (string) post('action', 'save');

    if ($action === 'delete') {
        $staff = $staffM->find($eid);
        if (!$staff) {
            flash('error', 'Không tìm thấy hồ sơ nhân viên.');
        } else {
            $db->begin();
            try {
                // Không để tài khoản kỹ thuật tiếp tục đăng nhập sau khi xóa hồ sơ nhân sự.
                if (!empty($staff['id_user'])) {
                    $db->exec(
                        "UPDATE `user` SET status = 'Khóa' WHERE id_user = ? AND role = 'nhanvien'",
                        [(int) $staff['id_user']]
                    );
                }
                $staffM->delete($eid);
                $db->commit();
                write_log('Xóa hồ sơ nhân viên kỹ thuật #' . $eid . ' (' . $staff['staff_name'] . ')', 'STAFF');
                flash('success', 'Đã xóa hồ sơ nhân viên. Tài khoản liên kết (nếu có) đã được khóa để tránh đăng nhập không còn hồ sơ.');
            } catch (Throwable $e) {
                $db->rollback();
                error_log('[NhanVien] Không thể xóa staff #' . $eid . ': ' . $e->getMessage());
                flash('error', 'Không thể xóa nhân viên vì hồ sơ đang được yêu cầu sửa chữa tham chiếu. Hãy chuyển nhân viên sang trạng thái Nghỉ.');
            }
        }
        redirect('he_thong/nhan_vien.php');
    }

    $staffName = trim((string) post('staff_name'));
    $status = post('status') === 'Nghỉ' ? 'Nghỉ' : 'Đang làm';
    $accountChoice = (string) post('id_user');
    $createAccount = ($accountChoice === '__create__');
    $oldStaff = $eid > 0 ? $staffM->find($eid) : null;
    $oldUserId = $oldStaff && !empty($oldStaff['id_user']) ? (int) $oldStaff['id_user'] : 0;

    $data = [
        'staff_name' => $staffName,
        'specialty' => trim((string) post('specialty')),
        'phone' => trim((string) post('phone')),
        'email' => trim((string) post('email')) === '' ? null : trim((string) post('email')),
        'status' => $status,
        'id_user' => null,
    ];

    $error = '';
    $newUsername = trim((string) post('new_username'));
    $newPassword = (string) post('new_password');

    if ($staffName === '') {
        $error = 'Vui lòng nhập họ tên nhân viên.';
    } elseif ($data['phone'] !== '' && !preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
        $error = 'Số điện thoại phải gồm 9–11 chữ số.';
    } elseif ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    }

    if ($error === '' && $createAccount) {
        if ($status !== 'Đang làm') {
            $error = 'Chỉ có thể tạo tài khoản đăng nhập cho nhân viên đang làm việc.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.]{4,30}$/', $newUsername)) {
            $error = 'Tên đăng nhập gồm 4–30 ký tự chữ, số, dấu chấm hoặc gạch dưới.';
        } elseif ($userM->usernameExists($newUsername)) {
            $error = 'Tên đăng nhập đã tồn tại hoặc đã bị thu hồi. Vui lòng chọn tên khác.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Mật khẩu ban đầu phải có ít nhất 8 ký tự.';
        }
    } elseif ($error === '' && $accountChoice !== '') {
        if (!ctype_digit($accountChoice) || (int) $accountChoice <= 0) {
            $error = 'Lựa chọn tài khoản không hợp lệ.';
        } else {
            $selectedUserId = (int) $accountChoice;
            $account = $db->one(
                "SELECT id_user, role, status FROM `user` WHERE id_user = ? LIMIT 1",
                [$selectedUserId]
            );
            $isCurrentLink = ($selectedUserId === $oldUserId);

            if (!$account || $account['role'] !== 'nhanvien') {
                $error = 'Chỉ được liên kết tài khoản có vai trò Nhân viên kỹ thuật.';
            } elseif ($account['status'] !== 'Hoạt động' && !$isCurrentLink) {
                $error = 'Tài khoản đang bị khóa, không thể liên kết mới. Hãy mở khóa tài khoản trước.';
            } else {
                $used = (int) $db->value(
                    "SELECT COUNT(*) FROM staff WHERE id_user = ? AND id_staff <> ?",
                    [$selectedUserId, $eid]
                );
                if ($used > 0) {
                    $error = 'Tài khoản này đã được liên kết với một nhân viên kỹ thuật khác.';
                } else {
                    $data['id_user'] = $selectedUserId;
                }
            }
        }
    }

    if ($error !== '') {
        flash('error', $error);
        redirect($eid > 0 ? 'he_thong/nhan_vien.php?id=' . $eid : 'he_thong/nhan_vien.php');
    }

    $createdAccountId = 0;
    $savedStaffId = 0;
    $db->begin();
    try {
        if ($createAccount) {
            // User::insert() kiểm tra username chưa tồn tại/chưa bị thu hồi và tự băm mật khẩu.
            $createdAccountId = (new User())->insert([
                'username' => $newUsername,
                'password' => $newPassword,
                'role' => 'nhanvien',
                'full_name' => $staffName,
                'status' => 'Hoạt động',
            ]);
            $data['id_user'] = (int) $createdAccountId;
        }

        $savedStaffId = (int) $staffM->save($data, $eid > 0 ? $eid : null);

        // Nếu gỡ/đổi tài khoản đã liên kết thì khóa tài khoản cũ, tránh tài khoản mồ côi vẫn đăng nhập.
        if ($oldUserId > 0 && $oldUserId !== (int) ($data['id_user'] ?? 0)) {
            $db->exec(
                "UPDATE `user` SET status = 'Khóa' WHERE id_user = ? AND role = 'nhanvien'",
                [$oldUserId]
            );
        }

        // Đồng bộ tên hiển thị tài khoản theo hồ sơ nhân viên.
        if (!empty($data['id_user'])) {
            $db->exec(
                "UPDATE `user` SET full_name = ? WHERE id_user = ? AND role = 'nhanvien'",
                [$staffName, (int) $data['id_user']]
            );
        }

        // Nhân viên nghỉ việc không được tiếp tục dùng tài khoản kỹ thuật.
        if ($status === 'Nghỉ' && !empty($data['id_user'])) {
            $db->exec(
                "UPDATE `user` SET status = 'Khóa' WHERE id_user = ? AND role = 'nhanvien'",
                [(int) $data['id_user']]
            );
        }

        $db->commit();
        write_log(
            ($eid > 0 ? 'Cập nhật' : 'Thêm') . ' hồ sơ nhân viên kỹ thuật #' . $savedStaffId
            . ' (' . $staffName . '); trạng thái: ' . $status
            . (!empty($data['id_user']) ? '; tài khoản ID: ' . (int) $data['id_user'] : '; chưa liên kết tài khoản'),
            'STAFF'
        );

        if ($createdAccountId > 0) {
            // Chỉ hiển thị mật khẩu ban đầu một lần cho Admin; không ghi mật khẩu vào nhật ký.
            flash('success', 'Đã lưu hồ sơ và tạo tài khoản kỹ thuật. Tên đăng nhập: ' . $newUsername . ' | Mật khẩu ban đầu: ' . $newPassword . ' | Hãy chuyển riêng cho nhân viên và yêu cầu đổi mật khẩu sau khi đăng nhập.');
        } else {
            flash('success', $eid > 0 ? 'Đã cập nhật hồ sơ nhân viên.' : 'Đã thêm nhân viên kỹ thuật.');
        }
    } catch (Throwable $e) {
        $db->rollback();
        error_log('[NhanVien] Lưu hồ sơ thất bại: ' . $e->getMessage());
        $friendly = $e instanceof mysqli_sql_exception ? mysql_error_text($e) : $e->getMessage();
        flash('error', 'Không thể lưu nhân viên: ' . $friendly);
    }

    redirect('he_thong/nhan_vien.php');
}

$pageTitle = 'Quản Lý Hệ Thống';
$pageSub = 'Nhân viên kỹ thuật thực hiện sửa chữa, bảo trì';
$active = 'he_thong';
require __DIR__ . '/../includes/header.php';
echo tabs(
    ['pq' => ['Phân quyền truy cập', 'he_thong/phan_quyen.php'], 'cs' => ['Chỉnh sửa hệ thống', 'he_thong/chinh_sua.php'], 'nv' => ['Nhân viên kỹ thuật', 'he_thong/nhan_vien.php']],
    'nv'
);

$list = $db->query(
    "SELECT s.*, u.username, u.status AS account_status, u.role AS account_role
     FROM staff s LEFT JOIN `user` u ON u.id_user = s.id_user
     ORDER BY s.staff_name"
);
$currentEditId = $edit ? (int) $edit['id_staff'] : 0;
$currentUserId = $edit && !empty($edit['id_user']) ? (int) $edit['id_user'] : 0;
$accounts = $db->query(
    "SELECT u.id_user, u.username, u.full_name, u.status
     FROM `user` u
     WHERE u.role = 'nhanvien'
       AND (u.status = 'Hoạt động' OR u.id_user = ?)
       AND NOT EXISTS (
           SELECT 1 FROM staff sx
           WHERE sx.id_user = u.id_user AND sx.id_staff <> ?
       )
     ORDER BY u.username",
    [$currentUserId, $currentEditId]
);
$e = $edit ?: [
    'staff_name' => '', 'specialty' => '', 'phone' => '', 'email' => '',
    'status' => 'Đang làm', 'id_user' => '',
];
?>
<form class="card" method="post" data-validate>
  <div class="card-h"><?= $edit ? 'Sửa nhân viên' : 'Thêm nhân viên kỹ thuật' ?></div>
  <div class="card-b">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $currentEditId ?>">
    <div class="alert info">Trạng thái nhân sự mô tả tình trạng làm việc. Tài khoản đăng nhập được quản lý riêng. Nếu chưa có tài khoản kỹ thuật phù hợp, bạn có thể tạo ngay tại biểu mẫu này.</div>
    <div class="form-row">
      <div class="field"><label>Họ tên *</label><input name="staff_name" value="<?= h($e['staff_name']) ?>" required data-label="Họ tên"></div>
      <div class="field"><label>Chuyên môn</label><input name="specialty" value="<?= h($e['specialty']) ?>" placeholder="Điện lạnh, điện nước..."></div>
      <div class="field"><label>Điện thoại</label><input name="phone" value="<?= h($e['phone']) ?>" inputmode="numeric"></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($e['email']) ?>"></div>
      <div class="field">
        <label>Tài khoản đăng nhập</label>
        <select name="id_user" id="staffUserSelect">
          <option value="" <?= empty($e['id_user']) ? 'selected' : '' ?>>Chưa liên kết tài khoản</option>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= (int) $a['id_user'] ?>" <?= (string) ($e['id_user'] ?? '') === (string) $a['id_user'] ? 'selected' : '' ?>>
              <?= h($a['username']) ?> (<?= h($a['full_name']) ?><?= $a['status'] === 'Khóa' ? ' - Đang khóa' : '' ?>)
            </option>
          <?php endforeach; ?>
          <option value="__create__">＋ Tạo tài khoản mới cho nhân viên này</option>
        </select>
        <small>Nếu danh sách không có tài khoản phù hợp, chọn “Tạo tài khoản mới cho nhân viên này”.</small>
      </div>
      <div class="field"><label>Trạng thái nhân sự</label><select name="status"><?php foreach (['Đang làm', 'Nghỉ'] as $s): ?><option value="<?= h($s) ?>" <?= $e['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
    </div>

    <div id="newStaffAccountFields" class="card" style="margin-top:14px; display:none; border:1px dashed #60a5fa;">
      <div class="card-h">Tạo tài khoản đăng nhập mới</div>
      <div class="card-b">
        <div class="alert info">Tài khoản sẽ tự động được gán vai trò Nhân viên kỹ thuật và liên kết với hồ sơ này. Mật khẩu được băm trước khi lưu.</div>
        <div class="form-row">
          <div class="field"><label>Tên đăng nhập *</label><input type="text" name="new_username" id="newStaffUsername" minlength="4" maxlength="30" pattern="[A-Za-z0-9_.]{4,30}" autocomplete="off" placeholder="Ví dụ: phamminhtuan"></div>
          <div class="field"><label>Mật khẩu ban đầu *</label><input type="password" name="new_password" id="newStaffPassword" minlength="8" autocomplete="new-password" placeholder="Ít nhất 8 ký tự"></div>
        </div>
        <small>Không sử dụng tên đăng nhập đã tồn tại hoặc đã bị thu hồi. Sau khi tạo, hãy chuyển thông tin đăng nhập riêng cho nhân viên.</small>
      </div>
    </div>

    <button class="btn">Lưu nhân viên</button>
    <?php if ($edit): ?><a class="btn gray" href="<?= h(url('he_thong/nhan_vien.php')) ?>">Hủy</a><?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="card-h">Danh sách nhân viên kỹ thuật</div>
  <div class="tw"><table class="t">
    <thead><tr><th>Họ tên</th><th>Chuyên môn</th><th>Điện thoại</th><th>Email</th><th>Tài khoản</th><th>Trạng thái nhân sự</th><th>Quyền truy cập</th><th>Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($list as $s):
      if ($s['status'] === 'Nghỉ') {
          $accessLabel = 'Nhân viên đã nghỉ';
          $accessClass = 'red';
      } elseif (empty($s['id_user'])) {
          $accessLabel = 'Chưa cấp tài khoản';
          $accessClass = 'amber';
      } elseif (($s['account_role'] ?? '') !== 'nhanvien') {
          $accessLabel = 'Sai vai trò tài khoản';
          $accessClass = 'red';
      } elseif (($s['account_status'] ?? '') !== 'Hoạt động') {
          $accessLabel = 'Tài khoản bị khóa';
          $accessClass = 'red';
      } else {
          $accessLabel = 'Có thể đăng nhập';
          $accessClass = 'green';
      }
    ?>
      <tr>
        <td><b><?= h($s['staff_name']) ?></b></td>
        <td><?= h($s['specialty']) ?></td>
        <td><?= h($s['phone']) ?></td>
        <td><?= h($s['email']) ?></td>
        <td><?= h($s['username'] ?: '—') ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td><?= badge($accessLabel, $accessClass) ?></td>
        <td><div class="actions">
          <a class="btn sm gray" href="<?= h(url('he_thong/nhan_vien.php?id=' . (int) $s['id_staff'])) ?>">Sửa</a>
          <form class="inline" method="post" data-confirm="Xóa hồ sơ nhân viên này? Tài khoản đã liên kết sẽ bị khóa. Nếu nhân viên đã có lịch sử sửa chữa, hệ thống sẽ từ chối xóa và bạn nên chuyển sang trạng thái Nghỉ.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id_staff'] ?>">
            <button class="btn sm red">Xóa</button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="8" class="t-muted" style="text-align:center;padding:24px;">Chưa có nhân viên kỹ thuật.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<script>
(function () {
    const select = document.getElementById('staffUserSelect');
    const box = document.getElementById('newStaffAccountFields');
    const username = document.getElementById('newStaffUsername');
    const password = document.getElementById('newStaffPassword');
    if (!select || !box) return;
    function updateAccountFields() {
        const creating = select.value === '__create__';
        box.style.display = creating ? 'block' : 'none';
        username.required = creating;
        password.required = creating;
        if (!creating) {
            username.value = '';
            password.value = '';
        }
    }
    select.addEventListener('change', updateAccountFields);
    updateAccountFields();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
