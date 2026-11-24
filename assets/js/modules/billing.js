document.addEventListener('DOMContentLoaded', () => {
    const billingForm = document.querySelector('form[action*="hoa_don"]') || document.getElementById('billingForm');
    if (!billingForm) return;

    const electricOld = billingForm.querySelector('input[name="dien_cu"]');
    const electricNew = billingForm.querySelector('input[name="dien_moi"]');
    const electricPrice = billingForm.querySelector('input[name="gia_dien"]');
    
    const waterOld = billingForm.querySelector('input[name="nuoc_cu"]');
    const waterNew = billingForm.querySelector('input[name="nuoc_moi"]');
    const waterPrice = billingForm.querySelector('input[name="gia_nuoc"]');

    const roomPrice = billingForm.querySelector('input[name="tien_phong"]');
    const servicePrice = billingForm.querySelector('input[name="tien_dich_vu"]');
    const totalDisplay = document.getElementById('tong_tien_hien_thi') || billingForm.querySelector('input[name="tong_tien"]');

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('vi-VN').format(Math.max(0, amount)) + ' đ';
    };

    const calculateTotal = () => {
        const dCu = parseFloat(electricOld?.value) || 0;
        const dMoi = parseFloat(electricNew?.value) || 0;
        const dGia = parseFloat(electricPrice?.value) || 0;
        const tienDien = dMoi >= dCu ? (dMoi - dCu) * dGia : 0;

        const nCu = parseFloat(waterOld?.value) || 0;
        const nMoi = parseFloat(waterNew?.value) || 0;
        const nGia = parseFloat(waterPrice?.value) || 0;
        const tienNuoc = nMoi >= nCu ? (nMoi - nCu) * nGia : 0;

        const tPhong = parseFloat(roomPrice?.value) || 0;
        const tDichVu = parseFloat(servicePrice?.value) || 0;

        const tongCong = tPhong + tienDien + tienNuoc + tDichVu;

        if (totalDisplay) {
            if (totalDisplay.tagName === 'INPUT') {
                totalDisplay.value = tongCong;
            } else {
                totalDisplay.textContent = formatCurrency(tongCong);
            }
        }
    };

    // Lắng nghe sự kiện nhập liệu trên tất cả các ô số
    const numberInputs = billingForm.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', calculateTotal);
    });

    calculateTotal();
});
