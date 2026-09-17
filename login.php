<?php
/**
 * login.php — ورود مشتری به باشگاه مشتریان
 */
require_once __DIR__ . '/config.php';

if (is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/account.php');
    exit;
}

$error = '';
$recovery = isset($_GET['action']) && $_GET['action'] === 'recover';
$recoveryStep = (int)($_POST['recovery_step'] ?? 0);
$recoveryInfo = '';
$recoveryUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recovery) {
    // ---- جریان بازیابی رمز دوکاناله: ایمیل یا پیامک با کد (انتخاب کاربر) ----
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $recoveryUsername = $username;
        $rec = $_SESSION['recover'] ?? [];

        if (!login_rate_limit('recover:' . ($username !== '' ? $username : (string)($rec['username'] ?? '?')))) {
            $error = 'به دلیل تلاش‌های زیاد، بازیابی موقتاً مسدود شد. بعد از ۱۵ دقیقه دوباره تلاش کنید.';
        } elseif ($recoveryStep === 1) {
            // مرحلهٔ ۱: نام کاربری → بررسی حساب و کانال‌های موجود
            $user = find_recovery_user($username);
            if (!$user) {
                $error = 'کاربری با این نام کاربری یافت نشد.';
            } else {
                $phone = sms_normalize_phone((string)($user['phone'] ?? ''));
                $email = trim((string)($user['email'] ?? ''));
                $hasSms = $phone !== null;
                $hasEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                if (!$hasSms && !$hasEmail) {
                    $error = 'برای این حساب شماره موبایل یا ایمیلی ثبت نشده است. لطفاً با پشتیبانی تماس بگیرید.';
                } else {
                    $_SESSION['recover'] = [
                        'username' => $username,
                        'user_id'  => (int)$user['id'],
                        'phone'    => $hasSms ? $phone : '',
                        'email'    => $hasEmail ? strtolower($email) : '',
                    ];
                    $recoveryStep = 2;
                    $recoveryInfo = 'روش دریافت کد بازیابی را انتخاب کنید.';
                }
            }
        } elseif ($recoveryStep === 2) {
            // مرحلهٔ ۲: ارسال کد از کانال انتخابی
            $channel = ($_POST['channel'] ?? '') === 'sms' ? 'sms' : 'email';
            $target = '';
            if ($channel === 'sms') {
                $target = (string)($rec['phone'] ?? '');
                if ($target === '') {
                    $error = 'برای این حساب شماره موبایلی ثبت نشده است.';
                }
            } else {
                $target = (string)($rec['email'] ?? '');
                if ($target === '') {
                    $error = 'برای این حساب ایمیلی ثبت نشده است.';
                }
            }
            if ($error === '') {
                $code = otp_create('reset:' . $target, $channel, 600); // اعتبار ۱۰ دقیقه
                recovery_send_code($channel, $target, $code);
                $_SESSION['recover']['channel'] = $channel;
                $_SESSION['recover']['target'] = $target;
                $recoveryStep = 3;
                $recoveryInfo = 'کد بازیابی به ' . mask_contact($channel, $target) . ' ارسال شد (اعتبار ۱۰ دقیقه).';
            }
        } elseif ($recoveryStep === 3) {
            // مرحلهٔ ۳: تأیید کد
            $code = trim($_POST['code'] ?? '');
            $target = (string)($rec['target'] ?? '');
            if ($target === '' || $code === '') {
                $error = 'کد بازیابی را وارد کنید.';
            } else {
                $v = otp_verify('reset:' . $target, $code);
                if (!$v['ok']) {
                    $error = $v['error'];
                } else {
                    $recoveryStep = 4;
                    $recoveryInfo = 'کد تأیید شد. رمز عبور جدید را ثبت کنید.';
                }
            }
        } elseif ($recoveryStep === 4) {
            // مرحلهٔ ۴: ثبت رمز جدید
            $password  = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');
            $userId = (int)($rec['user_id'] ?? 0);
            if ($userId <= 0) {
                $error = 'جلسهٔ بازیابی نامعتبر است. از ابتدا شروع کنید.';
            } elseif (strlen($password) < 6) {
                $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
            } elseif ($password !== $password2) {
                $error = 'تکرار رمز عبور مطابقت ندارد.';
            } elseif (recovery_reset_password($userId, $password)) {
                unset($_SESSION['recover']);
                flash('success', 'رمز عبور با موفقیت تغییر کرد. اکنون می‌توانید وارد شوید.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            } else {
                $error = 'خطا در ثبت رمز جدید. لطفاً دوباره تلاش کنید.';
            }
        } else {
            $error = 'مرحلهٔ بازیابی نامعتبر است. از ابتدا شروع کنید.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // بررسی CSRF
    if (!csrf_verify()) {
        http_response_code(403);
        $error = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // بررسی کپچا
        if (!captcha_verify($_POST['captcha'] ?? '')) {
            $error = 'پاسخ کپچا نادرست است؛ دوباره تلاش کنید.';
        } elseif (!login_rate_limit($username)) {
            $error = 'به دلیل تلاش‌های ناموفق زیاد، ورود موقتاً مسدود شد. بعد از ۱۵ دقیقه دوباره تلاش کنید.';
        } else {
            $st = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'customer'");
            $st->execute([$username]);
            $user = $st->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];

                $target = $_GET['next'] ?? '';
                // جلوگیری از redirect باز
                if ($target === '' || strpos($target, '//') !== false || $target[0] !== '/') {
                    $target = '/account.php';
                }
                header('Location: ' . BASE_URL . $target);
                exit;
            }
            $error = 'نام کاربری یا رمز عبور اشتباه است.';
        }
    }
}

