<?php
/**
 * 500.php — صفحهٔ خطای داخلی سرور (عمومی)
 */
require_once __DIR__ . '/config.php';
http_response_code(500);

$pageTitle = 'خطای سرور';
include __DIR__ . '/includes/header.php';
?>
<main class="container" style="text-align:center; padding: 80px 20px;">
    <p style="font-size: clamp(64px, 12vw, 140px); line-height:1; margin:0; color:#d97706; font-weight:900;" dir="ltr"><?= fa_digits('500') ?></p>
    <h1 style="margin: 8px 0 12px;"><?= e(pc_get('error500', 'title', 'مشکلی در سرور پیش آمد')) ?></h1>
    <p style="color:#64748b; max-width: 560px; margin: 0 auto 28px;">
        <?= e(pc_get('error500', 'message', 'خطای فنی موقتی رخ داده است. تیم فنی مطلع می‌شود؛ لطفاً چند لحظه بعد دوباره تلاش کنید.')) ?>
    </p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/index.php">تلاش مجدد</a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/messages.php">گزارش مشکل</a>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
