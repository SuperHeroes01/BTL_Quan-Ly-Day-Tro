-- =====================================================================
-- quanlytro_full.sql - BẢN SQL TỔNG HỢP HOÀN CHỈNH
-- Bao gồm: cấu trúc mới nhất + các cập nhật V2/V3 + dữ liệu mẫu ban đầu.
--
-- CÁCH DÙNG:
--   1) Sao lưu database quanlytro trước khi Import.
--   2) phpMyAdmin > Import > chọn file này > Go.
--   3) File tự chọn database quanlytro và tạo các bảng/cột còn thiếu.
--
-- AN TOÀN DỮ LIỆU:
--   * KHÔNG có lệnh DROP DATABASE / DROP TABLE / TRUNCATE.
--   * CREATE TABLE IF NOT EXISTS: không tạo lại bảng đã tồn tại.
--   * Các cột nâng cấp chỉ được thêm nếu chưa có.
--   * Dữ liệu mẫu chỉ được thêm khi toàn bộ các bảng nghiệp vụ còn trống.
--   * Khi database đã có dữ liệu, script giữ các bản ghi đó và chỉ chạy
--     một số cập nhật tương thích: điền thông tin khôi phục còn thiếu,
--     giá thuê đang bằng 0, và đồng bộ thư viện ảnh cũ.
--
-- LƯU Ý:
--   * File này chứa dữ liệu mẫu có sẵn trong bộ project, KHÔNG chứa dữ liệu
--     thực tế từ MySQL trên máy cá nhân của bạn (chưa được Export).
--   * Ảnh/tệp tải lên nằm trong thư mục uploads/ trên ổ đĩa, không nằm trong
--     SQL; hãy giữ nguyên/sao chép thư mục uploads/ khi triển khai.
--   * Không chạy quanlytro.sql cũ cùng với file này; chỉ cần Import file này.
-- =====================================================================

-- =====================================================================
-- quanlytro.sql - Cơ sở dữ liệu hệ thống quản lý dãy trọ (MySQL / MariaDB)
-- Cách dùng: phpMyAdmin > Import, hoặc: mysql -u root < quanlytro.sql
-- =====================================================================
CREATE DATABASE IF NOT EXISTS quanlytro DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quanlytro;
SET NAMES utf8mb4;

