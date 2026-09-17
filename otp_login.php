<?php
/**
 * otp_login.php — ورود با کد یک‌بارمصرف (OTP) از طریق پیامک یا ایمیل
 * ------------------------------------------------------------------
 * جریان:
 *   ۱) کاربر شماره موبایل یا ایمیل را وارد می‌کند
 *   ۲) کد ۵ رقمی تولید و از طریق پیامک/ایمیل ارسال می‌شود
 *   ۳) کاربر کد را وارد می‌کند → در صورت صحت، ورود انجام می‌شود
 *
 * اگر کاربر هنوز ثبت‌نام نکرده باشد، با اولین ورود OTP خودکار حساب ساخته می‌شود.
 */
require_once __DIR__ . '/config.php';

if (is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/account.php');
    exit;
}

if (!otp_login_enabled()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$error = '';
$info  = '';
$step  = 1; // 1 = درخواست کد، 2 = تأیید کد

// ادامهٔ بعد از درخواست کد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'verify') {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $action = $_POST['action'] ?? '';

        // --- مرحلهٔ ۱: درخواست کد ---
        if ($action === 'request') {
            $identifier = strtolower(trim($_POST['identifier'] ?? ''));
            if ($identifier === '') {
                $error = 'شماره موبایل یا ایمیل را وارد کنید.';
            } else {
                if (!ip_rate_limit('otpreq', 10, 3600)) {
                    $error = 'تعداد درخواست‌های کد ورود از سمت شما بیش از حد مجاز است؛ یک ساعت دیگر تلاش کنید.';
                } elseif (!login_rate_limit('otp:' . $identifier)) {
                    $error = 'به دلیل درخواست‌های زیاد، ارسال کد موقتاً مسدود شد. بعد از ۱۵ دقیقه دوباره تلاش کنید.';
                } else {
                    // تشخیص کانال: ایمیل یا پیامک
                    $channel = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms';
                    if ($channel === 'sms') {
                        $norm = sms_normalize_phone($identifier);
                        if ($norm === null) {
                            $error = 'شماره موبایل نامعتبر است (مثال: 09123456789).';
                        } else {
                            $code = otp_create($norm, 'sms');
                            otp_send_sms($identifier, $code);
                            $_SESSION['otp_identifier'] = $norm;
                            $_SESSION['otp_channel'] = 'sms';
                            $step = 2;
                            $info = 'کد ۵ رقمی به شمارهٔ شما پیامک شد.';
                        }
                    } else {
                        $code = otp_create($identifier, 'email');
                        otp_send_email($identifier, $code);
                        $_SESSION['otp_identifier'] = $identifier;
                        $_SESSION['otp_channel'] = 'email';
                        $step = 2;
                        $info = 'کد ۵ رقمی به ایمیل شما ارسال شد (پوشهٔ اسپم را هم بررسی کنید).';
                    }
                }
            }
        }
        // --- مرحلهٔ ۲: تأیید کد ---
        elseif ($action === 'verify') {
            $code = trim($_POST['code'] ?? '');
            $identifier = $_SESSION['otp_identifier'] ?? '';
            $channel = $_SESSION['otp_channel'] ?? 'sms';

            if ($identifier === '' || $code === '') {
                $error = 'کد را وارد کنید.';
            } elseif (!login_rate_limit('otpverify:' . $identifier)) {
                $error = 'به دلیل تلاش‌های زیاد، تأیید موقتاً مسدود شد.';
            } else {
                $res = otp_verify($identifier, $code, $channel);
                if (!$res['ok']) {
                    $error = $res['error'];
                } else {
                    // یافتن یا ساخت کاربر
                    $user = find_user_by_identifier($channel === 'sms' ? sms_normalize_phone($identifier) : $identifier);
                    if (!$user) {
                        // ساخت خودکار حساب برای کاربر جدید
                        $phone = $channel === 'sms' ? sms_normalize_phone($identifier) : null;
                        $email = $channel === 'email' ? $identifier : null;
                        $username = $email ?: 'u' . time() . rand(100, 999);
                        // اگر نام کاربری تکراری بود، پسوند بده
                        $base = $username;
                        $i = 0;
                        while (user_exists($username)) {
                            $username = $base . ++$i;
                        }
                        $st = db()->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                        $st->execute([$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $identifier, $email, $phone]);
                        $userId = (int)db()->lastInsertId();

                        // ---- پیامک اطلاع ثبت‌نام کاربر جدید (ورود با OTP) به ادمین‌ها ----
                        try {
                            sms_notify_admins(
                                sms_template('admin_new_user', [
                                    '{name}'     => $identifier,
                                    '{username}' => $username,
                                    '{phone}'    => ($phone !== null) ? $phone : 'ثبت نشده',
                                ]),
                                'admin_new_user',
                                [$identifier, $username, ($phone !== null) ? $phone : '-']
                            );
                        } catch (Throwable $tnu) {
                            // نوتیف نباید ورود را خراب کند
                        }
                    } else {
                        $userId = (int)$user['id'];
                        $userFull = $user['full_name'] ?: $user['username'];
                    }

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $userFull ?? ($email ?: ($phone ?? $identifier));

                    // پاک‌سازی نشست OTP
                    unset($_SESSION['otp_identifier'], $_SESSION['otp_channel']);

                    flash('success', 'ورود موفق بود. خوش آمدید!');
                    $target = $_GET['next'] ?? '';
                    if ($target === '' || strpos($target, '//') !== false || $target[0] !== '/') {
                        $target = '/account.php';
                    }
                    header('Location: ' . BASE_URL . $target);
                    exit;
                }
            }
        }
    }
}

$pageTitle = 'ورود با کد یک‌بارمصرف';
require __DIR__ . '/includes/header.php';
?>

<section class="container auth-wrap">
    <div class="auth-card">
        <p class="auth-eyebrow">باشگاه مشتریان</p>
        <h1 class="page-title">ورود با کد یک‌بارمصرف</h1>
        <p class="auth-sub">بدون نیاز به رمز عبور؛ کد ۵ رقمی به شماره یا ایمیل شما ارسال می‌شود.</p>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($info): ?><div class="alert alert-info"><?= e($info) ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post" action="otp_login.php<?= isset($_GET['next']) ? '?next=' . e(urlencode($_GET['next'])) : '' ?>" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request">
                <label>شماره موبایل یا ایمیل
                    <input type="text" name="identifier" value="<?= e($_POST['identifier'] ?? '') ?>" dir="ltr" placeholder="09123456789 یا you@example.com" required autofocus>
                </label>
                <button type="submit" class="btn btn-accent btn-block">ارسال کد</button>
            </form>
        <?php else: ?>
            <form method="post" action="otp_login.php<?= isset($_GET['next']) ? '?next=' . e(urlencode($_GET['next'])) : '' ?>" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <label>کد ۵ رقمی ارسال‌شده
                    <input type="text" name="code" inputmode="numeric" maxlength="5" dir="ltr" placeholder="•••••" required autofocus autocomplete="one-time-code">
                </label>
                <button type="submit" class="btn btn-accent btn-block">تأیید و ورود</button>
            </form>
            <p class="auth-alt"><a href="otp_login.php">ارسال دوبارهٔ کد</a></p>
        <?php endif; ?>

        <p class="auth-alt">
            <a href="login.php">ورود با نام کاربری و رمز عبور</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
