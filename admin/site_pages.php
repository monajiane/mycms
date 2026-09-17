<?php
/**
 * admin/site_pages.php — ویرایش محتوای صفحات ثابت سایت
 * ------------------------------------------------------------------
 * صفحاتی که قبلاً hardcode بودند (درباره ما، قوانین، حریم خصوصی، دانشنامه،
 * صفحات خطا) اینجا از پنل قابل ویرایش می‌شوند. مقادیر در جدول page_contents
 * ذخیره و در front-end با pc_get() خوانده می‌شوند؛ مقدار پیش‌فرض همان متن
 * فعلی است تا ظاهر سایت بدون تغییر بماند.
 */
$pageTitle = 'صفحات سایت';
require __DIR__ . '/_header.php';

require_once __DIR__ . '/../includes/page_content.php';

$defs = pc_definitions();
$keys = array_keys($defs);

// انتخاب صفحهٔ فعال
$active = $_GET['page'] ?? $keys[0];
if (!isset($defs[$active])) $active = $keys[0];
$def = $defs[$active];

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $pageKey = $_POST['page_key'] ?? '';
    if (isset($defs[$pageKey])) {
        $incoming = [];
        foreach ($defs[$pageKey]['fields'] as $fk => $meta) {
            // فیلدهای خالی را ذخیره نمی‌کنیم تا به پیش‌فرض برگردند
            if (array_key_exists($fk, $_POST)) {
                $incoming[$fk] = (string)$_POST[$fk];
            }
        }
        $n = pc_save($pageKey, $incoming);
        flash('success', 'محتوای «' . $defs[$pageKey]['label'] . '» ذخیره شد (' . $n . ' فیلد).');
        header('Location: site_pages.php?page=' . urlencode($pageKey));
        exit;
    }
}

$current = pc_all($active);
$val = function (string $fk) use ($def, $current): string {
    if (array_key_exists($fk, $current)) return $current[$fk];
    return (string)($def['fields'][$fk]['default'] ?? '');
};
?>
<div class="page-head">
    <h1>📄 صفحات سایت</h1>
    <p class="muted">محتوای صفحات ثابت سایت را از اینجا ویرایش کنید. تغییرات فوراً در سایت اعمال می‌شود.</p>
</div>

<div class="site-pages">
    <aside class="sp-sidebar">
        <ul class="sp-list">
            <?php foreach ($defs as $k => $d): ?>
                <li>
                    <a href="?page=<?= e(urlencode($k)) ?>" class="<?= $k === $active ? 'is-active' : '' ?>">
                        <span class="sp-list__title"><?= e($d['label']) ?></span>
                        <span class="sp-list__file"><?= e($d['file']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <main class="sp-main">
        <form method="post" class="sp-form">
            <?= csrf_field() ?>
            <input type="hidden" name="page_key" value="<?= e($active) ?>">

            <div class="sp-head">
                <h2><?= e($def['label']) ?></h2>
                <div class="sp-head__actions">
                    <a href="<?= e(BASE_URL) ?>/<?= e($def['file']) ?>" target="_blank" rel="noopener" class="btn">👁 مشاهدهٔ صفحه</a>
                    <button type="submit" class="btn btn-accent">💾 ذخیره</button>
                </div>
            </div>

            <?php foreach ($def['fields'] as $fk => $meta): ?>
                <div class="form-row">
                    <label><?= e($meta['label']) ?></label>
                    <?php if (($meta['type'] ?? 'text') === 'textarea'): ?>
                        <textarea name="<?= e($fk) ?>" rows="5"><?= e($val($fk)) ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?= e($fk) ?>" value="<?= e($val($fk)) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="sp-foot">
                <button type="submit" class="btn btn-accent">💾 ذخیرهٔ تغییرات</button>
                <span class="muted">خالی گذاشتن هر فیلد = بازگشت به متن پیش‌فرض</span>
            </div>
        </form>
    </main>
</div>

<style>
.site-pages{display:grid;grid-template-columns:260px 1fr;gap:18px;align-items:start}
.sp-sidebar{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.sp-list{list-style:none;margin:0;padding:6px}
.sp-list a{display:flex;flex-direction:column;gap:2px;padding:10px 12px;border-radius:8px;text-decoration:none;color:#334155}
.sp-list a:hover{background:#f1f5f9}
.sp-list a.is-active{background:var(--accent,#0d9488);color:#fff}
.sp-list__title{font-weight:600;font-size:14px}
.sp-list__file{font-size:11px;opacity:.7;direction:ltr}
.sp-main{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px}
.sp-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.sp-head h2{margin:0;font-size:18px}
.sp-head__actions,.sp-foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sp-foot{margin-top:18px;padding-top:14px;border-top:1px solid #e5e7eb}
.sp-form .form-row{margin-bottom:14px}
.sp-form label{display:block;font-weight:600;margin-bottom:6px;font-size:14px}
.sp-form input[type=text],.sp-form textarea{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;font-size:14px}
.sp-form textarea{line-height:1.9;resize:vertical}
@media(max-width:820px){.site-pages{grid-template-columns:1fr}}
</style>

<?php require __DIR__ . '/_footer.php'; ?>