<?php
/**
 * 403.php — صفحهٔ خطای دسترسی غیرمجاز (عمومی)
 */
require_once __DIR__ . '/config.php';
http_response_code(403);

$pageTitle = 'دسترسی غیرمجاز';
include __DIR__ . '/includes/header.php';
?>
<main class="container" style="text-align:center; padding: 80px 20px;">
    <p style="font-size: clamp(64px, 12vw, 140px); line-height:1; margin:0; color:#dc2626; font-weight:900;" dir="ltr"><?= fa_digits('403') ?></p>
    <h1 style="margin: 8px 0 12px;">دسترسی به این بخش مجاز نیست</h1>
    <p style="color:#64748b; max-width: 560px; margin: 0 auto 28px;">
        این محدودیت برای حفاظت از فایل‌های سیستمی و امنیت فروشگاه اعمال شده است.
        اگر فکر می‌کنید این یک اشتباه است، با پشتیبانی تماس بگیرید.
    </p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/index.php">صفحهٔ اصلی</a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/messages.php">تماس با پشتیبانی</a>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
