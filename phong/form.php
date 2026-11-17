<?php
// Thêm/sửa phòng và quản lý thư viện ảnh phòng (tối đa 10 ảnh/lần, 50MB/ảnh).
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro']);
$roomM = new Room();
$id = (int) qs('id', '0');
$room = $id ? $roomM->find($id) : null;
if ($id && !$room) { flash('error', 'Không tìm thấy phòng'); redirect('phong/index.php'); }

$errors = [];
$maxRoomImageBytes = 50 * 1024 * 1024;
$maxImagesPerUpload = 10;
$uploadedFiles = [];
$tooLargePost = is_post() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
$d = $room ?: ['room_number' => '', 'area' => '', 'price' => '', 'status' => 'Trống', 'description' => '', 'amenities' => '', 'image_path' => null];

if (is_post()) {
    if ($tooLargePost) {
        $errors[] = 'Tổng dung lượng đợt tải vượt giới hạn post_max_size của PHP. Hãy tăng post_max_size lên 520M trong php.ini rồi khởi động lại Apache.';
    } else {
        csrf_check();
        $d = [
            'room_number' => strtoupper(post('room_number')),
            'area' => post('area'),
            'price' => post('price'),
            'status' => post('status', 'Trống'),
            'description' => post('description'),
            'amenities' => post('amenities'),
            'image_path' => $room['image_path'] ?? null
        ];
        if ($d['room_number'] === '' || !preg_match('/^[A-Z0-9\-]{1,20}$/', $d['room_number'])) { $errors[] = 'Mã phòng chỉ gồm chữ, số, dấu gạch ngang (tối đa 20 ký tự)'; }
        elseif ($roomM->numberExists($d['room_number'], $id)) { $errors[] = 'Mã phòng đã tồn tại'; }
        if (!preg_match('/^[0-9]+$/D', trim((string) $d['price'])) || (float) $d['price'] < 1 || (float) $d['price'] > 999999999999) { $errors[] = 'Giá thuê phải là số nguyên dương, tối đa 999.999.999.999 đồng'; }
        if ($d['area'] !== '' && (!is_numeric($d['area']) || $d['area'] <= 0)) { $errors[] = 'Diện tích phải là số dương'; }
        if (!in_array($d['status'], ['Trống', 'Đã thuê', 'Bảo trì'], true)) { $errors[] = 'Trạng thái không hợp lệ'; }
                // Phòng đang có người ở (kể cả khi đang bảo trì) không thể chuyển sang Trống; hết bảo trì thì trở lại "Đã thuê".
        if ($id && $d['status'] === 'Trống' && $roomM->activeRenterCount($id) > 0) {
            $errors[] = 'Phòng đang có người thuê, hãy làm thủ tục trả phòng trước khi chuyển sang Trống';
        }

        // Đọc danh sách tệp được chọn theo dạng multiple.
        $files = $_FILES['room_images'] ?? null;
        if ($files && isset($files['name']) && is_array($files['name'])) {
            $selectedCount = 0;
            foreach ($files['name'] as $n) { if ($n !== '') { $selectedCount++; } }
            if ($selectedCount > $maxImagesPerUpload) { $errors[] = 'Mỗi lần chỉ được tải tối đa 10 ảnh.'; }
            if ($selectedCount > 0 && $id < 0) { $errors[] = 'Mã phòng không hợp lệ.'; }
            for ($i = 0; $i < count($files['name']) && $selectedCount <= $maxImagesPerUpload; $i++) {
                $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) { continue; }
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? 'Có ảnh vượt giới hạn dung lượng của máy chủ. Mỗi ảnh tối đa 50MB.'
                        : 'Không thể tải một ảnh lên (mã lỗi ' . (int) $err . ').';
                    continue;
                }
                $tmp = $files['tmp_name'][$i] ?? '';
                $size = (int) ($files['size'][$i] ?? 0);
                if (!$tmp || !is_uploaded_file($tmp)) { $errors[] = 'Tệp tải lên không hợp lệ.'; continue; }
                if ($size <= 0 || $size > $maxRoomImageBytes) { $errors[] = 'Mỗi ảnh phải có dung lượng lớn hơn 0 và không quá 50MB.'; continue; }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $info = @getimagesize($tmp);
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
                if (!$ext || !$info) { $errors[] = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ.'; continue; }
                if (!is_dir(ROOM_UPLOAD_DIR) && !mkdir(ROOM_UPLOAD_DIR, 0755, true)) { $errors[] = 'Không tạo được thư mục lưu ảnh.'; continue; }
                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($tmp, ROOM_UPLOAD_DIR . $stored)) { $errors[] = 'Không lưu được một ảnh phòng.'; continue; }
                $uploadedFiles[] = [
                    'file_path' => $stored,
                    'original_name' => mb_substr(basename((string) ($files['name'][$i] ?? 'anh-phong')), 0, 190),
                    'size_bytes' => $size
                ];
            }
        }

        // Chỉ xóa ảnh thuộc đúng phòng hiện tại.
        $deleteIds = [];
        if ($id && isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $rawId) {
                $imageId = filter_var($rawId, FILTER_VALIDATE_INT);
                if ($imageId) { $deleteIds[] = (int) $imageId; }
            }
            $deleteIds = array_values(array_unique($deleteIds));
        }

        if (!$errors) {
            $save = [
                'room_number' => $d['room_number'],
                'area' => $d['area'] === '' ? null : (float) $d['area'],
                'price' => (int) $d['price'],
                'status' => $d['status'],
                'description' => $d['description'],
                'amenities' => $d['amenities'],
                'image_path' => $room['image_path'] ?? null
            ];
            $db = Database::get();
            $deletedPaths = [];
            try {
                $db->begin();
                $savedId = (int) $roomM->save($save, $id ?: null);
                // Không phải đang bảo trì -> trạng thái theo người đang ở, giữ nguyên người thuê sau khi bảo trì xong.
                if ($d['status'] !== 'Bảo trì') { $roomM->refreshStatus($savedId, true); }
                if ($deleteIds) {
                    $marks = implode(',', array_fill(0, count($deleteIds), '?'));
                    $params = array_merge([$savedId], $deleteIds);
                    $rowsToDelete = $db->query("SELECT id_image, file_path FROM room_image WHERE id_room = ? AND id_image IN ($marks)", $params);
                    foreach ($rowsToDelete as $row) { $deletedPaths[] = basename($row['file_path']); }
                    $db->exec("DELETE FROM room_image WHERE id_room = ? AND id_image IN ($marks)", $params);
                }
                foreach ($uploadedFiles as $photo) {
                    $db->exec("INSERT INTO room_image (id_room, file_path, original_name, size_bytes, is_cover, sort_order) VALUES (?, ?, ?, ?, 0, 0)",
                        [$savedId, $photo['file_path'], $photo['original_name'], $photo['size_bytes']]);
                }
                $coverCount = (int) $db->value("SELECT COUNT(*) FROM room_image WHERE id_room = ? AND is_cover = 1", [$savedId]);
                if ($coverCount === 0) {
                    $first = $db->one("SELECT id_image FROM room_image WHERE id_room = ? ORDER BY id_image ASC LIMIT 1", [$savedId]);
                    if ($first) { $db->exec("UPDATE room_image SET is_cover = 1 WHERE id_image = ?", [(int) $first['id_image']]); }
                }
                $cover = $db->one("SELECT file_path FROM room_image WHERE id_room = ? ORDER BY is_cover DESC, sort_order ASC, id_image ASC LIMIT 1", [$savedId]);
                $db->exec("UPDATE room SET image_path = ? WHERE id_room = ?", [$cover ? $cover['file_path'] : null, $savedId]);
                $db->commit();
                foreach ($deletedPaths as $oldFile) {
                    $path = ROOM_UPLOAD_DIR . $oldFile;
                    if (is_file($path)) { @unlink($path); }
                }
                flash('success', 'Đã lưu thông tin phòng và thư viện ảnh.');
                redirect('phong/index.php');
            } catch (Throwable $e) {
                try { $db->rollback(); } catch (Throwable $ignore) {}
                foreach ($uploadedFiles as $photo) {
                    $path = ROOM_UPLOAD_DIR . $photo['file_path'];
                    if (is_file($path)) { @unlink($path); }
                }
                $errors[] = mysql_error_text($e);
            }
        }
    }
}

