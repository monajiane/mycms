<?php
/**
 * admin/page_builder.php — Page Builder با بلوک‌های قابل چینش
 * ------------------------------------------------------------------
 * ساخت صفحات فرود (لندینگ) بدون کدنویسی: hero + ویژگی + CTA + محصول + فرم
 */
$pageTitle = 'سازندهٔ صفحه';
require __DIR__ . '/_header.php';

// بارگذاری صفحات ساخته‌شده
$pages = db()->query("SELECT id, title, slug, status, updated_at FROM pages ORDER BY id DESC")->fetchAll();

// بارگذاری یک صفحه برای ویرایش
$editing = null;
$blocks = [];
if (isset($_GET['edit'])) {
    $st = db()->prepare("SELECT * FROM pages WHERE id = ?");
    $st->execute([(int)$_GET['edit']]);
    $editing = $st->fetch();
    if ($editing) {
        $st2 = db()->prepare("SELECT * FROM page_blocks WHERE page_id = ? ORDER BY position ASC");
        $st2->execute([$editing['id']]);
        $blocks = $st2->fetchAll();
    }
}

// ذخیره صفحه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') !== 'delete_page') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($slug === '') $slug = to_slug($title);
    $pageId = (int)($_POST['page_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDesc = trim($_POST['seo_description'] ?? '');
    $seoKw = trim($_POST['seo_keywords'] ?? '');
    $ogImage = trim($_POST['og_image'] ?? '');
    $canonical = trim($_POST['canonical'] ?? '');
    $noindex = isset($_POST['noindex']) ? 1 : 0;
    $blocksJson = $_POST['blocks_json'] ?? '[]';
    $blocksArr = json_decode($blocksJson, true) ?: [];

    if ($pageId > 0) {
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE pages SET title=?, slug=?, status=?, seo_title=?, seo_description=?, seo_keywords=?, og_image=?, canonical=?, noindex=?, blocks_json=?, updated_at=? WHERE id=?")
            ->execute([$title, $slug, $status, $seoTitle, $seoDesc, $seoKw, $ogImage, $canonical, $noindex, $blocksJson, $now, $pageId]);
    } else {
        $now = date('Y-m-d H:i:s');
        db()->prepare("INSERT INTO pages (title, slug, status, seo_title, seo_description, seo_keywords, og_image, canonical, noindex, blocks_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$title, $slug, $status, $seoTitle, $seoDesc, $seoKw, $ogImage, $canonical, $noindex, $blocksJson, $now, $now]);
        $pageId = db()->lastInsertId();
    }
    // ذخیرهٔ بلوک‌ها به‌صورت ردیفی هم (برای query سریع)
    db()->prepare("DELETE FROM page_blocks WHERE page_id = ?")->execute([$pageId]);
    $pos = 0;
    foreach ($blocksArr as $b) {
        db()->prepare("INSERT INTO page_blocks (page_id, type, content_json, position) VALUES (?,?,?,?)")
            ->execute([$pageId, $b['type'] ?? 'unknown', json_encode($b, JSON_UNESCAPED_UNICODE), $pos++]);
    }
    flash('success', 'صفحه ذخیره شد.');
    header('Location: page_builder.php?edit=' . $pageId);
    exit;
}
// حذف صفحه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete_page') {
    $delId = (int)($_POST['page_id'] ?? 0);
    if ($delId > 0) {
        db()->prepare("DELETE FROM page_blocks WHERE page_id = ?")->execute([$delId]);
        // طرح ویرایشگر بصری هم پاک شود تا رکورد یتیم نماند
        try { db()->prepare("DELETE FROM page_designs WHERE page_id = ?")->execute([$delId]); } catch (Throwable $e) {}
        db()->prepare("DELETE FROM pages WHERE id = ?")->execute([$delId]);
        flash('success', 'صفحه حذف شد.');
    }
    header('Location: page_builder.php');
    exit;
}
?>

<div class="page-builder">
    <aside class="pb-sidebar">
        <h2>📄 صفحات</h2>
        <a href="?new=1" class="btn btn-accent btn-block">+ صفحهٔ جدید</a>
        <ul class="pb-pages">
            <?php foreach ($pages as $p): ?>
                <li>
                    <a href="?edit=<?= (int)$p['id'] ?>" class="<?= ($editing && $editing['id']==$p['id']) ? 'is-active' : '' ?>">
                        <span class="pb-page__title"><?= e($p['title'] ?: '(بدون عنوان)') ?></span>
                        <span class="pb-page__slug">/<?= e($p['slug']) ?></span>
                        <span class="pb-page__status pb-page__status--<?= e($p['status']) ?>"><?= $p['status']==='published' ? 'منتشر' : 'پیش‌نویس' ?></span>
                    </a>
                
                <form method="post" action="page_builder.php" onsubmit="return confirm('این صفحه حذف شود؟');" style="margin:6px 10px 12px">
                    <input type="hidden" name="action" value="delete_page">
                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                    <?= csrf_field() ?>
                    <button type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;padding:5px 12px;font-size:12px;cursor:pointer;font-family:inherit">🗑 حذف صفحه</button>
                </form>
            </li>
            <?php endforeach; ?>
            <?php if (!$pages): ?><li class="pb-empty">هنوز صفحه‌ای نساخته‌اید.</li><?php endif; ?>
        </ul>
    </aside>

    <main class="pb-main">
        <?php if (!$editing && !isset($_GET['new'])): ?>
            <div class="pb-welcome">
                <h1>🧱 سازندهٔ صفحه</h1>
                <p>با کشیدن و رها کردن بلوک‌ها، صفحهٔ فرود یا لندینگ پیج حرفه‌ای بسازید.</p>
                <p>از ستون چپ یک صفحه انتخاب کنید یا <a href="?new=1">صفحهٔ جدید</a> بسازید.</p>
            </div>
        <?php else: ?>
            <?php if (!$editing) $editing = ['id'=>0, 'title'=>'', 'slug'=>'', 'status'=>'draft', 'seo_title'=>'', 'seo_description'=>'', 'seo_keywords'=>'', 'og_image'=>'', 'canonical'=>'', 'noindex'=>0, 'blocks_json'=>'[]']; ?>
            <form method="post" id="pbForm" class="pb-form">
                <?= csrf_field() ?>
                <input type="hidden" name="page_id" value="<?= (int)$editing['id'] ?>">
                <input type="hidden" name="blocks_json" id="blocksJson" value='<?= e($editing['blocks_json'] ?: '[]') ?>'>

                <div class="pb-head">
                    <input type="text" name="title" placeholder="عنوان صفحه" class="pb-title" value="<?= e($editing['title']) ?>" required>
                    <div class="pb-actions">
                        <select name="status">
                            <option value="draft" <?= $editing['status']==='draft'?'selected':'' ?>>پیش‌نویس</option>
                            <option value="published" <?= $editing['status']==='published'?'selected':'' ?>>منتشر</option>
                        </select>
                        <a href="page_designer.php?page=<?= (int)$editing['id'] ?>" class="btn">🎨 طراحی بصری</a>
                        <a href="<?= BASE_URL ?>/p/<?= e($editing['slug'] ?: 'preview') ?>" target="_blank" class="btn">👁 پیش‌نمایش</a>
                        <button type="submit" class="btn btn-accent">💾 ذخیره</button>
                    </div>
                </div>

                <div class="pb-tabs">
                    <button type="button" class="pb-tab is-active" data-pane="blocks">🧱 بلوک‌ها</button>
                    <button type="button" class="pb-tab" data-pane="seo">🔍 سئو</button>
                    <button type="button" class="pb-tab" data-pane="settings">⚙️ تنظیمات</button>
                </div>

                <div class="pb-pane is-active" data-pane="blocks">
                    <div class="pb-canvas" id="pbCanvas"></div>
                    <div class="pb-blocks-palette">
                        <h4>افزودن بلوک</h4>
                        <button type="button" data-add="hero">🌟 سربرگ (Hero)</button>
                        <button type="button" data-add="features">✨ ویژگی‌ها</button>
                        <button type="button" data-add="products">🛍 محصولات</button>
                        <button type="button" data-add="text">📝 متن آزاد</button>
                        <button type="button" data-add="image">🖼 تصویر</button>
                        <button type="button" data-add="cta">📣 دعوت به اقدام</button>
                        <button type="button" data-add="form">📬 فرم تماس</button>
                        <button type="button" data-add="testimonials">💬 نظرات مشتریان</button>
                        <button type="button" data-add="faq">❓ پرسش و پاسخ</button>
                        <button type="button" data-add="video">🎬 ویدیو</button>
                        <button type="button" data-add="pricing">💎 تعرفه</button>
                    </div>
                </div>

                <div class="pb-pane" data-pane="seo" hidden>
                    <div class="form-row">
                        <label>عنوان سئو (Title)
                            <input type="text" name="seo_title" value="<?= e($editing['seo_title']) ?>" maxlength="70">
                            <small>پیشنهاد: ۵۰ تا ۶۰ کاراکتر</small>
                        </label>
                    </div>
                    <div class="form-row">
                        <label>توضیح سئو (Description)
                            <textarea name="seo_description" rows="3" maxlength="200"><?= e($editing['seo_description']) ?></textarea>
                            <small>پیشنهاد: ۱۲۰ تا ۱۵۰ کاراکتر</small>
                        </label>
                    </div>
                    <div class="form-row">
                        <label>کلیدواژه‌ها (با ویرگول)
                            <input type="text" name="seo_keywords" value="<?= e($editing['seo_keywords']) ?>">
                        </label>
                    </div>
                    <div class="form-row">
                        <label>آدرس (slug)
                            <input type="text" name="slug" value="<?= e($editing['slug']) ?>" dir="ltr" pattern="[a-z0-9-]+">
                            <small>فقط حروف انگلیسی کوچک، خط تیره و عدد</small>
                        </label>
                    </div>
                    <div class="form-row">
                        <label>تصویر Open Graph
                            <input type="text" name="og_image" value="<?= e($editing['og_image']) ?>" placeholder="/uploads/og.jpg">
                        </label>
                    </div>
                    <div class="form-row">
                        <label>Canonical URL
                            <input type="text" name="canonical" value="<?= e($editing['canonical']) ?>" dir="ltr">
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="checkbox"><input type="checkbox" name="noindex" value="1" <?= $editing['noindex']?'checked':'' ?>> ایندکس نشدن در گوگل (noindex)</label>
                    </div>
                </div>

                <div class="pb-pane" data-pane="settings" hidden>
                    <p>تنظیمات اضافی صفحه</p>
                </div>
            </form>
        <?php endif; ?>
    </main>
</div>

<script src="<?= BASE_URL ?>/assets/admin/js/page-builder.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin/css/page-builder.css">
