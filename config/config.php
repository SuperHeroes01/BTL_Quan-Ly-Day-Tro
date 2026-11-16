<?php
// config/config.php - Thông số cấu hình chung của hệ thống
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP mặc định để trống
define('DB_NAME', 'quanlytro');

define('APP_NAME', 'Quản Lý Phòng Trọ');
define('UPLOAD_DIR', realpath(__DIR__ . '/..') . '/uploads/cccd/');
define('ROOM_UPLOAD_DIR', realpath(__DIR__ . '/..') . '/uploads/rooms/');

// BASE_URL tự nhận theo vị trí thư mục (vd: /quanlytro khi đặt trong htdocs)
$__root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
define('BASE_URL', ($__doc !== '' && strpos($__root, $__doc) === 0) ? substr($__root, strlen($__doc)) : '/quanlytro');