-- 1. Người dùng (tài khoản đăng nhập, phân quyền)
CREATE TABLE IF NOT EXISTS `user` (
  id_user    INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','chutro','nguoithue','nhanvien') NOT NULL DEFAULT 'nguoithue',
  full_name  VARCHAR(100) NOT NULL,
  recovery_phone VARCHAR(20) NULL,
  recovery_identity_card VARCHAR(20) NULL,
  status     ENUM('Hoạt động','Khóa') NOT NULL DEFAULT 'Hoạt động',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Phòng trọ
CREATE TABLE IF NOT EXISTS room (
  id_room     INT AUTO_INCREMENT PRIMARY KEY,
  room_number VARCHAR(20) NOT NULL UNIQUE,
  area        FLOAT NULL,
  price       DECIMAL(12,0) NOT NULL,
  status      ENUM('Trống','Đã thuê','Bảo trì') NOT NULL DEFAULT 'Trống',
  description TEXT NULL,
  amenities   TEXT NULL,
  image_path VARCHAR(255) NULL
) ENGINE=InnoDB;

-- Thư viện nhiều ảnh thực tế cho từng phòng (dùng cho slideshow)
CREATE TABLE IF NOT EXISTS room_image (
  id_image      INT AUTO_INCREMENT PRIMARY KEY,
  id_room       INT NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NULL,
  size_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_cover      TINYINT(1) NOT NULL DEFAULT 0,
  sort_order    INT NOT NULL DEFAULT 0,
  uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_room_image_file (id_room, file_path),
  KEY idx_room_image_order (id_room, is_cover, sort_order),
  CONSTRAINT fk_room_image_room FOREIGN KEY (id_room) REFERENCES room(id_room) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Người thuê (hồ sơ + hợp đồng thuê). end_date là ngày trả phòng, NULL = chưa xác định
CREATE TABLE IF NOT EXISTS renter (
  id_renter        INT AUTO_INCREMENT PRIMARY KEY,
  id_user          INT NOT NULL UNIQUE,
  id_room          INT NOT NULL,
  identity_card    VARCHAR(20) NOT NULL,
  birth_date       DATE NULL,
  registered_address VARCHAR(255) NULL,
  identity_issue_date DATE NULL,
  identity_issue_place VARCHAR(150) NULL,
  phone            VARCHAR(20) NOT NULL,
  hometown         VARCHAR(100) NULL,
  payment_method   VARCHAR(100) NULL,
  start_date       DATE NOT NULL,
  end_date         DATE NULL,
  deposit          DECIMAL(12,0) NOT NULL DEFAULT 0,
  rent_price       DECIMAL(12,0) NOT NULL DEFAULT 0,
  id_card_image    VARCHAR(255) NULL,
  residence_status ENUM('Chưa đăng ký','Chờ xử lý','Đã đăng ký') NOT NULL DEFAULT 'Chưa đăng ký',
  FOREIGN KEY (id_user) REFERENCES `user`(id_user),
  FOREIGN KEY (id_room) REFERENCES room(id_room)
) ENGINE=InnoDB;

-- 4. Dịch vụ và đơn giá
CREATE TABLE IF NOT EXISTS service (
  id_service   INT AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(20)  NOT NULL UNIQUE,
  service_name VARCHAR(100) NOT NULL,
  unit_price   DECIMAL(12,0) NOT NULL,
  unit         VARCHAR(20) NULL,
  type         ENUM('Theo chỉ số','Cố định') NOT NULL DEFAULT 'Cố định',
  description  TEXT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 5. Hóa đơn tháng của từng phòng
CREATE TABLE IF NOT EXISTS bill (
  id_bill        INT AUTO_INCREMENT PRIMARY KEY,
  id_room        INT NOT NULL,
  month          CHAR(7) NOT NULL,
  date_created   DATE NOT NULL,
  due_date       DATE NOT NULL,
  total_amount   DECIMAL(12,0) NOT NULL DEFAULT 0,
  payment_status ENUM('Chưa thu','Đã thu') NOT NULL DEFAULT 'Chưa thu',
  paid_date      DATE NULL,
  reminded_at    DATETIME NULL,
  note           TEXT NULL,
  UNIQUE KEY uq_bill_room_month (id_room, month),
  FOREIGN KEY (id_room) REFERENCES room(id_room)
) ENGINE=InnoDB;

-- 6. Hóa đơn chi tiết (từng khoản trong hóa đơn)
CREATE TABLE IF NOT EXISTS itemized_bill (
  id_itemized INT AUTO_INCREMENT PRIMARY KEY,
  id_bill     INT NOT NULL,
  id_service  INT NULL,
  item_name   VARCHAR(100) NOT NULL,
  old_index   INT NULL,
  new_index   INT NULL,
  quantity    DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price  DECIMAL(12,0) NOT NULL,
  amount      DECIMAL(12,0) NOT NULL,
  FOREIGN KEY (id_bill) REFERENCES bill(id_bill) ON DELETE CASCADE,
  FOREIGN KEY (id_service) REFERENCES service(id_service)
) ENGINE=InnoDB;

-- 7. Thiết bị (id_room NULL = thiết bị dùng chung / trong kho)
CREATE TABLE IF NOT EXISTS device (
  id_device        INT AUTO_INCREMENT PRIMARY KEY,
  device_name      VARCHAR(100) NOT NULL,
  id_room          INT NULL,
  status           ENUM('Hoạt động','Hỏng','Đang sửa') NOT NULL DEFAULT 'Hoạt động',
  last_maintenance DATE NULL,
  quantity         INT NOT NULL DEFAULT 1,
  note             TEXT NULL,
  FOREIGN KEY (id_room) REFERENCES room(id_room)
) ENGINE=InnoDB;

-- 8. Nhân viên kỹ thuật (id_user = tài khoản đăng nhập của nhân viên, nếu có)
CREATE TABLE IF NOT EXISTS staff (
  id_staff   INT AUTO_INCREMENT PRIMARY KEY,
  staff_name VARCHAR(100) NOT NULL,
  specialty  VARCHAR(100) NULL,
  phone      VARCHAR(20) NULL,
  email      VARCHAR(100) NULL UNIQUE,
  status     ENUM('Đang làm','Nghỉ') NOT NULL DEFAULT 'Đang làm',
  id_user    INT NULL UNIQUE,
  FOREIGN KEY (id_user) REFERENCES `user`(id_user)
) ENGINE=InnoDB;

-- 9. Yêu cầu sửa chữa thiết bị
CREATE TABLE IF NOT EXISTS repair_request (
  id_request  INT AUTO_INCREMENT PRIMARY KEY,
  id_device   INT NOT NULL,
  id_user     INT NOT NULL,
  description TEXT NOT NULL,
  priority    ENUM('Thấp','Trung bình','Cao') NOT NULL DEFAULT 'Trung bình',
  status      ENUM('Chờ tiếp nhận','Đang sửa','Đã sửa','Cần thay mới') NOT NULL DEFAULT 'Chờ tiếp nhận',
  id_staff    INT NULL,
  result_note TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  FOREIGN KEY (id_device) REFERENCES device(id_device),
  FOREIGN KEY (id_user)   REFERENCES `user`(id_user),
  FOREIGN KEY (id_staff)  REFERENCES staff(id_staff)
) ENGINE=InnoDB;

-- 10. Cấu hình hệ thống
CREATE TABLE IF NOT EXISTS setting (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;


-- =====================================================================
-- 11. NÂNG CẤP CỘT CŨ (chạy an toàn nhiều lần)
-- =====================================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS quanlytro_apply_schema_updates$$
CREATE PROCEDURE quanlytro_apply_schema_updates()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'recovery_phone') THEN
    ALTER TABLE `user` ADD COLUMN recovery_phone VARCHAR(20) NULL AFTER full_name;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'recovery_identity_card') THEN
    ALTER TABLE `user` ADD COLUMN recovery_identity_card VARCHAR(20) NULL AFTER recovery_phone;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'room' AND COLUMN_NAME = 'image_path') THEN
    ALTER TABLE room ADD COLUMN image_path VARCHAR(255) NULL AFTER description;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'room' AND COLUMN_NAME = 'amenities') THEN
    ALTER TABLE room ADD COLUMN amenities TEXT NULL AFTER description;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'rent_price') THEN
    ALTER TABLE renter ADD COLUMN rent_price DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER deposit;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'birth_date') THEN
    ALTER TABLE renter ADD COLUMN birth_date DATE NULL AFTER identity_card;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'registered_address') THEN
    ALTER TABLE renter ADD COLUMN registered_address VARCHAR(255) NULL AFTER birth_date;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'identity_issue_date') THEN
    ALTER TABLE renter ADD COLUMN identity_issue_date DATE NULL AFTER registered_address;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'identity_issue_place') THEN
    ALTER TABLE renter ADD COLUMN identity_issue_place VARCHAR(150) NULL AFTER identity_issue_date;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renter' AND COLUMN_NAME = 'payment_method') THEN
    ALTER TABLE renter ADD COLUMN payment_method VARCHAR(100) NULL AFTER hometown;
  END IF;
