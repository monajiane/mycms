<?php
/**
 * custom_request.php — درخواست ابزار سفارشی (باشگاه مشتریان)
 * مشتریِ واردشده نام ابزار، توضیحات و تعداد را ثبت و فایل فنی
 * (PDF / DXF / JPG / PNG) را آپلود می‌کند؛ درخواست برای بررسی به مدیر می‌رود.
 */
require_once __DIR__ . '/config.php';

if (!is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php?next=' . urlencode('/custom_request.php'));
    exit;
}

$user = current_customer();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity    = max(1, (int)($_POST['quantity'] ?? 1));
    $file        = null;
    $csrfOk      = csrf_verify();
    if (!$csrfOk) {
        $errors[] = 'نشست نامعتبر است؛ لطفاً صفحه را بازنشانی و دوباره تلاش کنید.';
    }

    if ($title === '') {
        $errors[] = 'نام ابزار سفارشی الزامی است.';
    } elseif (mb_strlen($title, 'UTF-8') > 200) {
        $errors[] = 'نام ابزار نباید بیش از ۲۰۰ کاراکتر باشد.';
    }
    if ($description === '') {
        $errors[] = 'توضیحات الزامی است (ابعاد، جنس، کاربرد و…).';
    }
    if ($quantity < 1 || $quantity > 100000) {
        $errors[] = 'تعداد باید عددی بین ۱ تا ۱۰۰٬۰۰۰ باشد.';
    }

    // آپلود فایل فنی (اختیاری اما توصیه‌شده)
    if ($csrfOk && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['file'];
        $ext = strtolower((string)pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf' => 'pdf', 'dxf' => 'dxf', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png'];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'خطا در آپلود فایل (کد ' . (int)$f['error'] . ').';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $errors[] = 'حجم فایل نباید بیش از ۱۰ مگابایت باشد.';
        } elseif (!isset($allowedExt[$ext])) {
            $errors[] = 'فقط فایل‌های PDF، DXF، JPG و PNG مجاز هستند.';
        } else {
            $ok = true;
            if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $info = @getimagesize($f['tmp_name']);
                if ($info === false) {
                    $ok = false;
                }
            } elseif ($ext === 'pdf') {
                $head = @file_get_contents($f['tmp_name'], false, null, 0, 5);
                if ($head !== '%PDF-') {
                    $ok = false;
                }
            } elseif ($ext === 'dxf') {
                $head = @file_get_contents($f['tmp_name'], false, null, 0, 1024);
                $ok = (strpos($head, 'SECTION') !== false || strpos($head, 'ENTITIES') !== false || strpos($head, 'HEADER') !== false);
            }
            if (!$ok) {
                $errors[] = 'محتوای فایل با پسوند آن مطابقت ندارد.';
            } else {
                $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedExt[$ext];
                $dest = __DIR__ . '/uploads/' . $filename;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $file = 'uploads/' . $filename;
                } else {
                    $errors[] = 'ذخیرهٔ فایل ناموفق بود (دسترسی نوشتن پوشهٔ uploads را بررسی کنید).';
                }
            }
        }
    }

    if ($csrfOk && !$errors) {
        db()->prepare("INSERT INTO custom_requests (user_id, title, description, quantity, file) VALUES (?, ?, ?, ?, ?)")
            ->execute([(int)$user['id'], $title, $description, $quantity, $file]);
        flash('success', 'درخواست ابزار سفارشی شما ثبت شد و برای بررسی به تیم ما ارسال گردید. پس از تأیید، محصول مخصوص شما در فروشگاه نمایش داده می‌شود.');
        header('Location: ' . BASE_URL . '/custom_request.php');
        exit;
    }
}

$requests = customer_requests((int)$user['id']);
$pageTitle = 'درخواست ابزار سفارشی | باشگاه مشتریان';
require __DIR__ . '/includes/header.php';
?>

<section class="container auth-wrap">
    <div class="auth-card auth-card-wide">
        <p class="auth-eyebrow">باشگاه مشتریان</p>
        <h1 class="page-title">درخواست ابزار سفارشی</h1>
        <p class="auth-sub">ابزار موردنظرتان را با مشخصات دقیق شرح دهید و نقشهٔ فنی (PDF، DXF یا عکس) را پیوست کنید. پس از بررسی و تأیید، قیمت نهایی تعیین و محصول فقط برای حساب شما در فروشگاه فعال می‌شود.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="custom_request.php" class="auth-form" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <label>نام ابزار سفارشی *
                <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="مثلاً: متهٔ مخصوص قطر ۱۲.۵ با طول بلند" required>
            </label>
            <label>توضیحات *
                <textarea name="description" rows="5" required placeholder="ابعاد، جنس، تلورانس، کاربرد و هر مشخصهٔ فنی دیگر…"><?= e($_POST['description'] ?? '') ?></textarea>
            </label>
            <div class="auth-row">
                <label>تعداد
                    <input type="number" name="quantity" min="1" max="100000" value="<?= (int)($_POST['quantity'] ?? 1) ?>">
                </label>
                <label>فایل فنی (PDF / DXF / JPG / PNG)
                    <input type="file" name="file" accept=".pdf,.dxf,.jpg,.jpeg,.png">
                </label>
            </div>
            <button type="submit" class="btn btn-accent btn-block">ثبت درخواست</button>
        </form>
    </div>
</section>

<section class="container" style="margin-top: 0;">
    <h2 class="section-title">درخواست‌های شما</h2>
    <?php if (!$requests): ?>
        <div class="empty-state">
            <p>هنوز درخواستی ثبت نکرده‌اید.</p>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($requests as $r): ?>
                <div class="order-row req-row">
                    <span class="order-id">درخواست #<?= (int)$r['id'] ?></span>
                    <span class="order-name"><?= e($r['title']) ?></span>
                    <span class="order-date"><?= e(persian_date($r['created_at'] ?? null)) ?></span>
                    <span class="req-qty">تعداد: <?= (int)$r['quantity'] ?></span>
                    <span class="status req-<?= e($r['status']) ?>"><?= e(request_status_label($r['status'])) ?></span>
                </div>
                <?php if ($r['status'] === 'approved'): ?>
                    <div class="req-result">
                        <?php if ($r['product_id']): ?>
                            <a href="product.php?id=<?= (int)$r['product_id'] ?>" class="btn btn-accent btn-sm">مشاهدهٔ محصول شما</a>
                        <?php endif; ?>
                        <?php if ($r['price']): ?>
                            <span>قیمت نهایی: <strong><?= e(fmt_price($r['price'])) ?></strong></span>
                        <?php endif; ?>
                    </div>
                <?php elseif ($r['status'] === 'rejected' && $r['admin_note']): ?>
                    <div class="req-result req-rejected">پاسخ مدیر: <?= e($r['admin_note']) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
