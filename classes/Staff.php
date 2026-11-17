<?php
// classes/Staff.php - Lớp Nhân viên kỹ thuật
class Staff extends BaseModel {
    protected $table = 'staff';
    protected $pk = 'id_staff';
    protected $fields = ['staff_name', 'specialty', 'phone', 'email', 'status', 'id_user'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    public function working() { return $this->db->query("SELECT * FROM staff WHERE status = 'Đang làm' ORDER BY staff_name"); }
    public function findByUser($idUser) {
    $idUser = (int) $idUser;

    if ($idUser <= 0) {
        return null;
    }

    return $this->db->one(
            "SELECT * FROM staff
            WHERE id_user = ? AND status = 'Đang làm'
            ORDER BY id_staff DESC
            LIMIT 1",
            [$idUser]
        );
    }

    // Nhân viên tiếp nhận một yêu cầu sửa chữa
    public function receiveRequest($idRequest, $idStaff) { return (new RepairRequest())->accept($idRequest, $idStaff); }

    // Nhân viên cập nhật kết quả công việc được phân công.
    public function updateTaskStatus($idRequest, $status, $note = '', $idStaff = 0) {
        if (!Auth::is('nhanvien')) {
            return 'Chỉ nhân viên kỹ thuật mới được cập nhật kết quả sửa chữa';
        }

        // Không tin id_staff gửi từ trình duyệt; luôn lấy từ phiên đăng nhập.
        $me = $this->findByUser(Auth::id());
        if (!$me) {
            return 'Tài khoản chưa được liên kết với nhân viên kỹ thuật đang làm việc';
        }

        return (new RepairRequest())->updateStatus(
            $idRequest,
            $status,
            $note,
            (int) $me['id_staff']
        );
    }

    // Lên lịch bảo trì định kỳ cho một thiết bị (tạo yêu cầu mức độ thấp giao cho nhân viên)
    public function scheduleMaintenance($idDevice, $idStaff, $idUser) {
        $rq = new RepairRequest();
        $id = $rq->create($idDevice, $idUser, 'Bảo trì định kỳ', 'Thấp');
        $rq->accept($id, $idStaff);
        return $id;
    }
}