END$$
CALL quanlytro_apply_schema_updates()$$
DROP PROCEDURE quanlytro_apply_schema_updates$$
DELIMITER ;

-- =====================================================================
-- 12. DỮ LIỆU MẪU: chỉ tạo khi database thực sự chưa có dữ liệu nghiệp vụ
-- =====================================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS quanlytro_seed_if_empty$$
CREATE PROCEDURE quanlytro_seed_if_empty()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM `user`)
     AND NOT EXISTS (SELECT 1 FROM room)
     AND NOT EXISTS (SELECT 1 FROM room_image)
     AND NOT EXISTS (SELECT 1 FROM renter)
     AND NOT EXISTS (SELECT 1 FROM service)
     AND NOT EXISTS (SELECT 1 FROM bill)
     AND NOT EXISTS (SELECT 1 FROM itemized_bill)
     AND NOT EXISTS (SELECT 1 FROM device)
     AND NOT EXISTS (SELECT 1 FROM staff)
     AND NOT EXISTS (SELECT 1 FROM repair_request)
     AND NOT EXISTS (SELECT 1 FROM setting)
  THEN

-- Tài khoản: admin/admin123, chutro/chutro123, kythuat/kythuat123, nguyenvana/123456 ...
INSERT INTO `user` (username,password,role,full_name) VALUES
  ('admin','$2y$10$W98xLCAmOQHplhcYkpxqZuy/HKYYnvMdJyRsmRZ7MOAASLd3Fv/oq','admin','Quản Trị Viên'),
  ('chutro','$2y$10$bjLdVyvSHT560MVRqrtW6eBiqukEX3sbaZkV7Z1FbdReYr.wC/YlO','chutro','Nguyễn Văn Chủ'),
  ('kythuat','$2y$10$GOAn5WvP3g0DIYvFIWQXe.XErAUDHjxHIxZcOqMyI/InmHTEL0ePa','nhanvien','Lê Văn Cường'),
  ('nguyenvana','$2y$10$SbMyT.eA38DWP6ziPppUu.KdIOzsynZiPuomxUxJGbFlQ0zmwS7aG','nguoithue','Nguyễn Văn A'),
  ('tranthib','$2y$10$i/LnXk/Ef/3QKY2QEnPftuKwJVxvNhAjPAGcjLeU5sxqGSBUw.S0m','nguoithue','Trần Thị B'),
  ('levanc','$2y$10$xCzWTGlCPdVLSMPi5sEFmujynRtxwNDjNQ1W4x7IyNnJUoLVYJW6q','nguoithue','Lê Văn C'),
  ('phamthid','$2y$10$FFpYsMuxJz6Y3AilFahBjuauw6QdHj5z/Md11VJ/BnFgftW17ujh.','nguoithue','Phạm Thị D'),
  ('hoangvane','$2y$10$/MDvXRr826uVrionedwxauki8aWGrbhDz.dUk.qd0ja20BTnspV96','nguoithue','Hoàng Văn E');

