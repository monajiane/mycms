<?php
/**
 * admin/article_edit.php — فرم افزودن / ویرایش مقالهٔ دانشنامه
 * شامل: ادیتور متن Quill (متن‌باز و رایگان) + آپلود تصویر شاخص
 */
$pageTitle = 'ویرایش مقاله';
require __DIR__ . '/_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = $id ? get_article_admin($id) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title'] ?? '');
    $excerpt   = trim($_POST['excerpt'] ?? '');
    $content   = sanitize_html($_POST['content'] ?? '');
$seoTitle       = trim((string)($_POST['seo_title'] ?? ''));
$seoDescription = trim((string)($_POST['seo_description'] ?? ''));
$seoKeywords    = trim((string)($_POST['seo_keywords'] ?? ''));
$noindex        = isset($_POST['noindex']) ? 1 : 0;
    $published = isset($_POST['published']) ? 1 : 0;
    $image     = trim($_POST['image'] ?? '');
    $video     = trim($_POST['video'] ?? '');

    if ($title === '') {
        $errors[] = 'عنوان مقاله الزامی است.';
    }

    // آپلود تصویر شاخص
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['image_file'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'خطا در آپلود تصویر (کد ' . (int)$f['error'] . ').';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $errors[] = 'حجم تصویر نباید بیش از ۵ مگابایت باشد.';
        } elseif (!isset($allowed[$mime])) {
            $errors[] = 'فقط فرمت‌های JPG، PNG، WebP و GIF مجاز هستند.';
        } else {
            $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            $dest = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $old = $article['image'] ?? '';
                if ($old && strpos($old, 'uploads/') === 0 && is_file(__DIR__ . '/../' . $old)) {
                    @unlink(__DIR__ . '/../' . $old);
                }
                $image = 'uploads/' . $filename;
            } else {
                $errors[] = 'ذخیرهٔ تصویر ناموفق بود.';
            }
        }
    }

    // آپلود فایل ویدیو
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['video_file'];
        $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : $f['type'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'خطا در آپلود ویدیو (کد ' . (int)$f['error'] . ').';
        } elseif ($f['size'] > 50 * 1024 * 1024) {
            $errors[] = 'حجم ویدیو نباید بیش از ۵۰ مگابایت باشد.';
        } elseif (!isset($allowed[$mime])) {
            $errors[] = 'فقط فرمت‌های MP4 و WebM مجاز هستند.';
        } else {
            $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            $dest = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $old = $article['video'] ?? '';
                if ($old && strpos($old, 'uploads/') === 0 && is_file(__DIR__ . '/../' . $old)) {
                    @unlink(__DIR__ . '/../' . $old);
                }
                $video = 'uploads/' . $filename;
            } else {
                $errors[] = 'ذخیرهٔ ویدیو ناموفق بود.';
            }
        }
    }

    if (!$errors) {
        $slug = make_slug($title);
        if ($id) {
            db()->prepare("UPDATE articles SET title=?, slug=?, excerpt=?, content=?, image=?, video=?, published=?, seo_title=?, seo_description=?, seo_keywords=?, noindex=? WHERE id=?")
                ->execute([$title, $slug, $excerpt, $content, $image, $video, $published, $seoTitle, $seoDescription, $seoKeywords, $noindex, $id]);
            flash('success', 'مقاله به‌روزرسانی شد.');
        } else {
            db()->prepare("INSERT INTO articles (title, slug, excerpt, content, image, video, published, seo_title, seo_description, seo_keywords, noindex) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$title, $slug, $excerpt, $content, $image, $video, $published, $seoTitle, $seoDescription, $seoKeywords, $noindex]);
            flash('success', 'مقاله افزوده شد.');
        }
        header('Location: articles.php');
        exit;
    }
}

$currentImage = $article['image'] ?? '';
$currentVideo = $article['video'] ?? '';
$currentContent = $article['content'] ?? ($_POST['content'] ?? '');
?>

