<?php
// Trang chi tiết phòng trống công khai; phòng đang thuê chỉ được xem bởi người có quyền.
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) qs('id', '0');
$roomM = new Room();
$room = $roomM->find($id);
if (!$room) { http_response_code(404); exit('Không tìm thấy phòng.'); }

$canViewPrivate = false;
if (Auth::check()) {
    if (Auth::is('admin') || Auth::is('chutro')) {
        $canViewPrivate = true;
    } elseif (Auth::is('nguoithue')) {
        $mine = (new Renter())->findByUser(Auth::id());
        $canViewPrivate = $mine && (int) $mine['id_room'] === $id;
    }
}
if ($room['status'] !== 'Trống' && !$canViewPrivate) {
    http_response_code(404);
    exit('Phòng này không có trong danh sách công khai.');
}

$photos = $roomM->images($id);
if (!$photos && !empty($room['image_path'])) {
    $photos[] = ['id_image' => 0, 'file_path' => $room['image_path'], 'original_name' => 'Ảnh phòng'];
}
$gallery = [];
foreach ($photos as $photo) {
    $photoUrl = $photo['id_image']
        ? url('phong/anh.php?id=' . $id . '&image_id=' . (int) $photo['id_image'])
        : url('phong/anh.php?id=' . $id);
    $gallery[] = ['url' => $photoUrl, 'alt' => 'Phòng ' . $room['room_number'] . ' - ' . ($photo['original_name'] ?: 'Ảnh phòng')];
}
$devices = (new Device())->forRoom($id);
$amenities = preg_split('/[\r\n,;]+/u', (string) ($room['amenities'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$brand = Setting::get('ten_day_tro', APP_NAME);
$phone = Setting::get('dien_thoai');
$isPublic = !Auth::check();
$isManager = Auth::isManager();
$activePhoto = $gallery ? $gallery[0]['url'] : url('assets/images/room-vacant.svg');
?>
<!DOCTYPE html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chi tiết phòng <?= h($room['room_number']) ?> - <?= h($brand) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head><body class="public-page detail-page">
<header class="public-nav">
  <a class="public-brand" href="<?= h(url('index.php')) ?>"><span class="public-brand-mark">⌂</span><span><b><?= h($brand) ?></b><small>Không gian sống tiện nghi</small></span></a>
  <nav><a href="<?= h(url('index.php')) ?>">← Danh sách phòng</a><?php if ($isManager): ?><a class="btn public-login" href="<?= h(url('home.php')) ?>">Trang quản lý</a><?php elseif ($isPublic): ?><a class="btn public-login" href="<?= h(url('login.php')) ?>">Đăng nhập</a><?php endif; ?></nav>
</header>
<section class="detail-hero"><div><span class="public-eyebrow dark-eyebrow">THÔNG TIN PHÒNG</span><h1>Phòng <?= h($room['room_number']) ?></h1><p><?= h($room['area'] ? $room['area'] . ' m²' : 'Phòng trọ tiện nghi') ?> · <?= h($brand) ?></p></div><?php if ($isManager): ?><a class="btn detail-manager-edit" href="<?= h(url('phong/form.php?id=' . $id)) ?>">Chỉnh sửa thông tin & ảnh</a><?php endif; ?></section>
<main class="detail-container">
  <div class="detail-layout">
    <section class="detail-gallery-card" data-room-gallery data-autoplay="4500">
      <div class="gallery-main">
        <img class="gallery-current" src="<?= h($activePhoto) ?>" alt="Ảnh phòng <?= h($room['room_number']) ?>" data-gallery-main>
        <?php if (count($gallery) > 1): ?>
          <button class="gallery-arrow prev" type="button" data-gallery-prev aria-label="Ảnh trước">‹</button>
          <button class="gallery-arrow next" type="button" data-gallery-next aria-label="Ảnh tiếp theo">›</button>
        <?php endif; ?>
        <span class="gallery-count" data-gallery-count><?= $gallery ? '1 / ' . count($gallery) : 'Chưa có ảnh thực tế' ?></span>
      </div>
      <?php if (count($gallery) > 1): ?>
      <div class="gallery-thumbs" data-gallery-thumbs>
        <?php foreach ($gallery as $i => $photo): ?><button type="button" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-gallery-index="<?= $i ?>" aria-label="Xem ảnh <?= $i + 1 ?>"><img src="<?= h($photo['url']) ?>" alt="Ảnh thu nhỏ <?= $i + 1 ?>"></button><?php endforeach; ?>
      </div>
      <?php elseif (!$gallery): ?><p class="gallery-empty-note">Chủ trọ chưa tải ảnh thực tế cho phòng này. Hình trên là ảnh minh họa.</p><?php endif; ?>
    </section>
    <aside class="detail-summary-card">
      <div class="detail-status-row"><span class="detail-room-code">Mã phòng: <?= h($room['room_number']) ?></span></div>
      <p class="detail-price"><?= h(money($room['price'])) ?><small>/tháng</small></p>
      <div class="detail-facts"><div><span>⌂</span><small>Diện tích</small><b><?= $room['area'] ? h($room['area']) . ' m²' : 'Chưa cập nhật' ?></b></div><div><span>▧</span><small>Số ảnh</small><b><?= count($gallery) ?> ảnh</b></div><div><span>⚑</span><small>Tình trạng</small><b><?= h($room['status']) ?></b></div></div>
      <div class="detail-description"><h3>Mô tả phòng</h3><p><?= nl2br(h($room['description'] ?: 'Chủ trọ chưa cập nhật mô tả cho phòng này.')) ?></p></div>
      <?php if ($room['status'] === 'Trống'): ?>
        <a class="btn detail-contact-btn" href="<?= h(url('login.php')) ?>">Liên hệ để thuê phòng <span>→</span></a>
      <?php endif; ?>
      <?php if ($phone): ?><p class="detail-hotline">☎ Liên hệ chủ trọ: <b><?= h($phone) ?></b></p><?php endif; ?>
    </aside>
  </div>

  <div class="detail-information-grid">
    <section class="detail-info-card"><div class="detail-card-heading"><span>✦</span><div><h2>Đồ dùng & tiện nghi</h2><p>Các vật dụng được trang bị trong phòng</p></div></div>
      <?php if ($amenities): ?><div class="amenity-list"><?php foreach ($amenities as $item): ?><div class="amenity-item"><span>✓</span><?= h(trim($item)) ?></div><?php endforeach; ?></div><?php else: ?><p class="detail-muted">Chưa cập nhật danh sách đồ dùng và tiện nghi.</p><?php endif; ?>
    </section>
    <section class="detail-info-card"><div class="detail-card-heading"><span>⚙</span><div><h2>Thiết bị trong phòng</h2><p>Thiết bị được quản lý trên hệ thống</p></div></div>
      <?php if ($devices): ?><div class="device-list"><?php foreach ($devices as $device): ?><div class="device-item"><div><b><?= h($device['device_name']) ?></b><small><?= h($device['quantity']) ?> thiết bị<?= $device['note'] ? ' · ' . h($device['note']) : '' ?></small></div><?= status_badge($device['status']) ?></div><?php endforeach; ?></div><?php else: ?><p class="detail-muted">Chưa có thiết bị nào được gán cho phòng này.</p><?php endif; ?>
    </section>
  </div>
  <div class="detail-bottom-actions"><a class="btn gray" href="<?= h(url('index.php')) ?>">← Quay lại danh sách phòng</a><?php if ($isPublic): ?><a class="detail-signin-link" href="<?= h(url('login.php')) ?>">Đăng nhập hệ thống</a><?php endif; ?></div>
</main>
<footer class="public-footer"><div><b><?= h($brand) ?></b><p><?= h(Setting::get('dia_chi') ?: 'Thông tin phòng trọ minh bạch và thuận tiện.') ?></p></div><small>© <?= date('Y') ?> <?= h(APP_NAME) ?></small></footer>
<script src="<?= h(url('assets/js/app.js?v=6.3')) ?>"></script>
</body></html>
