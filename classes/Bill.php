<?php
// classes/Bill.php - Lớp Hóa đơn: lập hóa đơn tháng, tính tổng, nhắc nhở và cập nhật thanh toán
class Bill extends BaseModel {
    protected $table = 'bill';
    protected $pk = 'id_bill';
    protected $fields = ['id_room', 'month', 'date_created', 'due_date', 'total_amount', 'payment_status',
                         'paid_date', 'reminded_at', 'note'];

    public function insert(array $d) { return $this->insertRow($d); }
    public function update($id, array $d) { return $this->updateRow($id, $d); }

    // Chỉ số mới nhất của phòng cho một dịch vụ trước tháng $month (null nếu chưa có hóa đơn nào)
    public function previousIndex($idRoom, $idService, $month) {
        $v = $this->db->value("SELECT i.new_index FROM itemized_bill i JOIN bill b ON b.id_bill = i.id_bill
                WHERE b.id_room = ? AND i.id_service = ? AND b.month < ? AND i.new_index IS NOT NULL
                ORDER BY b.month DESC LIMIT 1", [(int) $idRoom, (int) $idService, $month]);
        return $v === null ? null : (int) $v;
    }

    // Giá hợp đồng áp dụng cho tháng lập hóa đơn (không dùng giá niêm yết).
    public function contractRentPrice($idRoom, $month) {
        if (!valid_month($month)) {
            throw new RuntimeException('Tháng lập hóa đơn không hợp lệ');
        }
        $firstDay = $month . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));
        $contracts = $this->db->query(
            "SELECT id_renter, rent_price FROM renter
             WHERE id_room = ? AND start_date <= ?
               AND (end_date IS NULL OR end_date > ?)",
            [(int) $idRoom, $lastDay, $firstDay]
        );
        if (!$contracts) {
            throw new RuntimeException('Không có hợp đồng thuê có hiệu lực trong tháng đã chọn');
        }
        $price = null;
        foreach ($contracts as $contract) {
            $value = $contract['rent_price'];
            if ($value === null || !is_numeric($value) || (float) $value <= 0) {
                throw new RuntimeException('Hợp đồng chưa có giá thuê hợp lệ. Hãy cập nhật hồ sơ thuê trước khi lập hóa đơn');
            }
            $current = (float) $value;
            if ($price !== null && $price !== $current) {
                throw new RuntimeException('Có nhiều hợp đồng với giá thuê khác nhau trong tháng. Cần xác nhận mức giá trước khi lập hóa đơn');
            }
            $price = $current;
        }
        return $price;
    }

    public function calculateTotal(array $items) {
        $t = 0.0;
        foreach ($items as $it) { $t += (float) $it['amount']; }
        return $t;
    }

    // Lập hóa đơn tháng cho một phòng.
    // $inputs = ['idx' => [id_service => ['old'=>.., 'new'=>..]], 'use' => [id_service => '1']]
    public function generateBill($idRoom, $month, array $inputs, $note = '') {
        if (!valid_month($month)) { throw new RuntimeException('Tháng lập hóa đơn không hợp lệ'); }
        $room = (new Room())->find($idRoom);
        if (!$room) { throw new RuntimeException('Không tìm thấy phòng'); }
        if ($room['status'] !== 'Đã thuê') { throw new RuntimeException('Phòng chưa có người thuê, không thể lập hóa đơn'); }
        if ($this->db->value("SELECT COUNT(*) FROM bill WHERE id_room = ? AND month = ?", [(int) $idRoom, $month])) {
            throw new RuntimeException('Phòng ' . $room['room_number'] . ' đã có hóa đơn tháng ' . month_label($month));
        }

        $rentPrice = $this->contractRentPrice($idRoom, $month);
        $items = [[
            'id_service' => null, 'item_name' => 'Tiền phòng', 'old_index' => null, 'new_index' => null,
            'quantity' => 1, 'unit_price' => $rentPrice,
            'amount' => ItemizedBill::calculateAmount(1, $rentPrice),
        ]];

        foreach ((new Service())->active() as $s) {
            $sid = (int) $s['id_service'];
            if ($s['type'] === 'Theo chỉ số') {
                $in = $inputs['idx'][$sid] ?? ['old' => '', 'new' => ''];
                if (trim((string) $in['new']) === '') { continue; }          // dịch vụ không dùng -> không đưa vào hóa đơn
                if (!ctype_digit(trim((string) $in['new']))) {
                    throw new RuntimeException('Chỉ số mới của ' . mb_strtolower($s['service_name']) . ' phải là số nguyên không âm');
                }
                // Nếu đã có hóa đơn trước đó, chỉ số cũ luôn lấy từ chỉ số mới đã lưu gần nhất.
                // Chỉ lần ghi đầu tiên mới cho phép nhập chỉ số cũ ban đầu.
                $storedPrevious = $this->previousIndex($idRoom, $sid, $month);
                if ($storedPrevious !== null) {
                    $old = (int) $storedPrevious;
                } else {
                    if (!ctype_digit(trim((string) $in['old']))) {
                        throw new RuntimeException('Chỉ số cũ của ' . mb_strtolower($s['service_name']) . ' phải là số nguyên không âm');
                    }
                    $old = (int) $in['old'];
                }
                $new = (int) $in['new'];
                if ($new < $old) { throw new RuntimeException('Chỉ số mới của ' . mb_strtolower($s['service_name']) . ' không được nhỏ hơn chỉ số cũ gần nhất (' . $old . ')'); }
                $qty = $new - $old;
                $items[] = [
                    'id_service' => $sid, 'item_name' => $s['service_name'], 'old_index' => $old, 'new_index' => $new,
                    'quantity' => $qty, 'unit_price' => (float) $s['unit_price'],
                    'amount' => ItemizedBill::calculateAmount($qty, $s['unit_price']),
                ];
            } elseif (!empty($inputs['use'][$sid])) {
                $items[] = [
                    'id_service' => $sid, 'item_name' => $s['service_name'], 'old_index' => null, 'new_index' => null,
                    'quantity' => 1, 'unit_price' => (float) $s['unit_price'],
                    'amount' => ItemizedBill::calculateAmount(1, $s['unit_price']),
                ];
            }
        }

        $days = max(1, (int) Setting::get('payment_days', 10));
        $this->db->begin();
        try {
            $idBill = $this->insert([
                'id_room' => (int) $idRoom, 'month' => $month, 'date_created' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime("+$days days")),
                'total_amount' => $this->calculateTotal($items), 'payment_status' => 'Chưa thu', 'note' => $note,
            ]);
            $ib = new ItemizedBill();
            foreach ($items as $it) { $it['id_bill'] = $idBill; $ib->insert($it); }
            $this->db->commit();
            return $idBill;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updatePaymentStatus($id, $status) {
        if (!in_array($status, ['Chưa thu', 'Đã thu'], true)) { return false; }
        $this->update($id, ['payment_status' => $status, 'paid_date' => $status === 'Đã thu' ? date('Y-m-d') : null]);
        return true;
    }

    // Nhắc nhở thanh toán: ghi nhận thời điểm nhắc, người thuê thấy thông báo ở trang chủ
    public function sendNotification($id) {
        $b = $this->find($id);
        if (!$b || $b['payment_status'] === 'Đã thu') { return false; }
        $this->db->exec("UPDATE bill SET reminded_at = NOW() WHERE id_bill = ?", [(int) $id]);
        return true;
    }

    public function search($month = '', $status = '', $kw = '', $idRoom = 0) {
        $sql = "SELECT b.*, r.room_number,
                  (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM renter t JOIN `user` u ON u.id_user = t.id_user
                    WHERE t.id_room = b.id_room AND t.start_date <= LAST_DAY(CONCAT(b.month, '-01'))
                      AND (t.end_date IS NULL OR t.end_date > CONCAT(b.month, '-01'))) AS tenants
                FROM bill b JOIN room r ON r.id_room = b.id_room WHERE 1=1";
        $p = [];
        if ($month !== '')  { $sql .= " AND b.month = ?"; $p[] = $month; }
        if ($status !== '') { $sql .= " AND b.payment_status = ?"; $p[] = $status; }
        if ($kw !== '')     { $sql .= " AND r.room_number LIKE ?"; $p[] = "%$kw%"; }
        if ($idRoom > 0)    { $sql .= " AND b.id_room = ?"; $p[] = (int) $idRoom; }
        return $this->db->query($sql . " ORDER BY b.month DESC, r.room_number", $p);
    }

    public function detail($id) {
        $b = $this->db->one("SELECT b.*, r.room_number FROM bill b JOIN room r ON r.id_room = b.id_room WHERE b.id_bill = ?", [(int) $id]);
        if (!$b) { return null; }
        $b['items'] = (new ItemizedBill())->forBill($id);
        $b['tenants'] = $this->db->value("SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM renter t JOIN `user` u ON u.id_user = t.id_user
                WHERE t.id_room = ? AND t.start_date <= LAST_DAY(CONCAT(?, '-01')) AND (t.end_date IS NULL OR t.end_date > CONCAT(?, '-01'))",
                [(int) $b['id_room'], $b['month'], $b['month']]);
        return $b;
    }

    // Bảng chỉ số điện nước của các phòng đang thuê trong tháng
    public function meterTable($month) {
        $col = function ($code, $field) {
            return "(SELECT i.$field FROM itemized_bill i JOIN service s ON s.id_service = i.id_service
                     WHERE i.id_bill = b.id_bill AND s.code = '$code' LIMIT 1)";
        };
        return $this->db->query("SELECT r.id_room, r.room_number, b.id_bill, b.payment_status,
                {$col('dien','old_index')} AS dien_cu, {$col('dien','new_index')} AS dien_moi, {$col('dien','quantity')} AS dien_sd,
                {$col('nuoc','old_index')} AS nuoc_cu, {$col('nuoc','new_index')} AS nuoc_moi, {$col('nuoc','quantity')} AS nuoc_sd
            FROM room r LEFT JOIN bill b ON b.id_room = r.id_room AND b.month = ?
            WHERE r.status = 'Đã thuê' ORDER BY r.room_number", [$month]);
    }

    // Doanh thu của tháng (hóa đơn đã thu) chia theo nhóm khoản thu
    public function revenue($month) {
        $out = ['phong' => 0.0, 'dien' => 0.0, 'nuoc' => 0.0, 'khac' => 0.0, 'total' => 0.0, 'expected' => 0.0];
        $rows = $this->db->query("SELECT COALESCE(s.code, 'phong') AS code, SUM(i.amount) AS total
                FROM itemized_bill i JOIN bill b ON b.id_bill = i.id_bill LEFT JOIN service s ON s.id_service = i.id_service
                WHERE b.month = ? AND b.payment_status = 'Đã thu' GROUP BY COALESCE(s.code, 'phong')", [$month]);
        foreach ($rows as $r) {
            $k = in_array($r['code'], ['phong', 'dien', 'nuoc'], true) ? $r['code'] : 'khac';
            $out[$k] += (float) $r['total'];
            $out['total'] += (float) $r['total'];
        }
        $out['expected'] = (float) $this->db->value("SELECT COALESCE(SUM(total_amount),0) FROM bill WHERE month = ?", [$month]);
        return $out;
    }

    public function unpaidCount($idRoom = 0) {
        return (int) $this->db->value("SELECT COUNT(*) FROM bill WHERE payment_status = 'Chưa thu'" . ($idRoom ? " AND id_room = ?" : ""), $idRoom ? [(int) $idRoom] : []);
    }

    public function deleteBill($id) {
        $b = $this->find($id);
        if (!$b) { return 'Không tìm thấy hóa đơn'; }
        if ($b['payment_status'] === 'Đã thu') { return 'Hóa đơn đã thu tiền, không thể xóa'; }
        $this->delete($id);   // chi tiết hóa đơn tự xóa theo (ON DELETE CASCADE)
        return true;
    }
}
