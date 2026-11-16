<?php
// logout.php - Đăng xuất: hủy phiên làm việc rồi quay về trang đăng nhập
require_once __DIR__ . '/includes/bootstrap.php';
(new User())->logout();
header('Location: ' . url('login.php'));
exit;
