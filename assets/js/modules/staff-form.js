document.addEventListener('DOMContentLoaded', () => {
    const createAccountCheckbox = document.getElementById('tao_tai_khoan') || document.querySelector('input[name="tao_tai_khoan"]');
    const accountSection = document.getElementById('accountFields') || document.querySelector('.account-section');
    const roleSelect = document.querySelector('select[name="vai_tro"]');

    if (createAccountCheckbox && accountSection) {
        const toggleAccountSection = () => {
            accountSection.style.display = createAccountCheckbox.checked ? 'block' : 'none';
            
            // Bật/tắt thuộc tính required cho các ô nhập tài khoản khi ẩn/hiện
            const inputs = accountSection.querySelectorAll('input');
            inputs.forEach(input => {
                if (input.dataset.required === 'true') {
                    input.required = createAccountCheckbox.checked;
                }
            });
        };

        createAccountCheckbox.addEventListener('change', toggleAccountSection);
        toggleAccountSection();
    }
});
