<?php
/**
 * admin/seo_manager.php — مدیریت حرفه‌ای سئو
 * قابلیت‌ها: لینک‌های شکسته، ریدایرکت‌ها، sitemap دستی، اسکیما، و robots per-page
 */
$pageTitle = 'سئو حرفه‌ای';
require __DIR__ . '/_header.php';

$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $a = $_POST['action'] ?? '';
    if ($a === 'add_redirect') {
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        $code = (int)($_POST['code'] ?? 301);
        if ($from !== '' && $to !== '') {
            db()->prepare("INSERT INTO seo_redirects (from_path, to_url, code, created_at) VALUES (?,?,?,NOW())")
                ->execute([$from, $to, $code]);
            flash('success', 'ریدایرکت اضافه شد.');
        }
    } elseif ($a === 'del_redirect') {
        db()->prepare("DELETE FROM seo_redirects WHERE id = ?")->execute([(int)$_POST['id']]);
        flash('success', 'حذف شد.');
    } elseif ($a === 'save_meta') {
        $table = $_POST['table'] ?? '';
        $id = (int)$_POST['id'];
        $fields = ['seo_title','seo_description','seo_keywords','canonical','noindex','og_image'];
        if (in_array($table, ['products','articles','categories','pages'], true)) {
            $sets = []; $params = [];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) { $sets[] = "$f = ?"; $params[] = $_POST[$f]; }
            }
            if ($sets) {
                $params[] = $id;
                db()->prepare("UPDATE $table SET " . implode(',', $sets) . " WHERE id = ?")->execute($params);
                flash('success', 'سئو ذخیره شد.');
            }
        }
    }
    header('Location: seo_manager.php?tab=' . $tab);
    exit;
}

// دادهٔ هر تب
$redirects = $tab === 'redirects' ? db()->query("SELECT * FROM seo_redirects ORDER BY id DESC LIMIT 200")->fetchAll() : [];

$lowSeo = [];
if ($tab === 'overview') {
    // محصولات/مقالات بدون سئو
    $lowSeo['products'] = db()->query("SELECT id, name, slug FROM products WHERE (seo_title = '' OR seo_title IS NULL) OR (seo_description = '' OR seo_description IS NULL) LIMIT 50")->fetchAll();
    $lowSeo['articles'] = db()->query("SELECT id, title, slug FROM articles WHERE (seo_title = '' OR seo_title IS NULL) OR (seo_description = '' OR seo_description IS NULL) LIMIT 50")->fetchAll();
    $lowSeo['pages'] = db()->query("SELECT id, title, slug FROM pages WHERE (seo_title = '' OR seo_title IS NULL) OR (seo_description = '' OR seo_description IS NULL) LIMIT 50")->fetchAll();
}
?>

