<?php
// thiet_bi/tiep_nhan.php - Tab "Tiếp nhận yêu cầu": nhân viên kỹ thuật tiếp nhận và cập nhật kết quả sửa chữa
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nhanvien']);
$staffM = new Staff();
$myStaff = Auth::is('nhanvien') ? $staffM->findByUser(Auth::id()) : null;

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'accept') {
        $sid = $myStaff ? (int) $myStaff['id_staff'] : (int) post('id_staff');
        $r = $sid ? $staffM->receiveRequest($id, $sid) : 'Vui lòng chọn nhân viên kỹ thuật';
    } else {
        $r = $staffM->updateTaskStatus($id, post('result'), post('note'));
    }
    $r === true ? flash('success', 'Đã cập nhật yêu cầu') : flash('error', $r);
    redirect('thiet_bi/tiep_nhan.php');
}
$pageTitle = 'Quản Lý Thiết Bị';
$pageSub = 'Tiếp nhận yêu cầu sửa chữa';
$active = 'thiet_bi';
require __DIR__ . '/../includes/header.php';
echo tabs(['ds' => ['Danh sách thiết bị', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php'], 'tn' => ['Tiếp nhận yêu cầu', 'thiet_bi/tiep_nhan.php']], 'tn');
$filter = qs('s', 'open');
$reqs = (new RepairRequest())->search($filter);
$staffList = $staffM->working();
?>
<div class="card"><div class="card-h">Tiếp nhận yêu cầu sửa chữa
  <form class="filter" method="get" style="margin:0"><select name="s" style="padding:6px;border:1px solid #cbd5e1;border-radius:8px" onchange="this.form.submit()">
    <option value="open" <?= $filter === 'open' ? 'selected' : '' ?>>Đang xử lý</option><option value="" <?= $filter === '' ? 'selected' : '' ?>>Tất cả</option>
    <?php foreach (RepairRequest::STATUSES as $s): ?><option <?= $filter === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></form></div>
<div class="tw"><table class="t"><thead><tr><th>Mã YC</th><th>Người báo</th><th>Phòng</th><th>Thiết bị</th><th>Nội dung</th><th>Mức độ</th><th>Kỹ thuật viên</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($reqs as $q): ?>
  <tr><td>YC<?= str_pad((string) $q['id_request'], 3, '0', STR_PAD_LEFT) ?></td><td><?= h($q['reporter']) ?></td><td><?= h($q['room_number'] ?: 'Khu chung') ?></td><td><?= h($q['device_name']) ?></td>
    <td><?= h($q['description']) ?></td><td><?= status_badge($q['priority']) ?></td><td><?= h($q['staff_name'] ?: '—') ?></td><td><?= status_badge($q['status']) ?></td>
    <td><?php if ($q['status'] === 'Chờ tiếp nhận'): ?>
      <form class="inline" method="post" style="display:flex;gap:4px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int) $q['id_request'] ?>">
        <?php if (!$myStaff): ?><select name="id_staff" required style="padding:4px;border:1px solid #cbd5e1;border-radius:6px"><option value="">KTV...</option>
          <?php foreach ($staffList as $s): ?><option value="<?= (int) $s['id_staff'] ?>"><?= h($s['staff_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="btn sm">Tiếp nhận</button></form>
      <?php elseif ($q['status'] === 'Đang sửa'): ?>
      <form class="inline" method="post" style="display:flex;gap:4px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $q['id_request'] ?>">
        <input name="note" placeholder="Ghi chú xử lý" style="padding:4px;border:1px solid #cbd5e1;border-radius:6px;width:130px">
        <button class="btn sm green" name="result" value="Đã sửa">Đã sửa</button><button class="btn sm purple" style="background:var(--purple)" name="result" value="Cần thay mới">Cần thay mới</button></form>
      <?php else: ?><small class="t-muted"><?= h($q['result_note']) ?></small><?php endif; ?></td></tr>
<?php endforeach; if (!$reqs): ?><tr><td colspan="9" class="t-muted">Không có yêu cầu nào.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
