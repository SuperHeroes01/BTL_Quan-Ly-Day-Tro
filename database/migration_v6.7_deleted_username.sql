-- Migration V6.7: giữ chỗ tên đăng nhập đã bị thu hồi sau khi xóa tài khoản.
-- Chạy một lần trên database quanlytro hiện có bằng phpMyAdmin > Import.
CREATE TABLE IF NOT EXISTS deleted_username (
  username          VARCHAR(50) NOT NULL PRIMARY KEY,
  original_user_id  INT NULL,
  deleted_by        INT NULL,
  deleted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_deleted_username_date (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
