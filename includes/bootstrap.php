<?php
// includes/bootstrap.php - Khởi động chung: cấu hình, session, nạp lớp tự động
require_once __DIR__ . '/../config/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (is_file($file)) { require_once $file; }
});

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
