<?php
/**
 * p.php — نمایش صفحات ساخته‌شده با Page Builder
 * URL: /p/{slug}
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page_builder_render.php';

$slug = $_GET['slug'] ?? '';
$page = $slug ? get_page_by_slug($slug) : null;

// پیش‌نمایشِ طرحِ در حال ساخت برای مدیر: صفحه‌های پیش‌نویس/منتشرنشده هم قابل دیدن باشند.
if (!$page && $slug !== '' && isset($_GET['preview']) && function_exists('is_logged_in') && is_logged_in()) {
    try {
        $st = db()->prepare("SELECT * FROM pages WHERE slug = ? LIMIT 1");
        $st->execute([$slug]);
        $page = $st->fetch() ?: null;
    } catch (Throwable $e) {
        $page = null;
    }
}

if (!$page) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = $page['seo_title'] ?: $page['title'];
$pageDescription = $page['seo_description'] ?? '';
$pageOgType = 'website';
if (!empty($page['og_image'])) $pageImage = product_image_url($page['og_image']);

require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/page-builder.css">
<?php
// اگر صفحه طرحِ ویرایشگر بصری (GrapesJS) داشته باشد همان رندر می‌شود،
// وگرنه به بلوک‌های سازندهٔ صفحهٔ قبلی برمی‌گردیم (سازگاری کامل).
page_design_render($page);
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
