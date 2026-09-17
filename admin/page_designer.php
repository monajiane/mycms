<?php
/**
 * admin/page_designer.php — ویرایشگر بصری صفحه (GrapesJS)
 * ------------------------------------------------------------------
 * طراح درگ‌اند‌دراپ برای ساخت لندینگ پیج؛ طرح در جدول page_designs ذخیره
 * می‌شود و در /p/{slug} رندر می‌گردد. (سازندهٔ بلوکی قبلی دست‌نخورده است.)
 */
$pageTitle = 'طراحی بصری صفحه';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/page_builder_render.php';

$pages = [];
$schemaOk = true;
try {
    $pages = db()->query("SELECT id, title, slug, status, updated_at FROM pages ORDER BY id DESC")->fetchAll();
} catch (Throwable $e) {
    $schemaOk = false;
}

// اگر هنوز هیچ صفحه‌ای ساخته نشده، یک صفحهٔ پیش‌نویس پیشنهادی بساز تا ویرایشگر
// بلافاصله نمایش داده شود (به‌جای کادر خوش‌آمد خالی).
$autoCreatedId = 0;
if ($schemaOk && !$pages && (int)($_GET['page'] ?? 0) <= 0) {
    try {
        $nowTs = date('Y-m-d H:i:s');
        db()->prepare("INSERT INTO pages (title, slug, status, blocks_json, created_at, updated_at) VALUES (?,?,?,?,?,?)")
            ->execute(["\u{0635}\u{0641}\u{062D}\u{0647}\u{200C}\u{0627}\u{06CC}\u{200C}\u{062C}\u{062F}\u{06CC}\u{062F}", 'page-' . date('Ymd-His'), 'draft', '[]', $nowTs, $nowTs]);
        $autoCreatedId = (int)db()->lastInsertId();
    } catch (Throwable $e) {
        $autoCreatedId = 0;
    }
}

$currentId = (int)($_GET['page'] ?? 0);
if ($currentId <= 0 && $autoCreatedId > 0) {
    $currentId = $autoCreatedId;
} else if ($currentId <= 0 && $pages) {
    $currentId = (int)$pages[0]['id'];
}

$current = null;
$design  = null;
if ($currentId > 0) {
    $st = db()->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
    $st->execute([$currentId]);
    $current = $st->fetch() ?: null;
    if (!$current) {
        $currentId = 0;
    } else {
        $design = get_page_design($currentId);
    }
}

/** دارایی‌های کتابخانهٔ رسانه (اگر جدول media موجود باشد) */
$assetUrls = [];
try {
    foreach (db()->query("SELECT path FROM media ORDER BY id DESC LIMIT 80")->fetchAll(PDO::FETCH_COLUMN) as $p) {
        if (!is_string($p) || trim($p) === '') continue;
        $assetUrls[] = (strpos($p, 'http') === 0) ? $p : BASE_URL . '/' . ltrim($p, '/');
    }
} catch (Throwable $e) { /* جدول media نیست */ }

// محصولات فعال برای بلوک «محصولات»
$pickProducts = [];
try {
    $pickProducts = db()->query("SELECT id, name, price, image FROM products WHERE active = 1 ORDER BY id DESC LIMIT 12")->fetchAll();
} catch (Throwable $e) {}