<h1 class="page-title"><?= $id ? 'ویرایش مقاله' : 'افزودن مقاله جدید' ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="article_edit.php<?= $id ? '?id=' . $id : '' ?>" class="admin-form" enctype="multipart/form-data">
    <label>عنوان مقاله *
        <input type="text" name="title" value="<?= e($article['title'] ?? $_POST['title'] ?? '') ?>" required>
    </label>

    <label>خلاصه (اختیاری — در فهرست دانشنامه نمایش داده می‌شود)
        <textarea name="excerpt" rows="2"><?= e($article['excerpt'] ?? $_POST['excerpt'] ?? '') ?></textarea>
    </label>

    <label>متن مقاله
        <div id="editor"></div>
        <textarea name="content" id="content" data-wysiwyg hidden><?= e($currentContent) ?></textarea>
        <?php include_once __DIR__ . '/partials/wysiwyg.php'; ?>
<div style="margin-top:24px;border-top:1px solid #e2e8f0;padding-top:16px">
    <h3 style="margin:0 0 12px;font-size:16px">سئو (SEO)</h3>
    <label>عنوان سئو (Meta Title) — خالی بگذارید = عنوان مقاله</label>
    <input type="text" name="seo_title" maxlength="255" value="<?= e($article['seo_title'] ?? ($_POST['seo_title'] ?? '')) ?>" placeholder="عنوانی که در گوگل نمایش داده می‌شود">
    <label>توضیحات سئو (Meta Description) — خالی بگذارید = خلاصهٔ مقاله</label>
    <textarea name="seo_description" rows="2" maxlength="1000" placeholder="توضیحی که زیر لینک در گوگل نمایش داده می‌شود"><?= e($article['seo_description'] ?? ($_POST['seo_description'] ?? '')) ?></textarea>
    <label>کلمات کلیدی (با ویرگول جدا کنید)</label>
    <input type="text" name="seo_keywords" maxlength="500" value="<?= e($article['seo_keywords'] ?? ($_POST['seo_keywords'] ?? '')) ?>" placeholder="مثال: خنک‌کننده تراشکاری, آب صابون, سنتتیک">
    <label style="display:flex;gap:8px;align-items:center;margin-top:10px">
        <input type="checkbox" name="noindex" value="1" <?= !empty($article['noindex']) ? 'checked' : '' ?>>
        نمایش ندادن این مقاله به موتورهای جستجو (noindex)
    </label>
</div>
    </label>

    <div class="form-row">
        <label>تصویر شاخص (اختیاری)
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
            <?php if ($currentImage): ?>
                <div class="thumb-preview">
                    <img src="<?= e(product_image_url($currentImage)) ?>" alt="تصویر فعلی">
                    <span>تصویر فعلی</span>
                </div>
            <?php endif; ?>
        </label>
        <label>یا آدرس تصویر (URL خارجی، اختیاری)
            <input type="text" name="image" value="<?= e($currentImage && strpos($currentImage, 'uploads/') !== 0 ? $currentImage : '') ?>" placeholder="https://…">
        </label>
    </div>

    <div class="form-row">
        <label>فایل ویدیو (اختیاری — MP4 یا WebM، حداکثر ۵۰ مگابایت)
            <input type="file" name="video_file" accept="video/mp4,video/webm">
            <?php if ($currentVideo && strpos($currentVideo, 'uploads/') === 0): ?>
                <div class="video-preview">
                    <video src="<?= e(product_image_url($currentVideo)) ?>" controls preload="metadata"></video>
                    <span>ویدیوی فعلی</span>
                </div>
            <?php endif; ?>
        </label>
        <label>یا لینک ویدیو (آپارات / یوتیوب / URL مستقیم، اختیاری)
            <input type="text" name="video" value="<?= e($currentVideo && strpos($currentVideo, 'uploads/') !== 0 ? $currentVideo : '') ?>" placeholder="https://www.aparat.com/v/…">
            <small>می‌توانید لینک آپارات یا یوتیوب را اینجا بچسبانید یا داخل متن از ابزار ویدیوی ادیتور استفاده کنید.</small>
        </label>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="published" <?= ($article['published'] ?? 1) ? 'checked' : '' ?>> انتشار (نمایش در دانشنامه)
    </label>
    <div class="form-actions">
        <button type="submit" class="btn btn-accent">ذخیره</button>
        <a href="articles.php" class="btn btn-ghost">انصراف</a>
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
        placeholder: 'متن مقاله را اینجا بنویسید…'
    });
    var hidden = document.getElementById('content');
    quill.clipboard.dangerouslyPasteHTML(hidden.value);
    var form = quill.root.closest('form');
    form.addEventListener('submit', function () {
        hidden.value = quill.root.innerHTML;
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
