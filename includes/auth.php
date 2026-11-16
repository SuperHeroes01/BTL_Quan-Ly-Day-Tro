<?php
// includes/auth.php - Đăng nhập, phiên làm việc và phân quyền truy cập
class Auth {
    const ROLES = [
        'admin'     => 'Admin',
        'chutro'    => 'Chủ trọ',
        'nguoithue' => 'Người thuê',
        'nhanvien'  => 'Nhân viên kỹ thuật',
    ];

    public static function user()  { return $_SESSION['user'] ?? null; }
    public static function check() { return isset($_SESSION['user']); }
    public static function id()    { return $_SESSION['user']['id'] ?? 0; }
    public static function role()  { return $_SESSION['user']['role'] ?? null; }
    public static function is(...$roles) { return in_array(self::role(), $roles, true); }
    public static function isManager() { return self::is('admin', 'chutro'); }

    public static function login(array $u) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $u['id_user'], 'username' => $u['username'],
            'full_name' => $u['full_name'], 'role' => $u['role'],
        ];
    }

    public static function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // Bảo vệ trang: chưa đăng nhập -> về trang đăng nhập; sai quyền -> 403
    public static function guard(array $roles = null) {
        if (!self::check()) { redirect('login.php'); }
        if ($roles !== null && !self::is(...$roles)) {
            http_response_code(403);
            die('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;padding:40px">'
                . '<h2>403 - Bạn không có quyền truy cập chức năng này</h2>'
                . '<p><a href="' . h(url('home.php')) . '">&larr; Về trang chủ</a></p></body>');
        }
    }
}
