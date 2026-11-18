# BTL_Quan-Ly-Day-Tro
# Hệ thống quản lý dãy trọ (PHP + MySQL) — Phiên bản V6.9

Bài tập lớn học phần **Phát triển ứng dụng trên nền Web** – đề tài *Xây dựng phần mềm quản lý dãy trọ trên nền web*. Dự án dùng PHP hướng đối tượng, MySQL/MariaDB, HTML, CSS và JavaScript; chạy bằng Apache trong XAMPP.

## 1. Cài đặt lần đầu (XAMPP trên Windows)
1. Cài XAMPP, mở **XAMPP Control Panel**, bấm **Start** cho **Apache** và **MySQL**.
2. Giải nén project, chép thư mục `quanlytrov6.9` vào `C:\xampp\htdocs\` (hoặc thư mục `htdocs` tương ứng trên ổ đĩa cài XAMPP của bạn, ví dụ `D:\Program Files\Xampp\htdocs\quanlytrov6.9\`).
3. Mở `http://localhost/phpmyadmin` → tab **Import** → chọn `database/quanlytro_full.sql` → **Import**. Script tạo database `quanlytro` nếu chưa có, tạo các bảng/cột còn thiếu và thêm dữ liệu mẫu khi database nghiệp vụ còn trống.
4. Nếu MySQL có mật khẩu hoặc khác cổng, sửa `config/config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`).
5. Mở địa chỉ project trên trình duyệt: `http://localhost/quanlytrov6.9/`.

> **Lưu ý về dữ liệu:** `database/quanlytro_full.sql` là file SQL tổng hợp hiện được đóng gói trong project. File không có lệnh `DROP DATABASE`, `DROP TABLE` hoặc `TRUNCATE`; các bảng được tạo bằng `CREATE TABLE IF NOT EXISTS`, các cột cũ được bổ sung có điều kiện. Dữ liệu mẫu chỉ được thêm khi các bảng nghiệp vụ đều trống. Tuy vậy, hãy **Export sao lưu database trước khi Import**, đặc biệt với dữ liệu đang sử dụng. File SQL này không chứa dữ liệu thực tế phát sinh riêng trên máy bạn và cũng không chứa ảnh/tệp trong `uploads/`.
>
> Yêu cầu: PHP 7.4 trở lên, bật `mysqli` và `fileinfo` (các extension thường có sẵn trong XAMPP).

## 2. Cập nhật database đã có
- Project hiện chỉ đóng gói một file SQL tổng hợp: `database/quanlytro_full.sql`; không còn yêu cầu Import lần lượt `quanlytro.sql`, `cap_nhat_tinh_nang.sql` và `cap_nhat_tinh_nang_v3.sql`.
- Trước khi cập nhật database đang sử dụng, vào phpMyAdmin → chọn database `quanlytro` → **Export** để sao lưu.
- Sau khi sao lưu, Import `database/quanlytro_full.sql`. Script tạo bảng/cột còn thiếu, bổ sung các trường khôi phục mật khẩu, thông tin người thuê/hợp đồng, tiện nghi và thư viện ảnh; đồng bộ ảnh đại diện cũ vào `room_image` nếu có dữ liệu phù hợp.
- Khi database đã có hồ sơ thực tế, script không chạy bộ dữ liệu mẫu vì chỉ seed khi toàn bộ bảng nghiệp vụ đều trống. Dù vậy, nên thử trên bản sao lưu trước khi cập nhật database chính.
- Không Import lại các file SQL cũ từ bản phát hành trước cùng với file tổng hợp này.

### Cấu hình tải ảnh phòng tối đa 50MB
- Ảnh thực tế của phòng chấp nhận JPG, PNG hoặc WEBP, kích thước tối đa **50MB/ảnh**; mỗi lần tải tối đa 10 ảnh.
- Trên XAMPP Windows, mở `php.ini` (bấm **Config → PHP (php.ini)** trên XAMPP Control Panel), chỉnh:
  ```ini
  upload_max_filesize=50M
  post_max_size=520M
  max_file_uploads=10
  max_execution_time=300
  max_input_time=300
  ```
