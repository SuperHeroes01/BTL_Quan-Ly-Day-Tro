<?php
// phong/index.php - Danh sách phòng cho Admin/Chủ trọ/Nhân viên và thông tin phòng của Người thuê.
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::guard(['admin', 'chutro', 'nhanvien', 'nguoithue']);

$roomM = new Room();
$isManager = Auth::isManager();
$isStaff = Auth::is('nhanvien');
$isTenant = Auth::is('nguoithue');

// Chỉ Admin/Chủ trọ được phép xóa phòng. Room::deleteRoom() tự kiểm tra
// phòng đã có hồ sơ thuê/hóa đơn để tránh xóa dữ liệu đang được tham chiếu.
if (is_post() && $isManager) {
    csrf_check();

    if (post('action') === 'delete') {
        $roomId = (int) post('id');
        $result = $roomM->deleteRoom($roomId);

        if ($result === true) {
            flash('success', 'Đã xóa phòng.');
        } else {
            flash('error', is_string($result) ? $result : 'Không thể xóa phòng.');
        }
    }

    redirect('phong/index.php');
}

$pageTitle = 'Quản Lý Phòng Trọ';
$pageSub = 'Quản lý người thuê, phòng trống và phòng đã cho thuê';
$active = 'phong';
require __DIR__ . '/../includes/header.php';

if ($isManager || $isStaff) {
    // Người thuê không được thấy các tab quản trị. Nhân viên kỹ thuật chỉ xem danh sách phòng.
    $tabs = $isManager
        ? [
            'ds' => ['Danh sách phòng', 'phong/index.php'],
            'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'],
            'nt' => ['Người thuê', 'phong/nguoi_thue.php'],
        ]
        : ['ds' => ['Danh sách phòng', 'phong/index.php']];
    echo tabs($tabs, 'ds');

    $keyword = trim((string) qs('q'));
    $statusFilter = trim((string) qs('status'));

    // Quan trọng: chỉ Admin/Chủ trọ mới truy vấn tên người thuê.
    // Nhân viên kỹ thuật chỉ nhận dữ liệu phòng và số người ở, tránh lộ thông tin cá nhân.
    $rooms = $roomM->searchRoom($keyword, $statusFilter, $isManager);
    $stats = $roomM->stats();
