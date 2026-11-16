<?php
// dich_vu/index.php - Tab "Quản lý dịch vụ": bảng giá dịch vụ và chỉ số điện nước của tháng
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);

$pageTitle = 'Quản Lý Dịch Vụ';
$pageSub = 'Tiền phòng, tiền nước, tiền điện, internet, vệ sinh, máy giặt, tạm trú, thanh toán';
$active = 'dich_vu';
require __DIR__ . '/../includes/header.php';
echo tabs(['dv' => ['Quản lý dịch vụ', 'dich_vu/index.php'], 'hd' => ['Hóa đơn & Thanh toán', 'dich_vu/hoa_don.php'], 'tt' => ['Tạm trú', 'dich_vu/tam_tru.php']], 'dv');

$month = qs('month', date('Y-m'));
if (!valid_month($month)) { $month = date('Y-m'); }
$services = (new Service())->active();
$meter = (new Bill())->meterTable($month);
$icons = ['dien' => '⚡', 'nuoc' => '💧', 'mang' => '📶', 'vesinh' => '🧹', 'maygiat' => '🧺'];
?>
<div class="grid g4" style="margin-bottom:18px">
  <a class="svc" href="<?= h(url('phong/index.php')) ?>"><div class="ico">⌂</div><b>Tiền phòng</b><small>Theo giá từng phòng</small></a>
  <?php foreach ($services as $s): ?>
    <div class="svc"><div class="ico"><?= $icons[$s['code']] ?? '☰' ?></div><b><?= h($s['service_name']) ?></b>
      <small><?= h(money($s['unit_price'])) ?>/<?= h($s['unit']) ?> &middot; <?= h($s['type']) ?></small></div>
  <?php endforeach; ?>
  <a class="svc" href="<?= h(url('dich_vu/tam_tru.php')) ?>"><div class="ico">🪪</div><b>Tạm trú</b><small>Đăng ký bằng ảnh CCCD</small></a>
  <a class="svc" href="<?= h(url('dich_vu/hoa_don.php')) ?>"><div class="ico">💳</div><b>Thanh toán</b><small>Thu tiền, nhắc nhở</small></a>
</div>
<?php if (Auth::is('admin')): ?><p class="t-muted">Để thay đổi đơn giá hoặc thêm dịch vụ, vào <a href="<?= h(url('he_thong/chinh_sua.php')) ?>">Quản lý hệ thống &rsaquo; Chỉnh sửa hệ thống</a>.</p><?php endif; ?>

<div class="card"><div class="card-h">Chỉ số điện nước tháng <?= h(month_label($month)) ?>
  <form method="get" class="filter" style="margin:0"><input type="month" name="month" value="<?= h($month) ?>" style="padding:6px;border:1px solid #cbd5e1;border-radius:8px"><button class="btn sm">Xem</button>
  <a class="btn sm green" href="<?= h(url('dich_vu/hoa_don_tao.php?month=' . $month)) ?>">+ Lập hóa đơn</a></form></div>
  <div class="tw"><table class="t"><thead><tr><th>Phòng</th><th>Điện cũ</th><th>Điện mới</th><th>Tiêu thụ</th><th>Nước cũ</th><th>Nước mới</th><th>Tiêu thụ</th><th>Trạng thái</th></tr></thead><tbody>
  <?php foreach ($meter as $m): ?>
    <tr><td><b><?= h($m['room_number']) ?></b></td>
      <td><?= h($m['dien_cu'] ?? '—') ?></td><td><?= h($m['dien_moi'] ?? '—') ?></td><td><?= $m['dien_sd'] !== null ? h((int) $m['dien_sd']) . ' kWh' : '—' ?></td>
      <td><?= h($m['nuoc_cu'] ?? '—') ?></td><td><?= h($m['nuoc_moi'] ?? '—') ?></td><td><?= $m['nuoc_sd'] !== null ? h((int) $m['nuoc_sd']) . ' m³' : '—' ?></td>
      <td><?php if ($m['id_bill']): ?><a href="<?= h(url('dich_vu/hoa_don_xem.php?id=' . (int) $m['id_bill'])) ?>"><?= status_badge($m['payment_status']) ?></a>
          <?php else: ?><a class="btn sm" href="<?= h(url('dich_vu/hoa_don_tao.php?room=' . (int) $m['id_room'] . '&month=' . $month)) ?>">Lập hóa đơn</a><?php endif; ?></td></tr>
  <?php endforeach; if (!$meter): ?><tr><td colspan="8" class="t-muted">Chưa có phòng nào đang cho thuê.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
