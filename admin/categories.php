<?php
/**
 * admin/categories.php — مدیریت دسته‌بندی‌ها (با تصویر)
 */
$pageTitle = 'مدیریت دسته‌بندی‌ها';
require __DIR__ . '/_header.php';

// حذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    db()->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$_GET['id']]);
    flash('success', 'دسته‌بندی حذف شد.');
    header('Location: categories.php');
    exit;
}

// افزودن / ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($name !== '') {
        // آپلود تصویر (اختیاری)
        $image = trim($_POST['image'] ?? '');
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path, $upErr] = process_image_upload($_FILES['image_file'], 400, 400, 5242880);
            if ($ok) {
                $image = $path;
            } else {
                flash('error', $upErr);
            }
        }

        if ($id > 0) {
            $st = db()->prepare("UPDATE categories SET name = ?, description = ?, image = COALESCE(?, image) WHERE id = ?");
            // COALESCE تا اگر فیلد تصویر خالی بود، مقدار قبلی حفظ شود
            $st->execute([$name, $desc, $image !== '' ? $image : null, $id]);
            flash('success', 'دسته‌بندی به‌روزرسانی شد.');
        } else {
            $st = db()->prepare("INSERT INTO categories (name, slug, description, image) VALUES (?, ?, ?, ?)");
            $st->execute([$name, make_slug($name), $desc, $image]);
            flash('success', 'دسته‌بندی افزوده شد.');
        }
    }
    header('Location: categories.php');
    exit;
}

// ویرایش یک دسته
$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare("SELECT * FROM categories WHERE id = ?");
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$categories = get_categories();
?>

<h1 class="page-title">مدیریت دسته‌بندی‌ها</h1>

<form method="post" action="categories.php" class="admin-form" enctype="multipart/form-data" style="max-width: 560px; margin-bottom: 2rem;">
    <?php if ($edit): ?>
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <h2 style="font-size: 1.05rem; margin-bottom: .8rem;">ویرایش «<?= e($edit['name']) ?>»</h2>
    <?php else: ?>
        <h2 style="font-size: 1.05rem; margin-bottom: .8rem;">دسته‌بندی جدید</h2>
    <?php endif; ?>
    <label>نام دسته‌بندی *
        <input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required>
    </label>
    <label>توضیح (اختیاری)
        <input type="text" name="description" value="<?= e($edit['description'] ?? '') ?>" placeholder="توضیح کوتاه">
    </label>
    <label>تصویر دسته‌بندی (اختیاری)
        <?php if (!empty($edit['image'])): ?>
            <span class="logo-preview"><img src="<?= e(product_image_url($edit['image'])) ?>" alt="تصویر دسته"></span>
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <input type="text" name="image" value="<?= e($edit['image'] ?? '') ?>" dir="ltr" placeholder="یا آدرس مستقیم تصویر">
    </label>
    <button type="submit" class="btn btn-accent"><?= $edit ? 'ذخیرهٔ تغییرات' : 'افزودن' ?></button>
    <?php if ($edit): ?><a href="categories.php" class="btn btn-ghost">انصراف</a><?php endif; ?>
</form>

<table class="data-table">
    <thead><tr><th>#</th><th>تصویر</th><th>نام</th><th>تعداد محصول</th><th>عملیات</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td><?= (int)$c['id'] ?></td>
            <td>
                <?php if (!empty($c['image'])): ?>
                    <img src="<?= e(product_image_url($c['image'])) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                <?php else: ?>
                    <span class="thumb-placeholder" style="width:44px;height:44px;display:inline-grid;place-items:center;border-radius:8px;background:var(--accent-soft);color:var(--accent);font-weight:700;"><?= e(mb_substr($c['name'], 0, 1, 'UTF-8')) ?></span>
                <?php endif; ?>
            </td>
            <td><?= e($c['name']) ?></td>
            <td><?= (int)$c['cnt'] ?></td>
            <td>
                <a href="categories.php?edit=<?= (int)$c['id'] ?>" class="btn btn-sm">ویرایش</a>
                <a href="categories.php?action=delete&id=<?= (int)$c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')">حذف</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/_footer.php'; ?>
