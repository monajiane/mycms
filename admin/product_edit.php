<?php
/**
 * admin/product_edit.php — فرم افزودن / ویرایش محصول
 * شامل: آپلود چند تصویر (گالری) + ادیتور متن Quill (متن‌باز و رایگان)
 */
$pageTitle = 'ویرایش محصول';
require __DIR__ . '/_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = $id ? get_product($id) : null;
$errors = [];

// ---- حذف یک تصویر از گالری ----
if (isset($_GET['action']) && $_GET['action'] === 'delete_image' && isset($_GET['img'])) {
    $imgId = (int)$_GET['img'];
    $st = db()->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
    $st->execute([$imgId, $id]);
    $row = $st->fetch();
    if ($row) {
        if (strpos($row['image'], 'uploads/') === 0) {
            $f = __DIR__ . '/../' . $row['image'];
            if (is_file($f)) {
                @unlink($f);
            }
        }
        db()->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
        flash('success', 'تصویر حذف شد.');
    }
    header('Location: product_edit.php?id=' . $id);
    exit;
}

// ---- حذف یک وارینت ----
if (isset($_GET['action']) && $_GET['action'] === 'delete_variant' && isset($_GET['vid'])) {
    db()->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?")
        ->execute([(int)$_GET['vid'], $id]);
    flash('success', 'نوع (وارینت) حذف شد.');
    header('Location: product_edit.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = sanitize_html($_POST['description'] ?? '');
    $price       = (int)($_POST['price'] ?? 0);
    $salePrice   = (int)($_POST['sale_price'] ?? 0);
    if ($salePrice <= 0 || $salePrice >= $price) {
        $salePrice = null; // نامعتبر → بدون تخفیف تکی
    }
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $featured    = isset($_POST['featured']) ? 1 : 0;
    $active      = isset($_POST['active']) ? 1 : 0;
    $image       = trim($_POST['image'] ?? '');

    if ($name === '') {
        $errors[] = 'نام محصول الزامی است.';
    }
    if ($price <= 0) {
        $errors[] = 'قیمت باید بزرگ‌تر از صفر باشد.';
    }

    if (!$errors) {
        $slug = make_slug($name);

        // ۱) ذخیرهٔ محصول (ابتدا برای گرفتن شناسه)
        if ($id) {
            db()->prepare("UPDATE products SET name=?, slug=?, description=?, price=?, category_id=?, stock=?, image=?, featured=?, active=?, sale_price=? WHERE id=?")
                ->execute([$name, $slug, $description, $price, $categoryId ?: null, $stock, $image, $featured, $active, $salePrice, $id]);
            $productId = $id;
        } else {
            db()->prepare("INSERT INTO products (name, slug, description, price, category_id, stock, image, featured, active, sale_price) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$name, $slug, $description, $price, $categoryId ?: null, $stock, $image, $featured, $active, $salePrice]);
            $productId = (int)db()->lastInsertId();
        }

        // ۲) آپلود تصویر اصلی (تکی) — جایگزین تصویر قبلی
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['image_file'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'خطا در آپلود تصویر اصلی (کد ' . (int)$f['error'] . ').';
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $errors[] = 'حجم تصویر اصلی نباید بیش از ۵ مگابایت باشد.';
            } elseif (!isset($allowed[$mime])) {
                $errors[] = 'فرمت تصویر اصلی مجاز نیست (فقط JPG/PNG/WebP/GIF).';
            } else {
                $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $old = $product['image'] ?? '';
                    if ($old && strpos($old, 'uploads/') === 0 && is_file(__DIR__ . '/../' . $old)) {
                        @unlink(__DIR__ . '/../' . $old);
                    }
                    db()->prepare("UPDATE products SET image = ? WHERE id = ?")->execute(['uploads/' . $filename, $productId]);
                } else {
                    $errors[] = 'ذخیرهٔ تصویر اصلی ناموفق بود (دسترسی نوشتن پوشهٔ uploads را بررسی کنید).';
                }
            }
        }

        // ۳) آپلود تصاویر گالری (چندتایی)
        if (isset($_FILES['image_files']) && is_array($_FILES['image_files']['name'])) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $count = count($_FILES['image_files']['name']);
            $uploaded = 0;
            for ($i = 0; $i < $count; $i++) {
                $err = $_FILES['image_files']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = 'خطا در آپلود یکی از تصاویر گالری (کد ' . (int)$err . ').';
                    continue;
                }
                $tmp  = $_FILES['image_files']['tmp_name'][$i];
                $size = $_FILES['image_files']['size'][$i];
                $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : $_FILES['image_files']['type'][$i];
                if ($size > 5 * 1024 * 1024) {
                    $errors[] = 'حجم یکی از تصاویر گالری بیش از ۵ مگابایت است.';
                    continue;
                }
                if (!isset($allowed[$mime])) {
                    $errors[] = 'فرمت یکی از تصاویر گالری مجاز نیست.';
                    continue;
                }
                $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . $i . '.' . $allowed[$mime];
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($tmp, $dest)) {
                    add_product_image($productId, 'uploads/' . $filename);
                    $uploaded++;
                }
            }
        }

        // ۴) ذخیرهٔ وارینت‌ها (بر اساس نام تکراری جایگزین می‌شود)
        //    فرمت ورودی: variants[name][], variants[sku][], variants[delta][], variants[stock][], variants[id][]
        $vNames = $_POST['variants']['name'] ?? [];
        if (is_array($vNames)) {
            // نگهداری وارینت‌های موجود برای تشخیص حذف‌شده‌ها
            $existing = [];
            foreach (product_variants_all($productId) as $ev) {
                $existing[(int)$ev['id']] = $ev;
            }
            $keptIds = [];
            $order = 0;
            foreach ($vNames as $i => $vname) {
                $vname = trim((string)$vname);
                if ($vname === '') {
                    continue;
                }
                $vsku   = trim($_POST['variants']['sku'][$i] ?? '');
                $vdelta = (int)($_POST['variants']['delta'][$i] ?? 0);
                $vstock = (int)($_POST['variants']['stock'][$i] ?? 0);
                $vId    = (int)($_POST['variants']['id'][$i] ?? 0);
                if ($vId > 0 && isset($existing[$vId])) {
                    db()->prepare("UPDATE product_variants SET name=?, sku=?, price_delta=?, stock=?, sort_order=? WHERE id=? AND product_id=?")
                        ->execute([$vname, $vsku ?: null, $vdelta, $vstock, $order, $vId, $productId]);
                    $keptIds[] = $vId;
                } else {
                    db()->prepare("INSERT INTO product_variants (product_id, name, sku, price_delta, stock, sort_order) VALUES (?,?,?,?,?,?)")
                        ->execute([$productId, $vname, $vsku ?: null, $vdelta, $vstock, $order]);
                }
                $order++;
            }
            // حذف وارینت‌هایی که در فرم نیستند
            foreach (array_keys($existing) as $eid) {
                if (!in_array($eid, $keptIds, true)) {
                    db()->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$eid]);
                }
            }
        }

        flash('success', 'محصول ذخیره شد.');

        // اگر محصول تازه موجود شده، اطلاع‌رسانی‌های در انتظار را ارسال کن
        if ($id && product_in_stock($productId)) {
            $sent = stock_notify_flush($productId);
            if ($sent > 0) {
                flash('success', 'محصول ذخیره شد و ' . fa_digits((string)$sent) . ' اطلاع‌رسانی موجودشدن ارسال شد.');
            }
        }

        header('Location: product_edit.php?id=' . $productId);
        exit;
    }
}

