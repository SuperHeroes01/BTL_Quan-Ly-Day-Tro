-- Migration V6.8: tạo bảng nhật ký hoạt động và bảo đảm bảng thu hồi username tồn tại.
-- Sao lưu database trước khi chạy. Chọn database quanlytro trong phpMyAdmin rồi Import file này.
-- Có thể chạy lại nhiều lần vì sử dụng CREATE TABLE IF NOT EXISTS.

USE quanlytro;

CREATE TABLE IF NOT EXISTS deleted_username (
  username          VARCHAR(50) NOT NULL PRIMARY KEY,
  original_user_id  INT NULL,
  deleted_by        INT NULL,
  deleted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_deleted_username_date (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Không tạo khóa ngoại tới user: nhật ký cần được giữ lại ngay cả khi tài khoản bị xóa.
CREATE TABLE IF NOT EXISTS activity_logs (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_user         INT NULL,
  username        VARCHAR(80) NOT NULL,
  hanh_dong       VARCHAR(500) NOT NULL,
  loai_hanh_dong  VARCHAR(40) NOT NULL DEFAULT 'SYSTEM',
  ip_address      VARCHAR(45) NULL,
  thoi_gian       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_logs_time (thoi_gian),
  KEY idx_activity_logs_type_time (loai_hanh_dong, thoi_gian),
  KEY idx_activity_logs_user_time (id_user, thoi_gian),
  KEY idx_activity_logs_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
