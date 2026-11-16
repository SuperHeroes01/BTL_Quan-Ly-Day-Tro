<?php
// API quản lý thư viện ảnh phòng: thêm/xóa ảnh ngay, không cần lưu lại biểu mẫu phòng.
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function room_image_json($status, array $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Auth::isManager()) {
    room_image_json(403, ['ok' => false, 'message' => 'Bạn không có quyền quản lý ảnh phòng.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    room_image_json(405, ['ok' => false, 'message' => 'Phương thức không được hỗ trợ.']);
}
if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST) && empty($_FILES)) {
    room_image_json(413, ['ok' => false, 'message' => 'Tổng dung lượng ảnh vượt post_max_size của PHP. Hãy tăng post_max_size trong php.ini (khuyến nghị 520M) và khởi động lại Apache.']);
}
$sessionToken = $_SESSION['csrf'] ?? '';
$postedToken = (string) ($_POST['_csrf'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
    room_image_json(419, ['ok' => false, 'message' => 'Phiên làm việc hết hạn. Hãy tải lại trang và thử lại.']);
}

$roomId = filter_var($_POST['id_room'] ?? null, FILTER_VALIDATE_INT);
if (!$roomId || $roomId < 1) {
    room_image_json(400, ['ok' => false, 'message' => 'Mã phòng không hợp lệ.']);
}
$roomM = new Room();
$room = $roomM->find((int) $roomId);
if (!$room) {
    room_image_json(404, ['ok' => false, 'message' => 'Không tìm thấy phòng.']);
}
$db = Database::get();

function room_image_sync_cover($db, $roomId) {
    $rows = $db->query("SELECT id_image, file_path, is_cover FROM room_image WHERE id_room = ? ORDER BY is_cover DESC, sort_order ASC, id_image ASC", [(int) $roomId]);
    if ($rows) {
        $hasCover = false;
        foreach ($rows as $row) { if ((int) $row['is_cover'] === 1) { $hasCover = true; break; } }
        if (!$hasCover) {
            $db->exec("UPDATE room_image SET is_cover = 1 WHERE id_image = ? AND id_room = ?", [(int) $rows[0]['id_image'], (int) $roomId]);
            $rows = $db->query("SELECT id_image, file_path, is_cover FROM room_image WHERE id_room = ? ORDER BY is_cover DESC, sort_order ASC, id_image ASC", [(int) $roomId]);
        }
    }
    $coverPath = $rows ? basename($rows[0]['file_path']) : null;
    $db->exec("UPDATE room SET image_path = ? WHERE id_room = ?", [$coverPath, (int) $roomId]);
    return $rows;
}

function room_image_response_data($db, $roomId, $roomM) {
    $rows = $roomM->images((int) $roomId);
    $images = [];
    foreach ($rows as $row) {
        $images[] = [
            'id' => (int) $row['id_image'],
            'name' => (string) ($row['original_name'] ?: 'Ảnh phòng'),
            'url' => url('phong/anh.php?id=' . (int) $roomId . '&image_id=' . (int) $row['id_image']),
            'is_cover' => (int) $row['is_cover'] === 1,
        ];
    }
    $cover = $roomM->coverImage((int) $roomId);
    if ($cover) {
        $coverUrl = url('phong/anh.php?id=' . (int) $roomId . '&image_id=' . (int) $cover['id_image']);
    } else {
        $currentRoom = $roomM->find((int) $roomId);
        $coverUrl = !empty($currentRoom['image_path'])
            ? url('phong/anh.php?id=' . (int) $roomId)
            : url('assets/images/room-vacant.svg');
    }
    return ['images' => $images, 'count' => count($images), 'cover_url' => $coverUrl];
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'upload') {
    $files = $_FILES['room_images'] ?? null;
    if (!$files || !isset($files['name']) || !is_array($files['name'])) {
        room_image_json(400, ['ok' => false, 'message' => 'Bạn chưa chọn ảnh để tải lên.']);
    }
    $selected = [];
    foreach ($files['name'] as $i => $name) {
        if ((string) $name !== '') { $selected[] = (int) $i; }
    }
    if (!$selected) { room_image_json(400, ['ok' => false, 'message' => 'Bạn chưa chọn ảnh để tải lên.']); }
    if (count($selected) > 10) { room_image_json(400, ['ok' => false, 'message' => 'Mỗi lần chỉ được tải tối đa 10 ảnh.']); }

    $prepared = [];
    $uploadErrors = [];
    $maxBytes = 50 * 1024 * 1024;
    foreach ($selected as $i) {
        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $uploadErrors[] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Có ảnh vượt giới hạn dung lượng máy chủ (tối đa 50MB/ảnh).'
                : 'Không thể tải một ảnh lên (mã lỗi ' . $error . ').';
            continue;
        }
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        $size = (int) ($files['size'][$i] ?? 0);
        if (!$tmp || !is_uploaded_file($tmp)) { $uploadErrors[] = 'Tệp tải lên không hợp lệ.'; continue; }
        if ($size <= 0 || $size > $maxBytes) { $uploadErrors[] = 'Mỗi ảnh phải lớn hơn 0 và không quá 50MB.'; continue; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext || !$info) { $uploadErrors[] = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ.'; continue; }
        $prepared[] = [
            'tmp' => $tmp,
            'size' => $size,
            'ext' => $ext,
            'original_name' => mb_substr(basename((string) ($files['name'][$i] ?? 'anh-phong')), 0, 190),
        ];
    }
    if ($uploadErrors) { room_image_json(422, ['ok' => false, 'message' => implode(' ', $uploadErrors)]); }
    if (!is_dir(ROOM_UPLOAD_DIR) && !mkdir(ROOM_UPLOAD_DIR, 0755, true)) {
        room_image_json(500, ['ok' => false, 'message' => 'Không tạo được thư mục lưu ảnh.']);
    }

    $moved = [];
    try {
        foreach ($prepared as $photo) {
            $stored = bin2hex(random_bytes(16)) . '.' . $photo['ext'];
            if (!move_uploaded_file($photo['tmp'], ROOM_UPLOAD_DIR . $stored)) {
                throw new RuntimeException('Không lưu được một ảnh phòng.');
            }
            $moved[] = [
                'file_path' => $stored,
                'original_name' => $photo['original_name'],
                'size_bytes' => $photo['size'],
            ];
        }

        $db->begin();
        // Tương thích ảnh đại diện cũ: đưa ảnh cũ vào thư viện trước khi thêm ảnh mới.
        $currentCount = (int) $db->value("SELECT COUNT(*) FROM room_image WHERE id_room = ?", [(int) $roomId]);
        if ($currentCount === 0 && !empty($room['image_path'])) {
            $legacyName = basename((string) $room['image_path']);
            $legacyFile = ROOM_UPLOAD_DIR . $legacyName;
            if ($legacyName !== '' && is_file($legacyFile)) {
                $db->exec("INSERT IGNORE INTO room_image (id_room, file_path, original_name, size_bytes, is_cover, sort_order) VALUES (?, ?, 'Ảnh phòng cũ', ?, 1, 0)",
                    [(int) $roomId, $legacyName, (int) filesize($legacyFile)]);
                $currentCount = (int) $db->value("SELECT COUNT(*) FROM room_image WHERE id_room = ?", [(int) $roomId]);
            }
        }
        $nextOrder = (int) $db->value("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM room_image WHERE id_room = ?", [(int) $roomId]);
        foreach ($moved as $photo) {
            $isCover = $currentCount === 0 ? 1 : 0;
            $db->exec("INSERT INTO room_image (id_room, file_path, original_name, size_bytes, is_cover, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [(int) $roomId, $photo['file_path'], $photo['original_name'], $photo['size_bytes'], $isCover, $nextOrder++]);
            $currentCount++;
        }
        room_image_sync_cover($db, (int) $roomId);
        $db->commit();
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignore) {}
        foreach ($moved as $photo) {
            $path = ROOM_UPLOAD_DIR . basename($photo['file_path']);
            if (is_file($path)) { @unlink($path); }
        }
        room_image_json(500, ['ok' => false, 'message' => mysql_error_text($e)]);
    }

    $data = room_image_response_data($db, (int) $roomId, $roomM);
    room_image_json(200, array_merge(['ok' => true, 'message' => 'Đã thêm ảnh vào thư viện phòng.'], $data));
}

if ($action === 'delete') {
    $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT);
    if ($imageId === false || $imageId === null || $imageId < 0) {
        room_image_json(400, ['ok' => false, 'message' => 'Mã ảnh không hợp lệ.']);
    }
    $deletedPath = '';
    try {
        $db->begin();
        if ($imageId > 0) {
            $image = $db->one("SELECT file_path FROM room_image WHERE id_image = ? AND id_room = ?", [(int) $imageId, (int) $roomId]);
            if (!$image) {
                $db->rollback();
                room_image_json(404, ['ok' => false, 'message' => 'Ảnh không tồn tại trong phòng này.']);
            }
            $deletedPath = basename($image['file_path']);
            $db->exec("DELETE FROM room_image WHERE id_image = ? AND id_room = ?", [(int) $imageId, (int) $roomId]);
        } else {
            // Xóa ảnh đại diện cũ chưa được chuyển vào bảng room_image.
            $hasLibrary = (int) $db->value("SELECT COUNT(*) FROM room_image WHERE id_room = ?", [(int) $roomId]);
            if ($hasLibrary > 0 || empty($room['image_path'])) {
                $db->rollback();
                room_image_json(404, ['ok' => false, 'message' => 'Ảnh không tồn tại trong phòng này.']);
            }
            $deletedPath = basename((string) $room['image_path']);
        }
        room_image_sync_cover($db, (int) $roomId);
        $db->commit();
        if ($deletedPath !== '') {
            $stillUsed = (int) $db->value(
                "SELECT (SELECT COUNT(*) FROM room_image WHERE file_path = ?) + (SELECT COUNT(*) FROM room WHERE image_path = ?)",
                [$deletedPath, $deletedPath]
            );
            $file = ROOM_UPLOAD_DIR . basename($deletedPath);
            if ($stillUsed === 0 && is_file($file)) { @unlink($file); }
        }
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignore) {}
        room_image_json(500, ['ok' => false, 'message' => mysql_error_text($e)]);
    }
    $data = room_image_response_data($db, (int) $roomId, $roomM);
    room_image_json(200, array_merge(['ok' => true, 'message' => 'Đã xóa ảnh khỏi thư viện phòng.'], $data));
}

room_image_json(400, ['ok' => false, 'message' => 'Thao tác không hợp lệ.']);
