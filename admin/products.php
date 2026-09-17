<?php
/**
 * admin/products.php — لیست محصولات + عملیات گروهی (checkbox + bulk actions)
 */
$pageTitle = 'مدیریت محصولات';
require __DIR__ . '/_header.php';

// ===== عملیات گروهی =====
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action  = $_POST['bulk_action'];
    $ids_raw = $_POST['selected'] ?? [];
    $ids = array_filter(array_map('intval', is_array($ids_raw) ? $ids_raw : [$ids_raw]));
    $count = count($ids);

    if ($count === 0) {
        $msg = '<div class="alert alert-error">هیچ محصولی انتخاب نشده است.</div>';
    } else {
        $placeholders = implode(',', array_fill(0, $count, '?'));
        switch ($action) {
            case 'delete':
                // حذف فقط محصولاتی که سفارش ندارند؛ بقیه غیرفعال می‌شوند
                $deleted = 0; $deactivated = 0;
                foreach ($ids as $pid) {
                    $has_orders = db()->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                    $has_orders->execute([$pid]);
                    if ($has_orders->fetchColumn() > 0) {
                        db()->prepare("UPDATE products SET active = 0 WHERE id = ?")->execute([$pid]);
                        $deactivated++;
                    } else {
                        $prod = get_product($pid);
                        if ($prod && !empty($prod['image']) && strpos($prod['image'], 'uploads/') === 0) {
                            $f = __DIR__ . '/../' . $prod['image'];
                            if (is_file($f)) { @unlink($f); }
                        }
                        db()->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$pid]);
                        db()->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
                        $deleted++;
                    }
                }
                $parts = [];
                if ($deleted) $parts[] = "حذف {$deleted} محصول";
                if ($deactivated) $parts[] = "غیرفعال‌سازی {$deactivated} محصول (سفارش داشتند)";
                $msg = '<div class="alert alert-success">عملیات انجام شد: ' . implode('، ', $parts) . '.</div>';
                break;

            case 'activate':
                db()->prepare("UPDATE products SET active = 1 WHERE id IN ($placeholders)")->execute($ids);
                $msg = '<div class="alert alert-success">' . $count . ' محصول فعال شد.</div>';
                break;

            case 'deactivate':
                db()->prepare("UPDATE products SET active = 0 WHERE id IN ($placeholders)")->execute($ids);
                $msg = '<div class="alert alert-success">' . $count . ' محصول غیرفعال شد.</div>';
                break;

            case 'feature':
                db()->prepare("UPDATE products SET featured = 1 WHERE id IN ($placeholders)")->execute($ids);
                $msg = '<div class="alert alert-success">' . $count . ' محصول ویژه شد.</div>';
                break;

            case 'unfeature':
                db()->prepare("UPDATE products SET featured = 0 WHERE id IN ($placeholders)")->execute($ids);
                $msg = '<div class="alert alert-success">' . $count . ' محصول از حالت ویژه خارج شد.</div>';
                break;

            case 'move_category':
                $target = (int)($_POST['target_category'] ?? 0);
                if ($target > 0) {
                    db()->prepare("UPDATE products SET category_id = ? WHERE id IN ($placeholders)")->execute(array_merge([$target], $ids));
                    $msg = '<div class="alert alert-success">' . $count . ' محصول به دستهٔ #' . $target . ' منتقل شد.</div>';
                } else {
                    $msg = '<div class="alert alert-error">دستهٔ مقصد انتخاب نشده.</div>';
                }
                break;

            case 'set_price':
                $new_price = (int)str_replace([',', ' '], '', $_POST['new_price'] ?? '0');
                $mode = $_POST['price_mode'] ?? 'set'; // set | inc | dec | inc_pct | dec_pct
                if ($new_price > 0 || in_array($mode, ['inc', 'dec', 'inc_pct', 'dec_pct'])) {
                    foreach ($ids as $pid) {
                        $p = get_product($pid);
                        if (!$p) continue;
                        $cur = (int)$p['price'];
                        $new = $cur;
                        switch ($mode) {
                            case 'set':    $new = $new_price; break;
                            case 'inc':    $new = $cur + $new_price; break;
                            case 'dec':    $new = max(0, $cur - $new_price); break;
                            case 'inc_pct':$new = (int)($cur * (1 + $new_price/100)); break;
                            case 'dec_pct':$new = (int)($cur * (1 - $new_price/100)); break;
                        }
                        db()->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$new, $pid]);
                    }
                    $msg = '<div class="alert alert-success">قیمت ' . $count . ' محصول به‌روز شد.</div>';
                } else {
                    $msg = '<div class="alert alert-error">مبلغ وارد نشده.</div>';
                }
                break;

            case 'set_stock':
                $new_stock = (int)($_POST['new_stock'] ?? 0);
                db()->prepare("UPDATE products SET stock = ? WHERE id IN ($placeholders)")->execute(array_merge([$new_stock], $ids));
                $msg = '<div class="alert alert-success">موجودی ' . $count . ' محصول به ' . $new_stock . ' تنظیم شد.</div>';
                break;
        }
    }
}