$pageTitle = 'ورود به حساب';
require __DIR__ . '/includes/header.php';
?>

<section class="container auth-wrap">
    <div class="auth-card">
        <?php if ($recovery): ?>
            <p class="auth-eyebrow">باشگاه مشتریان</p>
            <h1 class="page-title">بازیابی رمز عبور</h1>

            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($recoveryInfo): ?><div class="alert alert-info"><?= e($recoveryInfo) ?></div><?php endif; ?>

            <?php $recView = $_SESSION['recover'] ?? []; ?>
            <?php if ($recoveryStep === 4 && !empty($recView['user_id'])): ?>
                <form method="post" action="login.php?action=recover" class="auth-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="recovery_step" value="4">
                    <input type="hidden" name="username" value="<?= e($recView['username']) ?>">
                    <label>رمز عبور جدید
                        <input type="password" name="password" required autofocus>
                    </label>
                    <label>تکرار رمز عبور جدید
                        <input type="password" name="password2" required>
                    </label>
                    <button type="submit" class="btn btn-accent btn-block">ثبت رمز جدید</button>
                </form>
            <?php elseif ($recoveryStep === 3 && !empty($recView['target'])): ?>
                <form method="post" action="login.php?action=recover" class="auth-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="recovery_step" value="3">
                    <input type="hidden" name="username" value="<?= e($recView['username']) ?>">
                    <label>کد بازیابی (<?= e(mask_contact($recView['channel'] ?? 'sms', $recView['target'])) ?>)
                        <input type="text" name="code" inputmode="numeric" maxlength="5" dir="ltr" required autofocus autocomplete="one-time-code">
                    </label>
                    <button type="submit" class="btn btn-accent btn-block">تأیید کد</button>
                </form>
                <form method="post" action="login.php?action=recover" class="auth-form" style="padding:0;border:none;box-shadow:none;max-width:none;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="recovery_step" value="2">
                    <input type="hidden" name="username" value="<?= e($recView['username']) ?>">
                    <input type="hidden" name="channel" value="<?= e($recView['channel'] ?? 'sms') ?>">
                    <button type="submit" class="btn btn-ghost btn-block">ارسال دوبارهٔ کد</button>
                </form>
            <?php elseif ($recoveryStep >= 2 && !empty($recView)): ?>
                <form method="post" action="login.php?action=recover" class="auth-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="recovery_step" value="2">
                    <input type="hidden" name="username" value="<?= e($recView['username'] ?? '') ?>">
                    <label>روش دریافت کد بازیابی</label>
                    <?php if (($recView['phone'] ?? '') !== ''): ?>
                        <label class="check-item" style="background:var(--bg,#f8fafc);">
                            <input type="radio" name="channel" value="sms" checked>
                            <span>پیامک به <?= e(fa_digits(mask_contact('sms', $recView['phone']))) ?></span>
                        </label>
                    <?php endif; ?>
                    <?php if (($recView['email'] ?? '') !== ''): ?>
                        <label class="check-item" style="background:var(--bg,#f8fafc);">
                            <input type="radio" name="channel" value="email" <?= (($recView['phone'] ?? '') === '') ? 'checked' : '' ?>>
                            <span>ایمیل به <?= e(mask_contact('email', $recView['email'])) ?></span>
                        </label>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-accent btn-block">ارسال کد بازیابی</button>
                </form>
            <?php else: ?>
                <form method="post" action="login.php?action=recover" class="auth-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="recovery_step" value="1">
                    <label>نام کاربری
                        <input type="text" name="username" value="<?= e($recoveryUsername) ?>" required autofocus>
                    </label>
                    <button type="submit" class="btn btn-accent btn-block">ادامه</button>
                </form>
            <?php endif; ?>

            <p class="auth-alt"><a href="login.php">بازگشت به ورود</a></p>
        <?php else: ?>
        <p class="auth-eyebrow">باشگاه مشتریان</p>
        <h1 class="page-title">ورود به حساب</h1>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="login.php<?= isset($_GET['next']) ? '?next=' . e(urlencode($_GET['next'])) : '' ?>" class="auth-form" novalidate>
            <?= csrf_field() ?>
            <label>نام کاربری
                <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
            </label>
            <label>رمز عبور
                <input type="password" name="password" required>
            </label>
            <?= captcha_field() ?>
            <button type="submit" class="btn btn-accent btn-block">ورود</button>
        </form>

        <p class="auth-alt">
            <a href="login.php?action=recover">رمز عبور خود را فراموش کرده‌اید؟</a>
            &nbsp;&middot;&nbsp;
            حساب ندارید؟ <a href="register.php">ثبت‌نام</a>
        </p>
        <?php if (otp_login_enabled()): ?>
            <p class="auth-alt">
                <a href="otp_login.php<?= isset($_GET['next']) ? '?next=' . e(urlencode($_GET['next'])) : '' ?>">ورود سریع با کد یک‌بارمصرف (OTP)</a>
            </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
