<?php
// Trang công khai: tìm phòng trống và mở trang chi tiết từng phòng.
require_once __DIR__ . '/includes/bootstrap.php';
if (Auth::check() && !Auth::isManager()) { redirect('home.php'); }
$isManager = Auth::isManager();
$kw = qs('q');
$max = (float) qs('max', '0');
$rooms = (new Room())->vacant($kw, $max);
$brand = Setting::get('ten_day_tro', APP_NAME);
$address = Setting::get('dia_chi');
$phone = Setting::get('dien_thoai');
$email = Setting::get('email');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Danh sách phòng trống - <?= h($brand) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head>
<body class="public-page">
<header class="public-nav">
  <a class="public-brand" href="<?= h(url('index.php')) ?>"><span class="public-brand-mark">⌂</span><span><b><?= h($brand) ?></b><small>Không gian sống tiện nghi</small></span></a>
  <nav><a href="#rooms">Danh sách phòng</a><a href="#contact">Liên hệ</a><?php if ($isManager): ?><a class="btn public-login" href="<?= h(url('home.php')) ?>">Trang quản lý</a><a href="<?= h(url('logout.php')) ?>">Đăng xuất</a><?php else: ?><a class="btn public-login" href="<?= h(url('login.php')) ?>">Đăng nhập</a><?php endif; ?></nav>
</header>
<section class="public-hero">
  <div class="public-hero-overlay"></div>
  <div class="public-hero-content">
    <span class="public-eyebrow">TÌM KHÔNG GIAN SỐNG PHÙ HỢP</span>
    <h1>Chọn căn phòng phù hợp<br>với cuộc sống của bạn</h1>
    <p><?= h($address ?: 'Phòng trọ sạch sẽ, tiện nghi và an toàn') ?><?= $phone ? ' · Hotline: ' . h($phone) : '' ?></p>
    <a class="btn public-hero-btn" href="#rooms">Khám phá phòng trống <span>↓</span></a>
  </div>
  <div class="hero-floating-card"><b><?= count($rooms) ?></b><span>phòng đang sẵn sàng</span></div>
</section>
<main class="pub public-content" id="rooms">
  <div class="public-section-heading"><div><span class="public-eyebrow dark-eyebrow">DANH SÁCH MỚI NHẤT</span><h2>Phòng trọ đang trống</h2><p>Chọn “Xem chi tiết” để xem tiện nghi, thiết bị và hình ảnh thực tế của từng phòng.</p></div><div class="public-room-count"><b><?= count($rooms) ?></b><span>phòng trống</span></div></div>
  <form class="public-filter" method="get">
    <label class="public-search"><span>⌕</span><input name="q" value="<?= h($kw) ?>" placeholder="Tìm theo mã phòng, ví dụ P101"></label>
    <label class="public-price"><span>Giá tối đa</span><input type="number" name="max" min="0" step="100000" value="<?= $max > 0 ? h($max) : '' ?>" placeholder="Không giới hạn"></label>
    <button class="btn public-search-btn">Tìm phòng</button>
    <?php if ($kw !== '' || $max > 0): ?><a class="public-reset" href="<?= h(url('index.php')) ?>">Xóa lọc</a><?php endif; ?>
  </form>
  <?php if (!$rooms): ?>
    <div class="public-empty"><div>⌂</div><h3>Chưa tìm thấy phòng phù hợp</h3><p>Vui lòng thay đổi điều kiện tìm kiếm hoặc liên hệ chủ trọ để được hỗ trợ.</p></div>
  <?php else: ?>
    <div class="public-room-grid">
      <?php foreach ($rooms as $r):
        $photoUrl = '';
        if (!empty($r['cover_image_id'])) $photoUrl = url('phong/anh.php?id=' . (int) $r['id_room'] . '&image_id=' . (int) $r['cover_image_id']);
        elseif (!empty($r['image_path'])) $photoUrl = url('phong/anh.php?id=' . (int) $r['id_room']);
        else $photoUrl = url('assets/images/room-vacant.svg');
      ?>
      <article class="public-room-card">
        <a class="public-room-photo" href="<?= h(url('phong_chi_tiet.php?id=' . (int) $r['id_room'])) ?>">
          <img src="<?= h($photoUrl) ?>" alt="Hình ảnh phòng <?= h($r['room_number']) ?>" loading="lazy">
          <?php if (!empty($r['photo_count'])): ?><span class="public-photo-count">▧ <?= (int) $r['photo_count'] ?> ảnh</span><?php endif; ?>
        </a>
        <div class="public-room-info">
          <div class="public-room-title"><h3>Phòng <?= h($r['room_number']) ?></h3><span><?= $r['area'] ? h($r['area']) . ' m²' : 'Diện tích linh hoạt' ?></span></div>
          <div class="public-room-price"><?= h(money($r['price'])) ?><small>/tháng</small></div>
          <p class="public-room-desc"><?= h(mb_strimwidth((string) ($r['description'] ?? ''), 0, 100, '…', 'UTF-8') ?: 'Phòng đang sẵn sàng đón người thuê mới.') ?></p>
          <div class="public-room-actions">
            <a class="btn public-detail-btn" href="<?= h(url('phong_chi_tiet.php?id=' . (int) $r['id_room'])) ?>">Xem chi tiết <span>→</span></a>
            <a class="public-contact-link" href="#contact">Liên hệ</a>
            <?php if ($isManager): ?><a class="btn gray public-manage-room-btn" href="<?= h(url('phong/form.php?id=' . (int) $r['id_room'])) ?>">Chỉnh sửa thông tin & ảnh</a><?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<footer class="public-footer" id="contact">
  <div><b><?= h($brand) ?></b><p><?= h($address ?: 'Cảm ơn bạn đã quan tâm đến phòng trọ của chúng tôi.') ?></p></div>
  <div class="public-contact-detail"><?php if ($phone): ?><span>☎ <?= h($phone) ?></span><?php endif; ?><?php if ($email): ?><span>✉ <?= h($email) ?></span><?php endif; ?><?php if ($isManager): ?><a href="<?= h(url('home.php')) ?>">Quay lại trang quản lý →</a><?php else: ?><a href="<?= h(url('login.php')) ?>">Đăng nhập hệ thống →</a><?php endif; ?></div>
  <small>© <?= date('Y') ?> <?= h(APP_NAME) ?> · An toàn · Tiện nghi · Minh bạch</small>
</footer>
</body>
</html>
