<?php
// dich_vu/hoa_don_xem.php - Xem chi tiết và in một hóa đơn.
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::guard(['admin', 'chutro', 'nguoithue']);

$b = (new Bill())->detail((int) qs('id', '0'));

if (!$b) {
    flash('error', 'Không tìm thấy hóa đơn');
    redirect('dich_vu/hoa_don.php');
}

// Người thuê chỉ xem được hóa đơn thuộc phòng mình.
if (Auth::is('nguoithue')) {
    $me = (new Renter())->findByUser(Auth::id());

    if (!$me || (int) $me['id_room'] !== (int) $b['id_room']) {
        http_response_code(403);
        die('Bạn không có quyền xem hóa đơn này');
    }
}

$pageTitle = 'Hóa đơn #HD' . str_pad(
    (string) $b['id_bill'],
    4,
    '0',
    STR_PAD_LEFT
);

$pageSub = 'Phòng ' . $b['room_number'] .
    ' - tháng ' . month_label($b['month']);

$active = 'dich_vu';

require __DIR__ . '/../includes/header.php';

// CSS in dùng chung cho hợp đồng và hóa đơn.
$printCssPath = __DIR__ . '/../assets/css/pages/print-document.css';
$printCssVer = file_exists($printCssPath)
    ? filemtime($printCssPath)
    : '1.0';

// Tương thích với hóa đơn cũ chưa có period_start / period_end.
$monthFirstDay = $b['month'] . '-01';
$periodStart = !empty($b['period_start'])
    ? $b['period_start']
    : $monthFirstDay;
$periodEnd = !empty($b['period_end'])
    ? $b['period_end']
    : date('Y-m-t', strtotime($monthFirstDay));
?>

<link
    rel="stylesheet"
    href="<?= h(url('assets/css/pages/print-document.css?v=' . $printCssVer)) ?>"
>

<div class="card invoice">
    <div class="card-b">

        <div class="head">
            <div>
                <h2><?= h(Setting::get('ten_day_tro', APP_NAME)) ?></h2>

                <p class="t-muted">
                    <?= h(Setting::get('dia_chi')) ?>
                    <br>
                    ĐT: <?= h(Setting::get('dien_thoai')) ?>
                </p>
            </div>

            <div style="text-align:right">
                <b>
                    HÓA ĐƠN TIỀN PHÒNG THÁNG
                    <?= h(month_label($b['month'])) ?>
                </b>
                <br>

                Mã: #HD<?= str_pad(
                    (string) $b['id_bill'],
                    4,
                    '0',
                    STR_PAD_LEFT
                ) ?>
                <br>

                Ngày lập: <?= h(dmy($b['date_created'])) ?>
                <br>

                Kỳ tính tiền:
                <?= h(dmy($periodStart)) ?>
                &ndash;
                <?= h(dmy($periodEnd)) ?>
                <br>

                Hạn thanh toán: <?= h(dmy($b['due_date'])) ?>
            </div>
        </div>

        <div class="rowline">
            <span>Phòng</span>
            <b><?= h($b['room_number']) ?></b>
        </div>

        <div class="rowline">
            <span>Người thuê</span>
            <b><?= h($b['tenants'] ?: '—') ?></b>
        </div>

        <div class="tw" style="margin-top:12px">
            <table class="t">
                <thead>
                    <tr>
                        <th>Khoản thu</th>
                        <th>Chỉ số cũ</th>
                        <th>Chỉ số mới</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($b['items'] as $it): ?>
                        <tr>
                            <td><?= h($it['item_name']) ?></td>
                            <td><?= h($it['old_index'] ?? '') ?></td>
                            <td><?= h($it['new_index'] ?? '') ?></td>
                            <td><?= h((float) $it['quantity'] + 0) ?></td>
                            <td><?= h(money($it['unit_price'])) ?></td>
                            <td><b><?= h(money($it['amount'])) ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="total">
            Tổng cộng: <?= h(money($b['total_amount'])) ?>
        </p>

        <p>
            Trạng thái:
            <?= status_badge($b['payment_status']) ?>

            <?= !empty($b['paid_date'])
                ? '&middot; Ngày thu: ' . h(dmy($b['paid_date']))
                : '' ?>
        </p>

        <?php if (!empty($b['note'])): ?>
            <p class="t-muted">
                Ghi chú: <?= h($b['note']) ?>
            </p>
        <?php endif; ?>

        <div class="noprint actions">
            <button
                class="btn"
                type="button"
                onclick="window.print()"
            >
                In hóa đơn
            </button>

            <a
                class="btn gray"
                href="<?= h(url('dich_vu/hoa_don.php')) ?>"
            >
                &larr; Quay lại
            </a>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
 
