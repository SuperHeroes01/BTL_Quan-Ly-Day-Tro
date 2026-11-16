<?php
// classes/Device.php - Lớp Thiết bị (điều hòa, máy giặt, router, bình nóng lạnh...)
class Device extends BaseModel {
    protected $table = 'device';
    protected $pk = 'id_device';
    protected $fields = ['device_name', 'id_room', 'status', 'last_maintenance', 'quantity', 'note'];
    const STATUSES = ['Hoạt động', 'Hỏng', 'Đang sửa'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    public function addDevice(array $d)  { return $this->insert($d); }

    public function assignToRoom($id, $idRoom) {
        $this->update($id, ['id_room' => $idRoom ? (int) $idRoom : null]);
        return true;
    }

    public function updateDeviceStatus($id, $status) {
        if (!in_array($status, self::STATUSES, true)) { return false; }
        $this->update($id, ['status' => $status]);
        return true;
    }

    // Ghi nhận đã bảo trì: cập nhật ngày bảo trì gần nhất và đưa thiết bị về hoạt động
    public function maintainDevice($id, $date = null) {
        $this->update($id, ['last_maintenance' => $date ?: date('Y-m-d'), 'status' => 'Hoạt động']);
        return true;
    }

    public function search($kw = '', $status = '', $idRoom = 0) {
        $sql = "SELECT d.*, r.room_number FROM device d LEFT JOIN room r ON r.id_room = d.id_room WHERE 1=1";
        $p = [];
        if ($kw !== '')     { $sql .= " AND d.device_name LIKE ?"; $p[] = "%$kw%"; }
        if ($status !== '') { $sql .= " AND d.status = ?"; $p[] = $status; }
        if ($idRoom > 0)    { $sql .= " AND d.id_room = ?"; $p[] = (int) $idRoom; }
        return $this->db->query($sql . " ORDER BY r.room_number IS NULL, r.room_number, d.device_name", $p);
    }

    public function forRoom($idRoom) { return $this->search('', '', (int) $idRoom); }

    public function countByStatus($status) {
        return (int) $this->db->value("SELECT COUNT(*) FROM device WHERE status = ?", [$status]);
    }
}
