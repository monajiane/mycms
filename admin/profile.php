<?php
/**
 * admin/profile.php — پروفایل مدیر جاری + تغییر رمز عبور
 */
$pageTitle = 'پروفایل من';
require __DIR__ . '/_header.php';

$errors = [];
$success_msg = '';

$admin_id = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // بررسی رمز فعلی
    $st = db()->prepare("SELECT password, username FROM users WHERE id = ?");
    $st->execute([$admin_id]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $errors[] = 'کاربر یافت نشد.';
    } elseif (!password_verify($current, $me['password'])) {
        $errors[] = 'رمز فعلی اشتباه است.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'رمز جدید باید حداقل ۶ کاراکتر باشد.';
    } elseif ($new !== $confirm) {
        $errors[] = 'تکرار رمز جدید مطابقت ندارد.';
    } else {
        db()->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $admin_id]);
        $success_msg = 'رمز عبور شما با موفقیت تغییر کرد.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    // نرمال‌سازی شماره: ارقام فارسی/عربی، فاصله‌ها و +98/98 → 09xxxxxxxxx
    if ($phone !== '') {
        $digits = preg_replace('/[^0-9]/', '', this_to_en_digits($phone));
        if (strlen($digits) === 12 && strpos($digits, '98') === 0) {
            $digits = '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
            $digits = '0' . $digits;
        }
        $phone = $digits;
    }

    if ($phone !== '' && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $errors[] = 'شماره تماس باید با ۰۹ شروع شود و ۱۱ رقم باشد.';
    }

    if (!$errors) {
        db()->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?")
            ->execute([$full_name, $email, $phone, $admin_id]);
        $success_msg = 'اطلاعات پروفایل به‌روز شد.';
    }
}

$st = db()->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$admin_id]);
$me = $st->fetch(PDO::FETCH_ASSOC);

if (!$me) {
    echo '<div class="alert alert-error">کاربر یافت نشد.</div>';
    require __DIR__ . '/_footer.php';
    exit;
}
?>

<div class="page-head">
    <h1 class="page-title">پروفایل من</h1>
    <a href="users.php" class="btn btn-ghost">→ بازگشت به مدیریت کاربران</a>
</div>

<?php if ($success_msg): ?><div class="alert alert-success"><?= e($success_msg) ?></div><?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="profile-grid">
    <div class="profile-card">
        <h3>اطلاعات حساب</h3>
        <table class="info-table">
            <tr><th>نام کاربری</th><td><?= e($me['username']) ?></td></tr>
            <tr><th>نقش</th><td><span class="status status-paid"><?= $me['role'] === 'admin' ? 'مدیر' : 'مشتری' ?></span></td></tr>
            <tr><th>امتیاز</th><td><?= (int)($me['points'] ?? 0) ?></td></tr>
            <tr><th>تاریخ عضویت</th><td dir="ltr"><?= e($me['created_at'] ?? '—') ?></td></tr>
        </table>
    </div>

    <div class="profile-card">
        <h3>ویرایش اطلاعات</h3>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="update_profile">
            <label>نام کامل:</label>
            <input type="text" name="full_name" value="<?= e($me['full_name'] ?? '') ?>" placeholder="نام و نام خانوادگی">

            <label>ایمیل:</label>
            <input type="email" name="email" value="<?= e($me['email'] ?? '') ?>" placeholder="example@site.com">

            <label>شماره تماس:</label>
            <input type="tel" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="09124045217" maxlength="11" dir="ltr">
            <span class="hint">برای اطلاع‌رسانی‌ها استفاده می‌شود؛ با ارقام انگلیسی وارد کنید (۰۹...).</span>

            <button type="submit" class="btn btn-accent">ذخیره</button>
        </form>
    </div>

    <div class="profile-card profile-card-wide">
        <h3>تغییر رمز عبور</h3>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="change_password">
            <label>رمز فعلی:</label>
            <input type="password" name="current_password" required>

            <label>رمز جدید (حداقل ۶ کاراکتر):</label>
            <input type="password" name="new_password" minlength="6" required>

            <label>تکرار رمز جدید:</label>
            <input type="password" name="confirm_password" minlength="6" required>

            <button type="submit" class="btn btn-accent">تغییر رمز</button>
        </form>
    </div>
</div>

<style>
.page-head { display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; }
.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.profile-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; }
.profile-card h3 { margin: 0 0 14px; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
.profile-card-wide { grid-column: span 2; }
.info-table { width: 100%; border-collapse: collapse; }
.info-table th, .info-table td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
.info-table th { text-align: right; color: #6b7280; font-weight: 500; width: 120px; }
.profile-card label { display: block; margin: 10px 0 4px; font-weight: 600; color: #374151; font-size: 14px; }
.profile-card input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
.profile-card input:focus { outline: 2px solid #3b82f6; border-color: #3b82f6; }
.profile-card .btn { margin-top: 16px; }
.status { padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.status-paid { background: #d1fae5; color: #065f46; }
.alert { padding: 10px 14px; border-radius: 8px; margin: 10px 0; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
@media (max-width: 720px) {
    .profile-grid { grid-template-columns: 1fr; }
    .profile-card-wide { grid-column: span 1; }
}
</style>

<?php require __DIR__ . '/_footer.php'; ?>
