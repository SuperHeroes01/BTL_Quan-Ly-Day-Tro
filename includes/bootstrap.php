
<?php
// includes/bootstrap.php - Khởi động chung: cấu hình, session, nạp lớp tự động

require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

ini_set('session.cookie_httponly', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Tự động nạp class ----
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ---- Khởi tạo kết nối Database ----
global $db;

try {
    if (class_exists('Database')) {
        $db = Database::get();
    }
} catch (Throwable $e) {
    $db = null;
    error_log('Lỗi kết nối Database: ' . $e->getMessage());
}

// ---- Nạp các file dùng chung ----
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// ---- Kiểm tra trạng thái tài khoản khi tải trang ----
if (class_exists('Auth') && Auth::check()) {

    $currentUserId = Auth::id();

    if ($currentUserId > 0 && isset($db) && $db) {

        try {
            // Sử dụng value() theo đúng lớp Database MySQLi
            $dbStatus = $db->value(
                "SELECT status
                 FROM user
                 WHERE id_user = ?
                 LIMIT 1",
                [$currentUserId]
            );

            $dbStatus = trim(
                mb_strtolower((string)$dbStatus, 'UTF-8')
            );

            // Nếu tài khoản không còn hoạt động thì đăng xuất
            if ($dbStatus !== 'hoạt động') {
                Auth::logout();

                header('Location: ' . url('login.php'));
                exit;
            }

        } catch (Throwable $e) {
            error_log('Lỗi kiểm tra trạng thái tài khoản: ' . $e->getMessage());
        }
    }
}
