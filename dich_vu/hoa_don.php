<?php
// dich_vu/hoa_don.php - Tab "Hóa đơn & Thanh toán": lịch sử hóa đơn, thu tiền, nhắc nhở
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nguoithue']);
$billM = new Bill();

if (is_post() && Auth::isManager()) {
    csrf_check();
    $id = (int) post('id');
    switch (post('action')) {
        case 'pay':    $billM->updatePaymentStatus($id, 'Đã thu');  flash('success', 'Đã ghi nhận thu tiền'); break;
        case 'unpay':  $billM->updatePaymentStatus($id, 'Chưa thu'); flash('success', 'Đã chuyển hóa đơn về chưa thu'); break;
        case 'remind': $billM->sendNotification($id) ? flash('success', 'Đã gửi nhắc nhở thanh toán tới người thuê') : flash('error', 'Hóa đơn đã thu hoặc không tồn tại'); break;
        case 'delete': $r = $billM->deleteBill($id); $r === true ? flash('success', 'Đã xóa hóa đơn') : flash('error', $r); break;
    }
    redirect('dich_vu/hoa_don.php?' . http_build_query(['month' => post('month'), 'status' => post('status'), 'q' => post('q')]));
}

$pageTitle = 'Quản Lý Dịch Vụ';
$pageSub = 'Lịch sử thanh toán và hóa đơn';
$active = 'dich_vu';
require __DIR__ . '/../includes/header.php';
$month = qs('month'); $status = qs('status'); $kw = qs('q');
if ($month !== '' && !valid_month($month)) { $month = ''; }
if (Auth::isManager()) {
    echo tabs(['dv' => ['Quản lý dịch vụ', 'dich_vu/index.php'], 'hd' => ['Hóa đơn & Thanh toán', 'dich_vu/hoa_don.php'], 'tt' => ['Tạm trú', 'dich_vu/tam_tru.php']], 'hd');
    $bills = $billM->search($month, $status, $kw);
} else {
    echo tabs(['hd' => ['Hóa đơn & Thanh toán', 'dich_vu/hoa_don.php'], 'tt' => ['Tạm trú', 'dich_vu/tam_tru.php']], 'hd');
    $bills = (new Renter())->viewBill(Auth::id());
}
$sum = 0; foreach ($bills as $b) { $sum += (float) $b['total_amount']; }
?>
<div class="card"><div class="card-h">Lịch sử thanh toán <?php if (Auth::isManager()): ?><a class="btn" href="<?= h(url('dich_vu/hoa_don_tao.php')) ?>">+ Xuất hóa đơn</a><?php endif; ?></div>
 <div class="card-b">
  <?php if (Auth::isManager()): ?>
  <form class="filter" method="get">
    <div class="field"><label>Tháng</label><input type="month" name="month" value="<?= h($month) ?>"></div>
    <div class="field"><label>Trạng thái</label><select name="status"><option value="">Tất cả</option><?php foreach (['Chưa thu', 'Đã thu'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Phòng</label><input name="q" value="<?= h($kw) ?>" placeholder="P101"></div>
    <button class="btn">Lọc</button> <a class="btn gray" href="<?= h(url('dich_vu/hoa_don.php')) ?>">Xóa lọc</a>
  </form><?php endif; ?>
  <div class="tw"><table class="t"><thead><tr><th>Mã HĐ</th><th>Phòng</th><th>Người thuê</th><th>Nội dung</th><th>Số tiền</th><th>Hạn thanh toán</th><th>Ngày thu</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($bills as $b): $late = $b['payment_status'] === 'Chưa thu' && $b['due_date'] < date('Y-m-d'); ?>
    <tr><td>#HD<?= str_pad((string) $b['id_bill'], 4, '0', STR_PAD_LEFT) ?></td><td><b><?= h($b['room_number']) ?></b></td><td><?= h($b['tenants']) ?></td>
      <td>Tiền phòng + dịch vụ T<?= h(month_label($b['month'])) ?></td><td><b><?= h(money($b['total_amount'])) ?></b></td>
      <td class="<?= $late ? 't-red' : '' ?>"><?= h(dmy($b['due_date'])) ?><?= $late ? ' (quá hạn)' : '' ?></td><td><?= h(dmy($b['paid_date'])) ?></td>
      <td><?= status_badge($b['payment_status']) ?><?= $b['reminded_at'] && $b['payment_status'] === 'Chưa thu' ? '<br><small class="t-muted">đã nhắc ' . h(time_ago($b['reminded_at'])) . '</small>' : '' ?></td>
      <td><div class="actions"><a class="btn sm gray" href="<?= h(url('dich_vu/hoa_don_xem.php?id=' . (int) $b['id_bill'])) ?>">Xem</a>
      <?php if (Auth::isManager()): $ctx = '<input type="hidden" name="id" value="' . (int) $b['id_bill'] . '"><input type="hidden" name="month" value="' . h($month) . '"><input type="hidden" name="status" value="' . h($status) . '"><input type="hidden" name="q" value="' . h($kw) . '">' . csrf_field(); ?>
        <?php if ($b['payment_status'] === 'Chưa thu'): ?>
          <form class="inline" method="post" data-confirm="Xác nhận đã thu tiền hóa đơn này?"><?= $ctx ?><input type="hidden" name="action" value="pay"><button class="btn sm green">Thu tiền</button></form>
          <form class="inline" method="post"><?= $ctx ?><input type="hidden" name="action" value="remind"><button class="btn sm amber">Nhắc nhở</button></form>
          <form class="inline" method="post" data-confirm="Xóa hóa đơn này?"><?= $ctx ?><input type="hidden" name="action" value="delete"><button class="btn sm red">Xóa</button></form>
        <?php else: ?>
          <form class="inline" method="post" data-confirm="Chuyển hóa đơn về chưa thu?"><?= $ctx ?><input type="hidden" name="action" value="unpay"><button class="btn sm gray">Hoàn tác</button></form>
        <?php endif; endif; ?></div></td></tr>
  <?php endforeach; if (!$bills): ?><tr><td colspan="9" class="t-muted">Không có hóa đơn nào.</td></tr><?php endif; ?>
  </tbody></table></div>
  <p class="total">Tổng cộng: <?= h(money($sum)) ?></p>
 </div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
