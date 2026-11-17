<?php
// thiet_bi/bao_loi.php - Tab "Báo lỗi thiết bị": người thuê (hoặc quản lý) gửi yêu cầu sửa chữa và theo dõi tiến độ
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nhanvien', 'nguoithue']);
$renterM = new Renter(); $devM = new Device();

if (is_post()) {
    csrf_check();
    if (Auth::is('nhanvien')) {
        http_response_code(403);
        die('Nhân viên kỹ thuật không có quyền tạo yêu cầu sửa chữa tại chức năng này.');
    }

    $idDev = (int) post('id_device');
    if (Auth::is('nguoithue')) {
        $r = $renterM->reportIssue(Auth::id(), $idDev, post('description'), post('priority', 'Trung bình'));
    } elseif (!Auth::isManager()) {
        $r = 'Bạn không có quyền gửi yêu cầu sửa chữa';
    } elseif (!$devM->find($idDev)) {
        $r = 'Vui lòng chọn thiết bị';
    } elseif (post('description') === '') {
        $r = 'Vui lòng mô tả lỗi';
    } else {
        (new RepairRequest())->create($idDev, Auth::id(), post('description'), post('priority', 'Trung bình'));
        $r = true;
    }
    $r === true ? flash('success', 'Đã gửi yêu cầu sửa chữa. Nhân viên kỹ thuật sẽ tiếp nhận sớm nhất') : flash('error', $r);
    redirect('thiet_bi/bao_loi.php');
}
$pageTitle = 'Quản Lý Thiết Bị';
$pageSub = 'Báo lỗi thiết bị và theo dõi yêu cầu sửa chữa';
$active = 'thiet_bi';
require __DIR__ . '/../includes/header.php';
if (Auth::is('nguoithue')) {
    echo tabs(['ds' => ['Thiết bị phòng tôi', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php']], 'bl');
    $me = $renterM->findByUser(Auth::id());
    $choices = $me ? $devM->forRoom((int) $me['id_room']) : [];
    $reqs = (new RepairRequest())->search('', Auth::id());
} elseif (Auth::is('nhanvien')) {
    echo tabs(['ds' => ['Danh sách thiết bị', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php'], 'tn' => ['Tiếp nhận yêu cầu', 'thiet_bi/tiep_nhan.php']], 'bl');
    $staff = (new Staff())->findByUser(Auth::id());
    $choices = [];
    $reqs = $staff ? (new RepairRequest())->search('', 0, (int) $staff['id_staff']) : [];
} else {
    echo tabs(['ds' => ['Danh sách thiết bị', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php'], 'tn' => ['Tiếp nhận yêu cầu', 'thiet_bi/tiep_nhan.php']], 'bl');
    $choices = $devM->search();
    $reqs = (new RepairRequest())->search();
}
?>
<?php if (Auth::is('nguoithue') || Auth::isManager()): ?>
<form class="card" method="post" data-validate><div class="card-h">Gửi báo lỗi thiết bị mới</div><div class="card-b"><?= csrf_field() ?>
  <div class="form-row">
    <div class="field"><label>Thiết bị *</label><select name="id_device" required data-label="Thiết bị"><option value="">-- Chọn thiết bị --</option>
      <?php foreach ($choices as $c): ?><option value="<?= (int) $c['id_device'] ?>"><?= h($c['device_name']) ?> (<?= h($c['room_number'] ?: 'Khu chung') ?>)</option><?php endforeach; ?></select></div>
    <div class="field"><label>Mức độ</label><select name="priority"><?php foreach (['Thấp', 'Trung bình', 'Cao'] as $p): ?><option <?= $p === 'Trung bình' ? 'selected' : '' ?>><?= h($p) ?></option><?php endforeach; ?></select></div>
    <div class="field full"><label>Mô tả lỗi *</label><textarea name="description" rows="2" required data-label="Mô tả lỗi" placeholder="Ví dụ: Điều hòa không lạnh, chảy nước..."></textarea></div>
  </div>
  <button class="btn red">Gửi báo lỗi</button>
</div></form>
<?php endif; ?>
<div class="card"><div class="card-h"><?= Auth::is('nguoithue') ? 'Yêu cầu của tôi' : 'Danh sách báo lỗi' ?></div><div class="tw"><table class="t">
<thead><tr><th>Mã YC</th><th>Thiết bị</th><th>Phòng</th><?php if (!Auth::is('nhanvien')): ?><th>Người báo</th><?php endif; ?><th>Mô tả</th><th>Ngày báo</th><th>Mức độ</th><th>Trạng thái</th><th>Kết quả</th></tr></thead><tbody>
<?php foreach ($reqs as $q): ?>
  <tr><td>YC<?= str_pad((string) $q['id_request'], 3, '0', STR_PAD_LEFT) ?></td><td><?= h($q['device_name']) ?></td><td><?= h($q['room_number'] ?: 'Khu chung') ?></td><?php if (!Auth::is('nhanvien')): ?><td><?= h($q['reporter']) ?></td><?php endif; ?>
    <td><?= h($q['description']) ?></td><td><?= h(dmy($q['created_at'])) ?></td><td><?= status_badge($q['priority']) ?></td><td><?= status_badge($q['status']) ?></td>
    <td><?= h($q['staff_name'] ? 'KTV: ' . $q['staff_name'] : '') ?><?= $q['result_note'] ? '<br><small class="t-muted">' . h($q['result_note']) . '</small>' : '' ?></td></tr>
<?php endforeach; if (!$reqs): ?><tr><td colspan="<?= Auth::is('nhanvien') ? 8 : 9 ?>" class="t-muted">Chưa có yêu cầu nào.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
