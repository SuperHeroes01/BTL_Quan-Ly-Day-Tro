
document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // 1. KHAI BÁO CÁC PHẦN TỬ TRÊN FORM
    // =====================================================

    // Danh sách tài khoản đã đăng ký.
    // Hỗ trợ cả ID của form hiện tại và ID của form cũ.
    const accountSelect =
        document.getElementById('registeredUser') ||
        document.getElementById('user_id') ||
        document.querySelector(
            'select[name="registered_user_id"], select[name="user_id"]'
        );

    // Khung tạo tài khoản mới.
    const newAccountFields =
        document.getElementById('newUserBox') ||
        document.getElementById('newAccountBox') ||
        document.querySelector('.new-account-fields');

    // Khung chọn tài khoản đã tồn tại.
    const existingAccountFields =
        document.getElementById('existingUserBox');

    // Danh sách chọn chế độ tài khoản.
    const accountMode =
        document.getElementById('accountMode');

    // Các trường thông tin người thuê.
    const nameInput =
        document.querySelector(
            'input[name="full_name"], input[name="ho_ten"]'
        );

    const phoneInput =
        document.querySelector(
            'input[name="phone"], input[name="so_dien_thoai"]'
        );

    const emailInput =
        document.querySelector('input[name="email"]');

    // Các trường tạo tài khoản mới.
    const usernameInput =
        document.getElementById('newUsername');

    const passwordInput =
        document.getElementById('newPassword');


    // =====================================================
    // 2. TỰ ĐỘNG ĐIỀN THÔNG TIN TÀI KHOẢN ĐÃ ĐĂNG KÝ
    // =====================================================

    function fillAccountInformation() {

        if (!accountSelect) return;

        const selectedOption =
            accountSelect.options[accountSelect.selectedIndex];

        const hasExistingAccount =
            accountSelect.value !== '' &&
            accountSelect.value !== '0';

        if (!hasExistingAccount || !selectedOption) return;

        // Lấy thông tin từ data-attribute của option.
        const accountName = selectedOption.dataset.name;
        const accountPhone = selectedOption.dataset.phone;
        const accountEmail = selectedOption.dataset.email;

        // Tự động điền họ tên.
        if (nameInput && accountName) {
            nameInput.value = accountName;
        }

        // Tự động điền số điện thoại.
        if (phoneInput && accountPhone) {
            phoneInput.value = accountPhone;
        }

        // Tự động điền email nếu form và dữ liệu có hỗ trợ.
        if (emailInput && accountEmail) {
            emailInput.value = accountEmail;
        }
    }


    // =====================================================
    // 3. ẨN / HIỆN KHUNG TẠO TÀI KHOẢN
    // =====================================================

    function syncAccountMode() {

        if (!accountMode) return;

        const useExisting =
            accountMode.value === 'existing';

        // Ẩn hoặc hiện khung tài khoản đã đăng ký.
        if (existingAccountFields) {
            existingAccountFields.hidden = !useExisting;
        }

        // Ẩn hoặc hiện khung tạo tài khoản mới.
        if (newAccountFields) {
            newAccountFields.hidden = useExisting;

            newAccountFields.style.display =
                useExisting ? 'none' : '';
        }

        // Thiết lập trạng thái danh sách tài khoản.
        if (accountSelect) {
            accountSelect.required = useExisting;
            accountSelect.disabled = !useExisting;
        }

        // Thiết lập trạng thái tên đăng nhập.
        if (usernameInput) {
            usernameInput.required = !useExisting;
            usernameInput.disabled = useExisting;
        }

        // Thiết lập trạng thái mật khẩu.
        if (passwordInput) {
            passwordInput.required = !useExisting;
            passwordInput.disabled = useExisting;
        }

        // Khi chọn tài khoản đã đăng ký,
        // tự động điền thông tin người thuê.
        if (useExisting) {
            fillAccountInformation();
        }
    }


    // =====================================================
    // 4. XỬ LÝ SỰ KIỆN CHỌN TÀI KHOẢN
    // =====================================================

    if (accountSelect) {

        accountSelect.addEventListener(
            'change',
            function () {

                // Tự động điền thông tin khi đổi tài khoản.
                fillAccountInformation();

                // Hỗ trợ form cũ không có accountMode.
                if (!accountMode && newAccountFields) {

                    const hasExistingAccount =
                        accountSelect.value !== '' &&
                        accountSelect.value !== '0';

                    newAccountFields.hidden =
                        hasExistingAccount;

                    newAccountFields.style.display =
                        hasExistingAccount ? 'none' : 'block';
                }
            }
        );
    }


    // =====================================================
    // 5. KHỞI TẠO TRẠNG THÁI TÀI KHOẢN KHI TẢI TRANG
    // =====================================================

    if (accountMode) {

        accountMode.addEventListener(
            'change',
            syncAccountMode
        );

        // Đồng bộ ngay khi trang được tải.
        syncAccountMode();

    } else if (accountSelect) {

        // Tương thích với biểu mẫu phiên bản cũ.
        const hasExistingAccount =
            accountSelect.value !== '' &&
            accountSelect.value !== '0';

        if (newAccountFields) {
            newAccountFields.hidden = hasExistingAccount;

            newAccountFields.style.display =
                hasExistingAccount ? 'none' : 'block';
        }

        if (hasExistingAccount) {
            fillAccountInformation();
        }
    }


    // =====================================================
    // 6. TỰ ĐỘNG ĐIỀN GIÁ THUÊ KHI CHỌN PHÒNG
    // =====================================================

    const roomSelect =
        document.getElementById('renterRoomSelect');

    const rentPriceInput =
        document.getElementById('contractRentPrice');

    if (roomSelect && rentPriceInput) {

        function suggestRoomPrice() {

            const selectedOption =
                roomSelect.options[roomSelect.selectedIndex];

            if (
                selectedOption &&
                selectedOption.dataset.price
            ) {
                rentPriceInput.value =
                    selectedOption.dataset.price;
            }
        }

        // Khi người dùng thay đổi phòng.
        roomSelect.addEventListener(
            'change',
            suggestRoomPrice
        );

        // Chỉ tự điền nếu giá thuê đang trống.
        if (!rentPriceInput.value && roomSelect.value) {
            suggestRoomPrice();
        }
    }

});
s