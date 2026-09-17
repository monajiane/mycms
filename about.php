<?php
/**
 * about.php — صفحهٔ دربارهٔ ما (ابزارسازی شرق)
 * معرفی شرکت، خدمات، آدرس و راه‌های ارتباطی
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'درباره ما | ابزارسازی شرق';
$s = get_settings();
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="hero-eyebrow">دربارهٔ ما</p>
        <h1 class="page-title page-title-lg">ابزارسازی شرق</h1>
        <p class="hero-tagline"><?= e($s['store_tagline']) ?></p>
    </div>
</section>

<section class="container about-section">
    <div class="about-grid">
        <div class="about-text">
            <h2>معرفی شرکت</h2>
            <p><?= e(setting('company_about', 'ابزارسازی شرق با تکیه بر دانش فنی و ماشین‌آلات پیشرفته، ابزارهای استاندارد و سفارشی صنعتی را طراحی و عرضه می‌کند.')) ?></p>
            <p>حوزه‌های فعالیت ما شامل تولید ابزارهای برشی، بازسازی و تیزکردن ابزار (ریکاندیشن)، تأمین ابزار دقیق اندازه‌گیری و تجهیزات ایمنی صنعتی است. ما با هدف کاهش هزینه‌ها و افزایش عمر ابزار، خدمات بازسازی و سنگ‌زنی را نیز به‌صورت تخصصی ارائه می‌دهیم.</p>
        </div>
        <div class="about-card">
            <h3>اطلاعات تماس</h3>
            <dl class="contact-list">
                <dt>آدرس</dt>
                <dd><?= e(setting('company_address', '—')) ?></dd>
                <dt>تلفن</dt>
                <dd dir="ltr"><?= e(fa_digits(setting('company_phone', '—'))) ?></dd>
                <dt>ایمیل</dt>
                <dd dir="ltr"><?= e(setting('company_email', '—')) ?></dd>
            </dl>
        </div>
    </div>
</section>

<section class="container about-map" style="margin-top:40px">
    <h2 style="margin:0 0 14px">موقعیت کارخانه</h2>
    <p style="color:#475569;margin:0 0 16px"><?= e(setting('company_address', 'شهرک صنعتی عباس‌آباد')) ?> — برای مسیریابی، روی دکمهٔ پایین نقشه کلیک کنید.</p>
    <div style="border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 6px 24px rgba(0,0,0,0.08)">
        <iframe src="https://www.google.com/maps?q=<?= e(setting('company_lat', '35.4302595')) ?>,<?= e(setting('company_lng', '51.8367681')) ?>&z=17&hl=fa&output=embed" width="100%" height="400" style="border:0;display:block" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="موقعیت کارخانه ابزارسازی شرق روی نقشه"></iframe>
    </div>
    <p style="text-align:center;margin:16px 0 0">
        <a class="btn btn-primary" href="https://www.google.com/maps?q=<?= e(setting('company_lat', '35.4302595')) ?>,<?= e(setting('company_lng', '51.8367681')) ?>" target="_blank" rel="noopener">📍 مسیریابی در گوگل‌مپس</a>
    </p>
</section>
<section class="container about-values">
    <h2>چرا ابزارسازی شرق؟</h2>
    <div class="values-grid">
        <div class="value-card">
            <span class="value-num">۳۰+</span>
            <span class="value-label">سال تجربه</span>
        </div>
        <div class="value-card">
            <span class="value-num">۵۰+</span>
            <span class="value-label">صنعت فعال</span>
        </div>
        <div class="value-card">
            <span class="value-num">ISO</span>
            <span class="value-label">کیفیت استاندارد</span>
        </div>
        <div class="value-card">
            <span class="value-num">۱۰۰٪</span>
            <span class="value-label">ضمانت اصالت</span>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