// Nếu xác thực không đạt, xóa các tệp đã tạm lưu để tránh rác trên máy chủ.
if ($errors && $uploadedFiles) {
    foreach ($uploadedFiles as $photo) {
        $path = ROOM_UPLOAD_DIR . $photo['file_path'];
        if (is_file($path)) { @unlink($path); }
    }
}

$pageTitle = $id ? 'Sửa phòng ' . $room['room_number'] : 'Thêm phòng mới';
$pageSub = 'Quản lý mô tả, tiện nghi và tối đa 10 ảnh mỗi lần tải';
$active = 'phong';
require __DIR__ . '/../includes/header.php';
$existingImages = $id ? $roomM->images($id) : [];
$legacyPreview = $id && !empty($room['image_path']) ? url('phong/anh.php?id=' . $id) : url('assets/images/room-vacant.svg');
$managerPreview = $existingImages
    ? url('phong/anh.php?id=' . $id . '&image_id=' . (int) $existingImages[0]['id_image'])
    : ($id && !empty($room['image_path']) ? $legacyPreview : url('assets/images/room-vacant.svg'));
?>
<form class="card room-manage-form" method="post" enctype="multipart/form-data" data-validate data-room-upload-form>
  <div class="card-h"><span><?= h($pageTitle) ?></span><span class="badge blue">Ảnh tối đa 50MB/tệp</span></div>
  <div class="card-b">
    <?= csrf_field() ?>
    <?php foreach ($errors as $e): ?><div class="alert error"><?= h($e) ?></div><?php endforeach; ?>
    <div class="grid g2 room-edit-layout">
      <div>
        <div class="form-row">
          <div class="field"><label>Mã phòng *</label><input name="room_number" value="<?= h($d['room_number']) ?>" required data-label="Mã phòng" placeholder="P101"></div>
          <div class="field"><label>Giá thuê (đ/tháng) *</label><input type="number" name="price" min="1" max="999999999999" step="1" value="<?= h($d['price']) ?>" required data-label="Giá thuê"><small>Nhập số tiền nguyên đồng tùy ý, ví dụ: 3000000, 3800000 hoặc 4000000. Không bắt buộc theo bậc 1.000 đồng.</small></div>
          <div class="field"><label>Diện tích (m²)</label><input type="number" name="area" min="1" step="0.5" value="<?= h($d['area']) ?>"></div>
          <div class="field"><label>Trạng thái</label><select name="status"><?php $occupied = $id && $roomM->activeRenterCount($id) > 0; foreach (($occupied ? ['Đã thuê', 'Bảo trì'] : ['Trống', 'Đã thuê', 'Bảo trì']) as $s): ?><option <?= $d['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select><small>Trạng thái được đồng bộ khi thêm hoặc trả phòng cho người thuê.</small></div>
          <div class="field full"><label>Mô tả phòng</label><textarea name="description" rows="3" placeholder="Ví dụ: Phòng thoáng, có cửa sổ, ban công..."><?= h($d['description']) ?></textarea></div>
          <div class="field full"><label>Đồ dùng / tiện nghi trong phòng</label><textarea name="amenities" rows="4" placeholder="Mỗi dòng một tiện nghi, ví dụ:\nGiường và nệm\nTủ quần áo\nBàn học\nĐiều hòa\nWifi miễn phí"><?= h($d['amenities'] ?? '') ?></textarea><small>Thông tin này sẽ hiển thị ở trang chi tiết phòng công khai.</small></div>
          <div class="field full"><label>Thêm nhiều ảnh thực tế cùng lúc</label><input type="file" name="room_images[]" accept="image/jpeg,image/png,image/webp" multiple data-room-files><small><?php if ($id): ?>Ảnh sẽ được tải và lưu ngay vào thư viện (tối đa 10 ảnh/lần, 50MB/ảnh). Không cần bấm “Lưu thông tin phòng”; xóa ảnh cũng có hiệu lực ngay.<?php else: ?>Chọn tối đa 10 ảnh/lần, 50MB/ảnh. Với phòng mới, ảnh sẽ được lưu cùng lúc khi bạn tạo phòng.<?php endif; ?></small><div class="room-image-message" data-room-image-message aria-live="polite"></div></div>
          <div class="field full"><div class="upload-preview-list" data-room-file-preview></div></div>
        </div>
        <button class="btn">Lưu thông tin phòng</button> <a class="btn gray" href="<?= h(url('phong/index.php')) ?>">Hủy</a>
      </div>
      <div class="room-gallery-manager" <?php if ($id): ?>data-room-image-manager data-room-id="<?= $id ?>" data-image-endpoint="<?= h(url('phong/anh_quan_ly.php')) ?>" data-csrf="<?= h(csrf_token()) ?>"<?php endif; ?>>
        <div class="preview-label">Thư viện ảnh phòng <span data-room-image-count>(<?= count($existingImages) + (!$existingImages && $id && !empty($room['image_path']) ? 1 : 0) ?> ảnh)</span></div>
        <div class="room-live-cover"><img class="room-manager-cover" src="<?= h($managerPreview) ?>" alt="Ảnh xem trước của phòng <?= h($d['room_number'] ?? '') ?>" data-room-live-cover></div>
        <?php if ($id): ?>
          <div class="room-existing-grid" data-room-existing-grid>
            <?php foreach ($existingImages as $photo): ?>
              <div class="room-existing-photo" data-room-image-card="<?= (int) $photo['id_image'] ?>">
                <img src="<?= h(url('phong/anh.php?id=' . $id . '&image_id=' . (int) $photo['id_image'])) ?>" alt="<?= h($photo['original_name'] ?: 'Ảnh phòng') ?>">
                <?php if ((int) $photo['is_cover'] === 1): ?><b class="cover-flag">Ảnh đại diện</b><?php endif; ?>
                <button type="button" class="room-image-delete" data-delete-room-image="<?= (int) $photo['id_image'] ?>">Xóa ảnh này</button>
              </div>
            <?php endforeach; ?>
            <?php if (!$existingImages && !empty($room['image_path'])): ?>
              <div class="room-existing-photo" data-room-image-card="0">
                <img src="<?= h($legacyPreview) ?>" alt="Ảnh phòng cũ">
                <b class="cover-flag">Ảnh đại diện</b>
                <button type="button" class="room-image-delete" data-delete-room-image="0">Xóa ảnh này</button>
              </div>
            <?php endif; ?>
          </div>
          <p class="room-gallery-empty" data-room-gallery-empty <?= ($existingImages || !empty($room['image_path'])) ? 'hidden' : '' ?>>Chưa có ảnh thực tế cho phòng này. Chọn ảnh ở bên trái để thêm; ảnh sẽ được lưu ngay.</p>
          <p class="muted room-gallery-hint">Ảnh được thêm hoặc xóa ngay trong thư viện. Những thay đổi về mã phòng, giá thuê, mô tả và tiện nghi vẫn cần bấm “Lưu thông tin phòng”.</p>
        <?php elseif (!$existingImages): ?>
          <p class="room-gallery-empty">Chưa có ảnh thực tế. Ảnh bạn chọn sẽ được lưu khi tạo phòng mới.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