- Lưu tệp và **Stop/Start Apache** trong XAMPP Control Panel. `post_max_size=520M` cho phép gửi tối đa khoảng 10 ảnh x 50MB trong một lần; `upload_max_filesize=50M` vẫn giới hạn từng ảnh. Tệp `.htaccess` ở thư mục dự án cũng khai báo giới hạn cho Apache dùng mod_php; nếu máy chủ không hỗ trợ `php_value` trong `.htaccess`, hãy cấu hình trực tiếp bằng `php.ini`.

## 3. Trang công khai, chi tiết phòng và thư viện ảnh
- `index.php` (ở thư mục gốc `quanlytrov6.9/index.php`) là trang công khai không yêu cầu đăng nhập, hiển thị danh sách phòng đang trống; khách có thể xem thông tin phòng và liên hệ thuê phòng.
- `phong/index.php` là trang quản trị danh sách phòng dành cho tài khoản đã đăng nhập (Admin, Chủ trọ, Nhân viên, Người thuê), không dùng thay thế cho `index.php` ở thư mục gốc.
- `phong_chi_tiet.php` hiển thị giá thuê, diện tích, tình trạng, mô tả, tiện nghi từ `room.amenities`, thiết bị được gán và slideshow ảnh.
- Slideshow có ảnh lớn, thumbnail, nút chuyển ảnh và tự chạy theo cấu hình JavaScript. Ảnh thật được phục vụ qua `phong/anh.php` sau khi máy chủ kiểm tra quyền truy cập.
- Admin/Chủ trọ có thể mở trang công khai từ menu quản trị, chỉnh sửa từng phòng ngay trên trang danh sách hoặc trang chi tiết; liên kết mở trong **cùng tab**.
- Trong biểu mẫu sửa phòng đã tồn tại, ảnh được thêm/xóa tức thời qua `phong/anh_quan_ly.php`; không cần bấm **Lưu thông tin phòng** để thay đổi thư viện ảnh. Mã phòng, giá thuê, diện tích, mô tả và tiện nghi vẫn được lưu bằng nút lưu biểu mẫu.
- Với phòng mới chưa có mã trong database, ảnh được lưu cùng lúc khi tạo phòng.
- Ảnh thực tế được lưu trong `uploads/rooms/`; ảnh CCCD/tạm trú nằm trong `uploads/cccd/`. Các thư mục upload chặn truy cập trực tiếp và ảnh phòng được truy xuất qua endpoint có kiểm tra quyền.

## 4. Tài khoản mẫu

| Vai trò | Tên đăng nhập | Mật khẩu |
|---|---|---|
| Admin | `admin` | `admin123` |
| Chủ trọ | `chutro` | `chutro123` |
| Nhân viên kỹ thuật | `kythuat` | `kythuat123` |
| Người thuê | `nguyenvana`, `tranthib`, `levanc`, `phamthid`, `hoangvane` | `123456` |

Đổi mật khẩu sau khi dùng thử (menu **Tài khoản của tôi**).

## 5. Các chức năng được bổ sung (Phiên Bản V1 + V2 + V3)
### Đăng ký và quên mật khẩu
- Trang đăng nhập (`login.php`) có liên kết **Đăng ký tài khoản** (`register.php`), **Quên mật khẩu** (`quen_mat_khau.php`) và **Xem danh sách phòng trống** (`index.php` ở thư mục gốc).
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

## 6. Cấu trúc thư mục (phiên bản V6.9)
Cây thư mục dưới đây được đối chiếu với các tệp thực tế trong gói `quanlytrov6.9`:

