<?php
// دیباگ موقت
$DBG = function () {
    @file_put_contents(
        'C:/Users/CharismaStore.ir/.openclaw-autoclaw/workspace/.openclaw/tmp/post-debug.log',
        print_r([
            'method' => $_SERVER['REQUEST_METHOD'] ?? '?',
            'step'   => $_POST['step'] ?? '(none)',
            'ut_len' => strlen($_POST['user_text'] ?? ''),
            'ferr'   => $_FILES['product_image']['error'] ?? -1,
        ], true),
        FILE_APPEND
    );
};

/**
 * admin/ai_add_product.php — افزودن محصول با هوش مصنوعی
 * کاربر: عکس آپلود می‌کند + توضیح متنی می‌دهد → AI مشخصات استخراج و تکمیل می‌کند
 * → پیش‌نمایش و تأیید → محصول با همان عکس به دیتابیس اضافه می‌شود.
 */
$pageTitle = 'افزودن محصول با هوش مصنوعی';
require __DIR__ . '/_header.php';

if (!empty($DBG)) { $DBG(); }

$errors = [];
$preview = null;
$uploadedImage = '';

$categories = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'generate') {
    if (!csrf_verify()) {
        $errors[] = 'نشست شما منقضی شده است.';
    }

    $userText = trim($_POST['user_text'] ?? '');
    $hasFile = isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$hasFile) {
        $errors[] = 'تصویر محصول را انتخاب کنید (الزامی).';
    }
    if ($userText === '') {
        $errors[] = 'توضیح محصول را بنویسید (مثلاً: «دریل شارژی ۱۸ ولت برند بوش، دو باتری، قیمت حدود ۴ میلیون»).';
    }

    if (!$errors && setting('ai_api_key', '') === '') {
        $errors[] = 'کلید API هوش مصنوعی تنظیم نشده است (تنظیمات ← هوش مصنوعی).';
    }

    if (!$errors) {
        [$ok, $path, $upErr] = process_image_upload($_FILES['product_image'], 1200, 1200, 8388608);
        if (!$ok) {
            $errors[] = $upErr;
        } else {
            $_SESSION['ai_product_image'] = $path;
            $uploadedImage = $path;

            $res = ai_extract_product($userText);
            if ($res['ok']) {
                $preview = $res['product'];
                foreach ($categories as $c) {
                    if (mb_stripos($c['name'], $preview['category']) !== false || mb_stripos($preview['category'], $c['name']) !== false) {
                        $preview['category_id'] = (int)$c['id'];
                        break;
                    }
                }
            } else {
                $errors[] = $res['error'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'confirm') {
    if (!csrf_verify()) {
        $errors[] = 'نشست شما منقضی شده است.';
    }
    $name = trim($_POST['name'] ?? '');
    $price = (int)preg_replace('/[^0-9]/', '', (string)($_POST['price'] ?? '0'));
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $imagePath = trim((string)($_SESSION['ai_product_image'] ?? ''));

    if ($name === '' || $price <= 0 || $imagePath === '') {
        $errors[] = 'اطلاعات ناقص است. از ابتدا شروع کنید.';
    }

    if (!$errors) {
        try {
            $st = db()->prepare("INSERT INTO products (name, description, price, category_id, stock, image, active)
                                 VALUES (?, ?, ?, ?, ?, ?, 1)");
            $st->execute([$name, $description, $price, $categoryId ?: null, $stock, $imagePath]);
            $newId = (int)db()->lastInsertId();
            unset($_SESSION['ai_product_image']);
            flash('success', 'محصول «' . $name . '» با موفقیت اضافه شد.');
            header('Location: product_edit.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'ثبت محصول ناموفق: ' . $e->getMessage();
        }
    }
}
?>
<style>
.ai-add-steps { display:flex; gap:.5rem; margin-bottom:1rem; font-size:.82rem; color:var(--ink-soft); }
.ai-step { padding:.3rem .8rem; border-radius:999px; background:var(--bg); border:1px solid var(--line); }
.ai-step.active { background: var(--accent); color:#fff; border-color: var(--accent); }
.ai-preview-img { max-width:240px; border-radius:10px; border:1px solid var(--line); display:block; margin-bottom:.8rem; }
</style>

<h1 class="page-title">افزودن محصول با هوش مصنوعی</h1>

<div class="ai-add-steps">
    <span class="ai-step <?= !$preview ? 'active' : '' ?>">۱) عکس + توضیح</span>
    <span class="ai-step <?= $preview ? 'active' : '' ?>">۲) بررسی و تأیید</span>
    <span class="ai-step">۳) ثبت</span>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (setting('ai_api_key', '') === '' && !$preview): ?>
<div class="ai-note">برای این قابلیت، کلید API را در <a href="settings.php?tab=ai">تنظیمات ← هوش مصنوعی</a> وارد کنید.</div>
<?php endif; ?>

<?php if ($preview === null): ?>
    <!-- ====== مرحلهٔ ۱ ====== -->
    <form method="post" action="ai_add_product.php" class="admin-form" enctype="multipart/form-data" style="max-width:640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="generate">
        <label>تصویر محصول *
            <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </label>
        <label>توضیح شما (به زبان خودتان بنویسید) *
            <textarea name="user_text" rows="4" required placeholder="مثلاً: دریل شارژی ۱۸ ولت برند بوش با دو باتری و کیف، قیمت حدود ۴٬۵۰۰٬۰۰۰ تومان، مناسب کارهای سنگین ساختمانی"></textarea>
        </label>
        <p class="muted">AI از توضیح شما نام حرفه‌ای، قیمت، توضیح بازاریابی و دسته‌بندی پیشنهاد می‌دهد؛ سپس قبل از ثبت، همه‌چیز را بررسی و اصلاح می‌کنید.</p>
        <button type="submit" class="btn btn-accent">تولید با هوش مصنوعی</button>
    </form>
<?php else: ?>
    <!-- ====== مرحلهٔ ۲: پیش‌نمایش و تأیید ====== -->
    <form method="post" action="ai_add_product.php" class="admin-form" style="max-width:640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="confirm">
        <?php if (!empty($_SESSION['ai_product_image'])): ?>
            <img src="<?= e(product_image_url($_SESSION['ai_product_image'])) ?>" alt="تصویر محصول" class="ai-preview-img">
        <?php endif; ?>
        <p class="muted">پیشنهاد هوش مصنوعی — می‌توانید هر فیلد را اصلاح کنید:</p>
        <label>نام محصول *
            <input type="text" name="name" value="<?= e($preview['name']) ?>" required>
        </label>
        <label>قیمت (تومان) *
            <input type="number" name="price" value="<?= (int)$preview['price'] ?>" min="0" step="1000" dir="ltr">
        </label>
        <label>دسته‌بندی
            <select name="category_id">
                <option value="0">— بدون دسته —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($preview['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>موجودی
            <input type="number" name="stock" value="<?= (int)$preview['stock'] ?>" min="0" dir="ltr">
        </label>
        <label>توضیحات
            <textarea name="description" rows="5"><?= e($preview['description']) ?></textarea>
        </label>
        <button type="submit" class="btn btn-accent">ثبت محصول در فروشگاه</button>
        <a href="ai_add_product.php" class="btn btn-ghost">انصراف / شروع مجدد</a>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
