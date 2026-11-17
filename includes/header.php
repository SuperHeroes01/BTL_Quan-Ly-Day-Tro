<?php
// includes/header.php - Phần đầu trang: thanh bên (menu theo vai trò) + thanh tiêu đề
// Trước khi nạp tệp này, trang cần đặt: $pageTitle, $pageSub (tuỳ chọn), $active (mục menu đang chọn)
$u = Auth::user();
$pageTitle = $pageTitle ?? APP_NAME;
$pageSub = $pageSub ?? '';
$active = $active ?? '';
$brand = Setting::get('ten_day_tro', APP_NAME);

$badgeService = 0; $badgeDevice = 0; $badgePending = 0;
if (Auth::isManager()) {
    $badgeService = (new Bill())->unpaidCount();
    $badgeDevice = (new RepairRequest())->countOpen();
    if (Auth::is('admin')) { $badgePending = (new User())->pendingTenantCount(); }
} elseif (Auth::is('nhanvien')) {
    $badgeDevice = (new RepairRequest())->countOpen();
} elseif (Auth::is('nguoithue')) {
    $rt = (new Renter())->findByUser(Auth::id());
    $badgeService = $rt ? (new Bill())->unpaidCount((int) $rt['id_room']) : 0;
}

$menu = [['home', 'Trang chủ', 'home.php', '▦', 0]];
if (Auth::isManager()) {
    $menu[] = ['dich_vu', 'Quản lý Dịch Vụ', 'dich_vu/index.php', '☰', $badgeService];
    $menu[] = ['phong', 'Quản lý Phòng Trọ', Auth::is('admin') && $badgePending > 0 ? 'phong/nguoi_thue.php' : 'phong/index.php', '⌂', $badgePending];
    $menu[] = ['thiet_bi', 'Quản lý Thiết Bị', 'thiet_bi/index.php', '⚒', $badgeDevice];
} elseif (Auth::is('nhanvien')) {
    $menu[] = ['thiet_bi', 'Quản lý Thiết Bị', 'thiet_bi/index.php', '⚒', $badgeDevice];
    $menu[] = ['phong', 'Danh sách phòng', 'phong/index.php', '⌂', 0];
} elseif (Auth::is('nguoithue')) {
    $menu[] = ['dich_vu', 'Hóa đơn & Dịch vụ', 'dich_vu/hoa_don.php', '☰', $badgeService];
    $menu[] = ['phong', 'Phòng của tôi', 'phong/index.php', '⌂', 0];
    $menu[] = ['thiet_bi', 'Thiết bị & Báo lỗi', 'thiet_bi/index.php', '⚒', 0];
}

$menu2 = [];
if (Auth::is('admin')) { 
    $menu2[] = ['he_thong', 'Quản lý Hệ Thống', 'he_thong/phan_quyen.php', '⚙', 0]; 
    $menu2[] = ['nhat_ky', 'Nhật ký hoạt động', 'he_thong/nhat_ky.php', '📜', 0]; // Đã tích hợp mục Nhật ký hoạt động
}
$menu2[] = ['tai_khoan', 'Tài khoản của tôi', 'tai_khoan.php', '☺', 0];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> - <?= h($brand) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="logo">⌂</div><div><b><?= h(APP_NAME) ?></b><small><?= h($brand) ?></small></div></div>
    <div class="usercard"><div class="avatar"><?= h(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) ?></div>
      <div><b><?= h($u['full_name']) ?></b><small><?= h(Auth::ROLES[$u['role']] ?? '') ?></small></div></div>
    <div class="menu-title">Tổng quan</div>
    <?php foreach ([$menu[0]] as $m): ?>
      <a class="menu-item<?= $active === $m[0] ? ' active' : '' ?>" href="<?= h(url($m[2])) ?>"><span class="ico"><?= $m[3] ?></span><?= h($m[1]) ?></a>
    <?php endforeach; ?>
    <div class="menu-title">Quản lý</div>
    <?php foreach (array_slice($menu, 1) as $m): ?>
      <a class="menu-item<?= $active === $m[0] ? ' active' : '' ?>" href="<?= h(url($m[2])) ?>"><span class="ico"><?= $m[3] ?></span><?= h($m[1]) ?>
        <?php if ($m[4] > 0): ?><span class="count"><?= (int) $m[4] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="menu-title">Hệ thống</div>
    <?php foreach ($menu2 as $m): ?>
      <a class="menu-item<?= $active === $m[0] ? ' active' : '' ?>" href="<?= h(url($m[2])) ?>"><span class="ico"><?= $m[3] ?></span><?= h($m[1]) ?></a>
    <?php endforeach; ?>
    <?php if (Auth::isManager()): ?><a class="menu-item" href="<?= h(url('index.php')) ?>"><span class="ico">↗</span>Xem trang phòng công khai</a><?php endif; ?>
    <div class="grow"></div>
    <a class="logout" href="<?= h(url('logout.php')) ?>">⇦ Đăng xuất</a>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="burger" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div><h1><?= h($pageTitle) ?></h1><?php if ($pageSub !== ''): ?><p><?= h($pageSub) ?></p><?php endif; ?></div>
    </header>
    <section class="content">
      <?php foreach (flash() as $f): ?>
        <div class="alert <?= h($f[0]) ?>"><?= h($f[1]) ?></div>
      <?php endforeach; ?>
