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
        if (!empty($d['username']) && $this->usernameExists((string) $d['username'])) {
            throw new RuntimeException('Tên đăng nhập đã tồn tại hoặc đã được thu hồi, không thể sử dụng lại.');
        }
        $d['password'] = password_hash($d['password'], PASSWORD_DEFAULT);
        return $this->insertRow($d);
    }

    public function update($id, array $d) {
        // Không cho đổi tên đăng nhập sang tên đã bị thu hồi.
        if (isset($d['username']) && $this->usernameExists((string) $d['username'], (int) $id)) {
            throw new RuntimeException('Tên đăng nhập đã tồn tại hoặc đã được thu hồi, không thể sử dụng lại.');
        }
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

    // Tên đăng nhập trong danh sách thu hồi được giữ chỗ vĩnh viễn sau khi xóa tài khoản,
    // tránh người khác đăng ký lại cùng username và giả danh tài khoản cũ.
    public function usernameExists($username, $exceptId = 0) {
        $inUse = (bool) $this->db->value(
            "SELECT COUNT(*) FROM `user` WHERE username = ? AND id_user <> ?",
            [(string) $username, (int) $exceptId]
        );
        if ($inUse) { return true; }
        return (bool) $this->db->value(
            "SELECT COUNT(*) FROM deleted_username WHERE username = ?",
            [(string) $username]
        );
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

           // Vai trò hệ thống cố định: không được đặt lại mật khẩu trực tiếp qua web, chỉ tự đổi mật khẩu
    // (cần mật khẩu hiện tại) tại trang "Tài khoản của tôi".
    public function canResetPassword($id) {
        $u = $this->find((int) $id);
        return $u && !in_array($u['role'], ['admin', 'chutro'], true);
    }

    // Đặt lại mật khẩu bằng bộ sinh số ngẫu nhiên mật mã an toàn.
    // Trả về false nếu tài khoản không tồn tại hoặc là Admin/Chủ trọ.
    public function resetPassword($id) {
        if (!$this->canResetPassword($id)) { return false; }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $pw = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 12; $i++) {
            $pw .= $alphabet[random_int(0, $max)];
        }
        $this->update($id, ['password' => $pw]);
        return $pw;
    }
    // ---- Tài khoản người thuê mới đăng ký, chưa được gắn phòng (thông báo cho Admin) ----
    public function pendingTenants() {
        return $this->db->query("SELECT u.id_user, u.username, u.full_name, u.recovery_phone, u.recovery_identity_card, u.created_at
            FROM `user` u LEFT JOIN renter t ON t.id_user = u.id_user
            WHERE u.role = 'nguoithue' AND u.status = 'Hoạt động' AND t.id_renter IS NULL
            ORDER BY u.created_at DESC, u.id_user DESC");
    }

    public function pendingTenantCount() {
        return (int) $this->db->value("SELECT COUNT(*) FROM `user` u LEFT JOIN renter t ON t.id_user = u.id_user
            WHERE u.role = 'nguoithue' AND u.status = 'Hoạt động' AND t.id_renter IS NULL");
    }

    // Admin xóa tài khoản người thuê khỏi bảng user và hồ sơ renter.
    // Username được lưu vào deleted_username để chặn đăng nhập cũ và ngăn đăng ký lại tên đã thu hồi.
    // Chỉ xóa sau khi người thuê đã trả phòng và không còn yêu cầu sửa chữa đang mở.
    public function deleteTenantAccount($id) {
        $id = (int) $id;
        if ($id <= 0) { return 'ID tài khoản không hợp lệ'; }
        if ($id === (int) Auth::id()) { return 'Không thể tự xóa tài khoản đang đăng nhập'; }

        $u = $this->find($id);
        if (!$u) { return 'Không tìm thấy tài khoản'; }
        if ($u['role'] !== 'nguoithue') { return 'Chỉ được xóa tài khoản Người thuê'; }

        // Bảo đảm database đã chạy migration lưu tên đăng nhập bị thu hồi.
        $hasRegistry = (int) $this->db->value(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deleted_username'"
        );
        if (!$hasRegistry) {
            return 'Cơ sở dữ liệu chưa có bảng deleted_username. Hãy chạy database/migration_v67_deleted_username.sql trước khi xóa tài khoản.';
        }

        $renter = $this->db->one("SELECT t.* FROM renter t WHERE t.id_user = ?", [$id]);
        if ($renter && ($renter['end_date'] === null || $renter['end_date'] > date('Y-m-d'))) {
            return 'Người thuê vẫn đang thuê phòng. Hãy làm thủ tục trả phòng trước khi xóa tài khoản';
        }

        // Không xóa nhầm một user vẫn đang được dùng làm tài khoản nhân viên kỹ thuật.
        $staffLinks = (int) $this->db->value("SELECT COUNT(*) FROM staff WHERE id_user = ?", [$id]);
        if ($staffLinks > 0) {
            return 'Tài khoản đang được liên kết với hồ sơ nhân viên kỹ thuật. Hãy gỡ liên kết trước khi xóa.';
        }

        $open = (int) $this->db->value(
            "SELECT COUNT(*) FROM repair_request WHERE id_user = ? AND status IN ('Chờ tiếp nhận','Đang sửa')",
            [$id]
        );
        if ($open > 0) {
            return 'Tài khoản còn yêu cầu sửa chữa chưa xử lý xong, hãy xử lý xong rồi mới xóa';
        }

        $this->db->begin();
        try {
            // Ghi tên đăng nhập vào sổ thu hồi trước khi xóa bản ghi tài khoản.
            $this->db->exec(
                "INSERT INTO deleted_username (username, original_user_id, deleted_by, deleted_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE original_user_id = VALUES(original_user_id), deleted_by = VALUES(deleted_by), deleted_at = VALUES(deleted_at)",
                [$u['username'], $id, (int) Auth::id() ?: null]
            );

            // Xóa các yêu cầu đã đóng để không vi phạm khóa ngoại id_user.
            $this->db->exec("DELETE FROM repair_request WHERE id_user = ?", [$id]);
            if ($renter) {
                $this->db->exec("DELETE FROM renter WHERE id_renter = ?", [(int) $renter['id_renter']]);
            }

            $deleted = $this->db->exec("DELETE FROM `user` WHERE id_user = ? AND role = 'nguoithue'", [$id]);
            if ($deleted !== 1) {
                throw new RuntimeException('Không xóa được bản ghi tài khoản. Giao dịch đã được hoàn tác.');
            }
            $stillExists = (int) $this->db->value("SELECT COUNT(*) FROM `user` WHERE id_user = ?", [$id]);
            if ($stillExists !== 0) {
                throw new RuntimeException('Tài khoản vẫn còn trong cơ sở dữ liệu. Giao dịch đã được hoàn tác.');
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        // Chỉ xóa ảnh CCCD sau khi transaction database đã commit thành công.
        if ($renter && !empty($renter['id_card_image'])) {
            $f = UPLOAD_DIR . basename($renter['id_card_image']);
            if (is_file($f)) { @unlink($f); }
        }
        return true;
    }
    public function search($kw = '', $role = '') {
        $sql = "SELECT * FROM `user` WHERE 1=1";
        $p = [];
        if ($kw !== '') { $sql .= " AND (username LIKE ? OR full_name LIKE ?)"; $p[] = "%$kw%"; $p[] = "%$kw%"; }
        if ($role !== '') { $sql .= " AND role = ?"; $p[] = $role; }
        return $this->db->query($sql . " ORDER BY FIELD(role,'admin','chutro','nhanvien','nguoithue'), username", $p);
    }
}
