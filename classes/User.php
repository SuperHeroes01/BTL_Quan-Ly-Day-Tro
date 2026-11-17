<?php
// classes/User.php - Lớp Người dùng: đăng nhập, đổi mật khẩu, cập nhật hồ sơ, phân quyền
class User extends BaseModel {
    protected $table = 'user';
    protected $pk = 'id_user';
    protected $fields = ['username', 'password', 'role', 'full_name', 'status', 'recovery_phone', 'recovery_identity_card'];

    public function insert(array $d) {
        // Vai trò Admin và Chủ trọ là vai trò hệ thống cố định; chỉ cấu hình trong mã nguồn/SQL triển khai.
        if (in_array(($d['role'] ?? 'nguoithue'), ['admin', 'chutro'], true)) {
            throw new RuntimeException('Không thể tạo vai trò Admin hoặc Chủ trọ từ giao diện hệ thống.');
        }
        $d['password'] = password_hash($d['password'], PASSWORD_DEFAULT);
        return $this->insertRow($d);
    }

    public function update($id, array $d) {
        // Bảo vệ vai trò hệ thống ở tầng model, kể cả khi có POST tự tạo ngoài giao diện.
        if (array_key_exists('role', $d)) {
            $current = $this->find((int) $id);
            if (!$current) { throw new RuntimeException('Không tìm thấy tài khoản cần cập nhật.'); }
            $fixedRoles = ['admin', 'chutro'];
            if (in_array($current['role'], $fixedRoles, true) && $d['role'] !== $current['role']) {
                throw new RuntimeException('Vai trò Admin và Chủ trọ là cố định, chỉ được thay đổi trong mã nguồn.');
            }
            if (!in_array($current['role'], $fixedRoles, true) && in_array($d['role'], $fixedRoles, true)) {
                throw new RuntimeException('Không thể gán vai trò Admin hoặc Chủ trọ từ giao diện hệ thống.');
            }
        }
        if (isset($d['password']) && $d['password'] !== '') {
            $d['password'] = password_hash($d['password'], PASSWORD_DEFAULT);
        } else {
            unset($d['password']);
        }
        return $this->updateRow($id, $d);
    }

    // Trả về ['ok'=>bool, 'msg'=>..., 'user'=>...]
    public function login($username, $password) {
        $u = $this->db->one("SELECT * FROM `user` WHERE username = ?", [$username]);
        if (!$u || !password_verify($password, $u['password'])) {
            return ['ok' => false, 'msg' => 'Sai tên đăng nhập hoặc mật khẩu'];
        }
        if ($u['status'] !== 'Hoạt động') {
            return ['ok' => false, 'msg' => 'Tài khoản đã bị khóa, vui lòng liên hệ quản trị viên'];
        }
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $this->db->exec("UPDATE `user` SET password = ? WHERE id_user = ?",
                [password_hash($password, PASSWORD_DEFAULT), (int) $u['id_user']]);
        }
        return ['ok' => true, 'user' => $u];
    }

    public function logout() { Auth::logout(); }

    public function changePassword($id, $old, $new) {
        $u = $this->find($id);
        if (!$u || !password_verify($old, $u['password'])) { return 'Mật khẩu cũ không đúng'; }
        if (strlen($new) < 6) { return 'Mật khẩu mới phải có ít nhất 6 ký tự'; }
        $this->update($id, ['password' => $new]);
        return true;
    }

    // Đăng ký tài khoản người thuê tự tạo; chưa được gán phòng cho tới khi chủ trọ xác nhận.
    public function registerTenantAccount($username, $password, $fullName, $phone, $identityCard) {
        if (!preg_match('/^[a-zA-Z0-9_.]{4,30}$/', $username)) { return 'Tên đăng nhập gồm 4-30 ký tự chữ, số, dấu chấm hoặc gạch dưới'; }
        if ($this->usernameExists($username)) { return 'Tên đăng nhập đã tồn tại'; }
        if (strlen($password) < 6) { return 'Mật khẩu phải có ít nhất 6 ký tự'; }
        if ($fullName === '') { return 'Vui lòng nhập họ tên'; }
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) { return 'Số điện thoại phải gồm 9-11 chữ số'; }
        if (!preg_match('/^[0-9]{9,12}$/', $identityCard)) { return 'CCCD/CMND phải gồm 9-12 chữ số'; }
        return $this->insert([
            'username' => $username, 'password' => $password, 'role' => 'nguoithue',
            'full_name' => $fullName, 'status' => 'Hoạt động',
            'recovery_phone' => $phone, 'recovery_identity_card' => $identityCard,
        ]);
    }

    // Khôi phục mật khẩu demo: đối chiếu username + số điện thoại + CCCD đã đăng ký.
    // Triển khai thực tế cần bổ sung OTP qua SMS/email trước khi cho phép đổi mật khẩu.
    public function recoverPassword($username, $phone, $identityCard, $newPassword) {
        if (strlen($newPassword) < 6) { return 'Mật khẩu mới phải có ít nhất 6 ký tự'; }
        $u = $this->db->one("SELECT id_user FROM `user` WHERE username = ? AND recovery_phone = ? AND recovery_identity_card = ? AND role = 'nguoithue' AND status = 'Hoạt động'", [$username, $phone, $identityCard]);
        if (!$u) { return 'Thông tin xác minh không khớp. Vui lòng kiểm tra lại hoặc liên hệ Admin.'; }
        $this->update((int) $u['id_user'], ['password' => $newPassword]);
        return true;
    }

    public function updateProfile($id, $fullName) {
        if ($fullName === '') { return 'Họ tên không được để trống'; }
        $this->update($id, ['full_name' => $fullName]);
        return true;
    }

    public function usernameExists($username, $exceptId = 0) {
        return (bool) $this->db->value("SELECT COUNT(*) FROM `user` WHERE username = ? AND id_user <> ?", [$username, (int) $exceptId]);
    }

    public function setRoleStatus($id, $role, $status) {
        if (!isset(Auth::ROLES[$role]) || !in_array($status, ['Hoạt động', 'Khóa'], true)) { return false; }
        $current = $this->find((int) $id);
        if (!$current) { return false; }
        // Admin và Chủ trọ luôn giữ vai trò hiện tại; tài khoản khác không thể nhận hai vai trò này qua giao diện.
        $fixedRoles = ['admin', 'chutro'];
        if ((in_array($current['role'], $fixedRoles, true) && $role !== $current['role'])
            || (!in_array($current['role'], $fixedRoles, true) && in_array($role, $fixedRoles, true))) {
            return false;
        }
        $this->update($id, ['role' => $role, 'status' => $status]);
        return true;
    }

        /    // Đặt lại mật khẩu ngẫu nhiên, trả về mật khẩu mới để quản trị viên chuyển cho người dùng
    public function resetPassword($id) {
        $pw = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        $this->update($id, ['password' => $pw]);
        return $pw;
    }

    public function search($kw = '', $role = '') {
        $sql = "SELECT * FROM `user` WHERE 1=1";
        $p = [];
        if ($kw !== '') { $sql .= " AND (username LIKE ? OR full_name LIKE ?)"; $p[] = "%$kw%"; $p[] = "%$kw%"; }
        if ($role !== '') { $sql .= " AND role = ?"; $p[] = $role; }
        return $this->db->query($sql . " ORDER BY FIELD(role,'admin','chutro','nhanvien','nguoithue'), username", $p);
    }
}
