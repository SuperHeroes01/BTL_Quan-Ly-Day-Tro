<?php
// classes/RepairRequest.php - Yêu cầu sửa chữa thiết bị (người thuê báo lỗi -> nhân viên tiếp nhận -> xử lý)
class RepairRequest extends BaseModel {
    protected $table = 'repair_request';
    protected $pk = 'id_request';
    protected $fields = ['id_device', 'id_user', 'description', 'priority', 'status', 'id_staff', 'result_note'];
    const STATUSES = ['Chờ tiếp nhận', 'Đang sửa', 'Đã sửa', 'Cần thay mới'];

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
        $r = $this->find($id);
        if (!$r || $r['status'] !== 'Chờ tiếp nhận') { return 'Yêu cầu không còn ở trạng thái chờ tiếp nhận'; }
        $this->db->begin();
        try {
            $this->update($id, ['status' => 'Đang sửa', 'id_staff' => (int) $idStaff]);
            (new Device())->updateDeviceStatus($r['id_device'], 'Đang sửa');
            $this->db->commit();
            return true;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    // Cập nhật kết quả xử lý: Đã sửa / Cần thay mới
    public function updateStatus($id, $status, $note = '') {
        $r = $this->find($id);
        if (!$r || !in_array($status, self::STATUSES, true)) { return 'Trạng thái không hợp lệ'; }
        $this->db->begin();
        try {
            $this->update($id, ['status' => $status, 'result_note' => $note]);
            $dev = new Device();
            if ($status === 'Đã sửa') { $dev->maintainDevice($r['id_device']); }
            elseif ($status === 'Cần thay mới') { $dev->updateDeviceStatus($r['id_device'], 'Hỏng'); }
            elseif ($status === 'Đang sửa') { $dev->updateDeviceStatus($r['id_device'], 'Đang sửa'); }
            $this->db->commit();
            return true;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function search($status = '', $idUser = 0) {
        $sql = "SELECT q.*, d.device_name, r.room_number, u.full_name AS reporter, s.staff_name
                FROM repair_request q JOIN device d ON d.id_device = q.id_device
                LEFT JOIN room r ON r.id_room = d.id_room
                JOIN `user` u ON u.id_user = q.id_user LEFT JOIN staff s ON s.id_staff = q.id_staff WHERE 1=1";
        $p = [];
        if ($status === 'open') { $sql .= " AND q.status IN ('Chờ tiếp nhận','Đang sửa')"; }
        elseif ($status !== '') { $sql .= " AND q.status = ?"; $p[] = $status; }
        if ($idUser > 0) { $sql .= " AND q.id_user = ?"; $p[] = (int) $idUser; }
        return $this->db->query($sql . " ORDER BY FIELD(q.status,'Chờ tiếp nhận','Đang sửa','Cần thay mới','Đã sửa'),
                FIELD(q.priority,'Cao','Trung bình','Thấp'), q.created_at DESC", $p);
    }

    public function countOpen() {
        return (int) $this->db->value("SELECT COUNT(*) FROM repair_request WHERE status IN ('Chờ tiếp nhận','Đang sửa')");
    }
}
