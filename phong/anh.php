<?php
// Phát ảnh sau khi kiểm tra quyền. Khách vãng lai chỉ xem ảnh phòng đang trống.
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int) qs('id', '0');
$imageId = (int) qs('image_id', '0');
$room = (new Room())->find($id);
if (!$room) { http_response_code(404); exit('Không tìm thấy phòng'); }

if (!Auth::check()) {
    if ($room['status'] !== 'Trống') { http_response_code(403); exit('Ảnh này không được công khai'); }
} elseif (Auth::is('admin') || Auth::is('chutro')) {
    // Admin và Chủ trọ được xem ảnh mọi phòng.
} elseif (Auth::is('nguoithue')) {
    $mine = (new Renter())->findByUser(Auth::id());
    if (!$mine || (int) $mine['id_room'] !== $id) { http_response_code(403); exit('Bạn không có quyền xem ảnh phòng này'); }
} else {
    http_response_code(403); exit('Bạn không có quyền xem ảnh phòng');
}

if ($imageId > 0) {
    $image = Database::get()->one("SELECT file_path FROM room_image WHERE id_image = ? AND id_room = ?", [$imageId, $id]);
    $name = $image ? basename($image['file_path']) : '';
} else {
    $cover = (new Room())->coverImage($id);
    $name = $cover ? basename($cover['file_path']) : basename((string) ($room['image_path'] ?? ''));
}
$file = $name !== '' ? ROOM_UPLOAD_DIR . $name : '';
if (!$file || !is_file($file)) { http_response_code(404); exit('Ảnh phòng chưa được tải lên'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(415); exit('Định dạng ảnh không hợp lệ'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
exit;
