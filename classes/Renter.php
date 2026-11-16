<?php
// classes/Renter.php - Lớp Người thuê (gắn với một tài khoản User và một phòng)
class Renter extends BaseModel {
    // Điều kiện "đang thuê" (dùng với bí danh bảng t): chưa có ngày trả phòng hoặc ngày trả phòng ở tương lai
    const ACTIVE = "(t.end_date IS NULL OR t.end_date > CURDATE())";

    protected $table = 'renter';
    protected $pk = 'id_renter';
    protected $fields = ['id_user', 'id_room', 'identity_card', 'birth_date', 'registered_address', 'identity_issue_date', 'identity_issue_place', 'phone', 'hometown', 'payment_method', 'start_date',
                         'end_date', 'deposit', 'id_card_image', 'residence_status', 'rent_price'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    // Tạo tài khoản + hồ sơ người thuê trong một giao dịch
    public function register(array $account, array $info) {
        $this->db->begin();
        try {
            $room = (new Room())->find((int) $info['id_room']);
            $info['rent_price'] = (float) ($info['rent_price'] ?? ($room['price'] ?? 0));
            $info['id_user'] = (new User())->insert([
                'username' => $account['username'], 'password' => $account['password'],
                'full_name' => $account['full_name'], 'role' => 'nguoithue', 'status' => 'Hoạt động',
                'recovery_phone' => $info['phone'], 'recovery_identity_card' => $info['identity_card'],
            ]);
            $id = $this->insert($info);
            (new Room())->refreshStatus((int) $info['id_room']);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Gắn một tài khoản người thuê đã tự đăng ký vào phòng do Admin/Chủ trọ phân công.
    public function registerExisting($idUser, array $info, $fullName) {
        $this->db->begin();
        try {
            $room = (new Room())->find((int) $info['id_room']);
            if (!$room) { throw new RuntimeException('Không tìm thấy phòng được chọn'); }
            (new User())->update((int) $idUser, [
                'full_name' => $fullName, 'recovery_phone' => $info['phone'],
                'recovery_identity_card' => $info['identity_card'],
            ]);
            $info['id_user'] = (int) $idUser;
            $info['rent_price'] = (float) $room['price'];
            $id = $this->insert($info);
            (new Room())->refreshStatus((int) $info['id_room']);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function listAll($kw = '', $onlyActive = false) {
        $sql = "SELECT t.*, u.full_name, u.username, r.room_number, r.price, r.area, r.description
                FROM renter t JOIN `user` u ON u.id_user = t.id_user JOIN room r ON r.id_room = t.id_room WHERE 1=1";
        $p = [];
        if ($onlyActive) { $sql .= " AND " . self::ACTIVE; }
        if ($kw !== '') {
            $sql .= " AND (u.full_name LIKE ? OR r.room_number LIKE ? OR t.phone LIKE ? OR t.identity_card LIKE ?)";
            array_push($p, "%$kw%", "%$kw%", "%$kw%", "%$kw%");
        }
        return $this->db->query($sql . " ORDER BY (" . self::ACTIVE . ") DESC, r.room_number, u.full_name", $p);
    }

    public function findFull($id) {
        return $this->db->one("SELECT t.*, u.full_name, u.username, r.room_number, r.price, r.area, r.description, r.image_path
                FROM renter t JOIN `user` u ON u.id_user = t.id_user JOIN room r ON r.id_room = t.id_room
                WHERE t.id_renter = ?", [(int) $id]);
    }

    public function findByUser($idUser) {
        return $this->db->one("SELECT t.*, u.full_name, r.room_number, r.price, r.area, r.description, r.image_path
                FROM renter t JOIN `user` u ON u.id_user = t.id_user JOIN room r ON r.id_room = t.id_room
                WHERE t.id_user = ?", [(int) $idUser]);
    }

    // Thông tin phòng đang ở + thiết bị trong phòng
    public function viewRoomInfo($idUser) {
        $r = $this->findByUser($idUser);
        if (!$r) { return null; }
        $r['devices'] = (new Device())->forRoom((int) $r['id_room']);
        return $r;
    }

    public function viewBill($idUser) {
        $r = $this->findByUser($idUser);
        return $r ? (new Bill())->search('', '', '', (int) $r['id_room']) : [];
    }

    // Người thuê báo hỏng thiết bị trong phòng của mình
    public function reportIssue($idUser, $idDevice, $desc, $priority = 'Trung bình') {
        $r = $this->findByUser($idUser);
        $dev = (new Device())->find($idDevice);
        if (!$r || !$dev || (int) $dev['id_room'] !== (int) $r['id_room']) { return 'Thiết bị không thuộc phòng của bạn'; }
        if ($desc === '') { return 'Vui lòng mô tả lỗi'; }
        (new RepairRequest())->create((int) $idDevice, (int) $idUser, $desc, $priority);
        return true;
    }

    public function updateContact($id, $phone, $hometown) {
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) { return 'Số điện thoại phải gồm 9-11 chữ số'; }
        $this->update($id, ['phone' => $phone, 'hometown' => $hometown]);
        return true;
    }

    // Trả phòng: đặt ngày kết thúc và cập nhật trạng thái phòng
    public function checkout($id, $date = null) {
        $r = $this->find($id);
        if (!$r) { return false; }
        $this->update($id, ['end_date' => $date ?: date('Y-m-d')]);
        (new Room())->refreshStatus((int) $r['id_room']);
        return true;
    }

    public function renew($id, $newEnd) {
        $r = $this->find($id);
        if (!$r || !valid_date($newEnd) || $newEnd <= $r['start_date']) { return 'Ngày kết thúc mới không hợp lệ'; }
        $this->update($id, ['end_date' => $newEnd]);
        (new Room())->refreshStatus((int) $r['id_room']);
        return true;
    }

    public static function contractStatus(array $r) {
        if ($r['end_date'] !== null && $r['end_date'] <= date('Y-m-d')) { return 'Đã kết thúc'; }
        if ($r['end_date'] !== null && $r['end_date'] <= date('Y-m-d', strtotime('+30 days'))) { return 'Sắp hết hạn'; }
        return 'Hiệu lực';
    }

    public function countActive() {
        return (int) $this->db->value("SELECT COUNT(*) FROM renter t WHERE " . self::ACTIVE);
    }

    // Tải ảnh CCCD để đăng ký tạm trú. Trả về true hoặc chuỗi báo lỗi
    public function uploadIdCard($idRenter, array $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return 'Vui lòng chọn ảnh CCCD (tối đa 3MB)'; }
        if ($file['size'] > 3 * 1024 * 1024) { return 'Ảnh không được lớn hơn 3MB'; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (!$ext) { return 'Chỉ chấp nhận ảnh JPG hoặc PNG'; }
        if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0755, true); }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) { return 'Không lưu được tệp tải lên'; }
        $old = $this->find($idRenter);
        if ($old && $old['id_card_image'] && is_file(UPLOAD_DIR . $old['id_card_image'])) { @unlink(UPLOAD_DIR . $old['id_card_image']); }
        $this->update($idRenter, ['id_card_image' => $name, 'residence_status' => 'Chờ xử lý']);
        return true;
    }

    public function setResidenceStatus($id, $status) {
        if (!in_array($status, ['Chưa đăng ký', 'Chờ xử lý', 'Đã đăng ký'], true)) { return false; }
        $this->update($id, ['residence_status' => $status]);
        return true;
    }
}
