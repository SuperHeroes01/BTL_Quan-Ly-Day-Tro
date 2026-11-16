<?php
// phong/index.php - Tab "Danh sách phòng": lưới phòng, tìm kiếm, thêm/sửa/xóa (người thuê chỉ xem phòng của mình)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nhanvien', 'nguoithue']);
$roomM = new Room();

if (is_post() && Auth::isManager()) {
    csrf_check();
    if (post('action') === 'delete') {
        $r = $roomM->deleteRoom((int) post('id'));
        $r === true ? flash('success', 'Đã xóa phòng') : flash('error', $r);
    }
    redirect('phong/index.php');
}

$pageTitle = 'Quản Lý Phòng Trọ';
$pageSub = 'Quản lý người thuê, phòng trống và phòng đã cho thuê';
$active = 'phong';
require __DIR__ . '/../includes/header.php';

if (Auth::isManager() || Auth::is('nhanvien')) {
    $tabs = Auth::isManager()
        ? ['ds' => ['Danh sách phòng', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'], 'nt' => ['Người thuê', 'phong/nguoi_thue.php']]
        : ['ds' => ['Danh sách phòng', 'phong/index.php']];
    echo tabs($tabs, 'ds');
    $kw = qs('q'); $status = qs('status');
    $rooms = $roomM->searchRoom($kw, $status);
    $st = $roomM->stats();
?>
<div class="card">
  <div class="card-h"><span>Danh sách phòng
      <?= badge($st['rented'] . ' Đã thuê', 'green') ?> <?= badge($st['vacant'] . ' Trống', 'amber') ?></span>
    <?php if (Auth::isManager()): ?><a class="btn" href="<?= h(url('phong/form.php')) ?>">+ Thêm phòng</a><?php endif; ?></div>
  <div class="card-b">
    <form class="filter" method="get">
      <div class="field"><label>Tìm theo mã phòng</label><input name="q" value="<?= h($kw) ?>" placeholder="Ví dụ: P101"></div>
      <div class="field"><label>Trạng thái</label><select name="status"><option value="">Tất cả</option>
        <?php foreach (['Trống', 'Đã thuê', 'Bảo trì'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
      <button class="btn">Tìm kiếm</button>
    </form>
    <div class="rooms">
      <?php foreach ($rooms as $r): $cls = ['Đã thuê' => 'rented', 'Trống' => 'vacant', 'Bảo trì' => 'fix'][$r['status']]; ?>
        <div class="room <?= $cls ?>">
          <?php if (Auth::isManager()): ?><img class="room-thumb" src="<?= h(!empty($r['image_path']) ? url('phong/anh.php?id=' . (int) $r['id_room']) : url('assets/images/' . ($r['status'] === 'Đã thuê' ? 'room-rented.svg' : ($r['status'] === 'Bảo trì' ? 'room-maintenance.svg' : 'room-vacant.svg')))) ?>" alt="Hình ảnh phòng <?= h($r['room_number']) ?>" loading="lazy"><?php endif; ?>
          <h3><?= h($r['room_number']) ?></h3>
          <p><?= h($r['tenant_names'] ?: 'Chưa có người thuê') ?></p>
          <p><?= h(money($r['price'])) ?>/tháng<?= $r['area'] ? ' &middot; ' . h($r['area']) . ' m²' : '' ?></p>
          <p><?= (int) $r['occupants'] ?> người ở</p>
          <?php if (Auth::isManager()): ?>
          <div class="actions" style="margin-top:8px">
            <a class="btn sm" href="<?= h(url('phong_chi_tiet.php?id=' . (int) $r['id_room'])) ?>">Xem chi tiết</a>
            <a class="btn sm gray" href="<?= h(url('phong/form.php?id=' . (int) $r['id_room'])) ?>">Sửa</a>
            <form class="inline" method="post" data-confirm="Xóa phòng <?= h($r['room_number']) ?>?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id_room'] ?>">
              <button class="btn sm red">Xóa</button></form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; if (!$rooms): ?><p class="t-muted">Không tìm thấy phòng nào.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php
} else {   // Người thuê: xem thông tin phòng của mình
    echo tabs(['ds' => ['Phòng của tôi', 'phong/index.php'], 'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php']], 'ds');
    $info = (new Renter())->viewRoomInfo(Auth::id());
    if (!$info) { echo '<div class="alert warn">Tài khoản chưa được gắn với phòng nào.</div>'; }
    else {
        $mates = Database::get()->query("SELECT u.full_name, t.phone FROM renter t JOIN `user` u ON u.id_user = t.id_user
                 WHERE t.id_room = ? AND " . Renter::ACTIVE . " ORDER BY u.full_name", [(int) $info['id_room']]);
        $tenantPhotos = (new Room())->images((int) $info['id_room']);
        $tenantGallery = [];
        foreach ($tenantPhotos as $photo) {
            $tenantGallery[] = [
                'url' => url('phong/anh.php?id=' . (int) $info['id_room'] . '&image_id=' . (int) $photo['id_image']),
                'alt' => 'Ảnh phòng ' . $info['room_number'] . ' - ' . ($photo['original_name'] ?: 'Ảnh thực tế')
            ];
        }
        if (!$tenantGallery && !empty($info['image_path'])) $tenantGallery[] = ['url' => url('phong/anh.php?id=' . (int) $info['id_room']), 'alt' => 'Ảnh phòng ' . $info['room_number']];
?>
<div class="grid g2">
  <div class="card"><div class="card-h"><span>Ảnh thực tế phòng <?= h($info['room_number']) ?> <small class="tenant-gallery-live-label">Tự động trình chiếu</small></span><span class="badge blue">Chỉ phòng của bạn</span></div><div class="card-b">
    <div class="tenant-room-gallery" data-room-gallery data-autoplay="4000">
      <div class="gallery-main tenant-gallery-main">
        <img class="gallery-current" src="<?= h($tenantGallery[0]['url'] ?? url('assets/images/room-rented.svg')) ?>" alt="<?= h($tenantGallery[0]['alt'] ?? ('Ảnh minh họa phòng ' . $info['room_number'])) ?>" data-gallery-main>
        <?php if (count($tenantGallery) > 1): ?>
          <button class="gallery-arrow prev" type="button" data-gallery-prev aria-label="Ảnh trước">‹</button>
          <button class="gallery-arrow next" type="button" data-gallery-next aria-label="Ảnh tiếp theo">›</button>
        <?php endif; ?>
        <span class="gallery-count" data-gallery-count><?= $tenantGallery ? '1 / ' . count($tenantGallery) : 'Ảnh minh họa' ?></span>
      </div>
      <?php if (count($tenantGallery) > 1): ?><div class="gallery-thumbs" data-gallery-thumbs>
        <?php foreach ($tenantGallery as $i => $photo): ?><button type="button" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-gallery-index="<?= $i ?>" aria-label="Xem ảnh <?= $i + 1 ?>"><img src="<?= h($photo['url']) ?>" alt="<?= h($photo['alt']) ?>"></button><?php endforeach; ?>
      </div><?php endif; ?>
    </div>
    <p class="gallery-auto-note">Ảnh tự động chuyển liên tục sau 4 giây. Dùng nút mũi tên hoặc ảnh thu nhỏ để xem ảnh khác.</p>
    <div class="rowline"><span>Giá thuê theo hồ sơ</span><b><?= h(money(($info['rent_price'] ?? 0) > 0 ? $info['rent_price'] : $info['price'])) ?>/tháng</b></div>
    <div class="rowline"><span>Diện tích</span><b><?= $info['area'] ? h($info['area']) . ' m²' : '—' ?></b></div>
    <div class="rowline"><span>Ngày vào ở</span><b><?= h(dmy($info['start_date'])) ?></b></div>
    <div class="rowline"><span>Mô tả</span><b><?= h($info['description']) ?></b></div>
    <p style="margin-top:12px"><a class="btn sm" href="<?= h(url('phong/hop_dong.php')) ?>">Xem hợp đồng thuê</a></p>
    <p><b>Người cùng phòng</b></p>
    <?php foreach ($mates as $m): ?><div class="rowline"><span><?= h($m['full_name']) ?></span><span class="t-muted"><?= h($m['phone']) ?></span></div><?php endforeach; ?>
  </div></div>
  <div class="card"><div class="card-h">Thiết bị trong phòng</div>
    <div class="tw"><table class="t"><thead><tr><th>Thiết bị</th><th>SL</th><th>Trạng thái</th></tr></thead><tbody>
    <?php foreach ($info['devices'] as $d): ?><tr><td><?= h($d['device_name']) ?></td><td><?= (int) $d['quantity'] ?></td><td><?= status_badge($d['status']) ?></td></tr><?php endforeach;
    if (!$info['devices']): ?><tr><td colspan="3" class="t-muted">Chưa có thiết bị.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<?php } } require __DIR__ . '/../includes/footer.php'; ?>