<div class="seo-manager">
    <div class="tabs">
        <a href="?tab=overview" class="<?= $tab==='overview'?'active':'' ?>">📊 نمای کلی</a>
        <a href="?tab=redirects" class="<?= $tab==='redirects'?'active':'' ?>">↪️ ریدایرکت‌ها</a>
        <a href="?tab=meta" class="<?= $tab==='meta'?'active':'' ?>">✏️ سئوی تکی</a>
        <a href="?tab=schema" class="<?= $tab==='schema'?'active':'' ?>">🧩 اسکیما</a>
        <a href="?tab=robots" class="<?= $tab==='robots'?'active':'' ?>">🤖 Robots</a>
    </div>

    <?php if ($tab === 'overview'): ?>
        <h2>📊 سلامت سئو</h2>
        <?php foreach ($lowSeo as $tbl => $rows): ?>
            <div class="seo-card">
                <h3><?= $tbl === 'products' ? 'محصولات' : ($tbl === 'articles' ? 'مقالات' : 'صفحات') ?> بدون سئو (<?= count($rows) ?>)</h3>
                <?php if (!$rows): ?><p class="muted">همگی سئو دارند. ✓</p><?php else: ?>
                <ul class="seo-list">
                    <?php foreach ($rows as $r): ?>
                        <li>
                            <a href="?tab=meta&type=<?= $tbl ?>&id=<?= (int)$r['id'] ?>"><?= e($r['name'] ?? $r['title']) ?></a>
                            <small>/<?= e($r['slug'] ?? '') ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php elseif ($tab === 'redirects'): ?>
        <h2>↪️ مدیریت ریدایرکت‌ها</h2>
        <form method="post" class="seo-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_redirect">
            <div class="form-grid">
                <label>آدرس مبدا (نسبی)<input type="text" name="from" placeholder="/old-page" dir="ltr" required></label>
                <label>آدرس مقصد<input type="text" name="to" placeholder="/new-page یا https://..." dir="ltr" required></label>
                <label>کد<select name="code"><option value="301">301 (دائمی)</option><option value="302">302 (موقت)</option><option value="410">410 (حذف شده)</option></select></label>
                <button type="submit" class="btn btn-accent">+ افزودن</button>
            </div>
        </form>

        <table class="data-table">
            <thead><tr><th>مبدا</th><th>مقصد</th><th>کد</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($redirects as $r): ?>
                <tr>
                    <td dir="ltr"><?= e($r['from_path']) ?></td>
                    <td dir="ltr"><?= e($r['to_url']) ?></td>
                    <td><?= (int)$r['code'] ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="del_redirect">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($tab === 'meta'): ?>
        <h2>✏️ ویرایش سئوی تکی</h2>
        <?php
        $type = $_GET['type'] ?? 'products';
        $id = (int)($_GET['id'] ?? 0);
        $tbl = in_array($type, ['products','articles','categories','pages']) ? $type : 'products';
        if ($id) {
            $st = db()->prepare("SELECT * FROM $tbl WHERE id = ?");
            $st->execute([$id]); $row = $st->fetch();
        } else { $row = null; }
        if ($row):
        ?>
        <form method="post" class="seo-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_meta">
            <input type="hidden" name="table" value="<?= e($tbl) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <p><strong>عنوان:</strong> <?= e($row['name'] ?? $row['title']) ?></p>
            <label>SEO Title (حداکثر ۷۰ کاراکتر)
                <input type="text" name="seo_title" value="<?= e($row['seo_title'] ?? '') ?>" maxlength="70">
            </label>
            <label>SEO Description (حداکثر ۱۶۰ کاراکتر)
                <textarea name="seo_description" rows="2" maxlength="160"><?= e($row['seo_description'] ?? '') ?></textarea>
            </label>
            <label>کلیدواژه‌ها
                <input type="text" name="seo_keywords" value="<?= e($row['seo_keywords'] ?? '') ?>">
            </label>
            <label>Canonical URL
                <input type="text" name="canonical" value="<?= e($row['canonical'] ?? '') ?>" dir="ltr">
            </label>
            <label>Open Graph Image
                <input type="text" name="og_image" value="<?= e($row['og_image'] ?? '') ?>" dir="ltr">
            </label>
            <label class="checkbox">
                <input type="checkbox" name="noindex" value="1" <?= !empty($row['noindex']) ? 'checked' : '' ?>>
                ایندکس نشدن در گوگل
            </label>
            <button type="submit" class="btn btn-accent">ذخیره</button>
        </form>
        <?php else: ?>
            <p>یک آیتم از لیست «نمای کلی» انتخاب کنید.</p>
        <?php endif; ?>
    <?php elseif ($tab === 'schema'): ?>
        <h2>🧩 اسکیمای سایت</h2>
        <p>اسکیمای JSON-LD سازمان، محصول و مقاله به‌صورت خودکار در <code>includes/header.php</code> اضافه می‌شود.</p>
        <p>برای تنظیم دستی، <a href="?tab=meta">سئوی تکی</a> هر صفحه را ویرایش کنید.</p>
    <?php elseif ($tab === 'robots'): ?>
        <h2>🤖 Robots.txt</h2>
        <p>فایل <code>/robots.txt</code> به‌صورت خودکار تولید می‌شود. محتوای فعلی:</p>
        <pre class="code-block"><?= e(file_get_contents(__DIR__ . '/../robots.txt')) ?></pre>
    <?php endif; ?>
</div>
