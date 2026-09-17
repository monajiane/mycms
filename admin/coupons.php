<?php
/**
 * admin/coupons.php — مدیریت کوپن‌های تخفیف
 * قابلیت‌ها: فهرست + ساخت + ویرایش + حذف + فعال/غیرفعال‌سازی
 * قوانین پیشرفته: حداقل خرید، تاریخ شروع/انقضا، سقف استفادهٔ کل و به‌ازای هر کاربر
 */
$pageTitle = 'کوپن‌های تخفیف';
require __DIR__ . '/_header.php';

/**
 * تبدیل تاریخ ذخیره‌شده (Y-m-d H:i:s یا ورودی datetime-local) به قالب
 * مقداردهی input[type=datetime-local].
 */
function coupon_dt_input($dt)
{
    $dt = trim((string)$dt);
    if ($dt === '') {
        return '';
    }
    $ts = strtotime(str_replace('T', ' ', $dt));
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/** برچسب فارسی نوع کوپن */
function coupon_type_label($type)
{
    return $type === 'fixed' ? 'مبلغ ثابت' : 'درصدی';
}

$errors = [];

// ---- حذف کوپن ----
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    db()->prepare("DELETE FROM coupons WHERE id = ?")->execute([(int)$_GET['id']]);
    flash('success', 'کوپن حذف شد.');
    header('Location: coupons.php');
    exit;
}

// ---- تغییر وضعیت فعال/غیرفعال ----
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $st = db()->prepare("SELECT active FROM coupons WHERE id = ?");
    $st->execute([(int)$_GET['id']]);
    $row = $st->fetch();
    if ($row) {
        $new = (int)$row['active'] ? 0 : 1;
        db()->prepare("UPDATE coupons SET active = ? WHERE id = ?")->execute([$new, (int)$_GET['id']]);
        flash('success', $new ? 'کوپن فعال شد.' : 'کوپن غیرفعال شد.');
    }
    header('Location: coupons.php');
    exit;
}

$editCoupon = null;

// ---- ذخیره (ساخت یا ویرایش) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = (int)($_POST['id'] ?? 0);
    $code             = strtoupper(trim($_POST['code'] ?? ''));
    $type             = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $value            = (int)($_POST['value'] ?? 0);
    $max_uses         = trim($_POST['max_uses'] ?? '');
    $min_order_amount = max(0, (int)($_POST['min_order_amount'] ?? 0));
    $starts_at        = trim($_POST['starts_at'] ?? '');
    $expires_at       = trim($_POST['expires_at'] ?? '');
    $per_user_limit   = trim($_POST['per_user_limit'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $active           = isset($_POST['active']) ? 1 : 0;

    // ---- اعتبارسنجی ----
    if ($code === '') {
        $errors[] = 'کد کوپن الزامی است.';
    } elseif (!preg_match('/^[A-Z0-9\-_]{2,60}$/', $code)) {
        $errors[] = 'کد کوپن فقط می‌تواند شامل حروف لاتین، عدد، خط تیره و زیرخط (۲ تا ۶۰ نویسه) باشد.';
    }
    if ($value <= 0) {
        $errors[] = 'مقدار تخفیف باید بزرگ‌تر از صفر باشد.';
    }
    if ($type === 'percent' && ($value < 1 || $value > 100)) {
        $errors[] = 'برای کوپن درصدی، مقدار باید بین ۱ تا ۱۰۰ باشد.';
    }
    if ($starts_at !== '' && $expires_at !== '') {
        $ts1 = strtotime(str_replace('T', ' ', $starts_at));
        $ts2 = strtotime(str_replace('T', ' ', $expires_at));
        if ($ts1 !== false && $ts2 !== false && $ts1 > $ts2) {
            $errors[] = 'تاریخ شروع نمی‌تواند بعد از تاریخ انقضا باشد.';
        }
    }
    if (!$errors) {
        // یکتایی کد کوپن
        $sql = "SELECT id FROM coupons WHERE code = ?" . ($id ? " AND id != ?" : "");
        $params = [$code];
        if ($id) {
            $params[] = $id;
        }
        $st = db()->prepare($sql);
        $st->execute($params);
        if ($st->fetch()) {
            $errors[] = 'این کد کوپن قبلاً ثبت شده است. کد دیگری انتخاب کنید.';
        }
    }

    if (!$errors) {
        $starts_at  = $starts_at  !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $starts_at)))  : null;
        $expires_at = $expires_at !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $expires_at))) : null;
        $max_uses   = $max_uses !== '' ? max(1, (int)$max_uses) : null;
        $per_user_limit = $per_user_limit !== '' ? max(1, (int)$per_user_limit) : null;

        if ($id) {
            db()->prepare("UPDATE coupons SET code=?, type=?, value=?, max_uses=?, min_order_amount=?, starts_at=?, expires_at=?, per_user_limit=?, description=?, active=? WHERE id=?")
                ->execute([$code, $type, $value, $max_uses, $min_order_amount, $starts_at, $expires_at, $per_user_limit, $description, $active, $id]);
            flash('success', 'کوپن ویرایش شد.');
        } else {
            db()->prepare("INSERT INTO coupons (code, type, value, max_uses, min_order_amount, starts_at, expires_at, per_user_limit, description, active) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$code, $type, $value, $max_uses, $min_order_amount, $starts_at, $expires_at, $per_user_limit, $description, $active]);
            flash('success', 'کوپن جدید ساخته شد.');
        }
        header('Location: coupons.php');
        exit;
    }

    // در صورت خطا، مقادیر واردشده را برای نمایش دوباره نگه می‌داریم
    $editCoupon = [
        'id'               => $id,
        'code'             => $code,
        'type'             => $type,
        'value'            => $value,
        'max_uses'         => $max_uses,
        'min_order_amount' => $min_order_amount,
        'starts_at'        => $starts_at,
        'expires_at'       => $expires_at,
        'per_user_limit'   => $per_user_limit,
        'description'      => $description,
        'active'           => $active,
    ];
} elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    // حالت ویرایش
    $st = db()->prepare("SELECT * FROM coupons WHERE id = ?");
    $st->execute([(int)$_GET['id']]);
    $editCoupon = $st->fetch() ?: null;
}

