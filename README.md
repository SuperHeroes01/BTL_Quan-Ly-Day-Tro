# BTL_Quan-Ly-Day-Tro
# Hệ thống quản lý dãy trọ (PHP + MySQL)

Bài tập lớn học phần **Phát triển ứng dụng trên nền Web** – đề tài *Xây dựng phần mềm quản lý dãy trọ trên nền web*. Dự án dùng PHP hướng đối tượng, MySQL/MariaDB, HTML, CSS và JavaScript; chạy bằng Apache trong XAMPP.

## 1. Cài đặt lần đầu (XAMPP trên Windows)
1. Cài XAMPP, mở **XAMPP Control Panel**, bấm **Start** cho **Apache** và **MySQL**.
2. Chép thư mục `quanlytro` vào `C:\xampp\htdocs\` (đường dẫn cuối cùng: `C:\xampp\htdocs\quanlytro\`).
3. Mở `http://localhost/phpmyadmin` → tab **Import** → chọn `database/quanlytro.sql` → **Import**. Tệp này tạo mới CSDL `quanlytro` và dữ liệu mẫu.
4. Nếu MySQL có mật khẩu hoặc khác cổng, sửa `config/config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`).
5. Mở `http://localhost/quanlytro/` hoặc `http://localhost/quanlytro/login.php`.

> **Lưu ý:** `database/quanlytro.sql` có lệnh `DROP DATABASE IF EXISTS quanlytro`; chỉ dùng khi cài mới hoặc khi đã sao lưu và chấp nhận tạo lại dữ liệu. Không import lại tệp này để nâng cấp một cơ sở dữ liệu đang sử dụng.
> Yêu cầu: PHP 7.4 trở lên, bật `mysqli` và `fileinfo` (các extension thường có sẵn trong XAMPP).

## 2. Nâng cấp cơ sở dữ liệu đã có
Nếu bạn đang dùng database `quanlytro` từ phiên bản trước, **sao lưu database trước**, sau đó mở phpMyAdmin, chọn database `quanlytro` → **Import** → chọn `database/cap_nhat_tinh_nang.sql`. Tệp migration bổ sung các cột `user.recovery_phone`, `user.recovery_identity_card`, `room.image_path`, `renter.rent_price` cùng các trường thông tin cá nhân/phục vụ hợp đồng của người thuê (`birth_date`, `registered_address`, `identity_issue_date`, `identity_issue_place`, `payment_method`) và các khóa cấu hình thông tin Bên A. Tệp này không xóa các bảng hoặc hồ sơ đang lưu.

### Cấu hình tải ảnh phòng tối đa 50MB
- Ảnh thực tế của phòng chấp nhận JPG, PNG hoặc WEBP, kích thước tối đa **50MB**.
- Trên XAMPP Windows, mở `C:\xampp\php\php.ini` (hoặc đúng tệp `php.ini` mà Apache đang dùng), chỉnh:
  ```ini
  upload_max_filesize=50M
  post_max_size=520M
  max_file_uploads=10
  max_execution_time=300
  max_input_time=300
  ```
- Lưu tệp và **Stop/Start Apache** trong XAMPP Control Panel. `post_max_size=520M` cho phép gửi tối đa khoảng 10 ảnh x 50MB trong một lần; `upload_max_filesize=50M` vẫn giới hạn từng ảnh. Tệp `.htaccess` ở thư mục dự án cũng khai báo các giới hạn cho Apache dùng mod_php; nếu máy chủ không hỗ trợ `php_value` trong `.htaccess`, hãy cấu hình trực tiếp bằng `php.ini` như trên.

## 3. Nâng cấp V3: trang chi tiết phòng trống và slideshow ảnh
Nếu bạn đang sử dụng phiên bản V2, hãy sao lưu CSDL rồi import `database/cap_nhat_tinh_nang_v3.sql` trong phpMyAdmin. Migration thêm cột `room.amenities`, tạo bảng `room_image` và chuyển ảnh đại diện `room.image_path` cũ vào thư viện nhiều ảnh. Không import lại `quanlytro.sql` trên dữ liệu đang sử dụng.

