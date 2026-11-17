<?php
// classes/Room.php - Lớp Phòng trọ
class Room extends BaseModel {
    protected $table = 'room';
    protected $pk = 'id_room';
    protected $fields = ['room_number', 'area', 'price', 'status', 'description', 'amenities', 'image_path'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    public function addRoom(array $d)          { return $this->insert($d); }
    public function editRoom($id, array $d)    { return $this->update($id, $d); }
    public function updateStatus($id, $status) { return $this->update($id, ['status' => $status]); }

       // Tự cập nhật trạng thái theo số người đang thuê.
    // Phòng đang "Bảo trì" được giữ nguyên (không bị ghi đè khi trả phòng/gia hạn/sửa người thuê);
    // chỉ khi $force = true (kết thúc bảo trì) mới tính lại theo người đang ở.
    public function refreshStatus($idRoom, $force = false) {
        $room = $this->find((int) $idRoom);
        if (!$room) { return; }
        if ($room['status'] === 'Bảo trì' && !$force) { return; }
        $n = (int) $this->db->value("SELECT COUNT(*) FROM renter t WHERE t.id_room = ? AND " . Renter::ACTIVE, [(int) $idRoom]);
        $this->updateStatus($idRoom, $n > 0 ? 'Đã thuê' : 'Trống');
    }

    public function activeRenterCount($idRoom) {
        return (int) $this->db->value("SELECT COUNT(*) FROM renter t WHERE t.id_room = ? AND " . Renter::ACTIVE, [(int) $idRoom]);
    }

    public function searchRoom($kw = '', $status = '') {
        $sql = "SELECT r.*,
                  (SELECT COUNT(*) FROM renter t WHERE t.id_room = r.id_room AND " . Renter::ACTIVE . ") AS occupants,
                  (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM renter t JOIN `user` u ON u.id_user = t.id_user
                    WHERE t.id_room = r.id_room AND " . Renter::ACTIVE . ") AS tenant_names
                FROM room r WHERE 1=1";
        $p = [];
        if ($kw !== '')     { $sql .= " AND r.room_number LIKE ?"; $p[] = "%$kw%"; }
        if ($status !== '') { $sql .= " AND r.status = ?"; $p[] = $status; }
        return $this->db->query($sql . " ORDER BY r.room_number", $p);
    }

    // Danh sách phòng trống có kèm ảnh đại diện từ thư viện nhiều ảnh.
    public function vacant($kw = '', $maxPrice = 0) {
        $sql = "SELECT r.*,
                  (SELECT ri.id_image FROM room_image ri WHERE ri.id_room = r.id_room ORDER BY ri.is_cover DESC, ri.sort_order, ri.id_image LIMIT 1) AS cover_image_id,
                  (SELECT ri.file_path FROM room_image ri WHERE ri.id_room = r.id_room ORDER BY ri.is_cover DESC, ri.sort_order, ri.id_image LIMIT 1) AS cover_image_path,
                  (SELECT COUNT(*) FROM room_image ri WHERE ri.id_room = r.id_room) AS photo_count
                FROM room r WHERE r.status = 'Trống'";
        $p = [];
        if ($kw !== '')      { $sql .= " AND r.room_number LIKE ?"; $p[] = "%$kw%"; }
        if ($maxPrice > 0)   { $sql .= " AND r.price <= ?"; $p[] = (float) $maxPrice; }
        return $this->db->query($sql . " ORDER BY r.price, r.room_number", $p);
    }

    public function images($idRoom) {
        return $this->db->query("SELECT * FROM room_image WHERE id_room = ? ORDER BY is_cover DESC, sort_order ASC, id_image ASC", [(int) $idRoom]);
    }

    public function coverImage($idRoom) {
        return $this->db->one("SELECT * FROM room_image WHERE id_room = ? ORDER BY is_cover DESC, sort_order ASC, id_image ASC LIMIT 1", [(int) $idRoom]);
    }

    public function syncLegacyCover($idRoom) {
        $cover = $this->coverImage($idRoom);
        $this->db->exec("UPDATE room SET image_path = ? WHERE id_room = ?", [$cover ? $cover['file_path'] : null, (int) $idRoom]);
    }

    public function stats() {
        $r = $this->db->one("SELECT COUNT(*) AS total, SUM(status='Đã thuê') AS rented, SUM(status='Trống') AS vacant FROM room");
        return ['total' => (int) $r['total'], 'rented' => (int) $r['rented'], 'vacant' => (int) $r['vacant']];
    }

    public function numberExists($number, $exceptId = 0) {
        return (bool) $this->db->value("SELECT COUNT(*) FROM room WHERE room_number = ? AND id_room <> ?", [$number, (int) $exceptId]);
    }

    // Chỉ xóa được phòng chưa từng có người thuê, hóa đơn
    public function deleteRoom($id) {
        $used = (int) $this->db->value("SELECT (SELECT COUNT(*) FROM renter WHERE id_room = ?) + (SELECT COUNT(*) FROM bill WHERE id_room = ?)", [(int) $id, (int) $id]);
        if ($used > 0) { return 'Phòng đã có người thuê hoặc hóa đơn, không thể xóa'; }
        $this->db->exec("UPDATE device SET id_room = NULL WHERE id_room = ?", [(int) $id]);
        $this->delete($id);
        return true;
    }
}
