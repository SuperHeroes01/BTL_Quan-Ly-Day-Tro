<?php
// classes/ItemizedBill.php - Lớp Hóa đơn chi tiết (từng khoản trong một hóa đơn)
class ItemizedBill extends BaseModel {
    protected $table = 'itemized_bill';
    protected $pk = 'id_itemized';
    protected $fields = ['id_bill', 'id_service', 'item_name', 'old_index', 'new_index', 'quantity', 'unit_price', 'amount'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    // Thành tiền = số lượng x đơn giá (làm tròn đến đồng)
    public static function calculateAmount($quantity, $unitPrice) {
        return (float) round($quantity * $unitPrice);
    }

    public function forBill($idBill) {
        return $this->db->query("SELECT * FROM itemized_bill WHERE id_bill = ? ORDER BY id_itemized", [(int) $idBill]);
    }
}