### Tải nhiều ảnh một lúc
- Vào **Quản lý phòng trọ → Danh sách phòng → Thêm/Sửa phòng**. Chọn nhiều tệp cùng lúc bằng `Ctrl` hoặc `Shift` trong hộp thoại chọn tệp.
- Mỗi lần được chọn tối đa 10 ảnh; từng ảnh JPG/PNG/WEBP tối đa 50MB. Các ảnh được lưu trong `uploads/rooms/` và phát qua `phong/anh.php` sau khi kiểm tra quyền.
- Để gửi tối đa 10 ảnh x 50MB trong một lần, trong `php.ini` cấu hình `upload_max_filesize=50M`, `post_max_size=520M`, `max_file_uploads=10`, `max_execution_time=300`, `max_input_time=300`; sau đó Stop/Start Apache. `post_max_size` là giới hạn tổng request, không phải giới hạn cho từng ảnh. Dự án cũng đã cập nhật `.htaccess` tương ứng cho Apache dùng mod_php.
- Có thể đánh dấu ảnh cần xóa ngay tại thư viện ảnh hiện có; ảnh được đánh dấu sẽ bị xóa khi lưu biểu mẫu.

### Trang công khai xem chi tiết phòng
- Tại trang đăng nhập, chọn **Xem danh sách phòng trống**. Mỗi thẻ phòng có nút **Xem chi tiết**.
- Trang chi tiết hiển thị trạng thái phòng, diện tích, giá thuê, mô tả, đồ dùng/tiện nghi từ trường `room.amenities`, thiết bị được gán trong bảng `device` và thư viện ảnh slideshow.
- Slideshow tự chuyển ảnh sau 5 giây, có nút ảnh trước/tiếp theo và thumbnail; tạm dừng khi rê chuột hoặc focus vào gallery.
- Khách chưa đăng nhập chỉ xem ảnh và chi tiết phòng có trạng thái **Trống**. Admin/Chủ trọ xem được mọi phòng; Người thuê chỉ xem ảnh phòng của mình. Nhân viên kỹ thuật không được truy cập endpoint ảnh riêng.
- Mục đồ dùng/tiện nghi được nhập trong biểu mẫu Thêm/Sửa phòng, mỗi dòng một món (ví dụ: giường, tủ, bàn học, điều hòa, wifi). Thiết bị kỹ thuật được quản lý tại **Quản lý thiết bị** và gán cho phòng tương ứng.

## 4. Tài khoản mẫu
| Vai trò | Tên đăng nhập | Mật khẩu |
|---|---|---|
| Admin | `admin` | `admin123` |
| Chủ trọ | `chutro` | `chutro123` |
| Nhân viên kỹ thuật | `kythuat` | `kythuat123` |
| Người thuê | `nguyenvana`, `tranthib`, `levanc`, `phamthid`, `hoangvane` | `123456` |

Đổi mật khẩu sau khi dùng thử (menu **Tài khoản của tôi**).

## 5. Các chức năng được bổ sung
### Đăng ký và quên mật khẩu
- Trang đăng nhập có liên kết **Đăng ký tài khoản** và **Quên mật khẩu**.
- Khách có thể tự đăng ký tài khoản vai trò **Người thuê**. Tài khoản chưa gắn phòng sẽ hiển thị thông báo chờ Admin/Chủ trọ phân phòng.
- Admin/Chủ trọ vào **Quản lý phòng trọ → Người thuê → Thêm người thuê** và chọn **Gắn tài khoản người thuê đã tự đăng ký** để gán tài khoản vào phòng, tránh tạo trùng tài khoản.
- Chức năng quên mật khẩu ở bản demo xác minh bằng tên đăng nhập, số điện thoại và CCCD/CMND đã đăng ký. Khi triển khai Internet thực tế cần bổ sung OTP qua SMS/email, giới hạn số lần thử và quy trình xác minh an toàn; không nên coi đối chiếu thông tin tĩnh là phương thức bảo mật đầy đủ.

