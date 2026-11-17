<?php
// phong/nguoi_thue.php - Tab "Người thuê": danh sách người thuê, tìm kiếm, trả phòng
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$renterM = new Renter();

if (is_post()) {
    csrf_check();
        if (post('action') === 'checkout') { $renterM->checkout((int) post('id')); flash('success', 'Đã làm thủ tục trả phòng'); }
    elseif (post('action') === 'delete_account' && Auth::is('admin')) {
        $r = (new User())->deleteTenantAccount((int) post('id_user'));
        $r === true ? flash('success', 'Đã xóa tài khoản người thuê') : flash('error', $r);
    }
    redirect('phong/nguoi_thue.php');
}
$pageTitle = 'Quản Lý Phòng Trọ';
$pageSub = 'Danh sách người thuê';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
echo tabs(['ds' => ['Danh sách phòng', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'], 'nt' => ['Người thuê', 'phong/nguoi_thue.php']], 'nt');
$rows = $renterM->listAll(qs('q'));
$pending = Auth::is('admin') ? (new User())->pendingTenants() : [];
$cnt = [];
foreach ($rows as $r) { if (Renter::contractStatus($r) !== 'Đã kết thúc') { $cnt[$r['id_room']] = ($cnt[$r['id_room']] ?? 0) + 1; } }
?>
<?php if ($pending): ?>
<div class="card" style="margin-bottom:18px"><div class="card-h">Tài khoản mới đăng ký, chờ gắn phòng <?= badge(count($pending) . ' tài khoản', 'amber') ?></div><div class="card-b">
  <div class="tw"><table class="t"><thead><tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Điện thoại</th><th>CCCD</th><th>Đăng ký lúc</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($pending as $p): ?>
    <tr><td><b><?= h($p['full_name']) ?></b></td><td><?= h($p['username']) ?></td><td><?= h($p['recovery_phone']) ?></td><td><?= h($p['recovery_identity_card']) ?></td><td><?= h(time_ago($p['created_at'])) ?></td>
      <td><div class="actions"><a class="btn sm" href="<?= h(url('phong/nguoi_thue_form.php?user=' . (int) $p['id_user'])) ?>">Gắn phòng</a>
        <form class="inline" method="post" data-confirm="Xóa tài khoản <?= h($p['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_account"><input type="hidden" name="id_user" value="<?= (int) $p['id_user'] ?>"><button class="btn sm red">Xóa</button></form></div></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div></div>
<?php endif; ?>
<div class="card"><div class="card-h">Danh sách người thuê <a class="btn" href="<?= h(url('phong/nguoi_thue_form.php')) ?>">+ Thêm người thuê</a></div><div class="card-b">
  <form class="filter" method="get"><div class="field"><label>Tìm kiếm</label><input name="q" value="<?= h(qs('q')) ?>" placeholder="Họ tên, phòng, SĐT, CCCD..."></div><button class="btn">Tìm</button></form>
  <div class="tw"><table class="t"><thead><tr><th>Họ tên</th><th>Phòng</th><th>Số người</th><th>Điện thoại</th><th>CCCD</th><th>Ngày vào</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $cs = Renter::contractStatus($r); ?>
    <tr><td><b><?= h($r['full_name']) ?></b><br><small class="t-muted"><?= h($r['username']) ?></small></td><td><?= h($r['room_number']) ?></td>
      <td><?= $cs === 'Đã kết thúc' ? '—' : (int) ($cnt[$r['id_room']] ?? 0) . ' người' ?></td><td><?= h($r['phone']) ?></td><td><?= h($r['identity_card']) ?></td>
      <td><?= h(dmy($r['start_date'])) ?></td><td><?= status_badge($cs === 'Đã kết thúc' ? 'Đã kết thúc' : 'Hiệu lực') ?></td>
      <td><div class="actions"><a class="btn sm gray" href="<?= h(url('phong/nguoi_thue_form.php?id=' . (int) $r['id_renter'])) ?>">Sửa</a>
        <?php if ($cs !== 'Đã kết thúc'): ?><form class="inline" method="post" data-confirm="Xác nhận trả phòng cho <?= h($r['full_name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="id" value="<?= (int) $r['id_renter'] ?>"><button class="btn sm amber">Trả phòng</button></form><?php endif; ?>
        <?php if ($cs === 'Đã kết thúc' && Auth::is('admin')): ?><form class="inline" method="post" data-confirm="Xóa tài khoản <?= h($r['username']) ?>? Hồ sơ thuê của người này cũng bị xóa."><?= csrf_field() ?><input type="hidden" name="action" value="delete_account"><input type="hidden" name="id_user" value="<?= (int) $r['id_user'] ?>"><button class="btn sm red">Xóa tài khoản</button></form><?php endif; ?></div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="t-muted">Chưa có người thuê nào.</td></tr><?php endif; ?>
  </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
