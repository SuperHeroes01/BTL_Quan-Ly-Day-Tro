<?php
// classes/Staff.php - Lớp Nhân viên kỹ thuật
class Staff extends BaseModel {
    protected $table = 'staff';
    protected $pk = 'id_staff';
    protected $fields = ['staff_name', 'specialty', 'phone', 'email', 'status', 'id_user'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    public function working() { return $this->db->query("SELECT * FROM staff WHERE status = 'Đang làm' ORDER BY staff_name"); }
    public function findByUser($idUser) { return $this->db->one("SELECT * FROM staff WHERE id_user = ?", [(int) $idUser]); }

    // Nhân viên tiếp nhận một yêu cầu sửa chữa
    public function receiveRequest($idRequest, $idStaff) { return (new RepairRequest())->accept($idRequest, $idStaff); }

    // Nhân viên cập nhật tiến độ công việc
    public function updateTaskStatus($idRequest, $status, $note = '') { return (new RepairRequest())->updateStatus($idRequest, $status, $note); }

    // Lên lịch bảo trì định kỳ cho một thiết bị (tạo yêu cầu mức độ thấp giao cho nhân viên)
    public function scheduleMaintenance($idDevice, $idStaff, $idUser) {
        $rq = new RepairRequest();
        $id = $rq->create($idDevice, $idUser, 'Bảo trì định kỳ', 'Thấp');
        $rq->accept($id, $idStaff);
        return $id;
    }
}
