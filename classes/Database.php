<?php
// classes/Database.php - Kết nối MySQL (mysqli) và thực thi truy vấn có tham số
class Database {
    private static $instance = null;
    private $conn;
    private $affected = 0;
    private $insertId = 0;

    private function __construct() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $this->conn->set_charset('utf8mb4');
    }

    public static function get() {
        if (self::$instance === null) {
            try {
                self::$instance = new Database();
            } catch (mysqli_sql_exception $e) {
                http_response_code(500);
                die('Không kết nối được MySQL. Hãy bật MySQL trong XAMPP, nhập database/quanlytro.sql '
                    . 'và kiểm tra config/config.php.');
            }
        }
        return self::$instance;
    }

    // Thực thi câu lệnh có tham số (?), trả về mảng các dòng (SELECT) hoặc mảng rỗng
    public function query($sql, array $params = []) {
        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $params = array_values($params);
            $types = '';
            foreach ($params as $p) { $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's'); }
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $this->affected = $stmt->affected_rows;
        $this->insertId = $stmt->insert_id;
        $stmt->close();
        return $rows;
    }

    public function one($sql, array $params = []) { $r = $this->query($sql, $params); return $r ? $r[0] : null; }
    public function value($sql, array $params = []) { $r = $this->one($sql, $params); return $r ? reset($r) : null; }
    public function exec($sql, array $params = []) { $this->query($sql, $params); return $this->affected; }
    public function lastId() { return $this->insertId; }

    public function begin()    { $this->conn->begin_transaction(); }
    public function commit()   { $this->conn->commit(); }
    public function rollback() { $this->conn->rollback(); }

    public function __destruct() { if ($this->conn) { $this->conn->close(); } }
}
