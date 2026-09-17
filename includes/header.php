<?php
/**
 * includes/header.php — قالب مشترک بالای صفحات فروشگاه (RTL)
 */
require_once __DIR__ . '/../config.php';

// ثبت بازدید در صفحات عمومی (قبل از نمایش)
// هر IP فقط یک‌بار در هر روز شمرده می‌شود؛ رفرش صفحه بازدید اضافه نمی‌کند.
if (LOG_VISITS && !defined('ADMIN_AREA')) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $cleanUri = strtok($uri, '?');
    visit_track($cleanUri);
}

$settings = get_settings();
$categories = get_categories();
$cartCount = cart_count();
$customer = is_customer_logged_in() ? current_customer() : null;

// ---- متادیتای سئو ----
$metaTitle = $pageTitle ?? $settings['store_name'];
$metaDescription = !empty($pageDescription) ? $pageDescription : $settings['store_tagline'];
$ogImage = !empty($pageImage) ? $pageImage : (SITE_DEFAULT_IMAGE !== '' ? SITE_DEFAULT_IMAGE : '');

// صفحات خصوصی (سبد، پرداخت، حساب و…) نباید ایندکس شوند
$privatePages = ['cart.php','checkout.php','login.php','register.php','account.php','messages.php','custom_request.php','order_result.php','view_order.php','payment.php','verify.php','logout.php'];
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$metaRobots = isset($pageRobots) ? $pageRobots : (in_array($scriptName, $privatePages) ? 'noindex, nofollow' : 'index, follow');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="<?= e($metaRobots) ?>">
    <link rel="canonical" href="<?= e(canonical_url()) ?>">
    <?= geo_meta_tags() ?>
    <meta property="og:type" content="<?= e($pageOgType ?? 'website') ?>">
    <meta property="og:site_name" content="<?= e($settings['store_name']) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e(canonical_url()) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:alt" content="<?= e($metaTitle) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="<?= e(SITE_THEME_COLOR) ?>">
    <?= favicon_tags($settings) ?>
    <?= website_jsonld() ?>
    <?= local_business_jsonld() ?>
    <?= $pageHeadExtra ?? '' ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css?v=1.18">
    <style><?= theme_css_vars() ?></style>
    <?php
    // ---- تأیید وبمستر ----
    if (setting('google_site_verification', '') !== ''): ?>
    <meta name="google-site-verification" content="<?= e(setting('google_site_verification')) ?>">
    <?php endif; ?>
    <?php if (setting('bing_verification', '') !== ''): ?>
    <meta name="msvalidate.01" content="<?= e(setting('bing_verification')) ?>">
    <?php endif; ?>
    <?php // ---- Google Tag Manager ----
    if (setting('gtm_container_id', '') !== ''): $gtm = setting('gtm_container_id'); ?>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($gtm) ?>');</script>
    <?php endif; ?>
    <?php // ---- Google Analytics 4 ----
    if (setting('ga_measurement_id', '') !== ''): $ga = setting('ga_measurement_id'); ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= e($ga) ?>');
    </script>
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?= e(BASE_URL) ?>/index.php" class="brand">
            <?php $siteLogo = trim((string)setting('site_logo', '')); ?>
            <?php if ($siteLogo !== ''): ?>
                <span class="brand-logo"><img src="<?= e(product_image_url($siteLogo)) ?>" alt="لوگوی <?= e($settings['store_name']) ?>"></span>
            <?php else: ?>
                <span class="brand-logo brand-logo--placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 0 0 5.6-6L14.6 12l-2.6-2.6 2.7-3.1z"/></svg>
                </span>
            <?php endif; ?>
            <span class="brand-text"><?= e($settings['store_name']) ?></span>
        </a>
        <form class="header-search" method="get" action="<?= e(BASE_URL) ?>/shop.php" role="search">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(setting('home_search_placeholder', 'جستجوی ابزار…')) ?>" aria-label="جستجو">
            <button type="submit" aria-label="جستجو">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
            </button>
        </form>
        <button type="button" class="nav-toggle" id="navToggle" aria-label="باز و بسته کردن منو" aria-expanded="false" aria-controls="mainNav">
    <span></span><span></span><span></span>
</button>
<nav class="main-nav" id="mainNav" aria-label="ناوبری اصلی">
            <a href="<?= e(BASE_URL) ?>/index.php">خانه</a>
            <a href="<?= e(BASE_URL) ?>/shop.php">فروشگاه</a>
            <a href="<?= e(BASE_URL) ?>/knowledge.php">دانشنامه</a>
            <a href="<?= e(BASE_URL) ?>/about.php">درباره ما</a>
            <a href="<?= e(BASE_URL) ?>/account.php">باشگاه مشتریان</a>            <a href="<?= e(BASE_URL) ?>/cart.php" class="cart-link">
                سبد خرید
                <span class="badge" id="cart-badge"><?= $cartCount ?></span>
            </a>
            <a href="<?= e(BASE_URL) ?>/custom_request.php">سفارش ابزار</a>
            <?php if ($customer): ?>
                <a href="<?= e(BASE_URL) ?>/messages.php" class="account-link">پیام‌ها<?php $um = unread_messages_count((int)$customer['id']); if ($um > 0): ?> <span class="badge"><?= $um ?></span><?php endif; ?></a>
                <a href="<?= e(BASE_URL) ?>/account.php" class="account-link">حساب من</a>
                <a href="<?= e(BASE_URL) ?>/logout.php">خروج</a>
            <?php else: ?>
                <a href="<?= e(BASE_URL) ?>/login.php">ورود</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($msg = get_flash('success')): ?>
    <div class="container"><div class="alert alert-success"><?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
    <div class="container"><div class="alert alert-error"><?= e($msg) ?></div></div>
<?php endif; ?>