```text
quanlytrov6.9/
├── .htaccess                     Cấu hình Apache và giới hạn tải tệp
├── index.php                     Trang công khai: danh sách phòng trống (nạp includes/bootstrap.php)
├── phong_chi_tiet.php            Trang chi tiết phòng, slideshow và nút sửa cho quản lý
├── login.php                     Đăng nhập
├── register.php                  Đăng ký tài khoản người thuê
├── quen_mat_khau.php             Khôi phục mật khẩu
├── logout.php                    Đăng xuất
├── home.php                      Trang chủ sau đăng nhập theo vai trò
├── tai_khoan.php                 Hồ sơ và đổi mật khẩu tài khoản
│
├── config/
│   ├── .htaccess                 Chặn truy cập trực tiếp tệp cấu hình
│   └── config.php                Cấu hình kết nối MySQL và hằng số dự án
│
├── includes/
│   ├── .htaccess                 Chặn truy cập trực tiếp mã dùng chung
│   ├── auth.php                  Phiên đăng nhập, vai trò và kiểm tra quyền
│   ├── bootstrap.php             Nạp cấu hình, session, model và tiện ích
│   ├── functions.php             Hàm URL, escape, CSRF, thông báo, định dạng tiền
│   ├── header.php                Sidebar và menu theo vai trò
│   └── footer.php                Phần cuối giao diện quản trị
│
├── classes/                      Các lớp model và truy vấn dữ liệu
│   ├── .htaccess                 Chặn truy cập trực tiếp mã model
│   ├── BaseModel.php             Lớp model nền
│   ├── Database.php              Kết nối và thao tác MySQL
│   ├── User.php                  Tài khoản, đăng nhập và vai trò cố định
│   ├── Room.php                  Phòng và thư viện ảnh phòng
│   ├── Renter.php                Hồ sơ người thuê
│   ├── Bill.php                  Hóa đơn
│   ├── ItemizedBill.php          Chi tiết hóa đơn
│   ├── Service.php               Dịch vụ và đơn giá
│   ├── Device.php                Thiết bị
│   ├── RepairRequest.php         Yêu cầu sửa chữa
│   ├── Staff.php                 Nhân viên kỹ thuật
│   └── Setting.php               Cấu hình hệ thống
│
├── phong/                        Quản lý phòng và nghiệp vụ thuê phòng
│   ├── anh.php                   Endpoint hiển thị ảnh có kiểm tra quyền
│   ├── anh_quan_ly.php           Endpoint POST/AJAX thêm hoặc xóa ảnh tức thời
│   ├── form.php                  Thêm/sửa phòng và giao diện quản lý thư viện ảnh
│   ├── index.php                 Danh sách phòng quản trị (nạp ../includes/bootstrap.php)
│   ├── nguoi_thue.php            Danh sách người thuê và trả phòng
│   ├── nguoi_thue_form.php       Thêm/sửa hồ sơ người thuê
│   ├── hop_dong.php              Danh sách hợp đồng
│   └── hop_dong_xem.php          Xem/in chi tiết hợp đồng
│
├── dich_vu/                      Dịch vụ, chỉ số, hóa đơn và tạm trú
│   ├── index.php                 Danh sách/cấu hình dịch vụ
│   ├── hoa_don.php               Danh sách hóa đơn
│   ├── hoa_don_tao.php           Lập hóa đơn và chỉ số điện/nước
│   ├── hoa_don_xem.php           Xem chi tiết hóa đơn
│   ├── tam_tru.php               Quản lý thông tin tạm trú
│   └── tam_tru_anh.php           Xem ảnh hồ sơ tạm trú có kiểm tra quyền
│
├── thiet_bi/                     Quản lý thiết bị và sửa chữa
│   ├── index.php                 Danh sách thiết bị
│   ├── form.php                  Thêm/sửa thiết bị
│   ├── bao_loi.php               Gửi báo lỗi thiết bị
│   └── tiep_nhan.php             Tiếp nhận/xử lý yêu cầu sửa chữa
│
├── he_thong/                     Quản trị tài khoản và cấu hình hệ thống
│   ├── phan_quyen.php            Danh sách tài khoản và phân quyền
│   ├── user_form.php             Biểu mẫu tài khoản
│   ├── nhan_vien.php             Quản lý nhân viên
│   └── chinh_sua.php             Cấu hình hệ thống và cập nhật giá thuê
│
├── assets/
│   ├── css/style.css             Giao diện công khai và quản trị (v=6.3+)
│   ├── js/app.js                 JavaScript: slideshow, tương tác và tải/xóa ảnh
│   └── images/                   Logo/hình nền và hình minh họa phòng
│       ├── login-hero.svg
│       ├── room-background-generated.webp
│       ├── room-vacant.svg
│       ├── room-rented.svg
│       └── room-maintenance.svg
│
├── database/
│   ├── .htaccess                 Chặn truy cập trực tiếp file SQL qua web
│   └── quanlytro_full.sql        SQL tổng hợp: cấu trúc, nâng cấp cột và dữ liệu mẫu
│
└── uploads/                      Tệp tải lên; không đưa dữ liệu ảnh thực tế vào SQL
    ├── .htaccess                 Chặn truy cập trực tiếp thư mục upload
    ├── cccd/                     Ảnh CCCD/tài liệu người thuê
    │   └── .gitkeep              Giữ thư mục trong Git khi chưa có ảnh
    └── rooms/                    Ảnh phòng; truy xuất qua phong/anh.php
        └── .htaccess             Chặn truy cập ảnh trực tiếp
```