$pdConfig = [
    'hasPage' => (bool)$current,
    'pageId'  => $currentId,
    'csrf'    => csrf_token(),
    'api'     => 'page_design_api.php',
    'base'    => BASE_URL,
    'page'    => $current ? [
        'id' => (int)$current['id'],
        'title' => (string)$current['title'],
        'slug' => (string)$current['slug'],
        'status' => (string)$current['status'],
    ] : null,
    'design' => $design ? [
        'project' => (string)$design['project_json'],
        'html' => (string)$design['html'],
        'css' => (string)$design['css'],
    ] : null,
    'assets' => $assetUrls,
    'canvasStyles' => [BASE_URL . '/assets/css/style.css' . (function_exists('pd_asset_ver') ? pd_asset_ver('assets/css/style.css') : '')],
    'products' => array_map(function ($p) {
        return [
            'name' => (string)$p['name'],
            'price' => fa_digits(number_format((float)$p['price'])),
            'image' => product_image_url($p['image'] ?? ''),
        ];
    }, $pickProducts),
];
$pdConfigJson = json_encode($pdConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/**
 * نسخهٔ دارایی بر پایهٔ زمان تغییر فایل: ?v=timestamp
 * چرا: فایل‌های JS/CSS با نام ثابت و کش طولانی (مرورگر + CDN) سرو می‌شوند؛
 * بدون این نسخه‌گذاری، کاربر ممکن است نسخهٔ قدیمی و خراب را ببیند.
 */
if (!function_exists('pd_asset_ver')) {
    function pd_asset_ver(string $rel): string
    {
        $t = @filemtime(dirname(__DIR__) . '/' . ltrim($rel, '/'));
        return $t ? '?v=' . $t : '';
    }
}
?>

<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/vendor/grapesjs/grapes.min.css<?= pd_asset_ver('assets/vendor/grapesjs/grapes.min.css') ?>">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/admin/css/page-designer.css<?= pd_asset_ver('assets/admin/css/page-designer.css') ?>">

<?php if (!$schemaOk): ?>
    <div class="pd-notice pd-notice--error">
        جدول <code>pages</code> در دیتابیس پیدا نشد. یک‌بار صفحهٔ «سازندهٔ صفحه» یا هر صفحهٔ پنل را باز کنید تا مهاجرت خودکار اجرا شود.
    </div>
<?php endif; ?>

<div class="pd-app" dir="rtl">
    <aside class="pd-side">
        <div class="pd-side__head">
            <h2>📄 صفحات</h2>
            <button type="button" class="pd-btn pd-btn--accent pd-btn--sm" id="pdNewToggle">+ صفحهٔ جدید</button>
        </div>

        <form class="pd-new" id="pdNewForm" hidden>
            <label>عنوان
                <input type="text" name="title" id="pdNewTitle" placeholder="مثلاً: فروش ویژه پاییز" required>
            </label>
            <label>آدرس (اختیاری)
                <input type="text" name="slug" id="pdNewSlug" dir="ltr" placeholder="special-offer">
            </label>
            <label>وضعیت
                <select name="status" id="pdNewStatus">
                    <option value="draft">پیش‌نویس</option>
                    <option value="published">منتشر</option>
                </select>
            </label>
            <div class="pd-new__actions">
                <button type="submit" class="pd-btn pd-btn--accent pd-btn--sm">ساخت</button>
                <button type="button" class="pd-btn pd-btn--sm" id="pdNewCancel">انصراف</button>
            </div>
            <p class="pd-msg" id="pdNewMsg"></p>
        </form>

        <ul class="pd-pages">
            <?php foreach ($pages as $p): ?>
                <li class="<?= ((int)$p['id'] === $currentId) ? 'is-active' : '' ?>">
                    <a href="?page=<?= (int)$p['id'] ?>">
                        <span class="pd-pages__title"><?= e($p['title'] ?: '(بدون عنوان)') ?></span>
                        <span class="pd-pages__slug" dir="ltr">/p/<?= e($p['slug']) ?></span>
                        <span class="pd-badge pd-badge--<?= $p['status'] === 'published' ? 'pub' : 'draft' ?>">
                            <?= $p['status'] === 'published' ? 'منتشر' : 'پیش‌نویس' ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (!$pages): ?>
                <li class="pd-empty">هنوز صفحه‌ای ساخته نشده است.</li>
            <?php endif; ?>
        </ul>

        <div class="pd-side__foot">
            <a href="page_builder.php" class="pd-link">🧱 سازندهٔ بلوکی (قدیمی)</a>
            <a href="site_pages.php" class="pd-link">🗂 مدیریت صفحات</a>
        </div>
    </aside>

    <section class="pd-main">
        <?php if (!$current): ?>
            <div class="pd-welcome">
                <h1>🎨 طراحی بصری صفحه</h1>
                <p>صفحه‌ای از فهرست سمت راست انتخاب کنید، یا با دکمهٔ <strong>«+ صفحهٔ جدید»</strong> یک صفحه بسازید و با کشیدن بلوک‌ها آن را طراحی کنید.</p>
                <p>هر طرح در پایگاه‌داده ذخیره می‌شود و در آدرس <code dir="ltr">/p/{slug}</code> نمایش داده می‌شود.</p>
            </div>
        <?php else: ?>
            <header class="pd-toolbar">
                <form class="pd-meta" id="pdMetaForm">
                    <input type="text" id="pdTitle" value="<?= e($current['title']) ?>" placeholder="عنوان صفحه" aria-label="عنوان صفحه">
                    <input type="text" id="pdSlug" value="<?= e($current['slug']) ?>" dir="ltr" placeholder="slug" aria-label="آدرس">
                    <select id="pdStatus" aria-label="وضعیت">
                        <option value="draft" <?= $current['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="published" <?= $current['status'] === 'published' ? 'selected' : '' ?>>منتشر</option>
                    </select>
                    <button type="submit" class="pd-btn pd-btn--sm">ثبت مشخصات</button>
                </form>

                <div class="pd-tools">
                    <div class="pd-devices" role="group" aria-label="نمای دستگاه">
                        <button type="button" class="pd-btn pd-btn--sm is-active" data-device="desktop">🖥 دسکتاپ</button>
                        <button type="button" class="pd-btn pd-btn--sm" data-device="tablet">📱 تبلت</button>
                        <button type="button" class="pd-btn pd-btn--sm" data-device="mobile">📲 موبایل</button>
                    </div>
                    <button type="button" class="pd-btn pd-btn--sm" id="pdUndo" title="واگرد">↶</button>
                    <button type="button" class="pd-btn pd-btn--sm" id="pdRedo" title="ازنو">↷</button>
                    <a class="pd-btn pd-btn--sm" id="pdPreview" href="<?= e(BASE_URL) ?>/p/<?= e($current['slug']) ?>?preview=1" target="_blank" rel="noopener">👁 پیش‌نمایش</a>
                    <button type="button" class="pd-btn pd-btn--danger pd-btn--sm" id="pdClear">🧹 پاک‌کردن بوم</button>
                    <button type="button" class="pd-btn pd-btn--accent" id="pdSave">💾 ذخیره طرح</button>
                </div>
            </header>

            <div class="pd-status">
                <span class="pd-msg" id="pdMsg"></span>
            </div>

            <div class="pd-body">
                <div class="pd-panel">
                    <div class="pd-panel__tabs">
                        <button type="button" class="pd-tab is-active" data-pane="blocks">🧱 بلوک‌ها</button>
                        <button type="button" class="pd-tab" data-pane="styles">🎛 استایل</button>
                        <button type="button" class="pd-tab" data-pane="layers">🗂 لایه‌ها</button>
                    </div>
                    <div class="pd-pane is-active" data-pane="blocks" id="pd-blocks"></div>
                    <div class="pd-pane" data-pane="styles" id="pd-styles" hidden></div>
                    <div class="pd-pane" data-pane="layers" id="pd-layers" hidden></div>
                </div>
                <div class="pd-canvas-wrap">
                    <div id="gjs" class="pd-canvas"></div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($current): ?>
<script src="<?= e(BASE_URL) ?>/assets/vendor/grapesjs/grapes.min.js<?= pd_asset_ver('assets/vendor/grapesjs/grapes.min.js') ?>"></script>
<script>window.PD_CONFIG = <?= $pdConfigJson ?>;</script>
<script src="<?= e(BASE_URL) ?>/assets/admin/js/page-designer.js<?= pd_asset_ver('assets/admin/js/page-designer.js') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