### Ảnh phòng và phân quyền xem ảnh
- Admin/Chủ trọ có thể tải nhiều ảnh thực tế JPG, PNG hoặc WEBP (mỗi ảnh tối đa 50 MB, tối đa 10 ảnh/lần) khi thêm/sửa phòng. Máy chủ kiểm tra lại dung lượng và MIME/định dạng ảnh.
- Admin/Chủ trọ xem ảnh tất cả phòng; Người thuê chỉ xem ảnh phòng của chính mình. Khách vãng lai chỉ xem ảnh phòng đang trống thông qua trang công khai; Nhân viên kỹ thuật không được xem ảnh phòng qua endpoint ảnh riêng.
- Khi chưa tải ảnh thực tế, hệ thống hiển thị hình minh họa cục bộ theo trạng thái **Trống**, **Đã thuê** hoặc **Bảo trì**. Các hình minh họa này không phải ảnh chụp thực tế; hãy tải ảnh từng phòng để thay thế.
- Ảnh được lưu trong `uploads/rooms/`, truy xuất qua `phong/anh.php` sau khi kiểm tra quyền; thư mục có `.htaccess` chặn truy cập trực tiếp.

### Chi tiết hợp đồng thuê theo mẫu PDF
- Danh sách hợp đồng có nút **Xem chi tiết**. Admin/Chủ trọ xem được mọi hồ sơ; Người thuê chỉ xem hợp đồng của mình; Nhân viên kỹ thuật bị từ chối.
- Trang xem/in được bố cục theo đúng mẫu PDF 2 trang: quốc hiệu, tiêu ngữ, tên hợp đồng; thông tin Bên A; thông tin Bên B; thông tin phòng và thỏa thuận giá/điện/nước/đặt cọc/thời hạn; Mục III trách nhiệm Bên A/Bên B; Mục IV trách nhiệm chung; hai khu vực ký tên.
- Admin nhập thông tin Bên A tại **Quản lý hệ thống → Chỉnh sửa hệ thống → Thông tin Bên A**. Admin/Chủ trọ nhập ngày sinh, hộ khẩu thường trú, ngày/nơi cấp CCCD/CMND và hình thức thanh toán của người thuê tại biểu mẫu **Thêm/Sửa người thuê**. Trường chưa có dữ liệu sẽ để dòng chấm trên hợp đồng để bổ sung thủ công, không tự tạo thông tin.
- Đơn giá điện lấy theo cấu hình dịch vụ điện hiện tại trong database (đ/kWh). Mẫu PDF quy định tiền nước theo đầu người (đ/người), trong khi phần lập hóa đơn của ứng dụng hiện cấu hình nước theo m³; vì hai cách tính khác nhau, dòng giá nước trong hợp đồng được để trống đúng theo mẫu để hai bên điền, không tự quy đổi đơn giá. Hợp đồng có thể in bằng nút **In hợp đồng**.
- Nội dung điều khoản được lấy theo mẫu PDF người dùng gửi; hệ thống không tự thêm điều khoản mới hoặc tự điền thông tin pháp lý chưa được cung cấp.

### Tự động kế thừa chỉ số điện/nước
- Khi lập hóa đơn tháng mới, chỉ số cũ được lấy từ chỉ số mới gần nhất đã lưu của cùng phòng và cùng dịch vụ. Ví dụ tháng 10 lưu chỉ số mới 450 thì tháng 11 tự dùng chỉ số cũ 450.
- Máy chủ PHP tự truy vấn và áp dụng chỉ số đã lưu, không chỉ tin giá trị gửi từ trình duyệt. Nếu phòng chưa có lần ghi chỉ số nào, người lập hóa đơn cần nhập chỉ số ban đầu.

### Admin chỉnh sửa giá thuê phòng
- Vào **Quản lý hệ thống → Chỉnh sửa hệ thống → Cập nhật giá thuê phòng trọ** để sửa giá riêng từng phòng.
- Giá phòng mới được dùng khi lập hóa đơn từ sau thời điểm cập nhật. Hồ sơ hợp đồng và hóa đơn cũ giữ nguyên giá đã ghi nhận.

