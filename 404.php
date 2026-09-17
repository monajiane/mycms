<?php
/**
 * 404.php — صفحهٔ خطای یافت‌نشده (عمومی)
 * Apache با ErrorDocument به اینجا می‌آید؛ برای ظاهر کامل سایت،
 * هدر/فوتر مشترک همان‌طور که هست استفاده می‌شود.
 */
require_once __DIR__ . '/config.php';
// --- Page Builder pretty-URL router: /p/{slug} (for web servers that ignore .htaccess) ---
{
    $pbPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $pbPath = trim(preg_replace('#/+#', '/', (string)$pbPath), '/');
    if (preg_match('#^p/([^/]+)$#', $pbPath, $pbM)) {
        $pbSlug = rawurldecode($pbM[1]);
        $pbPage = null;
        if ($pbSlug !== '' && is_file(__DIR__ . '/includes/page_builder_render.php')) {
            require_once __DIR__ . '/includes/page_builder_render.php';
            $pbPage = get_page_by_slug($pbSlug);
        }
        if ($pbPage) {
            http_response_code(200);
            header('Content-Type: text/html; charset=UTF-8');
            $pageTitle = (string)($pbPage['seo_title'] ?: $pbPage['title']);
            $pageDescription = (string)($pbPage['seo_description'] ?? '');
            $pageOgType = 'website';
            if (!empty($pbPage['og_image'])) { $pageImage = product_image_url($pbPage['og_image']); }
            include __DIR__ . '/includes/header.php';
            echo '<link rel="stylesheet" href="' . e(BASE_URL) . '/assets/css/page-builder.css">' . "\n";
            page_builder_render($pbPage['blocks_json']);
            include __DIR__ . '/includes/footer.php';
            exit;
        }
    }
}

// Apache مسیر درخواست اصلی را در REDIRECT_URL می‌گذارد
$requested = $_SERVER['REDIRECT_URL'] ?? ($_SERVER['REQUEST_URI'] ?? '');
http_response_code(404);

$pageTitle = 'صفحه پیدا نشد';
include __DIR__ . '/includes/header.php';
?>
<main class="container" style="text-align:center; padding: 80px 20px;">
    <p style="font-size: clamp(64px, 12vw, 140px); line-height:1; margin:0; color: var(--accent, #0f228c); font-weight: 900;" dir="ltr"><?= fa_digits('404') ?></p>
    <h1 style="margin: 8px 0 12px;"><?= e(pc_get('error404', 'title', 'صفحه‌ای که دنبالش بودید پیدا نشد')) ?></h1>
    <p style="color:#64748b; max-width: 560px; margin: 0 auto 28px;">
        <?= e(pc_get('error404', 'message', 'ممکن است آدرس تغییر کرده باشد، صفحه حذف شده باشد، یا در نوشتن آدرس اشتباهی رخ داده باشد.')) ?>
        <?php if ($requested !== ''): ?>
            آدرس درخواستی: <code dir="ltr"><?= e((string)$requested) ?></code>
        <?php endif; ?>
    </p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/index.php">رفتن به صفحهٔ اصلی</a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/shop.php">مشاهدهٔ فروشگاه</a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/knowledge.php">دانشنامه</a>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
