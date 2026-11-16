<?php
// dich_vu/tam_tru_anh.php - Trả ảnh CCCD (kiểm tra quyền trước khi cho xem; thư mục uploads không truy cập trực tiếp)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin', 'chutro', 'nguoithue']);
$r = (new Renter())->find((int) qs('id', '0'));
if (!$r || !$r['id_card_image'] || (Auth::is('nguoithue') && (int) $r['id_user'] !== Auth::id())) { http_response_code(404); die('Không tìm thấy ảnh'); }
$file = UPLOAD_DIR . basename($r['id_card_image']);
if (!is_file($file)) { http_response_code(404); die('Không tìm thấy ảnh'); }
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