$coupons = db()->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

// مقداردهی پیش‌فرض فرم (ساخت یا ویرایش)
$f = $editCoupon ?: [
    'id'               => 0,
    'code'             => '',
    'type'             => 'percent',
    'value'            => 0,
    'max_uses'         => '',
    'min_order_amount' => 0,
    'starts_at'        => '',
    'expires_at'       => '',
    'per_user_limit'   => '',
    'description'      => '',
    'active'           => 1,
];
$isEdit = (int)$f['id'] > 0;
?>

<h1 class="page-title">کوپن‌های تخفیف</h1>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($editCoupon !== null || isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <section class="dash-panel">
        <h2><?= $isEdit ? 'ویرایش کوپن' : 'ساخت کوپن جدید' ?></h2>
        <form method="post" action="coupons.php" class="admin-form">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">

            <div class="form-row">
                <label>کد کوپن *
                    <input type="text" name="code" value="<?= e($f['code']) ?>" dir="ltr" placeholder="مثلاً WELCOME10" required>
                </label>
                <label>نوع تخفیف
                    <select name="type">
                        <option value="percent" <?= $f['type'] === 'percent' ? 'selected' : '' ?>>درصدی</option>
                        <option value="fixed" <?= $f['type'] === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت (تومان)</option>
                    </select>
                </label>
            </div>

            <div class="form-row">
                <label>مقدار تخفیف * (<?= $f['type'] === 'percent' ? 'درصد ۱ تا ۱۰۰' : 'به تومان' ?>)
                    <input type="number" name="value" min="1" step="1" value="<?= (int)$f['value'] ?>" required>
                </label>
                <label>حداقل مبلغ خرید (تومان) — برای اعمال کوپن
                    <input type="number" name="min_order_amount" min="0" step="1" value="<?= (int)$f['min_order_amount'] ?>">
                </label>
            </div>

            <div class="form-row">
                <label>سقف استفادهٔ کل (خالی = نامحدود)
                    <input type="number" name="max_uses" min="1" step="1" value="<?= e($f['max_uses']) ?>">
                </label>
                <label>سقف استفاده به‌ازای هر کاربر (خالی = نامحدود)
                    <input type="number" name="per_user_limit" min="1" step="1" value="<?= e($f['per_user_limit']) ?>">
                </label>
            </div>

            <div class="form-row">
                <label>تاریخ شروع (اختیاری)
                    <input type="datetime-local" name="starts_at" value="<?= e(coupon_dt_input($f['starts_at'])) ?>">
                </label>
                <label>تاریخ انقضا (اختیاری)
                    <input type="datetime-local" name="expires_at" value="<?= e(coupon_dt_input($f['expires_at'])) ?>">
                </label>
            </div>

            <label>توضیح (فقط برای مدیر، نمایش داده نمی‌شود)
                <input type="text" name="description" value="<?= e($f['description']) ?>">
            </label>

            <label class="checkbox">
                <input type="checkbox" name="active" <?= $f['active'] ? 'checked' : '' ?>> فعال
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-accent"><?= $isEdit ? 'ذخیرهٔ تغییرات' : 'ساخت کوپن' ?></button>
                <a href="coupons.php" class="btn btn-ghost">انصراف</a>
            </div>
        </form>
    </section>
<?php else: ?>
    <a href="coupons.php?action=new" class="btn btn-accent">+ ساخت کوپن جدید</a>
<?php endif; ?>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th><th>کد</th><th>نوع</th><th>مقدار</th><th>استفاده / سقف</th>
            <th>حداقل خرید</th><th>شروع</th><th>انقضا</th><th>وضعیت</th><th>عملیات</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$coupons): ?>
        <tr><td colspan="10" class="muted">هنوز کوپنی ساخته نشده است.</td></tr>
    <?php endif; ?>
    <?php foreach ($coupons as $c): ?>
        <?php
            $maxUses = ($c['max_uses'] !== null && (int)$c['max_uses'] > 0) ? (int)$c['max_uses'] : null;
            $used    = (int)$c['used_count'];
            $usesText = $used . ' / ' . ($maxUses ?: 'نامحدود');
            $isActive = (int)$c['active'] === 1;
        ?>
        <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><code dir="ltr"><?= e($c['code']) ?></code></td>
            <td><?= e(coupon_type_label($c['type'])) ?></td>
            <td><?= $c['type'] === 'percent' ? (int)$c['value'] . '٪' : e(fmt_price($c['value'])) ?></td>
            <td><?= e($usesText) ?></td>
            <td><?= (int)$c['min_order_amount'] > 0 ? e(fmt_price($c['min_order_amount'])) : '—' ?></td>
            <td><?= e(persian_date($c['starts_at'] ?? null)) ?></td>
            <td><?= e(persian_date($c['expires_at'] ?? null)) ?></td>
            <td>
                <span class="status <?= $isActive ? 'status-paid' : 'status-cancelled' ?>"><?= $isActive ? 'فعال' : 'غیرفعال' ?></span>
            </td>
            <td class="actions">
                <a href="coupons.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">ویرایش</a>
                <a href="coupons.php?action=toggle&id=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm"><?= $isActive ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></a>
                <a href="coupons.php?action=delete&id=<?= (int)$c['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('این کوپن حذف شود؟')">حذف</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/_footer.php'; ?>
