<?php
// thiet_bi/index.php - Tab "Danh sách thiết bị": quản lý thiết bị (người thuê chỉ xem thiết bị phòng mình)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nhanvien', 'nguoithue']);
$devM = new Device();

if (is_post() && (Auth::isManager() || Auth::is('nhanvien'))) {
    csrf_check();
    $id = (int) post('id');
    $act = post('action');
    if ($act === 'maintain')  { $devM->maintainDevice($id); flash('success', 'Đã ghi nhận bảo trì thiết bị'); }
    elseif ($act === 'status') { $devM->updateDeviceStatus($id, post('status')) ? flash('success', 'Đã cập nhật trạng thái') : flash('error', 'Trạng thái không hợp lệ'); }
    elseif ($act === 'delete' && Auth::isManager()) {
        try { Database::get()->exec("DELETE FROM repair_request WHERE id_device = ?", [$id]); $devM->delete($id); flash('success', 'Đã xóa thiết bị'); }
        catch (mysqli_sql_exception $e) { flash('error', mysql_error_text($e)); }
    }
    redirect('thiet_bi/index.php');
}

$pageTitle = 'Quản Lý Thiết Bị';
$pageSub = 'Báo lỗi thiết bị và tiếp nhận yêu cầu sửa chữa';
$active = 'thiet_bi';
require __DIR__ . '/../includes/header.php';
if (Auth::is('nguoithue')) {
    echo tabs(['ds' => ['Thiết bị phòng tôi', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php']], 'ds');
    $me = (new Renter())->findByUser(Auth::id());
    $devices = $me ? $devM->forRoom((int) $me['id_room']) : [];
} else {
    echo tabs(['ds' => ['Danh sách thiết bị', 'thiet_bi/index.php'], 'bl' => ['Báo lỗi thiết bị', 'thiet_bi/bao_loi.php'], 'tn' => ['Tiếp nhận yêu cầu', 'thiet_bi/tiep_nhan.php']], 'ds');
    $devices = $devM->search(qs('q'), qs('status'));
}
$canEdit = Auth::isManager();
?>
<div class="card"><div class="card-h">Danh sách thiết bị <?php if ($canEdit): ?><a class="btn" href="<?= h(url('thiet_bi/form.php')) ?>">+ Thêm thiết bị</a><?php endif; ?></div><div class="card-b">
  <?php if (!Auth::is('nguoithue')): ?>
  <form class="filter" method="get"><div class="field"><label>Tên thiết bị</label><input name="q" value="<?= h(qs('q')) ?>"></div>
    <div class="field"><label>Trạng thái</label><select name="status"><option value="">Tất cả</option><?php foreach (Device::STATUSES as $s): ?><option <?= qs('status') === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div><button class="btn">Lọc</button></form>
  <?php endif; ?>
  <div class="tw"><table class="t"><thead><tr><th>Thiết bị</th><th>Phòng</th><th>SL</th><th>Bảo dưỡng gần nhất</th><th>Trạng thái</th><?php if (!Auth::is('nguoithue')): ?><th>Thao tác</th><?php endif; ?></tr></thead><tbody>
  <?php foreach ($devices as $d): ?>
    <tr><td><b><?= h($d['device_name']) ?></b><?= $d['note'] ? '<br><small class="t-muted">' . h($d['note']) . '</small>' : '' ?></td><td><?= h($d['room_number'] ?: 'Khu chung / kho') ?></td><td><?= (int) $d['quantity'] ?></td>
      <td><?= h(dmy($d['last_maintenance'])) ?></td><td><?= status_badge($d['status']) ?></td>
      <?php if (!Auth::is('nguoithue')): ?><td><div class="actions">
        <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id_device'] ?>"><input type="hidden" name="action" value="maintain"><button class="btn sm green">Đã bảo trì</button></form>
        <?php if ($canEdit): ?><a class="btn sm gray" href="<?= h(url('thiet_bi/form.php?id=' . (int) $d['id_device'])) ?>">Sửa</a>
        <form class="inline" method="post" data-confirm="Xóa thiết bị này cùng các yêu cầu sửa chữa liên quan?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id_device'] ?>"><input type="hidden" name="action" value="delete"><button class="btn sm red">Xóa</button></form><?php endif; ?>
      </div></td><?php endif; ?></tr>
  <?php endforeach; if (!$devices): ?><tr><td colspan="6" class="t-muted">Không có thiết bị nào.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