INSERT INTO room (room_number,area,price,status,description) VALUES
  ('P101',20,2500000,'Đã thuê','Phòng 1 giường, có cửa sổ'),
  ('P102',20,2500000,'Đã thuê','Có gác lửng'),
  ('P103',20,2500000,'Đã thuê','Gần cầu thang'),
  ('P104',20,2500000,'Trống','Có ban công'),
  ('P105',20,2500000,'Trống','Phòng góc, thoáng'),
  ('P106',20,2500000,'Bảo trì','Đang sơn sửa lại'),
  ('P201',24,2700000,'Đã thuê','Phòng 1 giường, có cửa sổ'),
  ('P202',24,2700000,'Trống','Có gác lửng'),
  ('P203',24,2700000,'Trống','Gần cầu thang'),
  ('P204',24,2700000,'Trống','Có ban công'),
  ('P205',24,2700000,'Trống','Phòng góc, thoáng'),
  ('P206',24,2700000,'Trống','Đang sơn sửa lại');

INSERT INTO renter (id_user,id_room,identity_card,phone,hometown,start_date,end_date,deposit,residence_status) VALUES
  (4,1,'079123456789','0912345678','Hà Nội','2025-01-01',NULL,2500000,'Đã đăng ký'),
  (5,2,'038987654321','0923456789','Nam Định','2025-02-10','2027-02-10',2500000,'Chưa đăng ký'),
  (6,3,'042123456321','0934567890','Nghệ An','2025-03-01',CURDATE() + INTERVAL 20 DAY,2500000,'Chưa đăng ký'),
  (7,7,'001234567890','0945678901','Thái Bình','2025-05-15',NULL,2700000,'Đã đăng ký'),
  (8,7,'001987654321','0956789012','Thanh Hóa','2025-05-15',NULL,0,'Chưa đăng ký');


INSERT INTO service (code,service_name,unit_price,unit,type,description) VALUES
  ('dien','Tiền điện',3500,'kWh','Theo chỉ số','Tính theo chỉ số công tơ điện từng phòng'),
  ('nuoc','Tiền nước',15000,'m³','Theo chỉ số','Tính theo chỉ số đồng hồ nước từng phòng'),
  ('mang','Tiền mạng',100000,'tháng','Cố định','Internet wifi dùng chung'),
  ('vesinh','Vệ sinh chung',30000,'tháng','Cố định','Phí vệ sinh khu vực chung, thu rác'),
  ('maygiat','Máy giặt',50000,'tháng','Cố định','Phí sử dụng máy giặt dùng chung');

INSERT INTO staff (staff_name,specialty,phone,email,status,id_user) VALUES
  ('Lê Văn Cường','Điện lạnh','0967890123','cuong.kt@example.com','Đang làm',3),
  ('Phạm Minh Tuấn','Điện nước','0978901234','tuan.kt@example.com','Đang làm',NULL);

