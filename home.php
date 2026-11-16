<?php
// home.php - Trang chủ sau đăng nhập: bảng điều khiển theo từng vai trò
require_once __DIR__ . '/includes/bootstrap.php';
Auth::guard();

$pageTitle = 'Trang Chủ';
$pageSub = 'Xin chào, ' . Auth::user()['full_name'] . '. Hãy chọn chức năng bạn muốn thực hiện';
$active = 'home';
require __DIR__ . '/includes/header.php';
$db = Database::get();

if (Auth::isManager()):
    $st = (new Room())->stats();
    $openReq = (new RepairRequest())->countOpen();
    $month = date('Y-m');
    $rev = (new Bill())->revenue($month);
    $prev = (new Bill())->revenue(date('Y-m', strtotime('first day of last month')));
    $pct = $st['total'] ? round($st['rented'] * 100 / $st['total']) : 0;

    // Hoạt động gần đây: gộp từ hóa đơn, người thuê, yêu cầu sửa chữa
    $ev = [];
    foreach ($db->query("SELECT b.paid_date AS t, r.room_number, b.total_amount FROM bill b JOIN room r ON r.id_room = b.id_room
                         WHERE b.paid_date IS NOT NULL ORDER BY b.paid_date DESC, b.id_bill DESC LIMIT 4") as $x) {
        $ev[] = [$x['t'], 'Thanh toán tiền phòng ' . $x['room_number'], money($x['total_amount'])];
    }
    foreach ($db->query("SELECT t.start_date AS t, r.room_number, u.full_name FROM renter t JOIN room r ON r.id_room = t.id_room
                         JOIN `user` u ON u.id_user = t.id_user ORDER BY t.start_date DESC, t.id_renter DESC LIMIT 3") as $x) {
        $ev[] = [$x['t'], 'Hợp đồng thuê phòng ' . $x['room_number'], $x['full_name']];
    }
    foreach ($db->query("SELECT q.created_at AS t, d.device_name, r.room_number FROM repair_request q JOIN device d ON d.id_device = q.id_device
                         LEFT JOIN room r ON r.id_room = d.id_room ORDER BY q.created_at DESC LIMIT 4") as $x) {
        $ev[] = [$x['t'], 'Báo lỗi ' . $x['device_name'] . ($x['room_number'] ? ' ' . $x['room_number'] : ''), 'Yêu cầu sửa chữa'];
    }
    usort($ev, function ($a, $b) { return strcmp($b[0], $a[0]); });
    $ev = array_slice($ev, 0, 6);
?>
  <div class="grid g4" style="margin-bottom:18px">
    <div class="card stat"><div class="ico i-blue">▮</div><div><b><?= $st['total'] ?></b><small>Tổng số phòng</small></div></div>
    <div class="card stat"><div class="ico i-green">☺</div><div><b><?= $st['rented'] ?></b><small>Phòng đang cho thuê</small><em class="t-green"><?= $pct ?>% lấp đầy</em></div></div>
    <div class="card stat"><div class="ico i-amber">▯</div><div><b><?= $st['vacant'] ?></b><small>Phòng trống</small><em class="t-red">Còn trống</em></div></div>
    <div class="card stat"><div class="ico i-red">!</div><div><b><?= $openReq ?></b><small>Yêu cầu sửa chữa</small><em class="t-red">Cần xử lý</em></div></div>
  </div>
  <div class="grid g2">
    <div class="card">
      <div class="card-h">Doanh thu tháng này</div>
      <div class="card-b">
        <div class="big"><?= h(money($rev['total'])) ?></div>
        <p class="t-muted">Tháng <?= h(month_label($month)) ?> &middot; Đã thu / phải thu: <?= h(money($rev['total'])) ?> / <?= h(money($rev['expected'])) ?>
          <?php if ($prev['total'] > 0): $d = round(($rev['total'] - $prev['total']) * 100 / $prev['total']); ?>
            &middot; <?= $d >= 0 ? 'Tăng' : 'Giảm' ?> <?= abs($d) ?>% so với tháng trước
          <?php endif; ?></p>
        <div class="rowline"><span>Tiền phòng</span><b><?= h(money($rev['phong'])) ?></b></div>
        <div class="rowline"><span>Tiền điện</span><b><?= h(money($rev['dien'])) ?></b></div>
        <div class="rowline"><span>Tiền nước</span><b><?= h(money($rev['nuoc'])) ?></b></div>
        <div class="rowline"><span>Các dịch vụ khác</span><b><?= h(money($rev['khac'])) ?></b></div>
      </div>
    </div>
    <div class="card">
      <div class="card-h">Hoạt động gần đây</div>
      <div class="card-b"><ul class="activity">
        <?php foreach ($ev as $e): ?>
          <li><b><?= h($e[1]) ?></b><small><?= h($e[2]) ?> &middot; <?= h(time_ago($e[0])) ?></small></li>
        <?php endforeach; ?>
        <?php if (!$ev): ?><li>Chưa có hoạt động nào</li><?php endif; ?>
      </ul></div>
    </div>
  </div>

<?php elseif (Auth::is('nhanvien')):
    $staff = (new Staff())->findByUser(Auth::id());
    $reqs = (new RepairRequest())->search('open');
?>
  <div class="grid g3" style="margin-bottom:18px">
    <div class="card stat"><div class="ico i-red">!</div><div><b><?= count(array_filter($reqs, function ($r) { return $r['status'] === 'Chờ tiếp nhận'; })) ?></b><small>Yêu cầu chờ tiếp nhận</small></div></div>
    <div class="card stat"><div class="ico i-amber">⚒</div><div><b><?= count(array_filter($reqs, function ($r) { return $r['status'] === 'Đang sửa'; })) ?></b><small>Đang xử lý</small></div></div>
    <div class="card stat"><div class="ico i-blue">☺</div><div><b style="font-size:18px"><?= h($staff['staff_name'] ?? Auth::user()['full_name']) ?></b><small><?= h($staff['specialty'] ?? 'Nhân viên kỹ thuật') ?></small></div></div>
  </div>
  <div class="card"><div class="card-h">Yêu cầu cần xử lý <a class="btn sm" href="<?= h(url('thiet_bi/tiep_nhan.php')) ?>">Mở trang tiếp nhận</a></div>
    <div class="tw"><table class="t"><thead><tr><th>Thiết bị</th><th>Phòng</th><th>Nội dung</th><th>Mức độ</th><th>Trạng thái</th></tr></thead><tbody>
    <?php foreach ($reqs as $r): ?>
      <tr><td><?= h($r['device_name']) ?></td><td><?= h($r['room_number'] ?: 'Khu chung') ?></td><td><?= h($r['description']) ?></td>
          <td><?= status_badge($r['priority']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach; if (!$reqs): ?><tr><td colspan="5" class="t-muted">Không có yêu cầu nào cần xử lý.</td></tr><?php endif; ?>
    </tbody></table></div></div>

<?php else:   // Người thuê
    $info = (new Renter())->viewRoomInfo(Auth::id());
    $bills = (new Renter())->viewBill(Auth::id());
    $unpaid = array_filter($bills, function ($b) { return $b['payment_status'] === 'Chưa thu'; });
    $myReq = (new RepairRequest())->search('', Auth::id());
    $tenantGallery = [];
    if ($info) {
        $tenantPhotos = (new Room())->images((int) $info['id_room']);
        foreach ($tenantPhotos as $photo) {
            $tenantGallery[] = [
                'url' => url('phong/anh.php?id=' . (int) $info['id_room'] . '&image_id=' . (int) $photo['id_image']),
                'alt' => 'Ảnh phòng ' . $info['room_number'] . ' - ' . ($photo['original_name'] ?: 'Ảnh thực tế')
            ];
        }
        if (!$tenantGallery && !empty($info['image_path'])) {
            $tenantGallery[] = ['url' => url('phong/anh.php?id=' . (int) $info['id_room']), 'alt' => 'Ảnh phòng ' . $info['room_number']];
        }
    }
?>
  <?php if (!$info): ?>
    <div class="alert warn">Tài khoản của bạn chưa được gắn với phòng nào. Vui lòng liên hệ chủ trọ.</div>
  <?php else: ?>
    <?php foreach ($unpaid as $b): ?>
      <div class="alert <?= $b['due_date'] < date('Y-m-d') ? 'error' : 'warn' ?>">
        Hóa đơn tháng <?= h(month_label($b['month'])) ?> (<?= h(money($b['total_amount'])) ?>) chưa thanh toán, hạn <?= h(dmy($b['due_date'])) ?>.
        <?= $b['reminded_at'] ? 'Chủ trọ đã nhắc bạn lúc ' . h(date('d/m/Y H:i', strtotime($b['reminded_at']))) . '.' : '' ?>
        <a href="<?= h(url('dich_vu/hoa_don_xem.php?id=' . (int) $b['id_bill'])) ?>">Xem chi tiết</a>
      </div>
    <?php endforeach; ?>
    <div class="grid g3" style="margin-bottom:18px">
      <div class="card stat"><div class="ico i-blue">⌂</div><div><b><?= h($info['room_number']) ?></b><small>Phòng của bạn &middot; <?= h(money(($info['rent_price'] ?? 0) > 0 ? $info['rent_price'] : $info['price'])) ?>/tháng</small></div></div>
      <div class="card stat"><div class="ico i-red">☰</div><div><b><?= count($unpaid) ?></b><small>Hóa đơn chưa thanh toán</small></div></div>
      <div class="card stat"><div class="ico i-amber">⚒</div><div><b><?= count(array_filter($myReq, function ($r) { return in_array($r['status'], ['Chờ tiếp nhận', 'Đang sửa'], true); })) ?></b><small>Yêu cầu sửa chữa đang xử lý</small></div></div>
    </div>

    <div class="card tenant-home-gallery-card">
      <div class="card-h"><span>Ảnh thực tế phòng <?= h($info['room_number']) ?> <small class="tenant-gallery-live-label">Tự động trình chiếu</small></span><a class="btn sm" href="<?= h(url('phong/index.php')) ?>">Xem thông tin phòng</a></div>
      <div class="card-b">
        <div class="tenant-home-gallery-layout">
          <div class="tenant-room-gallery tenant-home-room-gallery" data-room-gallery data-autoplay="4000">
            <div class="gallery-main tenant-gallery-main">
              <img class="gallery-current" src="<?= h($tenantGallery[0]['url'] ?? url('assets/images/room-rented.svg')) ?>" alt="<?= h($tenantGallery[0]['alt'] ?? ('Ảnh minh họa phòng ' . $info['room_number'])) ?>" data-gallery-main>
              <?php if (count($tenantGallery) > 1): ?>
                <button class="gallery-arrow prev" type="button" data-gallery-prev aria-label="Ảnh trước">‹</button>
                <button class="gallery-arrow next" type="button" data-gallery-next aria-label="Ảnh tiếp theo">›</button>
              <?php endif; ?>
              <span class="gallery-count" data-gallery-count><?= $tenantGallery ? '1 / ' . count($tenantGallery) : 'Ảnh minh họa' ?></span>
            </div>
            <?php if (count($tenantGallery) > 1): ?>
              <div class="gallery-thumbs" data-gallery-thumbs>
                <?php foreach ($tenantGallery as $i => $photo): ?>
                  <button type="button" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-gallery-index="<?= $i ?>" aria-label="Xem ảnh <?= $i + 1 ?>"><img src="<?= h($photo['url']) ?>" alt="<?= h($photo['alt']) ?>"></button>
                <?php endforeach; ?>
              </div>
            <?php elseif (!$tenantGallery): ?>
              <p class="gallery-empty-note">Chủ trọ chưa tải ảnh thực tế cho phòng này.</p>
            <?php endif; ?>
          </div>
          <div class="tenant-gallery-caption">
            <span class="public-status static-status">● <?= h($info['status'] ?? 'Đang thuê') ?></span>
            <h3>Không gian phòng <?= h($info['room_number']) ?> của bạn</h3>
            <p><?= h($info['description'] ?: 'Chủ trọ chưa cập nhật mô tả cho phòng.') ?></p>
            <div class="rowline"><span>Diện tích</span><b><?= $info['area'] ? h($info['area']) . ' m²' : '—' ?></b></div>
            <div class="rowline"><span>Ảnh trong thư viện</span><b><?= count($tenantGallery) ?> ảnh</b></div>
            <p class="gallery-auto-note">Ảnh tự động chuyển liên tục sau 4 giây. Bạn cũng có thể chọn ảnh bằng các hình thu nhỏ.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="grid g2">
      <div class="card"><div class="card-h">Hóa đơn gần đây <a class="btn sm" href="<?= h(url('dich_vu/hoa_don.php')) ?>">Xem tất cả</a></div>
        <div class="tw"><table class="t"><thead><tr><th>Tháng</th><th>Số tiền</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach (array_slice($bills, 0, 4) as $b): ?>
          <tr><td><?= h(month_label($b['month'])) ?></td><td><?= h(money($b['total_amount'])) ?></td><td><?= status_badge($b['payment_status']) ?></td></tr>
        <?php endforeach; if (!$bills): ?><tr><td colspan="3" class="t-muted">Chưa có hóa đơn.</td></tr><?php endif; ?>
        </tbody></table></div></div>
      <div class="card"><div class="card-h">Thiết bị trong phòng <a class="btn sm red" href="<?= h(url('thiet_bi/bao_loi.php')) ?>">Báo lỗi thiết bị</a></div>
        <div class="tw"><table class="t"><thead><tr><th>Thiết bị</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach ($info['devices'] as $d): ?>
          <tr><td><?= h($d['device_name']) ?></td><td><?= status_badge($d['status']) ?></td></tr>
        <?php endforeach; if (!$info['devices']): ?><tr><td colspan="2" class="t-muted">Phòng chưa có thiết bị nào.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
