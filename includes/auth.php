<?php
// includes/auth.php - Đăng nhập, phiên làm việc và phân quyền truy cập
class Auth {
    const ROLES = [
        'admin'     => 'Admin',
        'chutro'    => 'Chủ trọ',
        'nguoithue' => 'Người thuê',
        'nhanvien'  => 'Nhân viên kỹ thuật',
    ];

    // Xác minh tối đa một lần trong mỗi request để tránh truy vấn lặp.
    private static $sessionValidated = false;
    private static $sessionValid = false;

    public static function user() {
        return self::check() ? $_SESSION['user'] : null;
    }

    /**
     * Không tin dữ liệu đăng nhập chỉ nằm trong session.
     * Mỗi request sẽ đối chiếu id, trạng thái và vai trò với database.
     * Nếu tài khoản bị xóa, bị khóa, đổi vai trò hoặc không thể xác minh,
     * phiên đăng nhập bị thu hồi (fail closed).
     */
    public static function check() {
        if (empty($_SESSION['user']['id']) || (int) $_SESSION['user']['id'] <= 0) {
            if (isset($_SESSION['user'])) {
                self::invalidateAuthentication();
            }
            return false;
        }

        if (self::$sessionValidated) {
            return self::$sessionValid;
        }
        self::$sessionValidated = true;

        try {
            $dbUser = Database::get()->one(
                "SELECT id_user, username, full_name, role, status
                 FROM `user`
                 WHERE id_user = ?
                 LIMIT 1",
                [(int) $_SESSION['user']['id']]
            );
        } catch (Throwable $e) {
            error_log('[Auth] Không thể xác minh session: ' . $e->getMessage());
            self::invalidateAuthentication();
            return false;
        }

        $sessionRole = $_SESSION['user']['role'] ?? null;
        $staffIsActive = true;
        if ($dbUser && ($dbUser['role'] ?? null) === 'nhanvien') {
            try {
                $staffLink = Database::get()->one(
                    "SELECT id_staff FROM staff WHERE id_user = ? AND status = 'Đang làm' LIMIT 1",
                    [(int) $dbUser['id_user']]
                );
                $staffIsActive = (bool) $staffLink;
            } catch (Throwable $e) {
                error_log('[Auth] Không thể xác minh hồ sơ nhân viên: ' . $e->getMessage());
                $staffIsActive = false;
            }
        }
        if (!$dbUser
            || trim((string) ($dbUser['status'] ?? '')) !== 'Hoạt động'
            || ($dbUser['role'] ?? null) !== $sessionRole
            || !$staffIsActive) {
            // Không tự cập nhật vai trò mới vào session: buộc đăng nhập lại
            // sau khi Admin thay đổi vai trò để tránh giữ quyền cũ/quyền mới.
            self::invalidateAuthentication();
            return false;
        }

        // Đồng bộ tên hiển thị mới nhất, nhưng giữ nguyên id và role đã xác minh.
        $_SESSION['user'] = [
            'id'        => (int) $dbUser['id_user'],
            'username'  => $dbUser['username'],
            'full_name' => $dbUser['full_name'],
            'role'      => $dbUser['role'],
        ];

        self::$sessionValid = true;
        return true;
    }

    public static function id() {
        return self::check() ? (int) $_SESSION['user']['id'] : 0;
    }

    public static function role() {
        return self::check() ? ($_SESSION['user']['role'] ?? null) : null;
    }

    public static function is(...$roles) {
        return in_array(self::role(), $roles, true);
    }

    public static function isManager() {
        return self::is('admin', 'chutro');
    }

    // Hủy riêng thông tin xác thực, giữ CSRF/flash để form đăng nhập tiếp tục hoạt động.
    private static function invalidateAuthentication() {
        unset($_SESSION['user']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$sessionValidated = true;
        self::$sessionValid = false;
    }

    // ---- Đăng nhập ----
    public static function login(array $u) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'        => (int) $u['id_user'],
            'username'  => $u['username'],
            'full_name' => $u['full_name'],
            'role'      => $u['role'],
        ];

        // Tài khoản vừa được User::login() xác minh bằng mật khẩu và trạng thái.
        self::$sessionValidated = true;
        self::$sessionValid = true;

        // Giữ tương thích với phiên bản có hàm ghi nhật ký đăng nhập.
        if (function_exists('write_log')) {
            write_log('Đăng nhập vào hệ thống', 'AUTH');
        }
    }

    // ---- Đăng xuất thủ công ----
    public static function logout() {
        if (self::check() && function_exists('write_log')) {
            write_log('Đăng xuất khỏi hệ thống', 'AUTH');
        }
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$sessionValidated = true;
        self::$sessionValid = false;
    }

    // ---- Kiểm tra đăng nhập và phân quyền ----
    public static function guard(?array $roles = null) {
        if (!self::check()) {
            redirect('login.php');
        }

        if ($roles !== null && !self::is(...$roles)) {
            http_response_code(403);
            die(
                '<!DOCTYPE html><html lang="vi"><meta charset="UTF-8">'
                . '<body style="font-family:sans-serif;padding:40px">'
                . '<h2>403 - Bạn không có quyền truy cập chức năng này</h2>'
                . '<p><a href="' . h(url('home.php')) . '">&larr; Về trang chủ</a></p>'
                . '</body></html>'
            );
        }
    }
}
