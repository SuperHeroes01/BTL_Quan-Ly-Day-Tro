<?php
// includes/auth.php - Đăng nhập, phiên làm việc và phân quyền truy cập
class Auth {
    const ROLES = [
        'admin'     => 'Admin',
        'chutro'    => 'Chủ trọ',
        'nguoithue' => 'Người thuê',
        'nhanvien'  => 'Nhân viên kỹ thuật',
    ];

    // Mỗi request chỉ cần xác minh session một lần, tránh truy vấn lặp trong cùng trang.
    private static $sessionValidated = false;
    private static $sessionValid = false;

    public static function user()  { return self::check() ? $_SESSION['user'] : null; }
    public static function check() {
        if (empty($_SESSION['user']['id'])) { return false; }
        if (self::$sessionValidated) { return self::$sessionValid; }
        self::$sessionValidated = true;

        try {
            // Không tin session cũ: đối chiếu lại tài khoản với database ở mỗi request.
            $dbUser = Database::get()->one(
                "SELECT id_user, username, full_name, role, status FROM `user` WHERE id_user = ?",
                [(int) $_SESSION['user']['id']]
            );
        } catch (Throwable $e) {
            error_log('[Auth] Không thể xác minh session: ' . $e->getMessage());
            self::invalidateAuthentication();
            return false;
        }

        // Tài khoản đã bị xóa, bị khóa hoặc bị đổi vai trò: gỡ quyền đăng nhập ngay.
        if (!$dbUser || $dbUser['status'] !== 'Hoạt động'
            || $dbUser['role'] !== ($_SESSION['user']['role'] ?? null)) {
            self::invalidateAuthentication();
            return false;
        }

        // Đồng bộ tên hiển thị nếu Admin vừa cập nhật hồ sơ.
        $_SESSION['user']['username'] = $dbUser['username'];
        $_SESSION['user']['full_name'] = $dbUser['full_name'];
        self::$sessionValid = true;
        return true;
    }

    public static function id()    { return self::check() ? (int) $_SESSION['user']['id'] : 0; }
    public static function role()  { return self::check() ? $_SESSION['user']['role'] : null; }
    public static function is(...$roles) { return in_array(self::role(), $roles, true); }
    public static function isManager() { return self::is('admin', 'chutro'); }

    // Hủy trạng thái đăng nhập nhưng giữ CSRF/flash trong session hiện tại,
    // để biểu mẫu đăng nhập vẫn hoạt động ngay sau khi phát hiện session cũ.
    private static function invalidateAuthentication() {
        unset($_SESSION['user']);
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        self::$sessionValidated = true;
        self::$sessionValid = false;
    }

    public static function login(array $u) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $u['id_user'], 'username' => $u['username'],
            'full_name' => $u['full_name'], 'role' => $u['role'],
        ];
        self::$sessionValidated = true;
        self::$sessionValid = true;
    }

    public static function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        self::$sessionValidated = true;
        self::$sessionValid = false;
    }

    // Bảo vệ trang: chưa đăng nhập/tài khoản đã bị thu hồi -> về trang đăng nhập; sai quyền -> 403.
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
