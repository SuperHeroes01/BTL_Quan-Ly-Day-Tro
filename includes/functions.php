
<?php
// includes/functions.php - Các hàm tiện ích dùng chung

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

function money($n) {
    return number_format((float)$n, 0, ',', '.') . 'đ';
}

function dmy($d) {
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}

function month_label($m) {
    return preg_match('/^(\d{4})-(\d{2})$/', (string)$m, $x)
        ? $x[2] . '/' . $x[1]
        : (string)$m;
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post($k, $d = '') {
    return isset($_POST[$k]) && !is_array($_POST[$k])
        ? trim((string)$_POST[$k])
        : $d;
}

function qs($k, $d = '') {
    return isset($_GET[$k]) && !is_array($_GET[$k])
        ? trim((string)$_GET[$k])
        : $d;
}

function valid_date($d) {
    $x = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $x && $x->format('Y-m-d') === $d;
}

function valid_month($m) {
    return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$m);
}

// ---- Thông báo flash ----
function flash($type = null, $msg = null) {
    if ($type !== null) {
        $_SESSION['flash'][] = [$type, $msg];
        return null;
    }

    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $f;
}

// ---- Chống CSRF ----
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' .
        h(csrf_token()) . '">';
}

function csrf_check() {
    $sess = $_SESSION['csrf'] ?? '';

    if ($sess === '' || !hash_equals(
        $sess,
        (string)($_POST['_csrf'] ?? '')
    )) {
        http_response_code(419);
        die('Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

// ---- Hiển thị ----
function badge($text, $cls = 'gray') {
    return '<span class="badge ' . h($cls) . '">' .
        h($text) . '</span>';
}

function status_badge($s) {
    $map = [
        'Trống' => 'amber',
        'Đã thuê' => 'green',
        'Bảo trì' => 'red',
        'Hoạt động' => 'green',
        'Hỏng' => 'red',
        'Đang sửa' => 'amber',
        'Chưa thu' => 'red',
        'Đã thu' => 'green',
        'Chưa đăng ký' => 'gray',
        'Chờ xử lý' => 'amber',
        'Đã đăng ký' => 'green',
        'Chờ tiếp nhận' => 'red',
        'Đã sửa' => 'green',
        'Cần thay mới' => 'purple',
        'Hiệu lực' => 'green',
        'Sắp hết hạn' => 'amber',
        'Đã kết thúc' => 'gray',
        'Khóa' => 'red',
        'Đang làm' => 'green',
        'Nghỉ' => 'gray',
        'Cao' => 'red',
        'Trung bình' => 'amber',
        'Thấp' => 'blue',
    ];

    return badge($s, $map[$s] ?? 'gray');
}

function time_ago($dt) {
    if (!$dt) {
        return '';
    }

    $t = strtotime($dt);
    $diff = time() - $t;

    if (strlen($dt) > 10) {
        if ($diff < 3600) {
            return max(1, (int)($diff / 60)) . ' phút trước';
        }

        if ($diff < 86400) {
            return (int)($diff / 3600) . ' giờ trước';
        }
    }

    $days = (int)floor(
        (strtotime(date('Y-m-d')) -
        strtotime(date('Y-m-d', $t))) / 86400
    );

    if ($days <= 0) {
        return 'hôm nay';
    }

    if ($days == 1) {
        return 'hôm qua';
    }

    return $days . ' ngày trước';
}

function tabs(array $items, $active) {
    $o = '<div class="tabs">';

    foreach ($items as $key => $it) {
        $o .= '<a class="tab' .
            ($key === $active ? ' active' : '') .
            '" href="' . h(url($it[1])) . '">' .
            h($it[0]) . '</a>';
    }

    return $o . '</div>';
}

function mysql_error_text(Throwable $e) {
    if ($e instanceof mysqli_sql_exception && $e->getCode() == 1062) {
        return 'Dữ liệu bị trùng (đã tồn tại trong hệ thống).';
    }

    if ($e instanceof mysqli_sql_exception && $e->getCode() == 1451) {
        return 'Không thể xóa vì dữ liệu đang được sử dụng ở nơi khác.';
    }

    return $e instanceof mysqli_sql_exception
        ? 'Lỗi cơ sở dữ liệu, vui lòng thử lại.'
        : $e->getMessage();
}

// ---- Ghi lại lịch sử hoạt động ----
function write_log($action, $type = 'SYSTEM') {
    global $db;

    try {
        // Lấy đối tượng Database nếu chưa có
        if (!isset($db) || !$db) {
            if (!class_exists('Database')) {
                error_log('Không tìm thấy lớp Database');
                return;
            }

            $db = Database::get();
        }

        // Kiểm tra hàm exec của Database
        if (!is_object($db) || !method_exists($db, 'exec')) {
            error_log('Database không hỗ trợ hàm exec()');
            return;
        }

        // Thông tin tài khoản
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $username = $_SESSION['user']['username']
            ?? ($_SESSION['user']['full_name'] ?? 'Hệ thống');

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $sql = "INSERT INTO activity_logs
                (id_user, username, hanh_dong, loai_hanh_dong, ip_address, thoi_gian)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $db->exec($sql, [
            $userId,
            $username,
            $action,
            mb_strtoupper($type, 'UTF-8'),
            $ipAddress
        ]);

    } catch (Throwable $e) {
        error_log('Lỗi ghi lịch sử hoạt động: ' . $e->getMessage());
    }
}
