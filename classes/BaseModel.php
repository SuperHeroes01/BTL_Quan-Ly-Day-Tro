<?php
// classes/BaseModel.php - Lớp trừu tượng cho mọi lớp xử lý dữ liệu (mô hình DataStore_Adapter trong bài giảng)
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $pk;
    protected $fields = [];

    public function __construct() { $this->db = Database::get(); }

    // Mỗi lớp con phải tự cài đặt thao tác thêm và sửa
    abstract public function insert(array $data);
    abstract public function update($id, array $data);

    // Có id thì cập nhật, chưa có thì thêm mới
    public function save(array $data, $id = null) {
        return $id ? $this->update($id, $data) : $this->insert($data);
    }

    public function find($id) {
        return $this->db->one("SELECT * FROM `{$this->table}` WHERE `{$this->pk}` = ?", [(int) $id]);
    }

    public function all($order = null) {
        return $this->db->query("SELECT * FROM `{$this->table}` ORDER BY " . ($order ?: "`{$this->pk}`"));
    }

    public function delete($id) {
        return $this->db->exec("DELETE FROM `{$this->table}` WHERE `{$this->pk}` = ?", [(int) $id]);
    }

    protected function insertRow(array $d) {
        $d = array_intersect_key($d, array_flip($this->fields));
        $cols = implode(',', array_map(function ($c) { return "`$c`"; }, array_keys($d)));
        $ph = implode(',', array_fill(0, count($d), '?'));
        $this->db->exec("INSERT INTO `{$this->table}` ($cols) VALUES ($ph)", array_values($d));
        return $this->db->lastId();
    }

    protected function updateRow($id, array $d) {
        $d = array_intersect_key($d, array_flip($this->fields));
        if (!$d) { return $id; }
        $set = implode(',', array_map(function ($c) { return "`$c` = ?"; }, array_keys($d)));
        $params = array_values($d);
        $params[] = (int) $id;
        $this->db->exec("UPDATE `{$this->table}` SET $set WHERE `{$this->pk}` = ?", $params);
        return $id;
    }
}
