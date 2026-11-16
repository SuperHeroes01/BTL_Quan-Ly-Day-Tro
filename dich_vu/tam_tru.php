<?php
// dich_vu/tam_tru.php - Tab "Tạm trú": người thuê tải ảnh CCCD, quản lý duyệt trạng thái đăng ký
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nguoithue']);
$renterM = new Renter();

if (is_post()) {
    csrf_check();
    if (Auth::is('nguoithue') && post('action') === 'upload') {
        $me = $renterM->findByUser(Auth::id());
        $r = $me ? $renterM->uploadIdCard((int) $me['id_renter'], $_FILES['id_card'] ?? []) : 'Tài khoản chưa gắn với phòng';
        $r === true ? flash('success', 'Đã gửi ảnh CCCD, vui lòng chờ chủ trọ xác nhận') : flash('error', $r);
    } elseif (Auth::isManager() && post('action') === 'status') {
        $renterM->setResidenceStatus((int) post('id'), post('residence_status')) ? flash('success', 'Đã cập nhật trạng thái tạm trú') : flash('error', 'Trạng thái không hợp lệ');
    }
    redirect('dich_vu/tam_tru.php');
}
$pageTitle = 'Quản Lý Dịch Vụ';
$pageSub = 'Đăng ký tạm trú cho người thuê';
$active = 'dich_vu';
require __DIR__ . '/../includes/header.php';
if (Auth::isManager()) {
    echo tabs(['dv' => ['Quản lý dịch vụ', 'dich_vu/index.php'], 'hd' => ['Hóa đơn & Thanh toán', 'dich_vu/hoa_don.php'], 'tt' => ['Tạm trú', 'dich_vu/tam_tru.php']], 'tt');
    $rows = $renterM->listAll(qs('q'), true);
} else {
    echo tabs(['hd' => ['Hóa đơn & Thanh toán', 'dich_vu/hoa_don.php'], 'tt' => ['Tạm trú', 'dich_vu/tam_tru.php']], 'tt');
    $me = $renterM->findByUser(Auth::id());
    $rows = $me ? [array_merge($me, ['username' => ''])] : [];
}
?>
<div class="card"><div class="card-h">Danh sách tạm trú</div><div class="tw"><table class="t">
<thead><tr><th>Họ tên</th><th>Phòng</th><th>CCCD</th><th>Ngày đăng ký</th><th>Ảnh CCCD</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><b><?= h($r['full_name']) ?></b></td><td><?= h($r['room_number']) ?></td><td><?= h($r['identity_card']) ?></td><td><?= h(dmy($r['start_date'])) ?></td>
    <td><?= $r['id_card_image'] ? '<a href="' . h(url('dich_vu/tam_tru_anh.php?id=' . (int) $r['id_renter'])) . '" target="_blank">Xem ảnh</a>' : '<span class="t-muted">Chưa có</span>' ?></td>
    <td><?= status_badge($r['residence_status']) ?></td>
    <td><?php if (Auth::isManager()): ?>
      <form method="post" class="inline" style="display:flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $r['id_renter'] ?>">
        <select name="residence_status" style="padding:4px;border:1px solid #cbd5e1;border-radius:6px"><?php foreach (['Chưa đăng ký', 'Chờ xử lý', 'Đã đăng ký'] as $s): ?><option <?= $r['residence_status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
        <button class="btn sm">Lưu</button></form>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
        <input type="file" name="id_card" accept="image/jpeg,image/png" required><button class="btn sm">Gửi ảnh CCCD</button></form>
      <?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="t-muted">Chưa có dữ liệu.</td></tr><?php endif; ?>
</tbody></table></div></div>
<p class="t-muted">Người thuê tải ảnh căn cước công dân (JPG/PNG, tối đa 3MB). Ảnh được lưu riêng tư, chỉ chủ trọ/admin và chính người thuê xem được.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
