<?php
// register.php — ثبت‌نام کاربر
require_once __DIR__ . '/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است. لطفاً صفحه را رفرش کنید.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'نام کاربری باید ۳ تا ۳۰ کاراکتر (حرف، عدد، _) باشد.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    }
    if ($password !== $password2) {
        $errors[] = 'تکرار رمز عبور مطابقت ندارد.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{6,20}$/', $phone)) {
        $errors[] = 'شماره تماس نامعتبر است.';
    }

    if (!$errors) {
        $exists = db()->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $exists->execute([$username]);
        if ((int)$exists->fetchColumn() > 0) {
            $errors[] = 'این نام کاربری قبلاً ثبت شده است.';
        }
    }

    // شماره موبایل یکتا باشد (اگر وارد شده)
    if (!$errors && $phone !== '') {
        $phoneExists = db()->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
        $phoneExists->execute([$phone]);
        if ((int)$phoneExists->fetchColumn() > 0) {
            $errors[] = 'این شمارهٔ موبایل قبلاً ثبت شده است. لطفاً <a href="login.php?next=/checkout.php">وارد شوید</a>.';
        }
    }

    if (!$errors) {
        $st = db()->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'customer')");
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $phone]);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
        $_SESSION['user_name'] = $fullName;
        $success = true;
        // اطلاع‌رسانی تلگرام (کاربر جدید)
        if (function_exists('tg_notify_admins')) {
            try {
                $_tgUid = (int)$_SESSION['user_id'];
                $_tgUser = $fullName ?: $username;
                $_tgBase = defined('BASE_URL') ? BASE_URL : 'https://abzar-shargh.ir';
                $_tgText = "👤 <b>کاربر جدید ثبت‌نام کرد</b>\n" .
                           "شناسه: <code>#$_tgUid</code>\n" .
                           "نام: " . htmlspecialchars($_tgUser, ENT_QUOTES, 'UTF-8') . "\n" .
                           "موبایل: " . htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8') . "\n" .
                           "ایمیل: " . htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') . "\n" .
                           "نام کاربری: " . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') . "\n" .
                           "زمان: " . date('Y-m-d H:i:s') . "\n" .
                           "🔗 $_tgBase/admin/users.php";
                $_kb = json_encode(['inline_keyboard' => [
                    [ ['text' => '👥 مدیریت کاربران', 'url' => $_tgBase . '/admin/users.php'] ],
                ]], JSON_UNESCAPED_UNICODE);
                if (function_exists('tg_enabled') && tg_enabled()) {
                    foreach (tg_admin_chat_ids() as $_cid) {
                        if (function_exists('tg_send')) @tg_send($_cid, $_tgText, ['reply_markup' => $_kb]);
                    }
                } else {
                    @tg_notify_admins($_tgText, 'user_registered');
                }
            } catch (Throwable $_e) {}
        }
    }
}

$pageTitle = 'ثبت‌نام';
require __DIR__ . '/includes/header.php';
?>
<section class="container auth-page">
    <h1 class="page-title">ثبت‌نام</h1>
    <?php if ($success): ?>
        <div class="alert alert-success">ثبت‌نام با موفقیت انجام شد. <a href="checkout.php">ادامهٔ خرید</a></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
        <?= csrf_field() ?>
        <label>نام کاربری *
            <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
        </label>
        <label>رمز عبور *
            <input type="password" name="password" required>
        </label>
        <label>تکرار رمز عبور *
            <input type="password" name="password2" required>
        </label>
        <label>نام و نام خانوادگی
            <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>">
        </label>
        <label>ایمیل
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label>شمارهٔ موبایل
            <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
        </label>
        <button type="submit" class="btn btn-accent btn-block">ثبت‌نام</button>
        <p style="text-align:center;margin-top:8px">قبلاً عضو شدید؟ <a href="login.php?next=/checkout.php">وارد شوید</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
