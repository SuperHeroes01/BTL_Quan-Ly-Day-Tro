<?php
// phong/hop_dong.php - Danh sách, gia hạn/kết thúc và xem chi tiết hợp đồng.
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nguoithue']);
$renterM = new Renter();

if (is_post() && Auth::isManager()) {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'checkout') { $renterM->checkout($id); flash('success', 'Đã làm thủ tục trả phòng'); }
    elseif (post('action') === 'renew') {
        $r = $renterM->renew($id, post('end_date'));
        $r === true ? flash('success', 'Đã gia hạn hợp đồng') : flash('error', $r);
    }
    redirect('phong/hop_dong.php');
}

$pageTitle = 'Quản Lý Phòng Trọ';
$pageSub = 'Hợp đồng thuê phòng';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
if (Auth::isManager()) {
    echo tabs(['ds' => ['Danh sách phòng', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'], 'nt' => ['Người thuê', 'phong/nguoi_thue.php']], 'hd');
    $rows = $renterM->listAll(qs('q'));
} else {
    echo tabs(['ds' => ['Phòng của tôi', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php']], 'hd');
    $me = $renterM->findByUser(Auth::id());
    $rows = $me ? [$renterM->findFull((int) $me['id_renter'])] : [];
}
?>
<div class="card"><div class="card-h">Hợp đồng thuê phòng</div><div class="card-b">
  <?php if (Auth::isManager()): ?>
  <form class="filter" method="get"><div class="field"><label>Tìm kiếm</label><input name="q" value="<?= h(qs('q')) ?>" placeholder="Tên người thuê, phòng, SĐT..."></div><button class="btn">Tìm</button></form>
  <?php endif; ?>
  <div class="tw"><table class="t"><thead><tr><th>Mã HĐ</th><th>Phòng</th><th>Người thuê</th><th>Ngày bắt đầu</th><th>Ngày trả phòng</th><th>Giá thuê</th><th>Tiền cọc</th><th>Trạng thái</th><th>Chi tiết</th><?php if (Auth::isManager()): ?><th>Thao tác</th><?php endif; ?></tr></thead><tbody>
  <?php foreach ($rows as $r): if (!$r) continue; $cs = Renter::contractStatus($r); $rentPrice = (float) ($r['rent_price'] ?? 0); if ($rentPrice <= 0) { $rentPrice = (float) $r['price']; } ?>
    <tr><td><b>HĐ<?= str_pad((string) $r['id_renter'], 4, '0', STR_PAD_LEFT) ?></b></td><td><?= h($r['room_number']) ?></td><td><?= h($r['full_name']) ?></td>
      <td><?= h(dmy($r['start_date'])) ?></td><td><?= h(dmy($r['end_date'])) ?></td><td><?= h(money($rentPrice)) ?>/tháng</td><td><?= h(money($r['deposit'])) ?></td><td><?= status_badge($cs) ?></td>
      <td><a class="btn sm gray" href="<?= h(url('phong/hop_dong_xem.php?id=' . (int) $r['id_renter'])) ?>">Xem chi tiết</a></td>
      <?php if (Auth::isManager()): ?><td>
        <?php if ($cs !== 'Đã kết thúc'): ?>
        <div class="actions">
          <form class="inline" method="post" style="display:flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="renew"><input type="hidden" name="id" value="<?= (int) $r['id_renter'] ?>">
            <input type="date" name="end_date" required style="padding:4px;border:1px solid #cbd5e1;border-radius:6px"><button class="btn sm green">Gia hạn</button></form>
          <form class="inline" method="post" data-confirm="Xác nhận trả phòng cho <?= h($r['full_name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="id" value="<?= (int) $r['id_renter'] ?>"><button class="btn sm amber">Kết thúc</button></form>
        </div><?php endif; ?></td><?php endif; ?></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="10" class="t-muted">Chưa có hợp đồng nào hoặc tài khoản của bạn chưa được gán phòng.</td></tr><?php endif; ?>
  </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
