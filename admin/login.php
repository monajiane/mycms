<?php
/**
 * admin/login.php — ورود مدیر
 */
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!captcha_verify($_POST['captcha'] ?? '')) {
            $error = 'پاسخ کپچا نادرست است؛ دوباره تلاش کنید.';
        } else {
            // rate-limit: فقط بر اساس IP (بدون نام کاربری تا مهاجم با userهای متفاوت دور نزند)
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!rate_limit_check('admin|' . $ip, 5, 900)) {
                $error = 'تعداد تلاش‌های ناموفق شما از حد مجاز بیشتر شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.';
            } else {
                $st = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
                $st->execute([$username]);
                $user = $st->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['full_name'] ?: $user['username'];
                    header('Location: ' . BASE_URL . '/admin/index.php');
                    exit;
                }
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1>پنل مدیریت</h1>
    <p class="login-sub"><?= e(setting('store_name')) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
        <?= csrf_field() ?>
        <label>نام کاربری
            <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" autofocus required>
        </label>
        <label>رمز عبور
            <input type="password" name="password" required>
        </label>
        <?= captcha_field() ?>
        <button type="submit" class="btn btn-accent btn-block">ورود</button>
    </form>
</div>
</body>
</html>