## 6. Cấu trúc thư mục
```
quanlytro/
├── index.php                 Trang công khai xem phòng trống
├── phong_chi_tiet.php        Chi tiết phòng công khai và slideshow ảnh
├── login.php, register.php, quen_mat_khau.php, logout.php
├── home.php, tai_khoan.php
├── config/                   Cấu hình kết nối CSDL
├── includes/                 bootstrap, hàm tiện ích, CSRF, phân quyền, giao diện chung
├── classes/                  Các lớp xử lý dữ liệu
├── dich_vu/                  Dịch vụ, chỉ số điện nước, hóa đơn, tạm trú
├── phong/                    Phòng, ảnh phòng, hợp đồng, người thuê
├── thiet_bi/                 Thiết bị, báo lỗi, tiếp nhận sửa chữa
├── he_thong/                 Phân quyền, cấu hình hệ thống, nhân viên
├── assets/                   CSS, JavaScript và hình minh họa cục bộ
├── database/quanlytro.sql    Script cài mới
├── database/cap_nhat_tinh_nang.sql  Migration phiên bản V2
├── database/cap_nhat_tinh_nang_v3.sql  Migration thư viện ảnh và tiện nghi
└── uploads/                  Ảnh CCCD và ảnh phòng (không truy cập trực tiếp)
```

## 7. Phân quyền chính
| Chức năng | Admin | Chủ trọ | Nhân viên KT | Người thuê |
|---|:-:|:-:|:-:|:-:|
| Quản lý phòng, tải/xem ảnh các phòng | ✔ | ✔ | | Ảnh phòng của mình |
| Xem chi tiết hợp đồng | ✔ | ✔ | | Hợp đồng của mình |
| Lập hóa đơn, ghi chỉ số, cập nhật giá dịch vụ | ✔ | ✔ | | Xem hóa đơn của mình |
| Chỉnh sửa giá thuê phòng trong hệ thống | ✔ | | | |
| Phân quyền, chỉnh sửa hệ thống, nhân viên | ✔ | | | |
| Báo lỗi / xử lý yêu cầu sửa chữa | ✔ | ✔ | ✔ | Gửi báo lỗi |

## 8. Bảo mật và kiểm thử
Mật khẩu được băm bằng `password_hash`; truy vấn dùng prepared statement; dữ liệu hiển thị được `htmlspecialchars`; biểu mẫu POST có CSRF token; từng trang kiểm tra quyền tại máy chủ. Vai trò `admin` được khóa ở tầng model: giao diện không có lựa chọn đổi vai trò Admin và các thao tác PHP cũng từ chối hạ quyền/nâng quyền Admin qua form. Muốn thay đổi vai trò Admin phải sửa mã nguồn hoặc thao tác triển khai có kiểm soát trực tiếp trên CSDL. Ảnh phòng được giới hạn 50MB, kiểm tra MIME và nội dung ảnh.

Đã kiểm tra cú pháp PHP bằng `php -l` cho toàn bộ tệp PHP trong dự án sau khi nâng cấp. Chưa thể kiểm thử đầy đủ luồng chạy thực tế trên MySQL/Apache trong môi trường hiện tại; sau khi cài trên XAMPP, hãy chạy thử từng vai trò và các luồng được mô tả ở trên.

