<?php
/**
 * index.php — صفحهٔ اصلی (لندینگ شرکتی ابزارسازی شرق)
 * هیرو انیمیشنی + محصولات منتخب + معرفی شرکت + دسته‌بندی‌ها + دعوت به فروشگاه
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'ابزارسازی شرق | ابزار دقیق، صنعت مطمئن';
$pageDescription = 'ابزارسازی شرق؛ تولید، تأمین و بازسازی ابزارآلات دقیق صنعتی با بیش از سه دهه تجربه. خرید ابزار برشی، ابزار اندازه‌گیری و تجهیزات ایمنی صنعتی با ضمانت اصالت و ارسال به سراسر کشور.';

// آمار واقعی برای بخش Hero
$totalProducts = (int)db()->query("SELECT COUNT(*) FROM products p WHERE p.active = 1 AND " . product_visibility_clause('p'))->fetchColumn();
$totalCategories = (int)db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// محصولات منتخب برای بخش پایین هیرو
$stFeatured = db()->query("SELECT p.*, c.name AS category_name FROM products p
                          LEFT JOIN categories c ON c.id = p.category_id
                          WHERE p.active = 1 AND p.featured = 1 AND " . product_visibility_clause('p') . "
                          ORDER BY p.id DESC LIMIT 4");
$featured = $stFeatured->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php $heroAnim = setting('theme_hero_anim', 'orbit'); ?>
<?php $heroBg = setting('theme_hero_bg', 'default'); ?>
<?php $heroBgPresets = hero_background_presets(); $heroBgGrad = isset($heroBgPresets[$heroBg]) ? $heroBgPresets[$heroBg][1] : ''; ?>
<section class="hero hero-anim--<?= e($heroAnim) ?> hero-bg--<?= e($heroBg) ?><?= setting('home_hero_image', '') !== '' ? ' hero-has-image' : '' ?>"<?= ($heroImg = trim((string)setting('home_hero_image', ''))) === '' && $heroBgGrad !== '' ? ' style="background:' . e($heroBgGrad) . '"' : '' ?>>
    <?php if ($heroImg !== ''): ?>
        <div class="hero-bg" aria-hidden="true" style="background-image:url('<?= e(product_image_url($heroImg)) ?>')"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="hero-frame" aria-hidden="true"></div>
    <div class="hero-glow" aria-hidden="true"></div>
    <div class="hero-orbit" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="hero-particles" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
    <div class="hero-grid" aria-hidden="true"></div>
    <div class="hero-scan" aria-hidden="true"></div>
    <div class="container hero-inner">
        <?php if (setting_bool('home_show_eyebrow', true)): ?>
        <p class="hero-eyebrow reveal"><?= e(setting('home_hero_eyebrow', 'ابزار دقیق صنعتی')) ?></p>
        <?php endif; ?>
        <h1 class="hero-title reveal" data-delay="100"><?= e(setting('home_hero_title', $settings['store_name'])) ?></h1>
        <?php if (setting_bool('home_show_tagline', true)): ?>
        <p class="hero-tagline reveal" data-delay="200"><?= e(setting('home_hero_tagline', $settings['store_tagline'])) ?></p>
        <?php endif; ?>
        <?php if (setting_bool('home_show_cta', true)): ?>
        <div class="hero-actions reveal" data-delay="300">
            <a href="<?= e(setting('home_cta_primary_url', 'shop.php')) ?>" class="btn btn-accent"><?= e(setting('home_cta_primary_label', 'ورود به فروشگاه')) ?></a>
            <a href="<?= e(setting('home_cta_secondary_url', 'about.php')) ?>" class="btn btn-ghost"><?= e(setting('home_cta_secondary_label', 'دربارهٔ شرکت')) ?></a>
        </div>
        <?php endif; ?>
        <?php if (setting_bool('home_show_stats', true)): ?>
        <div class="hero-stats reveal" data-delay="400">
            <div class="stat">
                <span class="stat-num"><?= $totalProducts ?></span>
                <span class="stat-label">محصول فعال</span>
            </div>
            <div class="stat">
                <span class="stat-num"><?= $totalCategories ?></span>
                <span class="stat-label">دسته‌بندی</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (setting_bool('home_show_featured', true) && $featured): ?>
<section class="container featured-section" id="featured">
    <h2 class="section-title"><?= e(setting('home_featured_title', 'محصولات منتخب')) ?></h2>
    <p class="section-sub"><?= e(setting('home_featured_sub', 'پرفروش‌ترین و باکیفیت‌ترین ابزارها برای کارگاه و صنعت شما')) ?></p>
    <div class="product-grid">
        <?php foreach ($featured as $p): ?>
            <article class="product-card reveal-on-scroll">
                <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-thumb">
                    <?php if (!empty($p['image'])): ?>
                        <img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="thumb-placeholder"><?= product_placeholder_icon($p['name']) ?></div>
                    <?php endif; ?>
                    <?php if (!product_in_stock($p['id'])): ?>
                        <span class="thumb-out-stock">ناموجود</span>
                    <?php endif; ?>
                </a>
                <div class="product-body">
                    <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-name"><?= e($p['name']) ?></a>
                    <div class="product-meta"><?= e($p['category_name'] ?? '—') ?></div>
                    <?= price_or_login('<div class="product-price">' . price_html_discounted($p) . '</div>') ?>
                    <?php if (prices_hidden_for_guests()): ?>
                    <?php elseif (!product_in_stock($p['id'])): ?>
                        <span class="stock stock-out">ناموجود</span>
                        <a href="product.php?id=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-block btn-sm">🔔 موجود شد خبرم کن</a>
                    <?php else: ?>
                        <form method="post" action="cart.php" class="add-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-accent btn-block">افزودن به سبد</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="section-cta">
        <a href="shop.php" class="btn btn-ghost">مشاهدهٔ همهٔ محصولات →</a>
    </div>
</section>
<?php endif; ?>

<?php if (setting_bool('home_show_categories', true)): ?>
<section class="categories-band" id="cats">
    <div class="container">
        <h2 class="section-title cat-title--<?= e(setting('home_categories_align', 'center')) ?>"><?= e(setting('home_categories_title', 'بر اساس نیازتان مرور کنید')) ?></h2>
    </div>
    <div class="cat-scroll container" role="list">
        <?php foreach ($categories as $c): ?>
            <a href="shop.php?category=<?= (int)$c['id'] ?>" class="category-card reveal-on-scroll" role="listitem">
                <?php if (!empty($c['image'])): ?>
                    <span class="category-thumb"><img src="<?= e(product_image_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async"></span>
                <?php else: ?>
                    <span class="category-icon"><?= e(mb_substr($c['name'], 0, 1, 'UTF-8')) ?></span>
                <?php endif; ?>
                <span class="category-name"><?= e($c['name']) ?></span>
                <span class="category-count"><?= (int)$c['cnt'] ?> محصول</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (setting_bool('home_show_about', true)): ?>
<section class="about-teaser">
    <div class="about-teaser-inner container">
        <div class="about-teaser-text">
            <h2 class="section-title"><?= e(setting('home_about_title', 'سه دهه اعتماد صنعت')) ?></h2>
            <p><?= e(setting('company_about', 'ابزارسازی شرق با تکیه بر دانش فنی و ماشین‌آلات پیشرفته، ابزارهای استاندارد و سفارشی صنعتی را طراحی و عرضه می‌کند.')) ?></p>
            <a href="about.php" class="btn btn-accent"><?= e(setting('home_about_cta_label', 'بیشتر دربارهٔ ما')) ?></a>
        </div>
        <div class="about-teaser-stats">
            <div class="value-card"><span class="value-num"><?= e(setting('home_stat1_num', '۳۰+')) ?></span><span class="value-label"><?= e(setting('home_stat1_label', 'سال تجربه')) ?></span></div>
            <div class="value-card"><span class="value-num"><?= e(setting('home_stat2_num', '۵۰+')) ?></span><span class="value-label"><?= e(setting('home_stat2_label', 'صنعت فعال')) ?></span></div>
            <div class="value-card"><span class="value-num"><?= e(setting('home_stat3_num', 'ISO')) ?></span><span class="value-label"><?= e(setting('home_stat3_label', 'کیفیت استاندارد')) ?></span></div>
            <div class="value-card"><span class="value-num"><?= e(setting('home_stat4_num', '۱۰۰٪')) ?></span><span class="value-label"><?= e(setting('home_stat4_label', 'ضمانت اصالت')) ?></span></div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