?>
<div class="card">
  <div class="card-h">
    <span>
      Danh sách phòng
      <?= badge((int) ($stats['rented'] ?? 0) . ' Đã thuê', 'green') ?>
      <?= badge((int) ($stats['vacant'] ?? 0) . ' Trống', 'amber') ?>
    </span>
    <?php if ($isManager): ?>
      <a class="btn" href="<?= h(url('phong/form.php')) ?>">+ Thêm phòng</a>
    <?php endif; ?>
  </div>

  <div class="card-b">
    <form class="filter" method="get">
      <div class="field">
        <label for="room-search">Tìm theo mã phòng</label>
        <input id="room-search" name="q" value="<?= h($keyword) ?>" placeholder="Ví dụ: P101">
      </div>
      <div class="field">
        <label for="room-status">Trạng thái</label>
        <select id="room-status" name="status">
          <option value="">Tất cả</option>
          <?php foreach (['Trống', 'Đã thuê', 'Bảo trì'] as $statusOption): ?>
            <option value="<?= h($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>>
              <?= h($statusOption) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Tìm kiếm</button>
      <?php if ($keyword !== '' || $statusFilter !== ''): ?>
        <a class="btn gray" href="<?= h(url('phong/index.php')) ?>">Xóa lọc</a>
      <?php endif; ?>
    </form>

    <div class="rooms">
      <?php foreach ($rooms as $room):
          $roomStatus = (string) ($room['status'] ?? 'Trống');
          $roomClasses = [
              'Đã thuê' => 'rented',
              'Trống' => 'vacant',
              'Bảo trì' => 'fix',
          ];
          $roomClass = $roomClasses[$roomStatus] ?? 'vacant';
          $roomId = (int) ($room['id_room'] ?? 0);
          $roomNumber = (string) ($room['room_number'] ?? '');
          $fallbackImage = $roomStatus === 'Đã thuê'
              ? 'room-rented.svg'
              : ($roomStatus === 'Bảo trì' ? 'room-maintenance.svg' : 'room-vacant.svg');
      ?>
        <div class="room <?= h($roomClass) ?>">
          <?php if ($isManager): ?>
            <img
              class="room-thumb"
              src="<?= h(!empty($room['image_path'])
                  ? url('phong/anh.php?id=' . $roomId)
                  : url('assets/images/' . $fallbackImage)) ?>"
              alt="Hình ảnh phòng <?= h($roomNumber) ?>"
              loading="lazy">
          <?php endif; ?>

          <h3><?= h($roomNumber) ?></h3>

          <?php if ($isManager): ?>
            <p><?= h($room['tenant_names'] ?? 'Chưa có người thuê') ?></p>
          <?php endif; ?>

          <p>
            <?= h(money($room['price'] ?? 0)) ?>/tháng
            <?= !empty($room['area']) ? ' &middot; ' . h($room['area']) . ' m²' : '' ?>
          </p>
          <p><?= (int) ($room['occupants'] ?? 0) ?> người ở</p>

          <?php if ($isManager): ?>
            <div class="actions" style="margin-top:8px">
              <a class="btn sm" href="<?= h(url('phong_chi_tiet.php?id=' . $roomId)) ?>">Xem chi tiết</a>
              <a class="btn sm gray" href="<?= h(url('phong/form.php?id=' . $roomId)) ?>">Sửa</a>
              <form class="inline" method="post" data-confirm="Xóa phòng <?= h($roomNumber) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $roomId ?>">
                <button class="btn sm red" type="submit">Xóa</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!$rooms): ?>
        <p class="t-muted">Không tìm thấy phòng nào phù hợp.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
} elseif ($isTenant) {
    // Người thuê chỉ xem phòng thuộc hồ sơ của chính mình.
    echo tabs([
        'ds' => ['Phòng của tôi', 'phong/index.php'],
        'hd' => ['Hợp đồng thuê', 'phong/hop_dong.php'],
    ], 'ds');

    $info = (new Renter())->viewRoomInfo(Auth::id());

    // Chặn hiển thị lại phòng cũ khi hồ sơ thuê đã kết thúc.
    if ($info && !empty($info['end_date']) && $info['end_date'] <= date('Y-m-d')) {
        $info = null;
    }

    if (!$info) {
        echo '<div class="alert warn">Tài khoản hiện chưa được gắn với hợp đồng thuê còn hiệu lực.</div>';
    } else {
        $roomId = (int) $info['id_room'];

        // Chỉ hiển thị người ở cùng phòng; không tiết lộ số điện thoại của người khác.
        // Loại chính tài khoản hiện tại khỏi danh sách để nhãn "Người ở cùng phòng" chính xác.
        $mates = Database::get()->query(
            "SELECT u.full_name
             FROM renter t
             JOIN `user` u ON u.id_user = t.id_user
             WHERE t.id_room = ?
               AND " . Renter::ACTIVE . "
               AND t.id_user <> ?
             ORDER BY u.full_name",
            [$roomId, Auth::id()]
        );

        $tenantPhotos = (new Room())->images($roomId);
        $tenantGallery = [];

        foreach ($tenantPhotos as $photo) {
            $tenantGallery[] = [
                'url' => url('phong/anh.php?id=' . $roomId . '&image_id=' . (int) $photo['id_image']),
                'alt' => 'Ảnh phòng ' . $info['room_number'] . ' - ' . ($photo['original_name'] ?: 'Ảnh thực tế'),
            ];
        }

        if (!$tenantGallery && !empty($info['image_path'])) {
            $tenantGallery[] = [
                'url' => url('phong/anh.php?id=' . $roomId),
                'alt' => 'Ảnh phòng ' . $info['room_number'],
            ];
        }

        $hasContractRent = isset($info['rent_price']) && (float) $info['rent_price'] > 0;
        $displayRent = $hasContractRent ? $info['rent_price'] : ($info['price'] ?? 0);
        $rentLabel = $hasContractRent ? 'Giá thuê theo hồ sơ' : 'Giá niêm yết hiện tại';
?>
<div class="grid g2">
  <div class="card">
    <div class="card-h">
      <span>
        Ảnh thực tế phòng <?= h($info['room_number']) ?>
        <small class="tenant-gallery-live-label">Tự động trình chiếu</small>
      </span>
      <span class="badge blue">Chỉ phòng của bạn</span>
    </div>
    <div class="card-b">
      <div class="tenant-room-gallery" data-room-gallery data-autoplay="4000">
        <div class="gallery-main tenant-gallery-main">
          <img
            class="gallery-current"
            src="<?= h($tenantGallery[0]['url'] ?? url('assets/images/room-rented.svg')) ?>"
            alt="<?= h($tenantGallery[0]['alt'] ?? ('Ảnh minh họa phòng ' . $info['room_number'])) ?>"
            data-gallery-main>
          <?php if (count($tenantGallery) > 1): ?>
            <button class="gallery-arrow prev" type="button" data-gallery-prev aria-label="Ảnh trước">‹</button>
            <button class="gallery-arrow next" type="button" data-gallery-next aria-label="Ảnh tiếp theo">›</button>
          <?php endif; ?>
          <span class="gallery-count" data-gallery-count>
            <?= $tenantGallery ? '1 / ' . count($tenantGallery) : 'Ảnh minh họa' ?>
          </span>
        </div>

        <?php if (count($tenantGallery) > 1): ?>
          <div class="gallery-thumbs" data-gallery-thumbs>
            <?php foreach ($tenantGallery as $index => $photo): ?>
              <button
                type="button"
                class="gallery-thumb<?= $index === 0 ? ' active' : '' ?>"
                data-gallery-index="<?= $index ?>"
                aria-label="Xem ảnh <?= $index + 1 ?>">
                <img src="<?= h($photo['url']) ?>" alt="<?= h($photo['alt']) ?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <p class="gallery-auto-note">
        Ảnh tự động chuyển sau 4 giây. Dùng nút mũi tên hoặc ảnh thu nhỏ để xem ảnh khác.
      </p>

      <div class="rowline">
        <span><?= h($rentLabel) ?></span>
        <b><?= (float) $displayRent > 0 ? h(money($displayRent)) . '/tháng' : 'Chưa xác nhận giá thuê' ?></b>
      </div>
      <div class="rowline">
        <span>Diện tích</span>
        <b><?= !empty($info['area']) ? h($info['area']) . ' m²' : '—' ?></b>
      </div>
      <div class="rowline">
        <span>Ngày vào ở</span>
        <b><?= h(dmy($info['start_date'])) ?></b>
      </div>
      <div class="rowline">
        <span>Mô tả</span>
        <b><?= h($info['description'] ?? '') ?></b>
      </div>

      <p style="margin-top:12px">
        <a class="btn sm" href="<?= h(url('phong/hop_dong.php')) ?>">Xem hợp đồng thuê</a>
      </p>

      <p><b>Người ở cùng phòng</b></p>
      <?php if ($mates): ?>
        <?php foreach ($mates as $mate): ?>
          <div class="rowline"><span><?= h($mate['full_name']) ?></span></div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="rowline"><span class="t-muted">Hiện không có người ở cùng.</span></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-h">Thiết bị trong phòng</div>
    <div class="tw">
      <table class="t">
        <thead>
          <tr><th>Thiết bị</th><th>SL</th><th>Trạng thái</th></tr>
        </thead>
        <tbody>
          <?php foreach (($info['devices'] ?? []) as $device): ?>
            <tr>
              <td><?= h($device['device_name']) ?></td>
              <td><?= (int) $device['quantity'] ?></td>
              <td><?= status_badge($device['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($info['devices'])): ?>
            <tr><td colspan="3" class="t-muted">Chưa có thiết bị.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
    }
}

require __DIR__ . '/../includes/footer.php';