## 4. Nâng cấp giao diện V4 và sửa slideshow
- Giao diện quản trị dùng chung một bộ màu xanh dương – xanh ngọc – trắng với màn hình đăng nhập: sidebar, thanh tiêu đề, thẻ thống kê, nút, bảng, biểu mẫu, tab và nền trang.
- Ảnh nền màn hình đăng nhập chuyển động trái–phải theo chu kỳ; hình minh họa có hiệu ứng nổi nhẹ.
- Đã sửa lỗi cấu trúc HTML của slideshow: vùng `data-room-gallery` hiện bao gồm cả ảnh lớn và dải thumbnail. Trước đây thuộc tính này chỉ bao ngoài ảnh lớn, khiến JavaScript không tìm thấy thumbnail nên không khởi chạy slideshow dù đã tải nhiều ảnh.
- Slideshow tự chuyển ảnh theo vòng lặp (ảnh cuối quay lại ảnh đầu), có thanh tiến độ, nút trước/sau và thumbnail; không dừng khi rê chuột vào ảnh, chỉ tạm dừng khi tab trình duyệt bị ẩn. Slideshow hiển thị ở trang chi tiết phòng công khai, màn hình sửa phòng của Admin/Chủ trọ và mục Phòng của tôi của Người thuê.
- Admin/Chủ trọ có thêm nút **Xem chi tiết** từ thẻ phòng trong trang quản lý để xem thư viện ảnh và trạng thái của phòng đó.
- Trình duyệt được yêu cầu tải stylesheet/script với phiên bản `v=4.0` để tránh dùng CSS/JavaScript cũ trong cache.


## Phiên bản V5 – Slideshow cũng hiển thị cho Người thuê
- Người thuê thấy slideshow ảnh thực tế ngay trên Trang chủ sau đăng nhập, tại thẻ “Ảnh thực tế phòng của bạn”, và trong mục “Phòng của tôi”.
- Slideshow của người thuê chỉ lấy ảnh của đúng phòng đang gắn với tài khoản; endpoint `phong/anh.php` vẫn kiểm tra quyền ở máy chủ.
- Admin/Chủ trọ tiếp tục thấy slideshow trong màn hình Sửa phòng và có toàn quyền quản lý ảnh.
- Slideshow tự chạy 4 giây/ảnh, lặp vô hạn, có nút trước/sau và thumbnail; không dừng khi rê chuột.
- Tăng cache-busting CSS/JS lên v5.0 để trình duyệt không giữ lại mã V4 cũ.
- Không cần migration SQL mới.


## Cập nhật giao diện và thư viện ảnh V6.3

- Admin và Chủ trọ có thể mở trang phòng công khai ngay từ thanh menu quản trị; từng phòng trống có liên kết chỉnh sửa thông tin và ảnh.
- Trang chi tiết phòng có nút chỉnh sửa dành riêng cho Admin/Chủ trọ. Người dùng khác không được cấp quyền qua giao diện này.
- Khi sửa phòng đã tồn tại, ảnh được tải lên/xóa trực tiếp qua `phong/anh_quan_ly.php`, không cần lưu lại toàn bộ biểu mẫu. Các trường thông tin phòng vẫn được lưu bằng nút “Lưu thông tin phòng”.
- Endpoint ảnh kiểm tra vai trò, mã CSRF, định dạng JPG/PNG/WEBP và giới hạn 10 ảnh/lần, 50 MB/ảnh.
- Để tải nhiều ảnh lớn qua XAMPP, đặt tối thiểu `upload_max_filesize=50M`, `post_max_size=520M` và khởi động lại Apache.
- Cỡ chữ thông tin phòng được tăng lên; các nhãn trạng thái lặp ở đầu tiêu đề phòng đã được bỏ.


## Cập nhật V6.4 - Giá thuê phòng tự do
- Sửa giới hạn `step` của giá thuê phòng từ 1.000 đồng sang 1 đồng để chấp nhận mọi số nguyên dương (ví dụ 3.000.000, 3.800.000, 4.000.000).
- Áp dụng cho cả biểu mẫu thêm/sửa phòng và chức năng Admin cập nhật giá nhanh.
- Kiểm tra lại ở máy chủ: giá thuê phải là số nguyên dương, tối đa 999.999.999.999 đồng; dữ liệu vẫn lưu dạng số trong MySQL.


## Cập nhật V6.5 - Mở trang công khai trong cùng tab
- Liên kết **Xem trang phòng công khai** trong menu quản trị không còn mở bằng tab mới.
- Khi nhấn liên kết, trang công khai được mở ngay trong tab hiện tại; có thể dùng nút Back của trình duyệt để trở lại màn hình quản trị trước đó.
- Không thay đổi dữ liệu hoặc cấu trúc database.
