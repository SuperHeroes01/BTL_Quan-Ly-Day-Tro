<?php
// classes/Service.php - Lớp Dịch vụ (điện, nước, mạng, vệ sinh, máy giặt...) và đơn giá
class Service extends BaseModel {
    protected $table = 'service';
    protected $pk = 'id_service';
    protected $fields = ['code', 'service_name', 'unit_price', 'unit', 'type', 'description', 'is_active'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    public function addService(array $d)  { return $this->insert($d); }

    public function updatePrice($id, $price) {
        if ($price < 0) { return false; }
        $this->update($id, ['unit_price' => (float) $price]);
        return true;
    }

    // Dịch vụ đã được dùng trong hóa đơn thì chỉ ẩn đi để giữ lịch sử, chưa dùng thì xóa hẳn
    public function deleteService($id) {
        $used = (int) $this->db->value("SELECT COUNT(*) FROM itemized_bill WHERE id_service = ?", [(int) $id]);
        if ($used > 0) { $this->update($id, ['is_active' => 0]); return 'hidden'; }
        $this->delete($id);
        return 'deleted';
    }

    public function active() {
        return $this->db->query("SELECT * FROM service WHERE is_active = 1 ORDER BY id_service");
    }

    public function codeExists($code, $exceptId = 0) {
        return (bool) $this->db->value("SELECT COUNT(*) FROM service WHERE code = ? AND id_service <> ?", [$code, (int) $exceptId]);
    }
}