**Ghi chú về đường dẫn nạp (`require_once`):**
- Các tệp ở thư mục gốc (`index.php`, `login.php`, `home.php`, `phong_chi_tiet.php`,...) dùng `require_once __DIR__ . '/includes/bootstrap.php';`.
- Các tệp nằm trong thư mục con (`phong/*.php`, `dich_vu/*.php`, `thiet_bi/*.php`, `he_thong/*.php`) dùng `require_once __DIR__ . '/../includes/bootstrap.php';`.

**Ghi chú về endpoint ảnh:** Không có thư mục `ajax/` riêng. Thao tác thêm/xóa ảnh ngay trong biểu mẫu sửa phòng được xử lý bởi `phong/anh_quan_ly.php`; tệp này kiểm tra vai trò Admin/Chủ trọ, phương thức POST và CSRF token. `phong/anh.php` là endpoint đọc ảnh, có kiểm tra quyền trước khi trả nội dung ảnh.

**Ghi chú về SQL:** Bản V6.9 chỉ đóng gói `database/quanlytro_full.sql`. Các file `quanlytro.sql`, `cap_nhat_tinh_nang.sql` và `cap_nhat_tinh_nang_v3.sql` thuộc các bản trước, không phải các file cần Import riêng trong gói hiện tại.

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
Mật khẩu được băm bằng `password_hash`; truy vấn dùng prepared statement; dữ liệu hiển thị được `htmlspecialchars`; biểu mẫu POST có CSRF token; từng trang kiểm tra quyền tại máy chủ. Vai trò `admin` và `chutro` được khóa ở tầng model: giao diện không cho đổi hai vai trò này và các thao tác PHP cũng từ chối hạ quyền/nâng quyền hoặc gán các vai trò đó qua form. Muốn thay đổi vai trò Admin/Chủ trọ phải can thiệp có kiểm soát trong mã nguồn hoặc CSDL triển khai. Ảnh phòng được giới hạn 50MB, kiểm tra MIME và nội dung ảnh.

## Lịch sử cập nhật các phiên bản
### V4 – Giao diện thống nhất và sửa slideshow
- Giao diện quản trị dùng chung một bộ màu xanh dương – xanh ngọc – trắng với màn hình đăng nhập: sidebar, thanh tiêu đề, thẻ thống kê, nút, bảng, biểu mẫu, tab và nền trang.
- Ảnh nền màn hình đăng nhập chuyển động trái–phải theo chu kỳ; hình minh họa có hiệu ứng nổi nhẹ.
- Sửa cấu trúc HTML của slideshow (`data-room-gallery` bao gồm cả ảnh lớn và dải thumbnail). Slideshow tự chuyển ảnh theo vòng lặp, có thanh tiến độ, nút trước/sau và thumbnail; chỉ tạm dừng khi tab trình duyệt bị ẩn.

### V5 – Slideshow hiển thị cho Người thuê
- Người thuê thấy slideshow ảnh thực tế ngay trên Trang chủ sau đăng nhập, tại thẻ “Ảnh thực tế phòng của bạn”, và trong mục “Phòng của tôi”.
- Slideshow của người thuê chỉ lấy ảnh của đúng phòng đang gắn với tài khoản; endpoint `phong/anh.php` vẫn kiểm tra quyền ở máy chủ.

