document.addEventListener('DOMContentLoaded', () => {
    const gallery = document.querySelector('.room-slideshow, .gallery-container');
    if (!gallery) return;

    const slides = gallery.querySelectorAll('.slide, .gallery-item');
    const prevBtn = gallery.querySelector('.prev-btn, [data-slide="prev"]');
    const nextBtn = gallery.querySelector('.next-btn, [data-slide="next"]');
    const pauseBtn = gallery.querySelector('.pause-btn, [data-slide="toggle"]');

    if (slides.length <= 1) return;

    let currentIndex = 0;
    let slideInterval = null;
    let transitionTimeout = null;
    let isPaused = false;

    // Kiểm tra thiết lập giảm chuyển động của thiết bị
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const showSlide = (index) => {
        // Hủy timeout chuyển cảnh cũ nếu người dùng bấm liên tục
        if (transitionTimeout) {
            clearTimeout(transitionTimeout);
        }

        currentIndex = (index + slides.length) % slides.length;

        slides.forEach((slide, idx) => {
            slide.classList.toggle('active', idx === currentIndex);
            slide.setAttribute('aria-hidden', idx !== currentIndex);
        });
    };

    const startAutoSlide = () => {
        if (prefersReducedMotion || isPaused) return;
        stopAutoSlide();
        slideInterval = setInterval(() => {
            showSlide(currentIndex + 1);
        }, 4500);
    };

    const stopAutoSlide = () => {
        if (slideInterval) {
            clearInterval(slideInterval);
            slideInterval = null;
        }
    };

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            showSlide(currentIndex - 1);
            startAutoSlide();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            showSlide(currentIndex + 1);
            startAutoSlide();
        });
    }

    if (pauseBtn) {
        pauseBtn.addEventListener('click', () => {
            isPaused = !isPaused;
            pauseBtn.textContent = isPaused ? 'Tiếp tục' : 'Tạm dừng';
            isPaused ? stopAutoSlide() : startAutoSlide();
        });
    }

    // Tự động dừng chạy slide khi người dùng chuyển sang tab khác
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAutoSlide();
        } else if (!isPaused) {
            startAutoSlide();
        }
    });

    showSlide(0);
    startAutoSlide();
});
