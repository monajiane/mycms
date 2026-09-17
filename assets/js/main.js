/**
 * assets/js/main.js — بهبود تعامل فروشگاه
 * - جلوگیری از ارسال دوبارهٔ فرم (double-submit)
 * - بستن خودکار پیام‌های فلش پس از چند ثانیه
 */
(function () {
    'use strict';

    // جلوگیری از ارسال دوبارهٔ فرم‌های افزودن به سبد
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('button[type="submit"]');
            if (submit && !submit.dataset.submitting) {
                submit.dataset.submitting = '1';
                submit.disabled = true;
                // اگر به دلایلی ناوبری رخ نداد، بعد از چند ثانیه دوباره فعال شود
                setTimeout(function () {
                    delete submit.dataset.submitting;
                    submit.disabled = false;
                }, 4000);
            }
        });
    });

    // بستن خودکار هشدارها
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .6s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 600);
        }, 5000);
    });

    // ---- انیمیشن ورود هیرو ----
    var hero = document.querySelector('.hero');
    if (hero) {
        // با کمی تأخیر کلاس animated را اضافه می‌کنیم تا انیمیشن پلکانی اجرا شود
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                hero.classList.add('animated');
            });
        });
    }

    // ---- نمایش تدریجی کارت‌ها هنگام اسکرول (IntersectionObserver) ----
    var revealEls = document.querySelectorAll('.reveal-on-scroll');
    if ('IntersectionObserver' in window && revealEls.length) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // ---- گالری ورق‌زدنی محصول ----
    document.querySelectorAll('[data-gallery]').forEach(function (g) {
        var track = g.querySelector('.gallery-track');
        var thumbs = g.querySelectorAll('.gallery-thumb');
        var slides = track ? track.children : [];
        if (!track || !slides.length) { return; }
        var index = 0;
        var total = slides.length;

        function go(i) {
            index = ((i % total) + total) % total;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            thumbs.forEach(function (t, k) {
                t.classList.toggle('active', k === index);
            });
        }

        var prev = g.querySelector('.gallery-prev');
        var next = g.querySelector('.gallery-next');
        if (prev) { prev.addEventListener('click', function () { go(index - 1); }); }
        if (next) { next.addEventListener('click', function () { go(index + 1); }); }
        thumbs.forEach(function (t, k) {
            t.addEventListener('click', function () { go(k); });
        });

        // پیمایش لمسی (سوایپ) برای موبایل
        var startX = 0, dragging = false;
        var viewport = g.querySelector('.gallery-viewport');
        if (viewport) {
            viewport.addEventListener('touchstart', function (e) {
                startX = e.touches[0].clientX; dragging = true;
            }, { passive: true });
            viewport.addEventListener('touchend', function (e) {
                if (!dragging) { return; }
                dragging = false;
                var dx = e.changedTouches[0].clientX - startX;
                if (Math.abs(dx) > 40) {
                    go(index + (dx < 0 ? 1 : -1));
                }
            }, { passive: true });
        }
    });
})();

// کپچا: کلیک روی تصویر = تولید کپچای جدید
document.querySelectorAll('.captcha-img').forEach(function (img) {
    img.addEventListener('click', function () {
        img.src = img.src.split('?')[0] + '?t=' + Date.now();
    });
    img.title = 'برای کپچای جدید کلیک کنید';
});