// ===== دریافت لیست =====
$q = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1";
$args = [];
if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $args[] = "%$q%"; $args[] = "%$q%";
}
if ($cat_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $args[] = $cat_filter;
}
if ($status_filter === 'active')   $sql .= " AND p.active = 1";
if ($status_filter === 'inactive') $sql .= " AND p.active = 0";
if ($status_filter === 'featured') $sql .= " AND p.featured = 1";
$sql .= " ORDER BY p.id DESC";

$stmt = db()->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();

$categories = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<?= $msg ?>

<div class="page-head">
    <h1 class="page-title">مدیریت محصولات</h1>
    <a href="product_edit.php" class="btn btn-accent">+ افزودن محصول جدید</a>
</div>

<!-- فیلترها -->
<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="جستجو در نام/توضیحات..." class="input">
    <select name="cat" class="input">
        <option value="0">— همهٔ دسته‌ها —</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $cat_filter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="input">
        <option value="">— همه —</option>
        <option value="active"   <?= $status_filter === 'active' ? 'selected' : '' ?>>فقط فعال</option>
        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>فقط غیرفعال</option>
        <option value="featured" <?= $status_filter === 'featured' ? 'selected' : '' ?>>فقط ویژه</option>
    </select>
    <button type="submit" class="btn">اعمال فیلتر</button>
    <a href="products.php" class="btn btn-ghost">پاک کردن</a>
    <span class="muted" style="margin-right:auto">تعداد: <strong><?= count($products) ?></strong></span>
</form>

<!-- فرم عملیات گروهی -->
<form method="post" id="bulkForm" class="bulk-wrap">
    <!-- نوار ابزار گروهی -->
    <div class="bulk-bar" id="bulkBar">
        <label class="bulk-checkbox">
            <input type="checkbox" id="selectAll"> انتخاب همه
        </label>
        <span class="bulk-count" id="bulkCount">0 مورد انتخاب شده</span>

        <div class="bulk-actions">
            <button type="submit" name="bulk_action" value="activate"   class="btn btn-sm btn-success" onclick="return confirmBulk('فعال‌سازی')">✓ فعال‌سازی</button>
            <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-warning" onclick="return confirmBulk('غیرفعال‌سازی')">✕ غیرفعال‌سازی</button>
            <button type="submit" name="bulk_action" value="feature"    class="btn btn-sm btn-accent"  onclick="return confirmBulk('ویژه کردن')">★ ویژه</button>
            <button type="submit" name="bulk_action" value="unfeature"  class="btn btn-sm btn-ghost"   onclick="return confirmBulk('خروج از ویژه')">☆ عادی</button>

            <span class="bulk-sep">|</span>

            <!-- تغییر دسته -->
            <select name="target_category" class="input input-sm" form="bulkForm">
                <option value="0">انتقال به دسته...</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="bulk_action" value="move_category" class="btn btn-sm" onclick="return confirmBulk('انتقال به دستهٔ دیگر')">↪ انتقال</button>

            <span class="bulk-sep">|</span>

            <!-- تنظیم قیمت -->
            <select name="price_mode" class="input input-sm" form="bulkForm">
                <option value="set">تنظیم قیمت</option>
                <option value="inc">افزایش (مبلغ)</option>
                <option value="dec">کاهش (مبلغ)</option>
                <option value="inc_pct">افزایش (%)</option>
                <option value="dec_pct">کاهش (%)</option>
            </select>
            <input type="number" name="new_price" placeholder="مبلغ/درصد" class="input input-sm" min="0" step="any" form="bulkForm">
            <button type="submit" name="bulk_action" value="set_price" class="btn btn-sm" data-requires="new_price" onclick="return confirmBulk('تغییر قیمت')">💰 اعمال قیمت</button>

            <span class="bulk-sep">|</span>

            <!-- تنظیم موجودی -->
            <input type="number" name="new_stock" placeholder="موجودی" class="input input-sm" min="0" step="any" form="bulkForm">
            <button type="submit" name="bulk_action" value="set_stock" class="btn btn-sm" data-requires="new_stock" onclick="return confirmBulk('تغییر موجودی')">📦 اعمال موجودی</button>

            <span class="bulk-sep">|</span>

            <button type="submit" name="bulk_action" value="delete" class="btn btn-sm btn-danger" onclick="return confirmBulk('حذف')">🗑 حذف</button>
        </div>
    </div>

    <table class="data-table bulk-table">
        <thead>
            <tr>
                <th class="col-check"><input type="checkbox" id="selectAll2"></th>
                <th>#</th>
                <th>تصویر</th>
                <th>نام</th>
                <th>دسته</th>
                <th>قیمت</th>
                <th>موجودی</th>
                <th>ویژه</th>
                <th>وضعیت</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr class="<?= !$p['active'] ? 'row-inactive' : '' ?>">
                <td class="col-check">
                    <input type="checkbox" name="selected[]" value="<?= (int)$p['id'] ?>" class="row-check">
                </td>
                <td><?= (int)$p['id'] ?></td>
                <td>
                    <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image'])): ?>
                        <img src="../<?= e($p['image']) ?>" alt="" class="thumb">
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= e($p['name']) ?></td>
                <td><?= e($p['category_name'] ?? '—') ?></td>
                <td><?= e(fmt_price($p['price'])) ?></td>
                <td><?= (int)$p['stock'] ?></td>
                <td><?= $p['featured'] ? '★' : '☆' ?></td>
                <td><?= $p['active'] ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-muted">غیرفعال</span>' ?></td>
                <td class="actions">
                    <a href="product_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-sm">ویرایش</a>
                    <a href="products.php?action=delete&id=<?= (int)$p['id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('حذف این محصول؟')">حذف</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
            <tr><td colspan="10" class="empty">هیچ محصولی یافت نشد.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</form>

