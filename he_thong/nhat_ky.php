
<?php
// he_thong/nhat_ky.php
// Trang hiển thị lịch sử hoạt động hệ thống (Audit Trail)

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::guard(['admin']);

$pageTitle = 'Nhật Ký Hoạt Động';
$pageSub = 'Theo dõi vết hành động (Audit Trail) của quản trị viên và người dùng trong hệ thống';
$active = 'he_thong';

require __DIR__ . '/../includes/header.php';

// ==============================
// PHÂN TRANG VÀ LỌC DỮ LIỆU
// ==============================

$limit = 20;
$page = max(1, (int) qs('page', 1));
$offset = ($page - 1) * $limit;

$type = qs('type');
$keyword = qs('q');

$where = "WHERE 1=1";
$params = [];

// Lọc theo loại hành động
if ($type !== '') {
    $where .= " AND loai_hanh_dong = ?";
    $params[] = $type;
}

// Tìm kiếm theo tài khoản, nội dung hoặc IP
if ($keyword !== '') {
    $where .= " AND (
        username LIKE ?
        OR hanh_dong LIKE ?
        OR ip_address LIKE ?
    )";

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// ==============================
// LẤY DỮ LIỆU TỪ DATABASE
// ==============================

$logs = [];
$totalRows = 0;
$types = [];
$loadError = '';

try {
    $dbConnection = Database::get();
    $tableExists = (int) $dbConnection->value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'activity_logs'"
    );

    if (!$tableExists) {
        $loadError = 'Cơ sở dữ liệu chưa có bảng nhật ký hoạt động. Admin vui lòng Import database/migration_v68_activity_logs.sql trong phpMyAdmin.';
    } else {
        $sqlTotal = "SELECT COUNT(*) AS total FROM activity_logs $where";
        $totalRows = (int) $dbConnection->value($sqlTotal, $params);

        $sqlLogs = "SELECT * FROM activity_logs $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $logs = $dbConnection->query($sqlLogs, $params);

        $typeRows = $dbConnection->query(
            "SELECT DISTINCT loai_hanh_dong FROM activity_logs ORDER BY loai_hanh_dong"
        );
        foreach ($typeRows as $row) {
            $types[] = $row['loai_hanh_dong'];
        }
    }
} catch (Throwable $e) {
    error_log('Lỗi nhật ký hoạt động: ' . $e->getMessage());
    $loadError = 'Không thể tải nhật ký hoạt động. Vui lòng kiểm tra kết nối database và cấu trúc bảng activity_logs.';
}

// Tổng số trang
$totalPages = max(1, (int) ceil($totalRows / $limit));
?>
<?php if ($loadError !== ''): ?><div class="alert error"><?= h($loadError) ?></div><?php endif; ?>

<!-- KHUNG TÌM KIẾM VÀ LỌC -->
<div class="card" style="margin-bottom: 18px;">
    <div class="card-b">

        <form method="get"
              style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">

            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Tìm theo nội dung, tài khoản, IP..."
                value="<?= h($keyword) ?>"
                style="flex:1; min-width:220px; padding:8px;"
            >

            <select name="type" class="form-control" style="padding:8px;">

                <option value="">-- Tất cả phân loại --</option>

                <?php foreach ($types as $t): ?>
                    <option
                        value="<?= h($t) ?>"
                        <?= $type === $t ? 'selected' : '' ?>
                    >
                        <?= h($t) ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn">
                Lọc dữ liệu
            </button>

            <?php if ($keyword !== '' || $type !== ''): ?>
                <a href="<?= h(url('he_thong/nhat_ky.php')) ?>"
                   class="btn gray">
                    Làm lại
                </a>
            <?php endif; ?>

        </form>

    </div>
</div>

<!-- BẢNG DANH SÁCH NHẬT KÝ -->
<div class="card">

    <div class="card-h">
        Danh sách lịch sử thao tác hệ thống
        (Tổng: <b><?= $totalRows ?></b> bản ghi)
    </div>

    <div class="tw">

        <table class="t">

            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th style="width:150px;">Thời gian</th>
                    <th style="width:130px;">Tài khoản</th>
                    <th style="width:110px;">Phân loại</th>
                    <th>Nội dung hành động</th>
                    <th style="width:120px;">Địa chỉ IP</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($logs as $l): ?>

                    <tr>

                        <td>
                            #<?= (int) $l['id'] ?>
                        </td>

                        <td>
                            <?= h(dmy($l['thoi_gian'])) ?>
                            <?= h(date('H:i:s', strtotime($l['thoi_gian']))) ?>
                        </td>

                        <td>
                            <b><?= h($l['username']) ?></b>
                            <small class="t-muted">
                                (ID: <?= !empty($l['id_user']) ? (int) $l['id_user'] : '—' ?>)
                            </small>
                        </td>

                        <td>
                            <span
                                class="status-badge"
                                style="background:#e3f2fd; color:#0d47a1; padding:3px 8px; border-radius:4px; font-weight:600; font-size:12px;"
                            >
                                <?= h($l['loai_hanh_dong']) ?>
                            </span>
                        </td>

                        <td>
                            <?= h($l['hanh_dong']) ?>
                        </td>

                        <td>
                            <code><?= h($l['ip_address'] ?? '—') ?></code>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($logs)): ?>

                    <tr>
                        <td colspan="6"
                            class="t-muted"
                            style="text-align:center; padding:30px;">
                            Chưa có lịch sử hoạt động nào được ghi nhận trong hệ thống.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- PHÂN TRANG -->
    <?php if ($totalPages > 1): ?>

        <div class="card-b"
             style="display:flex; justify-content:center; gap:5px;">

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <a
                    href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&type=<?= urlencode($type) ?>"
                    class="btn sm <?= $i === $page ? '' : 'gray' ?>"
                >
                    <?= $i ?>
                </a>

            <?php endfor; ?>

        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
