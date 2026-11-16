<?php
// phong/nguoi_thue.php - Tab "Người thuê": danh sách người thuê, tìm kiếm, trả phòng
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$renterM = new Renter();

if (is_post()) {
    csrf_check();
    if (post('action') === 'checkout') { $renterM->checkout((int) post('id')); flash('success', 'Đã làm thủ tục trả phòng'); }
    redirect('phong/nguoi_thue.php');
}
$pageTitle = 'Quản Lý Phòng Trọ';
$pageSub = 'Danh sách người thuê';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
echo tabs(['ds' => ['Danh sách phòng', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'], 'nt' => ['Người thuê', 'phong/nguoi_thue.php']], 'nt');
$rows = $renterM->listAll(qs('q'));
$cnt = [];
foreach ($rows as $r) { if (Renter::contractStatus($r) !== 'Đã kết thúc') { $cnt[$r['id_room']] = ($cnt[$r['id_room']] ?? 0) + 1; } }
?>
<div class="card"><div class="card-h">Danh sách người thuê <a class="btn" href="<?= h(url('phong/nguoi_thue_form.php')) ?>">+ Thêm người thuê</a></div><div class="card-b">
  <form class="filter" method="get"><div class="field"><label>Tìm kiếm</label><input name="q" value="<?= h(qs('q')) ?>" placeholder="Họ tên, phòng, SĐT, CCCD..."></div><button class="btn">Tìm</button></form>
  <div class="tw"><table class="t"><thead><tr><th>Họ tên</th><th>Phòng</th><th>Số người</th><th>Điện thoại</th><th>CCCD</th><th>Ngày vào</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $cs = Renter::contractStatus($r); ?>
    <tr><td><b><?= h($r['full_name']) ?></b><br><small class="t-muted"><?= h($r['username']) ?></small></td><td><?= h($r['room_number']) ?></td>
      <td><?= $cs === 'Đã kết thúc' ? '—' : (int) ($cnt[$r['id_room']] ?? 0) . ' người' ?></td><td><?= h($r['phone']) ?></td><td><?= h($r['identity_card']) ?></td>
      <td><?= h(dmy($r['start_date'])) ?></td><td><?= status_badge($cs === 'Đã kết thúc' ? 'Đã kết thúc' : 'Hiệu lực') ?></td>
      <td><div class="actions"><a class="btn sm gray" href="<?= h(url('phong/nguoi_thue_form.php?id=' . (int) $r['id_renter'])) ?>">Sửa</a>
        <?php if ($cs !== 'Đã kết thúc'): ?><form class="inline" method="post" data-confirm="Xác nhận trả phòng cho <?= h($r['full_name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="id" value="<?= (int) $r['id_renter'] ?>"><button class="btn sm amber">Trả phòng</button></form><?php endif; ?></div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="t-muted">Chưa có người thuê nào.</td></tr><?php endif; ?>
  </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