<style>
.bulk-wrap { margin-top: 16px; }
.bulk-bar {
    display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    padding: 12px 16px; background: #f7f8fb; border: 1px solid #e3e6ed;
    border-radius: 10px; margin-bottom: 12px; position: sticky; top: 0; z-index: 5;
}
.bulk-bar.is-active { background: #eaf3ff; border-color: #3b82f6; }
.bulk-checkbox { display: flex; align-items: center; gap: 6px; font-weight: 600; cursor: pointer; }
.bulk-count { color: #3b82f6; font-weight: 600; padding: 0 8px; }
.bulk-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.bulk-sep { color: #c8ccd4; margin: 0 2px; }
.input-sm { padding: 5px 8px; font-size: 13px; min-width: 0; }
.input-sm[name="new_price"], .input-sm[name="new_stock"] { width: 90px; }
.bulk-table th.col-check, .bulk-table td.col-check { width: 36px; text-align: center; }
.row-check { cursor: pointer; }
.row-inactive { opacity: 0.6; }
.bulk-table tbody tr:hover { background: #f0f7ff; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-muted   { background: #e5e7eb; color: #4b5563; }
.thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e3e6ed; }
.filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0; align-items: center; }
.filters .input { padding: 7px 10px; }
.muted { color: #6b7280; font-size: 13px; }
.page-head { display: flex; justify-content: space-between; align-items: center; }
.empty { text-align: center; padding: 32px; color: #9ca3af; }
</style>

<script>
(function() {
    const form = document.getElementById('bulkForm');
    const bar  = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    const all1 = document.getElementById('selectAll');
    const all2 = document.getElementById('selectAll2');
    const checks = () => form.querySelectorAll('.row-check');

    function update() {
        const n = Array.from(checks()).filter(c => c.checked).length;
        count.textContent = n + ' مورد انتخاب شده';
        bar.classList.toggle('is-active', n > 0);
        const all = checks().length > 0 && n === checks().length;
        all1.checked = all; all2.checked = all;
    }

    function bindAll(el) {
        el.addEventListener('change', () => {
            checks().forEach(c => c.checked = el.checked);
            update();
        });
    }
    bindAll(all1); bindAll(all2);

    checks().forEach(c => c.addEventListener('change', update));

    // جلوگیری از ارسال فرم بدون انتخاب + بررسی فیلد مورد نیاز
    form.addEventListener('submit', (e) => {
        const submitter = e.submitter;
        if (!submitter || submitter.name !== 'bulk_action') return;

        const n = Array.from(checks()).filter(c => c.checked).length;
        if (n === 0) {
            e.preventDefault();
            alert('هیچ محصولی انتخاب نشده است.');
            return false;
        }

        // بررسی فیلد اختصاصی هر عملیات
        const req = submitter.dataset.requires;
        if (req) {
            const fld = form.querySelector('[name="' + req + '"]');
            const v = (fld?.value || '').trim();
            if (v === '' || isNaN(parseFloat(v))) {
                e.preventDefault();
                alert('لطفاً مقدار فیلد «' + (fld?.placeholder || req) + '» را وارد کنید.');
                fld?.focus();
                return false;
            }
        }
    });

    update();
})();

function confirmBulk(action) {
    const n = document.querySelectorAll('.row-check:checked').length;
    if (n === 0) { alert('هیچ محصولی انتخاب نشده.'); return false; }
    return confirm('آیا می\u200cخواهید ' + n + ' محصول انتخاب\u200cشده را ' + action + ' کنید؟');
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