INSERT INTO device (device_name,id_room,status,last_maintenance,quantity,note) VALUES
  ('Điều hòa Daikin 1.5HP',1,'Hoạt động',CURDATE() - INTERVAL 60 DAY,1,'Bảo hành đến hết 2027'),
  ('Bình nóng lạnh Ariston',2,'Hỏng',CURDATE() - INTERVAL 200 DAY,1,'Không lên nhiệt'),
  ('Điều hòa Panasonic 1HP',3,'Hoạt động',CURDATE() - INTERVAL 90 DAY,1,NULL),
  ('Quạt trần',7,'Hoạt động',NULL,2,NULL),
  ('Máy giặt Samsung 9kg',NULL,'Đang sửa',CURDATE() - INTERVAL 120 DAY,1,'Khu giặt dùng chung'),
  ('Router TP-Link',NULL,'Hoạt động',CURDATE() - INTERVAL 30 DAY,2,'Wifi tầng 1 và tầng 2');

INSERT INTO repair_request (id_device,id_user,description,priority,status,id_staff,created_at) VALUES
  (2,5,'Bình nóng lạnh không lên nhiệt, nước chỉ ấm','Cao','Chờ tiếp nhận',NULL,NOW() - INTERVAL 3 HOUR),
  (5,2,'Máy giặt rung mạnh, kêu to khi vắt','Trung bình','Đang sửa',1,NOW() - INTERVAL 1 DAY);

INSERT INTO setting (setting_key,setting_value) VALUES
  ('ten_day_tro','Dãy Trọ Phúc An'),
  ('dia_chi','123 Ngô Thì Nhậm, Hà Đông, Hà Nội'),
  ('dien_thoai','0901234567'),
  ('email','daytro@example.com'),
  ('payment_days','10'),
  ('meter_day','28'),
  ('chu_tro_ho_ten',''),
  ('chu_tro_ngay_sinh',''),
  ('chu_tro_ho_khau',''),
  ('chu_tro_cccd',''),
  ('chu_tro_ngay_cap',''),
  ('chu_tro_noi_cap',''),
  ('chu_tro_dien_thoai','');

SET @m0 = DATE_FORMAT(CURDATE(), '%Y-%m');
SET @m1 = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m');
SET @m2 = DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m');

INSERT INTO bill (id_bill,id_room,month,date_created,due_date,total_amount,payment_status,paid_date,reminded_at,note) VALUES
  (1,1,@m2,CONCAT(@m2,'-28'),CONCAT(@m2,'-28') + INTERVAL 10 DAY,2932500,'Đã thu',CONCAT(@m2,'-12'),NULL,NULL),
  (2,2,@m2,CONCAT(@m2,'-28'),CONCAT(@m2,'-28') + INTERVAL 10 DAY,2950000,'Đã thu',CONCAT(@m2,'-12'),NULL,NULL),
  (3,3,@m2,CONCAT(@m2,'-28'),CONCAT(@m2,'-28') + INTERVAL 10 DAY,2815000,'Đã thu',CONCAT(@m2,'-12'),NULL,NULL),
  (4,7,@m2,CONCAT(@m2,'-28'),CONCAT(@m2,'-28') + INTERVAL 10 DAY,3265000,'Đã thu',CONCAT(@m2,'-12'),NULL,NULL),
  (5,1,@m1,CONCAT(@m1,'-28'),CONCAT(@m1,'-28') + INTERVAL 10 DAY,2932500,'Đã thu',CONCAT(@m1,'-12'),NULL,NULL),
  (6,2,@m1,CONCAT(@m1,'-28'),CONCAT(@m1,'-28') + INTERVAL 10 DAY,2950000,'Đã thu',CONCAT(@m1,'-12'),NULL,NULL),
  (7,3,@m1,CONCAT(@m1,'-28'),CONCAT(@m1,'-28') + INTERVAL 10 DAY,2815000,'Chưa thu',NULL,NOW() - INTERVAL 1 DAY,NULL),
  (8,7,@m1,CONCAT(@m1,'-28'),CONCAT(@m1,'-28') + INTERVAL 10 DAY,3265000,'Đã thu',CONCAT(@m1,'-12'),NULL,NULL),
  (9,1,@m0,CURDATE(),CURDATE() + INTERVAL 10 DAY,2932500,'Đã thu',CURDATE(),NULL,NULL),
  (10,2,@m0,CURDATE(),CURDATE() + INTERVAL 10 DAY,2950000,'Chưa thu',NULL,NULL,NULL);