$categories = get_categories();
$currentImage = $product['image'] ?? '';
$currentDesc = $product['description'] ?? ($_POST['description'] ?? '');
$galleryImages = $id ? product_images($id) : [];
$variantRows = $id ? product_variants_all($id) : [];
?>

<h1 class="page-title"><?= $id ? 'ویرایش محصول' : 'افزودن محصول جدید' ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="product_edit.php<?= $id ? '?id=' . $id : '' ?>" class="admin-form" enctype="multipart/form-data">
    <label>نام محصول *
        <input type="text" name="name" value="<?= e($product['name'] ?? $_POST['name'] ?? '') ?>" required>
    </label>

    <label>توضیحات
        <div id="editor"></div>
        <textarea name="description" id="description" data-wysiwyg hidden><?= e($currentDesc) ?></textarea>
<?php include_once __DIR__ . '/partials/wysiwyg.php'; ?>
    </label>

    <div class="form-row">
        <label>قیمت (تومان) *
            <input type="number" name="price" min="0" step="1" value="<?= (int)($product['price'] ?? $_POST['price'] ?? 0) ?>" required>
        </label>
        <label>قیمت با تخفیف (تومان) — اختیاری
            <input type="number" name="sale_price" min="0" step="1" value="<?= (int)($product['sale_price'] ?? $_POST['sale_price'] ?? 0) ?>" placeholder="خالی = بدون تخفیف">
            <small class="field-hint">اگر کمتر از قیمت اصلی باشد، به‌عنوان قیمت ویژه نمایش داده می‌شود (بج «٪ تخفیف» روی محصول).</small>
        </label>
        <label>موجودی
            <input type="number" name="stock" min="0" value="<?= (int)($product['stock'] ?? $_POST['stock'] ?? 0) ?>">
        </label>
    </div>

    <div class="form-row">
        <label>دسته‌بندی
            <select name="category_id">
                <option value="0">— بدون دسته —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($product['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>تصویر اصلی (کاور)
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
            <?php if ($currentImage): ?>
                <div class="thumb-preview">
                    <img src="<?= e(product_image_url($currentImage)) ?>" alt="تصویر فعلی">
                    <span>تصویر فعلی</span>
                </div>
            <?php endif; ?>
        </label>
    </div>

    <label>تصاویر گالری (می‌توانید چند عکس انتخاب کنید)
        <input type="file" name="image_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
    </label>

    <?php if ($galleryImages): ?>
        <div class="admin-gallery">
            <?php foreach ($galleryImages as $g): ?>
                <div class="admin-gallery-item">
                    <img src="<?= e(product_image_url($g['image'])) ?>" alt="تصویر گالری">
                    <a href="product_edit.php?id=<?= (int)$id ?>&action=delete_image&img=<?= (int)$g['id'] ?>"
                       class="admin-gallery-del" onclick="return confirm('این تصویر حذف شود؟')" title="حذف">✕</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <label>یا آدرس تصویر (URL خارجی، اختیاری)
        <input type="text" name="image" value="<?= e($currentImage && strpos($currentImage, 'uploads/') !== 0 ? $currentImage : '') ?>" placeholder="https://…">
    </label>

    <fieldset class="admin-fieldset">
        <legend>انواع محصول (وارینت) — اختیاری</legend>
        <p class="muted">اگر محصول سایز/رنگ/مدل مختلف دارد، اینجا اضافه کنید. «اختلاف قیمت» به قیمت پایه اضافه می‌شود (می‌تواند منفی هم باشد). اگر وارینت تعریف شود، موجودی هر نوع جداگانه شمارش می‌شود.</p>
        <div class="admin-variants" id="variantsWrap">
            <div class="admin-variant-head">
                <span>نام نوع</span>
                <span>کد (SKU)</span>
                <span>اختلاف قیمت (تومان)</span>
                <span>موجودی (تعداد)</span>
                <span></span>
            </div>
            <?php foreach ($variantRows as $vr): ?>
                <div class="admin-variant-row">
                    <input type="hidden" name="variants[id][]" value="<?= (int)$vr['id'] ?>">
                    <label class="v-field"><span class="v-lbl">نام نوع</span><input type="text" name="variants[name][]" value="<?= e($vr['name']) ?>" placeholder="مثلاً سایز ۱۰"></label>
                    <label class="v-field"><span class="v-lbl">کد (SKU)</span><input type="text" name="variants[sku][]" value="<?= e($vr['sku'] ?? '') ?>" placeholder="اختیاری" dir="ltr"></label>
                    <label class="v-field"><span class="v-lbl">اختلاف قیمت</span><input type="number" name="variants[delta][]" value="<?= (int)$vr['price_delta'] ?>" placeholder="تومان" title="به قیمت پایه اضافه می‌شود؛ می‌تواند منفی باشد"></label>
                    <label class="v-field"><span class="v-lbl">موجودی (تعداد)</span><input type="number" name="variants[stock][]" value="<?= (int)$vr['stock'] ?>" min="0" placeholder="تعداد"></label>
                    <a href="product_edit.php?id=<?= (int)$id ?>&action=delete_variant&vid=<?= (int)$vr['id'] ?>" class="btn btn-danger btn-sm v-del" onclick="return confirm('این نوع حذف شود؟')">حذف</a>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="addVariantRow">+ افزودن نوع جدید</button>
    </fieldset>

    <label class="checkbox">
        <input type="checkbox" name="featured" <?= !empty($product['featured']) ? 'checked' : '' ?>> نمایش به‌عنوان محصول ویژه
    </label>
    <label class="checkbox">
        <input type="checkbox" name="active" <?= ($product['active'] ?? 1) ? 'checked' : '' ?>> فعال
    </label>
    <div class="form-actions">
        <button type="submit" class="btn btn-accent">ذخیره</button>
        <a href="products.php" class="btn btn-ghost">انصراف</a>
    </div>
</form>

<!-- ادیتور متن Quill (متن‌باز، رایگان، بدون ثبت‌نام و API) -->
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/vendor/quill/quill.snow.css">
<script src="<?= e(BASE_URL) ?>/assets/vendor/quill/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            ['blockquote', 'link', 'image'],
            [{ 'align': [] }],
            ['clean']
        ]},
        placeholder: 'توضیحات محصول را اینجا بنویسید…'
    });
    var hidden = document.getElementById('description');
    quill.clipboard.dangerouslyPasteHTML(hidden.value);
    var form = quill.root.closest('form');
    form.addEventListener('submit', function () {
        hidden.value = quill.root.innerHTML;
    });
})();

// وارینت: افزودن ردیف جدید
(function () {
    var btn = document.getElementById('addVariantRow');
    var wrap = document.getElementById('variantsWrap');
    if (!btn || !wrap) { return; }
    btn.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'admin-variant-row';
        row.innerHTML =
            '<input type="hidden" name="variants[id][]" value="0">' +
            '<label class="v-field"><span class="v-lbl">نام نوع</span><input type="text" name="variants[name][]" placeholder="مثلاً سایز ۱۰"></label>' +
            '<label class="v-field"><span class="v-lbl">کد (SKU)</span><input type="text" name="variants[sku][]" placeholder="اختیاری" dir="ltr"></label>' +
            '<label class="v-field"><span class="v-lbl">اختلاف قیمت</span><input type="number" name="variants[delta][]" value="0" placeholder="تومان"></label>' +
            '<label class="v-field"><span class="v-lbl">موجودی (تعداد)</span><input type="number" name="variants[stock][]" value="0" min="0" placeholder="تعداد"></label>' +
            '<button type="button" class="btn btn-danger btn-sm v-del" onclick="this.closest(\'.admin-variant-row\').remove()">حذف</button>';
        wrap.appendChild(row);
        row.querySelector('input[type="text"]').focus();
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
