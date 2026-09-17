<?php
/**
 * admin/_header.php — قالب مشترک پنل مدیریت + محافظ ورود
 */
// بافر خروجی: هندلرهای POST صفحات پنل بعد از این قالب header() می‌فرستند؛
// با این بافر، خروجی HTML هنوز ارسال نشده و خطای «headers already sent» رخ نمی‌دهد.
ob_start();

define('ADMIN_AREA', true); // جلوگیری از ثبت بازدید در لاگ
require_once __DIR__ . '/../config.php';

if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'مدیر';

/** آیکون‌های SVG نوار ناوبری (stroke، هم‌رنگ متن) */
function admin_nav_icon(string $name): string
{
    static $icons = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box'        => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3.3 8.3L12 13l8.7-4.7"/><path d="M12 22V13"/>',
        'tags'       => '<path d="M20.6 13.4L12 22 2 12V2h10l8.6 8.6a2 2 0 010 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
        'star'       => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1L12 2z"/>',
        'clipboard'  => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 016 0"/><path d="M9 10h6M9 14h6M9 18h4"/>',
        'wrench'     => '<path d="M14.7 6.3a4.5 4.5 0 00-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 005.6-6L14.6 12l-2.6-2.6 2.7-3.1z"/>',
        'users'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><path d="M16 4.6a3.5 3.5 0 010 6.8M21.5 20a6.5 6.5 0 00-4.5-6.2"/>',
        'award'      => '<circle cx="12" cy="9" r="5"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/>',
        'ticket'     => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M9 6v12" stroke-dasharray="2 2.5"/>',
        'chat'       => '<path d="M21 11.5a8.4 8.4 0 01-8.5 8.3c-1.4 0-2.7-.3-3.9-.9L3 20l1.2-4.2A8.3 8.3 0 1121 11.5z"/>',
        'send'       => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        'doc'        => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/>',
        'layout'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'megaphone'  => '<path d="M3 11v2a1 1 0 001 1h2l4 4V6L6 10H4a1 1 0 00-1 1z"/><path d="M15 8a4 4 0 010 8"/>',
        'chart'      => '<path d="M3 3v18h18"/><path d="M7 15v-4M12 15V7M17 15v-7"/>',
        'sparkles'   => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z"/>',
        'pluscircle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'gear'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1-1.6 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.6-1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3h.1a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5h.1a1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9v.1a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
    ];
    $path = $icons[$name] ?? $icons['dashboard'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

/** ساخت یک آیتم ناوبری با تشخیص فعال‌بودن و نشان تعداد */
function admin_nav_item(string $href, string $label, string $icon, string $match, bool $exact = false, int $badge = 0): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $active = $exact ? ($script === $match) : (strpos($script, $match) !== false);
    $cls = 'nav-item' . ($active ? ' active' : '');
    $badgeHtml = $badge > 0 ? '<span class="nav-badge">' . (int)$badge . '</span>' : '';
    return '<a href="' . e($href) . '" class="' . $cls . '">'
        . '<span class="nav-ico">' . admin_nav_icon($icon) . '</span>'
        . '<span class="nav-label">' . e($label) . '</span>' . $badgeHtml . '</a>';
}

$prc = function_exists('pending_reviews_count') ? pending_reviews_count() : 0;
$au  = function_exists('admin_unread_count') ? admin_unread_count() : 0;
$brandLetter = mb_substr(trim((string)setting('store_name')), 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'پنل مدیریت') ?> | <?= e(setting('store_name')) ?></title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
    <style><?= theme_css_vars() ?></style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <span class="brand-mark"><?= e($brandLetter !== '' ? $brandLetter : 'پ') ?></span>
            <span class="brand-text">
                <strong><?= e(setting('store_name')) ?></strong>
                <small>پنل مدیریت</small>
            </span>
        </div>
        <nav class="admin-nav">
            <?= admin_nav_item('index.php', 'داشبورد', 'dashboard', '/admin/index.php', true) ?>

            <div class="nav-section">فروشگاه</div>
            <?= admin_nav_item('products.php', 'محصولات', 'box', '/admin/products') ?>
            <?= admin_nav_item('categories.php', 'دسته‌بندی‌ها', 'tags', '/admin/categories') ?>
            <?= admin_nav_item('reviews.php', 'نظرات', 'star', '/admin/reviews', false, $prc) ?>
            <?= admin_nav_item('orders.php', 'سفارش‌ها', 'clipboard', '/admin/orders') ?>
            <?= admin_nav_item('requests.php', 'درخواست‌های سفارشی', 'wrench', '/admin/requests') ?>

            <div class="nav-section">مشتریان</div>
            <?= admin_nav_item('users.php', 'کاربران', 'users', '/admin/users') ?>
            <?= admin_nav_item('loyalty.php', 'باشگاه مشتریان', 'award', '/admin/loyalty') ?>
            <?= admin_nav_item('coupons.php', 'کوپن‌های تخفیف', 'ticket', '/admin/coupons') ?>

            <div class="nav-section">ارتباطات</div>
            <?= admin_nav_item('messages.php', 'پیام‌ها', 'chat', '/admin/messages', false, $au) ?>
            <?= admin_nav_item('sms.php', 'پنل پیامک', 'send', '/admin/sms') ?>

            <div class="nav-section">محتوا</div>
            <?= admin_nav_item('articles.php', 'مقالات', 'doc', '/admin/articles') ?>
            <?= admin_nav_item('page_builder.php', 'سازندهٔ صفحه', 'layout', '/admin/page_builder') ?>
            <?= admin_nav_item('page_designer.php', 'طراحی بصری صفحه', 'layout', '/admin/page_designer') ?>
            <?= admin_nav_item('site_pages.php', 'صفحات سایت', 'doc', '/admin/site_pages') ?>
<?= admin_nav_item('seo_manager.php', 'سئو حرفه‌ای', 'search', '/admin/seo_manager') ?>
            <?= admin_nav_item('marketing.php', 'بازاریابی', 'megaphone', '/admin/marketing') ?>

            <div class="nav-section">تحلیل و تنظیمات</div>
            <?= admin_nav_item('reports.php', 'گزارش فروش', 'chart', '/admin/reports') ?>
            <?= admin_nav_item('ai_assistant.php', 'دستیار هوش مصنوعی', 'sparkles', '/admin/ai_assistant') ?>
            <?= admin_nav_item('ai_add_product.php', 'افزودن محصول با AI', 'pluscircle', '/admin/ai_add_product') ?>
            <?= admin_nav_item('settings.php', 'تنظیمات', 'gear', '/admin/settings') ?>
        </nav>
        <div class="admin-sidebar-foot">
            <a href="<?= e(BASE_URL) ?>/index.php" target="_blank" rel="noopener" class="side-link"><?= admin_nav_icon('send') ?> مشاهدهٔ سایت</a>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <span class="admin-welcome">سلام، <?= e($adminName) ?></span>
            <a href="logout.php" class="btn btn-ghost btn-sm">خروج</a>
        </header>
        <div class="admin-content">