INSERT INTO itemized_bill (id_bill,id_service,item_name,old_index,new_index,quantity,unit_price,amount) VALUES
  (1,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (1,1,'Tiền điện',100,155,55,3500,192500),
  (1,2,'Tiền nước',10,14,4,15000,60000),
  (1,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (1,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (1,5,'Máy giặt',NULL,NULL,1,50000,50000),
  (2,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (2,1,'Tiền điện',200,270,70,3500,245000),
  (2,2,'Tiền nước',30,35,5,15000,75000),
  (2,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (2,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (3,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (3,1,'Tiền điện',50,90,40,3500,140000),
  (3,2,'Tiền nước',5,8,3,15000,45000),
  (3,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (3,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (4,NULL,'Tiền phòng',NULL,NULL,1,2700000,2700000),
  (4,1,'Tiền điện',300,390,90,3500,315000),
  (4,2,'Tiền nước',40,48,8,15000,120000),
  (4,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (4,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (5,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (5,1,'Tiền điện',155,210,55,3500,192500),
  (5,2,'Tiền nước',14,18,4,15000,60000),
  (5,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (5,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (5,5,'Máy giặt',NULL,NULL,1,50000,50000),
  (6,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (6,1,'Tiền điện',270,340,70,3500,245000),
  (6,2,'Tiền nước',35,40,5,15000,75000),
  (6,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (6,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (7,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (7,1,'Tiền điện',90,130,40,3500,140000),
  (7,2,'Tiền nước',8,11,3,15000,45000),
  (7,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (7,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (8,NULL,'Tiền phòng',NULL,NULL,1,2700000,2700000),
  (8,1,'Tiền điện',390,480,90,3500,315000),
  (8,2,'Tiền nước',48,56,8,15000,120000),
  (8,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (8,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (9,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (9,1,'Tiền điện',210,265,55,3500,192500),
  (9,2,'Tiền nước',18,22,4,15000,60000),
  (9,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (9,4,'Vệ sinh chung',NULL,NULL,1,30000,30000),
  (9,5,'Máy giặt',NULL,NULL,1,50000,50000),
  (10,NULL,'Tiền phòng',NULL,NULL,1,2500000,2500000),
  (10,1,'Tiền điện',340,410,70,3500,245000),
  (10,2,'Tiền nước',40,45,5,15000,75000),
  (10,3,'Tiền mạng',NULL,NULL,1,100000,100000),
  (10,4,'Vệ sinh chung',NULL,NULL,1,30000,30000);

  END IF;
END$$
CALL quanlytro_seed_if_empty()$$
DROP PROCEDURE quanlytro_seed_if_empty$$
DELIMITER ;

-- =====================================================================
-- 13. CẬP NHẬT BỔ SUNG CHO DATABASE ĐANG CÓ DỮ LIỆU (có thể chạy lại)
-- =====================================================================
INSERT IGNORE INTO setting (setting_key, setting_value) VALUES
  ('chu_tro_ho_ten',''),
  ('chu_tro_ngay_sinh',''),
  ('chu_tro_ho_khau',''),
  ('chu_tro_cccd',''),
  ('chu_tro_ngay_cap',''),
  ('chu_tro_noi_cap',''),
  ('chu_tro_dien_thoai','');

UPDATE `user` u
JOIN renter t ON t.id_user = u.id_user
SET u.recovery_phone = COALESCE(NULLIF(u.recovery_phone, ''), t.phone),
    u.recovery_identity_card = COALESCE(NULLIF(u.recovery_identity_card, ''), t.identity_card);

UPDATE renter t
JOIN room r ON r.id_room = t.id_room
SET t.rent_price = r.price
WHERE t.rent_price = 0;

-- Chuyển ảnh đại diện cũ vào thư viện ảnh nếu chưa có bản ghi tương ứng.
INSERT IGNORE INTO room_image (id_room, file_path, original_name, is_cover, sort_order)
SELECT id_room, image_path, 'Ảnh phòng đã tải từ phiên bản trước', 1, 0
FROM room
WHERE image_path IS NOT NULL AND image_path <> '';

-- Đồng bộ ảnh bìa từ thư viện ảnh về cột image_path để tương thích phiên bản cũ.
UPDATE room r
SET r.image_path = (
  SELECT ri.file_path
  FROM room_image ri
  WHERE ri.id_room = r.id_room
  ORDER BY ri.is_cover DESC, ri.sort_order ASC, ri.id_image ASC
  LIMIT 1
)
WHERE EXISTS (SELECT 1 FROM room_image ri2 WHERE ri2.id_room = r.id_room);

-- Hoàn tất. Không có lệnh xóa database hay xóa bản ghi nghiệp vụ.
