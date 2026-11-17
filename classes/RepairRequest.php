<?php
// classes/RepairRequest.php - Yêu cầu sửa chữa thiết bị (người thuê báo lỗi -> nhân viên tiếp nhận -> xử lý)
class RepairRequest extends BaseModel {
    protected $table = 'repair_request';
    protected $pk = 'id_request';
    protected $fields = ['id_device', 'id_user', 'description', 'priority', 'status', 'id_staff', 'result_note'];

    const ST_PENDING     = 'Chờ tiếp nhận';
    const ST_REPAIRING   = 'Đang sửa';
    const ST_REPAIRED    = 'Đã sửa';
    const ST_REPLACE_NEW = 'Cần thay mới';
    const STATUSES = [
        self::ST_PENDING,
        self::ST_REPAIRING,
        self::ST_REPAIRED,
        self::ST_REPLACE_NEW
    ];

    const NOTE_MAX_LENGTH = 500;

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) {
        $this->updateRow($id, $d);
        $this->db->exec("UPDATE repair_request SET updated_at = NOW() WHERE id_request = ?", [(int) $id]);
        return $id;
    }

    // Báo lỗi thiết bị: tạo yêu cầu và đánh dấu thiết bị hỏng
    public function create($idDevice, $idUser, $description, $priority = 'Trung bình') {
        if (!in_array($priority, ['Thấp', 'Trung bình', 'Cao'], true)) { $priority = 'Trung bình'; }
        $this->db->begin();
        try {
            $id = $this->insert(['id_device' => (int) $idDevice, 'id_user' => (int) $idUser, 'description' => $description,
                                 'priority' => $priority, 'status' => 'Chờ tiếp nhận']);
            (new Device())->updateDeviceStatus($idDevice, 'Hỏng');
            $this->db->commit();
            return $id;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    // Nhân viên tiếp nhận yêu cầu
    public function accept($id, $idStaff) {

        if (!Auth::isManager()) {
            return 'Bạn không có quyền phân công yêu cầu sửa chữa';
        }

        $id = (int) $id;
        $idStaff = (int) $idStaff;

        if ($id <= 0 || $idStaff <= 0) {
            return 'Dữ liệu không hợp lệ';
        }

        $this->db->begin();

        try {
            // Khóa yêu cầu để tránh phân công trùng
            $r = $this->db->one(
                "SELECT * FROM repair_request
                WHERE id_request = ?
                FOR UPDATE",
                [$id]
            );

            if (!$r || $r['status'] !== self::ST_PENDING) {
                $this->db->rollback();
                return 'Yêu cầu không còn ở trạng thái chờ tiếp nhận';
            }

            // Chỉ phân công nhân viên đang làm việc
            $staff = $this->db->one(
                "SELECT id_staff FROM staff
                WHERE id_staff = ? AND status = 'Đang làm'
                FOR UPDATE",
                [$idStaff]
            );

            if (!$staff) {
                $this->db->rollback();
                return 'Nhân viên không tồn tại hoặc đã nghỉ việc';
            }

            $this->update($id, [
                'status' => self::ST_REPAIRING,
                'id_staff' => $idStaff
            ]);

            (new Device())->updateDeviceStatus(
                $r['id_device'],
                'Đang sửa'
            );

            $this->db->commit();

            error_log(sprintf(
                '[Audit][RepairRequest] user=%d phan cong request=%d cho staff=%d',
                (int) Auth::id(),
                $id,
                $idStaff
            ));

            return true;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Cập nhật kết quả xử lý. Chỉ nhân viên được phân công mới được kết thúc yêu cầu.
    public function updateStatus($idRequest, $status, $note = '', $idStaff = 0) {
        if (!Auth::is('nhanvien')) {
            return 'Chỉ nhân viên kỹ thuật mới được cập nhật kết quả sửa chữa';
        }

        $idRequest = (int) $idRequest;
        $idStaff = (int) $idStaff;
        $note = trim((string) $note);

        if ($idRequest <= 0) {
            return 'Mã yêu cầu sửa chữa không hợp lệ';
        }

        // Xác định nhân viên từ phiên đăng nhập, không nhận mã nhân viên do client gửi lên.
        $staff = (new Staff())->findByUser(Auth::id());
        $idStaff = $staff ? (int) $staff['id_staff'] : 0;
        if ($idStaff <= 0) {
            return 'Tài khoản chưa được liên kết với nhân viên kỹ thuật đang làm việc';
        }
        if (!in_array($status, [self::ST_REPAIRED, self::ST_REPLACE_NEW], true)) {
            return 'Kết quả xử lý không hợp lệ';
        }
        if ((function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note)) > self::NOTE_MAX_LENGTH) {
            return 'Ghi chú xử lý không được vượt quá ' . self::NOTE_MAX_LENGTH . ' ký tự';
        }

        $this->db->begin();
        try {
            $r = $this->db->one(
                "SELECT * FROM repair_request WHERE id_request = ? FOR UPDATE",
                [$idRequest]
            );

            if (!$r) {
                $this->db->rollback();
                return 'Không tìm thấy yêu cầu sửa chữa';
            }
            if ((int) $r['id_staff'] !== $idStaff) {
                $this->db->rollback();
                return 'Bạn không được phân công yêu cầu sửa chữa này';
            }
            if ($r['status'] !== self::ST_REPAIRING) {
                $this->db->rollback();
                return 'Yêu cầu không còn ở trạng thái đang sửa';
            }

            $this->update($idRequest, [
                'status' => $status,
                'result_note' => $note
            ]);

            $device = new Device();
            if ($status === self::ST_REPAIRED) {
                $device->maintainDevice((int) $r['id_device']);
            } else {
                $device->updateDeviceStatus((int) $r['id_device'], 'Hỏng');
            }

            $this->db->commit();
            error_log(sprintf('[Audit][RepairRequest] user=%d cap_nhat request=%d status=%s', (int) Auth::id(), $idRequest, $status));
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function search(
    $status = '',
    $idUser = 0,
    $idStaff = null,
    $limit = 200,
    $offset = 0
) {
    $allowedStatuses = [
        '',
        'open',
        self::ST_PENDING,
        self::ST_REPAIRING,
        self::ST_REPLACE_NEW,
        self::ST_REPAIRED
    ];

    // Trạng thái không hợp lệ thì không trả về dữ liệu
    if (!in_array($status, $allowedStatuses, true)) {
        return [];
    }

    $sql = "SELECT q.*, d.device_name, r.room_number,
                   u.full_name AS reporter, s.staff_name
            FROM repair_request q
            JOIN device d ON d.id_device = q.id_device
            LEFT JOIN room r ON r.id_room = d.id_room
            JOIN `user` u ON u.id_user = q.id_user
            LEFT JOIN staff s ON s.id_staff = q.id_staff
            WHERE 1=1";

    $p = [];

    if ($status === 'open') {
        $sql .= " AND q.status IN (?, ?)";
        $p[] = self::ST_PENDING;
        $p[] = self::ST_REPAIRING;

    } elseif ($status !== '') {
        $sql .= " AND q.status = ?";
        $p[] = $status;
    }

    if ((int) $idUser > 0) {
        $sql .= " AND q.id_user = ?";
        $p[] = (int) $idUser;
    }

    // Lọc theo nhân viên được phân công
    if ($idStaff !== null) {
        $sql .= " AND q.id_staff = ?";
        $p[] = (int) $idStaff;
    }

    // Giới hạn số bản ghi trả về
    $limit = max(1, min(500, (int) $limit));
    $offset = max(0, (int) $offset);

    $sql .= " ORDER BY
              FIELD(q.status,'Chờ tiếp nhận','Đang sửa','Cần thay mới','Đã sửa'),
              FIELD(q.priority,'Cao','Trung bình','Thấp'),
              q.created_at DESC
              LIMIT {$limit} OFFSET {$offset}";

    return $this->db->query($sql, $p);
}

    public function countOpen() {
        return (int) $this->db->value("SELECT COUNT(*) FROM repair_request WHERE status IN ('Chờ tiếp nhận','Đang sửa')");
    }
}