### V6.3 – Giao diện và thư viện ảnh
- Admin và Chủ trọ có thể mở trang phòng công khai ngay từ thanh menu quản trị; từng phòng trống có liên kết chỉnh sửa thông tin và ảnh.
- Khi sửa phòng đã tồn tại, ảnh được tải lên/xóa trực tiếp qua `phong/anh_quan_ly.php` mà không cần lưu lại toàn bộ biểu mẫu.

### V6.4 – Giá thuê phòng tự do
- Sửa giới hạn `step` của giá thuê phòng từ 1.000 đồng sang 1 đồng để chấp nhận mọi số nguyên dương (tối đa 999.999.999.999 đồng) trên cả biểu mẫu thêm/sửa phòng và chức năng Admin cập nhật giá nhanh.

### V6.5 – Mở trang công khai trong cùng tab
- Liên kết **Xem trang phòng công khai** trong menu quản trị mở ngay trong tab hiện tại để dễ dàng điều hướng bằng nút Back của trình duyệt.

### V6.6 - Chỉnh sửa/ thêm chức năng của admin
- Thêm nút xoá tài khoản, chỉnh sửa lại " đặt lại mật khẩu " của admin/ chủ trọ là không thể đặt lại mật khẩu ngay trên hệ thống mà cần phải tự đặt lại mật khẩu
- điều chỉnh lại việc khi có tài khoàn mới được tạo ra thì bên admin sẽ nhận được thông báo rằng có tài khoản mới vừa được tạo để gắn phòng trọ cho tài khoản ấy.

 ### V6.7 - Khắc phục triệt để việc xóa tài khoản người thuê + Sửa vấn đề danh sách nhân viên kỹ thuật
 - Trước đây, có một vấn đề quan trọng: hệ thống lưu thông tin đăng nhập trong Session nhưng không xác minh lại tài khoản trong database ở các request tiếp theo. Vì vậy, ngay cả khi Admin xóa tài khoản, người thuê vẫn có thể sử dụng phiên đăng nhập cũ.
 - Trước đây, hai khái niệm trạng thái nhân viên và trạng thái tài khoản chưa được phân biệt rõ.

 ### V6.8 Cải thiện các vấn đề + Bổ sung thêm phần nhật ký hoạt động 
 - Cải thiện chức năng đặt lại mật khẩu. Thay vì sử dụng str_shuffle() như trước, phiên bản mới dùng random_int() để tạo mật khẩu 12 ký tự với độ ngẫu nhiên an toàn hơn.
 - Các hoạt động được ghi nhận gồm đăng nhập, đăng xuất, thay đổi quyền, đặt lại mật khẩu, xóa tài khoản người thuê và thêm, sửa, xóa nhân viên kỹ thuật. Bổ sung bảng activity_logs vào SQL tổng hợp.
 - cải tiến biểu mẫu theo hướng tạo tài khoản cho nhân viên kỹ thuật trực tiếp. Khi sửa hoặc thêm nhân viên, Admin sẽ có lựa chọn: Chưa liên kết tài khoản, Chọn tài khoản kỹ thuật đã tồn tại, Tạo tài khoản mới cho nhân viên này.
 - Bổ sung kiểm soát trạng thái nhân viên: Đang làm, có tài khoản hoạt động, Đang làm, chưa có tài khoản, Đang làm, tài khoản bị khóa, Nghỉ,...

Lưu ý: Nhật ký chỉ bắt đầu ghi nhận từ khi cập nhật xong; các hoạt động trước đó không thể tự khôi phục.

### V6.9 – Chuẩn hóa thư mục dự án và phân tách trang công khai/quản trị
- Đóng gói dự án theo thư mục gốc `quanlytrov6.9/`.
- Phân tách rõ ràng đường dẫn nạp `includes/bootstrap.php` giữa trang công khai ngoài cùng (`index.php` dùng `/includes/bootstrap.php`) và trang danh sách phòng quản trị (`phong/index.php` dùng `/../includes/bootstrap.php`), đảm bảo khách vãng lai, Admin và Chủ trọ truy cập trang danh sách phòng trống bình thường từ cả thanh menu quản trị lẫn màn hình đăng nhập/đăng ký.
- Không yêu cầu thay đổi cấu trúc bảng trong database so với bản V6.5.
