<?php
/**
 * admin/settings.php — تنظیمات فروشگاه به‌صورت تب‌های مجزا
 * هر تب فقط فیلدهای خودش را ذخیره می‌کند (ذخیرهٔ جزئی امن) + محافظ CSRF
 */
$pageTitle = 'تنظیمات';
require __DIR__ . '/_header.php';

/** تعریف تب‌ها */
$tabs = [
    'store'     => 'فروشگاه و تماس',
    'orders'    => 'سفارش‌ها و فاکتور',
    'theme'     => 'ظاهر سایت',
    'homepage'  => 'متن‌های صفحهٔ اصلی',
    'loyalty'   => 'باشگاه مشتریان',
    'sms'       => 'سامانهٔ پیامک',
    'telegram'  => 'ربات تلگرام',
    'email'     => 'ایمیل',
    'shipping'  => 'ارسال و تخفیف',
    'payment'   => 'درگاه پرداخت',
    'analytics' => 'گوگل آنالیز و بینگ',
    'ai'        => 'هوش مصنوعی',
    'backup'    => 'پشتیبان‌گیری',
];

/** فیلدهای متنی هر تب (ذخیرهٔ مستقیم از POST) */
$tabTextFields = [
    'store'    => ['store_name', 'store_tagline', 'company_about', 'company_address', 'company_lat', 'company_lng', 'company_city', 'company_region', 'company_hours', 'company_phone', 'company_email', 'site_logo', 'site_favicon', 'enamad_image', 'enamad_link', 'enamad_code'],
    'orders'    => ['invoice_color', 'invoice_note'],
    'homepage' => [
        'home_hero_eyebrow', 'home_hero_title', 'home_hero_tagline', 'home_search_placeholder',
        'home_cta_primary_label', 'home_cta_secondary_label', 'home_hero_image',
        'home_cta_primary_url', 'home_cta_secondary_url',
        'home_featured_title', 'home_featured_sub', 'home_categories_title',
        'home_categories_align',
        'home_about_title', 'home_about_cta_label',
        'home_stat1_num', 'home_stat1_label', 'home_stat2_num', 'home_stat2_label',
        'home_stat3_num', 'home_stat3_label', 'home_stat4_num', 'home_stat4_label',
    ],
    'sms'      => ['sms_username', 'sms_password', 'sms_sender'],
    'email'    => ['email_provider', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'email_from_email', 'email_from_name'],
    'ai'       => ['ai_provider', 'ai_api_key', 'ai_base_url', 'ai_model'],
    'analytics'=> ['ga_measurement_id', 'gtm_container_id', 'bing_verification', 'google_site_verification', 'google_ads_id'],
    'payment'  => ['merchant_id', 'payment_gateway', 'bitpay_api', 'bitpay_env'],
];

$active = $_POST['tab'] ?? ($_GET['tab'] ?? 'store');
if (!isset($tabs[$active])) {
    $active = 'store';
}

$aiTestResult = null;
$aiModelList = null;
if ($active === 'ai' && isset($_POST['action']) && in_array($_POST['action'], ['test_ai', 'fetch_models'], true)) {
    if (csrf_verify()) {
        // اول ذخیرهٔ تنظیمات فعلی فرم
        foreach (['ai_provider', 'ai_api_key', 'ai_base_url', 'ai_model'] as $k) {
            set_setting($k, trim($_POST[$k] ?? setting($k)));
        }
        if ($_POST['action'] === 'test_ai') {
            $aiTestResult = ai_test_connection();
        } else {
            $r = ai_list_models();
            if ($r['ok']) {
                $aiModelList = $r['models'];
            } else {
                $aiTestResult = ['ok' => false, 'text' => '', 'error' => 'دریافت مدل‌ها ناموفق: ' . $r['error']];
            }
        }
        // از ادامهٔ پردازش POST اصلی (ذخیره/ریدایرکت) رد نشو
        $skipMainPost = true;
    }
}

if (!empty($skipMainPost)) {
    // ادامهٔ عادی رندر صفحه (بدون اجرای ذخیرهٔ اصلی)
} else

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: settings.php?tab=' . urlencode($active));
        exit;
    }

    // ۱) فیلدهای متنی همین تب
    foreach ($tabTextFields[$active] ?? [] as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($v === '' && in_array($k, ['store_name', 'store_tagline'], true)) {
            $cur = setting($k, '');
            $v = $cur !== '' ? $cur : ($k === 'store_name' ? STORE_NAME : STORE_TAGLINE);
        }
        set_setting($k, $v);
    }

    // ۲) منطق خاص هر تب
    switch ($active) {
        case 'store':
            // آپلود لوگو: اگر فایلی انتخاب شده باشد، resize و ذخیره می‌شود
            if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$ok, $path, $upErr] = process_image_upload($_FILES['site_logo_file'], 300, 300, 5242880);
                if ($ok) {
                    set_setting('site_logo', $path);
                } else {
                    flash('error', $upErr);
                }
            }
            // آپلود فاوآیکون
            if (isset($_FILES['site_favicon_file']) && $_FILES['site_favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$ok, $path, $upErr] = process_image_upload($_FILES['site_favicon_file'], 128, 128, 1048576);
                if ($ok) {
                    set_setting('site_favicon', $path);
                } else {
                    flash('error', $upErr);
                }
            }
            // آپلود نماد اعتماد (e-namad)
            if (isset($_FILES['enamad_image_file']) && $_FILES['enamad_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$ok, $path, $upErr] = process_image_upload($_FILES['enamad_image_file'], 300, 300, 2097152);
                if ($ok) {
                    set_setting('enamad_image', $path);
                } else {
                    flash('error', $upErr);
                }
            }
            // ورود با OTP (کد یک‌بارمصرف)
            set_setting('otp_login_enabled', isset($_POST['otp_login_enabled']) ? '1' : '0');
            $otpChannel = in_array($_POST['otp_channel'] ?? '', ['sms', 'email'], true) ? $_POST['otp_channel'] : 'sms';
            set_setting('otp_channel', $otpChannel);
            break;
        case 'orders':
            // اعتبارسنجی رنگ فاکتور
            $invColor = trim($_POST['invoice_color'] ?? '');
            set_setting('invoice_color', theme_hex_to_rgb($invColor) ? $invColor : '#06163a');
            // چکباکس نمایش لوگو در فاکتور
            set_setting('invoice_show_logo', isset($_POST['invoice_show_logo']) ? '1' : '0');
            // پنهانکردن قیمت از مهمان
            set_setting('hide_prices_guests', isset($_POST['hide_prices_guests']) ? '1' : '0');
            // رهگیری سفارش
            set_setting('order_tracking_enabled', isset($_POST['order_tracking_enabled']) ? '1' : '0');
            break;

        case 'theme':
            save_theme_settings($_POST);
            break;

        case 'homepage':
            // آپلود تصویر پس‌زمینهٔ هیرو (اختیاری)
            if (isset($_FILES['home_hero_image_file']) && $_FILES['home_hero_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$ok, $path, $upErr] = process_image_upload($_FILES['home_hero_image_file'], 1920, 1080, 8388608);
                if ($ok) {
                    set_setting('home_hero_image', $path);
                } else {
                    flash('error', $upErr);
                }
            }
            // چک‌باکس‌های نمایش بخش‌ها (فقط کلیدهای معتبر)
            $homeBooleans = ['home_show_eyebrow', 'home_show_tagline', 'home_show_cta', 'home_show_stats', 'home_show_featured', 'home_show_categories', 'home_show_about', 'reviews_enabled'];
            foreach ($homeBooleans as $bKey) {
                set_setting($bKey, isset($_POST[$bKey]) ? '1' : '0');
            }
            // تراز عنوان دسته‌بندی‌ها (فقط مقادیر معتبر)
            $align = $_POST['home_categories_align'] ?? 'center';
            set_setting('home_categories_align', in_array($align, ['right', 'center', 'left'], true) ? $align : 'center');
            break;

        case 'loyalty':
            set_setting('loyalty_welcome_bonus', (string)max(0, min(100000, (int)($_POST['loyalty_welcome_bonus'] ?? 0))));
            set_setting('loyalty_earn_rate', max(1, min(1000000, (int)($_POST['loyalty_earn_rate'] ?? 10000))));
            set_setting('referral_reward_points', (string)max(0, min(1000000, (int)($_POST['referral_reward_points'] ?? 100))));
            set_setting('referral_invitee_bonus', (string)max(0, min(100000, (int)($_POST['referral_invitee_bonus'] ?? 0))));
            break;

        case 'shipping':
            set_setting('shipping_flat_rate', (string)max(0, (int)($_POST['shipping_flat_rate'] ?? 0)));
        foreach (['tipax', 'post', 'express', 'pickup'] as $_m) {
            set_setting('ship_cost_' . $_m, (string)max(0, (int)($_POST['ship_cost_' . $_m] ?? 0)));
        }
        foreach (['tipax', 'post', 'express'] as $_m) {
            set_setting('ship_enabled_' . $_m, isset($_POST['ship_enabled_' . $_m]) ? '1' : '0');
        }
        set_setting('pickup_enabled', isset($_POST['pickup_enabled']) ? '1' : '0');
            set_setting('global_discount', (string)max(0, min(100, (int)($_POST['global_discount'] ?? 0))));
            // شماره‌های موبایل ادمین‌ها برای پیامک اطلاع (کاما/فاصله جدا)
            break;

        case 'payment':
            $mode = in_array($_POST['payment_mode'] ?? '', ['mock', 'sandbox', 'production'], true) ? $_POST['payment_mode'] : 'mock';
            set_setting('payment_mode', $mode);
            $gw = in_array($_POST['payment_gateway'] ?? '', ['zarinpal', 'bitpay'], true) ? $_POST['payment_gateway'] : 'zarinpal';
            set_setting('payment_gateway', $gw);
            $benv = in_array($_POST['bitpay_env'] ?? '', ['test', 'production'], true) ? $_POST['bitpay_env'] : 'test';
            set_setting('bitpay_env', $benv);
            break;

        case 'telegram':
            // توکن ربات از BotFather
            $_tok = trim((string)($_POST['tg_bot_token'] ?? ''));
            $_tokClean = preg_replace('/[^A-Za-z0-9_:\-]/', '', $_tok);
            set_setting('tg_bot_token', $_tokClean);
            // لیست chat id ادمین‌ها (کاما/فاصله/خط جدید)
            $_ids = preg_replace('/[^0-9,\-\s]/', '', (string)($_POST['tg_chat_admin_ids'] ?? ''));
            $_ids = preg_replace('/\s+/', ',', $_ids);
            $_idsArr = [];
            foreach (explode(',', $_ids) as $_p) { $_p = trim($_p); if ($_p !== '' && ctype_digit(ltrim($_p, '-'))) $_idsArr[] = $_p; }
            set_setting('tg_chat_admin_ids', implode(',', $_idsArr));
            if (($_POST['action'] ?? '') === 'tg_test') {
                if (function_exists('tg_enabled') && tg_enabled() && function_exists('tg_notify_admins')) {
                    $_r = @tg_notify_admins('✅ پیام تستی ربات ابزار شرق — ' . date('Y-m-d H:i:s'), 'test');
                    flash($_r > 0 ? 'success' : 'error', 'تست ارسال شد به ' . $_r . ' ادمین.');
                } else {
                    flash('error', 'ابتدا توکن و شناسهٔ چت را وارد و ذخیره کنید.');
                }
            }
            break;

        case 'sms':
            // انتخاب خط ملی‌پیامک: shared = خط خدماتی اشتراکی، dedicated = خط اختصاصی
            $lineMode = ($_POST['sms_line_mode'] ?? '') === 'dedicated' ? 'dedicated' : 'shared';
            set_setting('sms_line_mode', $lineMode);
            set_setting('sms_base_enabled', $lineMode === 'shared' ? '1' : '0');
            // شماره فرستنده: نرمال‌سازی ارقام فارسی/عربی به لاتین
            set_setting('sms_sender', sms_en_digits(trim($_POST['sms_sender'] ?? '')));
            // همگام‌سازی کد الگوها از خود پنل ملی‌پیامک (بدون ورود دستی)
            if (($_POST['action'] ?? '') === 'sms_sync') {
                $sync = sms_base_autosync();
                flash($sync['ok'] ? 'success' : 'error', $sync['msg']);
            }
            // شمارههای موبایل ادمینها (پیامک سفارش جدید و پیام چت)
            $adminPhones = preg_replace('/[^0-9,،;\s]/u', '', (string)($_POST['notify_admin_phones'] ?? ''));
            set_setting('notify_admin_phones', trim($adminPhones));
            break;

        case 'email':
            // رمز SMTP: فقط اگر فیلد پر شود ذخیره می‌شود (پاک‌شدن تصادفی ندارد)
            if (trim($_POST['smtp_pass'] ?? '') !== '' || setting('smtp_pass', '') === '') {
                set_setting('smtp_pass', trim($_POST['smtp_pass'] ?? ''));
            }
            // ارسال ایمیل تست (در همان فرم تب)
            $testTo = trim($_POST['email_test_to'] ?? '');
            if ($testTo !== '') {
                $tres = email_send($testTo, 'ایمیل تست — ' . setting('store_name', STORE_NAME), "سلام،\n\nاین یک ایمیل تست از پنل مدیریت «" . setting('store_name', STORE_NAME) . "» است.\n\nاگر این ایمیل را می‌بینید، تنظیمات ایمیل درست است.\n\n— " . setting('store_name', STORE_NAME));
                flash($tres['ok'] ? 'success' : 'error', $tres['ok'] ? ('ایمیل تست با موفقیت ارسال شد (' . $tres['status'] . ')') : ('ارسال ایمیل تست ناموفق بود: ' . $tres['status']));
            }
            break;
    }


    flash('success', 'تنظیمات بخش «' . $tabs[$active] . '» ذخیره شد.');
    header('Location: settings.php?tab=' . urlencode($active));
    exit;
}

$s = get_settings();

/* ===== پشتیبان‌گیری و بازگردانی ===== */
if ($active === 'backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: settings.php?tab=backup');
        exit;
    }

    // دانلود بکاپ
    if (isset($_POST['action']) && $_POST['action'] === 'download') {
        $backup = build_database_backup();
        if ($backup === null) {
            flash('error', 'ساخت بکاپ ناموفق بود.');
            header('Location: settings.php?tab=backup');
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup-' . date('Y-m-d-His') . '.sql' . (DB_DRIVER === 'sqlite' ? '.sqlite' : '') . '"');
        header('Content-Length: ' . strlen($backup));
        echo $backup;
        exit;
    }

    // بازگردانی از فایل آپلودی
    if (isset($_POST['action']) && $_POST['action'] === 'restore' && isset($_FILES['backup_file'])) {
        $f = $_FILES['backup_file'];
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] <= 0 || $f['size'] > 10485760) {
            flash('error', 'فایل بکاپ معتبر نیست (حداکثر ۱۰ مگابایت).');
        } else {
            $data = file_get_contents($f['tmp_name']);
            $res = restore_database_backup($data);
            if ($res === true) {
                flash('success', 'بازگردانی با موفقیت انجام شد.');
            } else {
                flash('error', 'بازگردانی ناموفق: ' . $res);
            }
        }
        header('Location: settings.php?tab=backup');
        exit;
    }
}

// تست اتصال AI / دریافت مدل‌ها (بدون ذخیرهٔ تنظیمات — فقط نمایش نتیجه)

/** مقدار فعلی یک کلید برای نمایش در فرم */
function sv(string $key, string $default = ''): string
{
    global $s;
    $v = isset($s[$key]) ? (string)$s[$key] : '';
    return $v !== '' ? $v : $default;
}
?>

<h1 class="page-title">تنظیمات فروشگاه</h1>

<nav class="settings-tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= e($key) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form method="post" action="settings.php?tab=<?= e($active) ?>" class="admin-form settings-panel" enctype="multipart/form-data">
    <input type="hidden" name="tab" value="<?= e($active) ?>">
    <?= csrf_field() ?>

<?php if ($active === 'store'): ?>
    <h2>اطلاعات فروشگاه</h2>
    <label>نام فروشگاه
        <input type="text" name="store_name" value="<?= e(sv('store_name')) ?>">
    </label>
    <label>شعار فروشگاه
        <input type="text" name="store_tagline" value="<?= e(sv('store_tagline')) ?>">
    </label>
    <label>لوگوی فروشگاه
        <?php if (sv('site_logo') !== ''): ?>
            <span class="logo-preview"><img src="<?= e(product_image_url(sv('site_logo'))) ?>" alt="لوگوی فعلی"></span>
        <?php endif; ?>
        <input type="file" name="site_logo_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <span class="muted">تصویر بزرگ به‌صورت خودکار به ۳۰۰×۳۰۰ تغییر اندازه می‌یابد (حداکثر ۵ مگابایت).</span>
        <input type="text" name="site_logo" value="<?= e(sv('site_logo')) ?>" dir="ltr" placeholder="یا آدرس مستقیم تصویر (اختیاری)">
    </label>
    <label>فاوآیکون (favicon) سایت
        <?php if (sv('site_favicon') !== ''): ?>
            <span class="logo-preview"><img src="<?= e(product_image_url(sv('site_favicon'))) ?>" alt="فاوآیکون فعلی"></span>
        <?php endif; ?>
        <input type="file" name="site_favicon_file" accept="image/jpeg,image/png,image/webp,image/gif,image/x-icon,image/vnd.microsoft.icon">
        <span class="muted">به‌صورت خودکار به ۱۲۸×۱۲۸ تغییر اندازه می‌یابد (حداکثر ۱ مگابایت). اگر خالی باشد، فاوآیکون خودکار (حرف اول نام + رنگ تم) نمایش داده می‌شود.</span>
        <input type="text" name="site_favicon" value="<?= e(sv('site_favicon')) ?>" dir="ltr" placeholder="یا آدرس مستقیم تصویر (اختیاری)">
    </label>
    <label>کد کامل نماد اعتماد (e-namad) — ترجیحی
        <textarea name="enamad_code" rows="4" dir="ltr" placeholder="کد HTML رسمی اینماد را اینجا بچسبانید (ترجیح داده می‌شود)"><?= e(sv('enamad_code')) ?></textarea>
        <span class="muted">اگر این فیلد پر باشد، همین کد به‌صورت زنده در فوتر نمایش داده می‌شود (تصویر از سرور اینماد).</span>
    </label>
    <label>نماد اعتماد الکترونیکی (e-namad)
        <?php if (sv('enamad_image') !== ''): ?>
            <span class="logo-preview"><img src="<?= e(product_image_url(sv('enamad_image'))) ?>" alt="نماد اعتماد فعلی"></span>
        <?php endif; ?>
        <input type="file" name="enamad_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <span class="muted">عکس نماد اعتماد (حداکثر ۲ مگابایت) — فقط در صورت نبودِ کد بالا.</span>
        <input type="text" name="enamad_image" value="<?= e(sv('enamad_image')) ?>" dir="ltr" placeholder="یا آدرس مستقیم تصویر">
    </label>
    <label>لینک نماد اعتماد (آدرس صفحهٔ اعتبارسنجی)
        <input type="text" name="enamad_link" value="<?= e(sv('enamad_link')) ?>" dir="ltr" placeholder="مثلاً https://trustseal.enamad.ir/?id=123456">
    </label>


    <h3 class="sub-title" style="margin-top:1.4rem;">ورود و رهگیری</h3>
    <label class="check-item" style="margin-bottom:1rem;"><input type="checkbox" name="otp_login_enabled" value="1" <?= setting_bool('otp_login_enabled', false) ? 'checked' : '' ?>> فعال‌کردن ورود با کد یک‌بارمصرف (OTP) — بدون نیاز به رمز عبور</label>
    <div class="form-row">
        <label>کانال ارسال کد OTP
            <select name="otp_channel">
                <option value="sms" <?= setting('otp_channel', 'sms') === 'sms' ? 'selected' : '' ?>>پیامک (نیازمند درگاه پیامک)</option>
                <option value="email" <?= setting('otp_channel', 'sms') === 'email' ? 'selected' : '' ?>>ایمیل</option>
            </select>
        </label>
    </div>
    <label>دربارهٔ شرکت (نمایش در صفحهٔ «درباره ما» و فوتر)
        <textarea name="company_about" rows="4"><?= e(sv('company_about')) ?></textarea>
    </label>
    <label>آدرس شرکت
        <input type="text" name="company_address" value="<?= e(sv('company_address')) ?>">
    </label>
    <div class="form-row">
        <label>عرض جغرافیایی (نقشهٔ صفحهٔ درباره ما)
            <input type="text" name="company_lat" value="<?= e(sv('company_lat', '35.6892')) ?>" dir="ltr">
        </label>
        <label>طول جغرافیایی (نقشهٔ صفحهٔ درباره ما)
            <input type="text" name="company_lng" value="<?= e(sv('company_lng', '51.3890')) ?>" dir="ltr">
        </label>
    </div>
    <div id="mapPickerStore" style="height:320px;border-radius:12px;border:1px solid #e2e8f0;margin:12px 0;direction:ltr"></div>
    <p class="muted">روی نقشه کلیک کنید یا نشانگر را بکشید تا موقعیت جدید ثبت شود؛ مختصات به‌صورت خودکار در دو فیلد بالا پر می‌شود و با ذخیرهٔ تنظیمات، نقشهٔ صفحهٔ «درباره ما» هم به‌روز می‌شود.</p>
    <div class="form-row">
        <label>شهر
            <input type="text" name="company_city" value="<?= e(sv('company_city', 'تهران')) ?>">
        </label>
        <label>کد منطقه (الگو: IR-XX)
            <input type="text" name="company_region" value="<?= e(sv('company_region', 'IR-07')) ?>" dir="ltr">
        </label>
    </div>
    <label>ساعات کاری (نمایش برای مشتریان)
        <input type="text" name="company_hours" value="<?= e(sv('company_hours', 'شنبه تا چهارشنبه ۸:۰۰ تا ۱۷:۰۰')) ?>">
    </label>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function () {
        var latEl = document.querySelector('[name=company_lat]');
        var lngEl = document.querySelector('[name=company_lng]');
        var box = document.getElementById('mapPickerStore');
        if (!box || !latEl || !lngEl || typeof L === 'undefined') return;
        var lat = parseFloat(latEl.value), lng = parseFloat(lngEl.value);
        if (isNaN(lat)) lat = 35.4302595;
        if (isNaN(lng)) lng = 51.8367681;
        var map = L.map('mapPickerStore').setView([lat, lng], 15);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        function fill(a, b) { latEl.value = a.toFixed(7); lngEl.value = b.toFixed(7); }
        map.on('click', function (e) { marker.setLatLng(e.latlng); fill(e.latlng.lat, e.latlng.lng); });
        marker.on('dragend', function () { var p = marker.getLatLng(); fill(p.lat, p.lng); });
        latEl.addEventListener('change', function () {
            var a = parseFloat(latEl.value), b = parseFloat(lngEl.value);
            if (!isNaN(a) && !isNaN(b)) { marker.setLatLng([a, b]); map.setView([a, b]); }
        });
        setTimeout(function () { map.invalidateSize(); }, 250);
    })();
    </script>
    <div class="form-row">
        <label>تلفن تماس
            <input type="text" name="company_phone" value="<?= e(sv('company_phone')) ?>" dir="ltr">
        </label>
        <label>ایمیل
            <input type="text" name="company_email" value="<?= e(sv('company_email')) ?>" dir="ltr">
        </label>
    </div>

<?php elseif ($active === 'orders'): ?>
    <h2>سفارش‌ها</h2>
    <label class="check-item" style="margin-bottom:1rem;"><input type="checkbox" name="hide_prices_guests" value="1" <?= setting_bool('hide_prices_guests', false) ? 'checked' : '' ?>> نمایش قیمت فقط برای کاربران واردشده (مهمان قیمت را نمی‌بیند و دکمهٔ «برای مشاهدهٔ قیمت وارد شوید» می‌بیند)</label>
    <label class="check-item" style="margin-bottom:1rem;"><input type="checkbox" name="order_tracking_enabled" value="1" <?= setting_bool('order_tracking_enabled', true) ? 'checked' : '' ?>> فعال‌کردن رهگیری سفارش برای مشتریان (آماده‌سازی / آمادهٔ ارسال / تحویل به پست)</label>

    <h2>فاکتور</h2>
    <div class="form-row">
        <label>رنگ اصلی فاکتور
            <span class="color-field">
                <input type="color" value="<?= e(sv('invoice_color', '#06163a')) ?>" data-color-for="invoice_color">
                <input type="text" name="invoice_color" id="invoice_color" value="<?= e(sv('invoice_color', '#06163a')) ?>" dir="ltr" maxlength="7">
            </span>
        </label>
        <label>متن پانوشت فاکتور
            <input type="text" name="invoice_note" value="<?= e(sv('invoice_note', 'با تشکر از خرید شما')) ?>">
        </label>
    </div>
    <label class="check-item" style="margin-bottom:1rem;"><input type="checkbox" name="invoice_show_logo" value="1" <?= setting_bool('invoice_show_logo', true) ? 'checked' : '' ?>> نمایش لوگو در فاکتور</label>

    <h2>تخفیف و هزینهٔ ثابت ارسال</h2>
    <label>نرخ ثابت ارسال برای هر سفارش (تومان)
        <input type="number" name="shipping_flat_rate" min="0" step="1" value="<?= e(sv('shipping_flat_rate', '0')) ?>" dir="ltr">
    </label>
    <p class="muted">مثلاً ۲۰۰٬۰۰۰ یعنی هزینهٔ ارسال کل سفارش ۲۰۰٬۰۰۰ تومان؛ صفر یعنی ارسال رایگان.</p>
    <label>تخفیف سراسری فروشگاه (درصد)
        <input type="number" name="global_discount" min="0" max="100" step="1" value="<?= e(sv('global_discount', '0')) ?>" dir="ltr">
    </label>
    <p class="muted">روی همهٔ سفارش‌ها اعمال می‌شود (۰ تا ۱۰۰).</p>
<?php elseif ($active === 'theme'): ?>
    <h2>ظاهر و رنگ‌بندی سایت</h2>
    <p class="muted">رنگ‌ها بلافاصله بعد از ذخیره در کل سایت (هدر، دکمه‌ها، لینک‌ها و پنل مدیریت) اعمال می‌شوند.</p>

    <h3 class="sub-title">تم‌های آماده</h3>
    <div class="preset-grid" id="presetGrid">
        <?php foreach (theme_presets() as $pKey => $pVal): ?>
            <button type="button" class="preset-card" data-preset="<?= e($pKey) ?>"
                data-accent="<?= e($pVal[1]) ?>" data-accent-dark="<?= e($pVal[2]) ?>"
                data-navy="<?= e($pVal[3]) ?>" data-radius="<?= e($pVal[4]) ?>">
                <span class="preset-swatches">
                    <span class="sw" style="background:<?= e($pVal[1]) ?>"></span>
                    <span class="sw" style="background:<?= e($pVal[2]) ?>"></span>
                    <span class="sw" style="background:<?= e($pVal[3]) ?>"></span>
                </span>
                <span class="preset-name"><?= e($pVal[0]) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <style>
    .anim-visual--aurora { background: linear-gradient(120deg, #0f228c, #7c3aed, #06b6d4, #0f228c); background-size: 300% 300%; animation: pvAurora 6s ease infinite; }
    @keyframes pvAurora { 50% { background-position: 100% 50%; } }
    .anim-visual--spotlight::after { content: ''; position: absolute; inset: 0; background: radial-gradient(60% 60% at 30% 40%, rgba(255,255,255,.92), transparent 70%); animation: pvSpot 4s ease-in-out infinite alternate; }
    @keyframes pvSpot { to { transform: translateX(62%); } }
    .anim-visual--bubbles span { position: absolute; bottom: -6px; left: 34%; width: 9px; height: 9px; border-radius: 50%; background: rgba(15,34,140,.35); animation: pvBub 3s ease-in infinite; }
    @keyframes pvBub { to { transform: translateY(-30px); opacity: 0; } }
    .anim-visual--glitch span { animation: pvGl 2.4s steps(2) infinite; }
    @keyframes pvGl { 25% { transform: translate(1px,-1px); box-shadow: 2px 0 #f43f5e; } 75% { transform: translate(-1px,1px); box-shadow: -2px 0 #06b6d4; } }
    .anim-visual--wave::after { content: ''; position: absolute; left: -20%; right: -20%; bottom: 2px; height: 10px; background: radial-gradient(12px 8px at 50% 100%, #0f228c 58%, transparent 60%) 0 0/24px 10px repeat-x; animation: pvWave 3s linear infinite; }
    @keyframes pvWave { to { background-position: -48px 0; } }
    </style>
    <h3 class="sub-title">انیمیشن هیرو</h3>
    <div class="anim-grid" id="animGrid">
        <?php foreach (hero_animation_presets() as $aKey => $aVal): ?>
            <button type="button" class="anim-card<?= sv('theme_hero_anim', 'orbit') === $aKey ? ' is-active' : '' ?>" data-anim="<?= e($aKey) ?>">
                <span class="anim-visual anim-visual--<?= e($aKey) ?>"><span></span></span>
                <span class="anim-name"><?= e($aVal[0]) ?></span>
                <span class="anim-desc"><?= e($aVal[1]) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="theme_hero_anim" id="theme_hero_anim" value="<?= e(sv('theme_hero_anim', 'orbit')) ?>">

    <h3 class="sub-title">بک‌گراند هیرو (فیدشده)</h3>
    <div class="bg-grid" id="bgGrid">
        <?php foreach (hero_background_presets() as $bKey => $bVal): ?>
            <button type="button" class="bg-card<?= sv('theme_hero_bg', 'default') === $bKey ? ' is-active' : '' ?>" data-hero-bg="<?= e($bKey) ?>">
                <span class="bg-visual" style="background:<?= e($bVal[1]) ?>"></span>
                <span class="bg-name"><?= e($bVal[0]) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="theme_hero_bg" id="theme_hero_bg" value="<?= e(sv('theme_hero_bg', 'default')) ?>">

    <div class="theme-preview" id="themePreview">
        <span class="tp-chip" id="tpChip">پیش‌نمایش دکمه</span>
        <span class="tp-card" id="tpCard">کارت</span>
    </div>

    <div class="form-row">
        <label>رنگ اصلی برند
            <span class="color-field">
                <input type="color" value="<?= e(sv('theme_accent', '#0f228c')) ?>" data-color-for="theme_accent">
                <input type="text" name="theme_accent" id="theme_accent" value="<?= e(sv('theme_accent', '#0f228c')) ?>" dir="ltr" maxlength="7">
            </span>
        </label>
        <label>رنگ اصلی (حالت هاور / تیره)
            <span class="color-field">
                <input type="color" value="<?= e(sv('theme_accent_dark', '#0a1a6b')) ?>" data-color-for="theme_accent_dark">
                <input type="text" name="theme_accent_dark" id="theme_accent_dark" value="<?= e(sv('theme_accent_dark', '#0a1a6b')) ?>" dir="ltr" maxlength="7">
            </span>
        </label>
    </div>
    <div class="form-row">
        <label>رنگ سرمه‌ای هدر و فوتر
            <span class="color-field">
                <input type="color" value="<?= e(sv('theme_navy', '#06163a')) ?>" data-color-for="theme_navy">
                <input type="text" name="theme_navy" id="theme_navy" value="<?= e(sv('theme_navy', '#06163a')) ?>" dir="ltr" maxlength="7">
            </span>
        </label>
        <label>گردی گوشه‌ها (۰ تا ۲۸ پیکسل)
            <span class="radius-field">
                <input type="range" min="0" max="28" step="1" value="<?= e(sv('theme_radius', '12')) ?>" data-radius-range>
                <input type="number" name="theme_radius" id="theme_radius" min="0" max="28" step="1" value="<?= e(sv('theme_radius', '12')) ?>" dir="ltr">
            </span>
        </label>
    </div>

<?php elseif ($active === 'homepage'): ?>
    <h2>متن‌های صفحهٔ اصلی</h2>
    <p class="muted">اگر این فیلدها را خالی بگذارید، مقادیر پیش‌فرض نمایش داده می‌شود.</p>

    <h3 class="sub-title">بخش هیرو (بالای صفحه)</h3>
    <label>تصویر پس‌زمینهٔ هیرو (اختیاری — بزرگ به ۱۹۲۰×۱۰۸۰ تغییر اندازه می‌یابد)
        <?php if (sv('home_hero_image') !== ''): ?>
            <span class="logo-preview"><img src="<?= e(product_image_url(sv('home_hero_image'))) ?>" alt="پیش‌نمایش هیرو"></span>
        <?php endif; ?>
        <input type="file" name="home_hero_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <input type="text" name="home_hero_image" value="<?= e(sv('home_hero_image')) ?>" dir="ltr" placeholder="یا آدرس مستقیم تصویر">
    </label>
    <div class="form-row">
        <label>متن کوچک بالای عنوان
            <input type="text" name="home_hero_eyebrow" value="<?= e(sv('home_hero_eyebrow', 'ابزار دقیق صنعتی')) ?>">
        </label>
        <label>عنوان اصلی (اگر خالی باشد نام فروشگاه)
            <input type="text" name="home_hero_title" value="<?= e(sv('home_hero_title')) ?>" placeholder="<?= e(sv('store_name')) ?>">
        </label>
    </div>
    <label>تگ‌لاین زیر عنوان
        <input type="text" name="home_hero_tagline" value="<?= e(sv('home_hero_tagline')) ?>" placeholder="<?= e(sv('store_tagline')) ?>">
    </label>
    <div class="form-row">
        <label>متن راهنمای جستجو (placeholder)
            <input type="text" name="home_search_placeholder" value="<?= e(sv('home_search_placeholder', 'جستجوی ابزار…')) ?>">
        </label>
    </div>
    <div class="form-row">
        <label>دکمهٔ اصلی
            <input type="text" name="home_cta_primary_label" value="<?= e(sv('home_cta_primary_label', 'ورود به فروشگاه')) ?>">
        </label>
        <label>مقصد دکمهٔ اصلی (آدرس صفحه)
            <input type="text" name="home_cta_primary_url" value="<?= e(sv('home_cta_primary_url', 'shop.php')) ?>" dir="ltr" placeholder="مثلاً shop.php یا product.php?id=1">
        </label>
    </div>
    <div class="form-row">
        <label>دکمهٔ دوم
            <input type="text" name="home_cta_secondary_label" value="<?= e(sv('home_cta_secondary_label', 'دربارهٔ شرکت')) ?>">
        </label>
        <label>مقصد دکمهٔ دوم (آدرس صفحه)
            <input type="text" name="home_cta_secondary_url" value="<?= e(sv('home_cta_secondary_url', 'about.php')) ?>" dir="ltr" placeholder="مثلاً about.php">
        </label>
    </div>

    <h3 class="sub-title">نمایش بخش‌های صفحهٔ اصلی</h3>
    <p class="muted">هر بخش را که تیکش را بردارید، از صفحهٔ اصلی حذف می‌شود.</p>
    <div class="checkbox-grid">
        <label class="check-item"><input type="checkbox" name="home_show_eyebrow" value="1" <?= setting_bool('home_show_eyebrow', true) ? 'checked' : '' ?>> متن کوچک بالای عنوان (هیرو)</label>
        <label class="check-item"><input type="checkbox" name="home_show_tagline" value="1" <?= setting_bool('home_show_tagline', true) ? 'checked' : '' ?>> تگلاین زیر عنوان (هیرو)</label>
        <label class="check-item"><input type="checkbox" name="home_show_cta" value="1" <?= setting_bool('home_show_cta', true) ? 'checked' : '' ?>> دکمه‌های هیرو</label>
        <label class="check-item"><input type="checkbox" name="home_show_stats" value="1" <?= setting_bool('home_show_stats', true) ? 'checked' : '' ?>> آمار هیرو (محصول فعال / دسته‌بندی)</label>
        <label class="check-item"><input type="checkbox" name="home_show_featured" value="1" <?= setting_bool('home_show_featured', true) ? 'checked' : '' ?>> بخش محصولات منتخب</label>
        <label class="check-item"><input type="checkbox" name="home_show_categories" value="1" <?= setting_bool('home_show_categories', true) ? 'checked' : '' ?>> بخش دسته‌بندی‌ها</label>
        <label class="check-item"><input type="checkbox" name="home_show_about" value="1" <?= setting_bool('home_show_about', true) ? 'checked' : '' ?>> بخش معرفی شرکت (سه دهه اعتماد)</label>
        <label class="check-item"><input type="checkbox" name="reviews_enabled" value="1" <?= setting_bool('reviews_enabled', true) ? 'checked' : '' ?>> اجازهٔ ثبت نظر برای محصولات</label>
    </div>

    <h3 class="sub-title">سرفصل بخش‌ها</h3>
    <div class="form-row">
        <label>عنوان «محصولات منتخب»
            <input type="text" name="home_featured_title" value="<?= e(sv('home_featured_title', 'محصولات منتخب')) ?>">
        </label>
        <label>زیرعنوان محصولات منتخب
            <input type="text" name="home_featured_sub" value="<?= e(sv('home_featured_sub', 'پرفروش‌ترین و باکیفیت‌ترین ابزارها برای کارگاه و صنعت شما')) ?>">
        </label>
    </div>
    <div class="form-row">
        <label>عنوان دسته‌بندی‌ها
            <input type="text" name="home_categories_title" value="<?= e(sv('home_categories_title', 'بر اساس نیازتان مرور کنید')) ?>">
        </label>
        <label>تراز عنوان دسته‌بندی‌ها
            <select name="home_categories_align">
                <option value="center" <?= sv('home_categories_align', 'center') === 'center' ? 'selected' : '' ?>>وسط‌چین</option>
                <option value="right" <?= sv('home_categories_align') === 'right' ? 'selected' : '' ?>>راست‌چین</option>
                <option value="left" <?= sv('home_categories_align') === 'left' ? 'selected' : '' ?>>چپ‌چین</option>
            </select>
        </label>
    </div>
    <label>عنوان معرفی شرکت
            <input type="text" name="home_about_title" value="<?= e(sv('home_about_title', 'سه دهه اعتماد صنعت')) ?>">
        </label>
    <label>متن دکمهٔ معرفی شرکت
        <input type="text" name="home_about_cta_label" value="<?= e(sv('home_about_cta_label', 'بیشتر دربارهٔ ما')) ?>">
    </label>

    <h3 class="sub-title">چهار آمار صفحهٔ اصلی</h3>
    <div class="form-row">
        <label>آمار ۱ - عدد
            <input type="text" name="home_stat1_num" value="<?= e(sv('home_stat1_num', '۳۰+')) ?>">
        </label>
        <label>آمار ۱ - برچسب
            <input type="text" name="home_stat1_label" value="<?= e(sv('home_stat1_label', 'سال تجربه')) ?>">
        </label>
    </div>
    <div class="form-row">
        <label>آمار ۲ - عدد
            <input type="text" name="home_stat2_num" value="<?= e(sv('home_stat2_num', '۵۰+')) ?>">
        </label>
        <label>آمار ۲ - برچسب
            <input type="text" name="home_stat2_label" value="<?= e(sv('home_stat2_label', 'صنعت فعال')) ?>">
        </label>
    </div>
    <div class="form-row">
        <label>آمار ۳ - عدد
            <input type="text" name="home_stat3_num" value="<?= e(sv('home_stat3_num', 'ISO')) ?>">
        </label>
        <label>آمار ۳ - برچسب
            <input type="text" name="home_stat3_label" value="<?= e(sv('home_stat3_label', 'کیفیت استاندارد')) ?>">
        </label>
    </div>
    <div class="form-row">
        <label>آمار ۴ - عدد
            <input type="text" name="home_stat4_num" value="<?= e(sv('home_stat4_num', '۱۰۰٪')) ?>">
        </label>
        <label>آمار ۴ - برچسب
            <input type="text" name="home_stat4_label" value="<?= e(sv('home_stat4_label', 'ضمانت اصالت')) ?>">
        </label>
    </div>

<?php elseif ($active === 'loyalty'): ?>
    <h2>باشگاه مشتریان و امتیاز</h2>
    <div class="form-row">
        <label>هدیهٔ خوش‌آمدگویی عضویت (امتیاز)
            <input type="number" name="loyalty_welcome_bonus" min="0" max="100000" step="1" value="<?= e(sv('loyalty_welcome_bonus', '0')) ?>">
        </label>
        <label>مبلغ لازم برای هر ۱ امتیاز (تومان)
            <input type="number" name="loyalty_earn_rate" min="1" max="1000000" step="1" value="<?= e(sv('loyalty_earn_rate', '10000')) ?>">
        </label>
    </div>
    <p class="muted">مثلاً با نرخ ۱۰۰۰۰، هر سفارشِ پرداخت‌شدهٔ ۵۰٬۰۰۰ تومانی = ۵ امتیاز (هر امتیاز = ۱ تومان تخفیف). هدیهٔ عضویت صفر یعنی غیرفعال.</p>

    <h3 class="sub-title">کد معرف (دعوت دوستان)</h3>
    <div class="form-row">
        <label>امتیاز پاداش به معرف (پس از اولین خرید مهمان)
            <input type="number" name="referral_reward_points" min="0" max="1000000" step="1" value="<?= e(sv('referral_reward_points', '100')) ?>">
        </label>
        <label>امتیاز فوری مهمان پس از عضویت با کد
            <input type="number" name="referral_invitee_bonus" min="0" max="100000" step="1" value="<?= e(sv('referral_invitee_bonus', '0')) ?>">
        </label>
    </div>
    <p class="muted">هر مشتری یک کد اختصاصی می‌گیرد (در حساب کاربری نمایش داده می‌شود). پاداش معرف فقط یک‌بار و فقط پس از اولین خریدِ پرداخت‌شدهٔ مهمان داده می‌شود. صفر = غیرفعال.</p>
    <p class="muted">افزودن/کسر دستی امتیاز مشتریان و تاریخچه: <a href="loyalty.php">مدیریت باشگاه مشتریان</a></p>


<?php elseif ($active === 'telegram'): ?>
    <h2>ربات تلگرام</h2>
    <p class="muted">با تنظیم توکن ربات و شناسهٔ چت ادمین‌ها، رویدادهای سفارش/ثبت‌نام/چت به تلگرام ارسال می‌شود.</p>
    <label>توکن ربات (از BotFather)
        <input type="text" name="tg_bot_token" value="<?= e(sv('tg_bot_token', '')) ?>" dir="ltr" placeholder="123456789:AAH...">
    </label>
    <p class="muted">ابتدا در <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> یک ربات بسازید و توکن را اینجا قرار دهید.</p>
    <label>شناسهٔ چت ادمین‌ها (با کاما جدا کنید)
        <textarea name="tg_chat_admin_ids" rows="3" dir="ltr" placeholder="123456789, -1001234567890"><?= e(sv('tg_chat_admin_ids', '')) ?></textarea>
    </label>
    <p class="muted">هر ادمین باید ابتدا ربات را <code>/start</code> کرده و سپس دستور <code>/chatid</code> را بفرستد تا شناسه‌اش را به دست آورد.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-accent" type="submit" name="action" value="save">💾 ذخیره تنظیمات</button>
        <button class="btn btn-ghost" type="submit" name="action" value="tg_test">📨 ارسال پیام تست</button>
    </div>
    <h3 style="margin-top:24px">راهنما</h3>
    <ol class="muted" style="line-height:1.9">
        <li>در BotFather ربات بسازید: <code>/newbot</code></li>
        <li>توکن را در بالا وارد و ذخیره کنید.</li>
        <li>به آدرس زیر وبهوک را تنظیم کنید:<br>
            <code dir="ltr">https://api.telegram.org/bot&lt;TOKEN&gt;/setWebhook?url=https://abzar-shargh.ir/telegram_webhook.php</code>
        </li>
        <li>هر ادمین در تلگرام ربات را <code>/start</code> کرده و <code>/chatid</code> را می‌فرستد؛ سپس شناسه را در کادر بالا وارد کنید.</li>
        <li>از دکمهٔ «ارسال پیام تست» استفاده کنید تا مطمئن شوید پیام می‌رسد.</li>
    </ol>
<?php elseif ($active === 'sms'): ?>
    <?php $smsLineMode = sv('sms_line_mode', ((int)sv('sms_base_enabled', '0') === 1 ? 'shared' : 'dedicated')); ?>
    <h2>سامانهٔ پیامک — ملی‌پیامک</h2>
    <p class="muted">همهٔ پیامک‌های خودکار سایت (کد ورود، بازیابی رمز، پرداخت سفارش، موجودشدن کالا و امتیاز) از همین‌جا مدیریت می‌شود.</p>

    <div class="settings-card">
        <h3>🔑 حساب ملی‌پیامک</h3>
        <div class="settings-grid">
            <label>نام کاربری پنل
                <input type="text" name="sms_username" value="<?= e(sv('sms_username')) ?>" dir="ltr" autocomplete="off">
            </label>
            <label>رمز عبور وب‌سرویس
                <input type="password" name="sms_password" value="<?= e(sv('sms_password')) ?>" dir="ltr" autocomplete="off">
            </label>
        </div>
        <label>شمارهٔ فرستندهٔ خط اختصاصی (اختیاری)
            <input type="text" name="sms_sender" value="<?= e(sv('sms_sender')) ?>" dir="ltr" placeholder="مثلاً 5000xxxxx">
            <span class="hint">فقط در حالت «خط اختصاصی» استفاده می‌شود؛ خالی بماند = خط پیش‌فرض پنل.</span>
        </label>
    </div>

    <div class="settings-card">
        <h3>🚀 مسیر ارسال</h3>
        <div class="radio-cards">
            <label class="radio-card">
                <input type="radio" name="sms_line_mode" value="shared" <?= $smsLineMode === 'shared' ? 'checked' : '' ?>>
                <span class="rc-title">خط خدماتی اشتراکی</span>
                <span class="rc-desc">ارسال با الگوی تأییدشدهٔ پنل — حتی اگر مشتری خط عادی را بلاک کرده باشد می‌رسد. (توصیه‌شده)</span>
            </label>
            <label class="radio-card">
                <input type="radio" name="sms_line_mode" value="dedicated" <?= $smsLineMode === 'dedicated' ? 'checked' : '' ?>>
                <span class="rc-title">خط اختصاصی</span>
                <span class="rc-desc">ارسال عادی از شمارهٔ فرستندهٔ بالا یا خط پیش‌فرض پنل.</span>
            </label>
        </div>
        <span class="hint">اگر الگویی برای رویدادی ثبت نشده باشد یا ارسال از خط خدماتی خطا بدهد، همان پیامک به‌صورت خودکار از خط عادی ارسال می‌شود — هیچ پیامکی از دست نمی‌رود.</span>
    </div>

    <div class="settings-card">
        <h3>📝 کد الگوهای تأییدشده (bodyId)</h3>
        <p class="hint">الگوها را با راهنمای پایین در پنل ملی‌پیامک بسازید؛ بعد از تأیید، دکمهٔ زیر را بزنید تا سایت خودش کدها را از ملی‌پیامک بخواند و ثبت کند. ورود دستی لازم نیست.</p>
        <ul class="status-list">
            <?php foreach ([
                'otp_code' => 'کد ورود (OTP)',
                'password_reset' => 'بازیابی رمز عبور',
                'order_paid' => 'پرداخت موفق سفارش',
                'stock_back' => 'موجودشدن کالا',
                'welcome' => 'خوش‌آمد عضویت',
                'admin_new_order' => 'اطلاع سفارش جدید به ادمین',
                'admin_new_user' => 'اطلاع کاربر جدید به ادمین',
                'admin_new_chat' => 'اطلاع پیام چت به ادمین',
            ] as $ev => $evLbl): $bid = setting('sms_base_bodyid_' . $ev, ''); ?>
                <li><span><?= e($evLbl) ?></span><strong dir="ltr"><?= ($bid !== '' && (int)$bid > 0) ? '✔ ' . e($bid) : '— ثبت نشده' ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <button type="submit" name="action" value="sms_sync" class="btn btn-accent" style="margin-top:.8rem;">🔄 همگام‌سازی کد الگوها از ملی‌پیامک</button>
    </div>
    </div>

    <div class="settings-card">
        <h3>📋 راهنمای ثبت الگو در پنل ملی‌پیامک</h3>
        <p class="hint">متن هر الگو را در مسیر «توسعه‌دهندگان ← وبسرویس خدماتی (الگو)» ثبت کنید؛ پس از تأیید مدیر، کد آن را در جدول بالا وارد کنید.</p>

        <div class="tpl-item">
            <div class="tpl-head"><b>۱. کد ورود (OTP)</b><span class="muted">— ۱ متغیر ({0} = کد)</span><button type="button" class="copy-btn" data-copy="ابزارسازی شرق | کد ورود شما: {0}">کپی متن</button></div>
            <div class="tpl-box">ابزارسازی شرق | کد ورود شما: {0}</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۲. بازیابی رمز عبور</b><span class="muted">— ۱ متغیر ({0} = کد)</span><button type="button" class="copy-btn" data-copy="ابزارسازی شرق | کد بازیابی رمز عبور: {0}">کپی متن</button></div>
            <div class="tpl-box">ابزارسازی شرق | کد بازیابی رمز عبور: {0}</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۳. پرداخت موفق سفارش</b><span class="muted">— ۳ متغیر ({0} نام، {1} شماره سفارش، {2} امتیاز)</span><button type="button" class="copy-btn" data-copy="{0} عزیز؛ سفارش {1} شما پرداخت شد. امتیاز: {2}">کپی متن</button></div>
            <div class="tpl-box">{0} عزیز؛ سفارش {1} شما پرداخت شد. امتیاز: {2}</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۴. موجودشدن کالا</b><span class="muted">— ۱ متغیر ({0} نام محصول)</span><button type="button" class="copy-btn" data-copy="ابزارسازی شرق | محصول «{0}» دوباره موجود شد">کپی متن</button></div>
            <div class="tpl-box">ابزارسازی شرق | محصول «{0}» دوباره موجود شد</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۵. خوش‌آمد عضویت</b><span class="muted">— ۲ متغیر ({0} نام، {1} امتیاز هدیه)</span><button type="button" class="copy-btn" data-copy="{0} عزیز؛ به ابزارسازی شرق خوش آمدید. امتیاز هدیه: {1}">کپی متن</button></div>
            <div class="tpl-box">{0} عزیز؛ به ابزارسازی شرق خوش آمدید. امتیاز هدیه: {1}</div>
        </div>

        <h3 style="margin-top:18px;">📨 الگوهای اطلاع‌رسانی مدیران</h3>
        <p class="hint">این سه الگو برای شماره‌های ادمین‌ها است. در پنل ملی‌پیامک ثبتشان کنید؛ پس از تأیید، دکمهٔ همگام‌سازی یا ورود دستی کد، ارسال را به خط خدماتی منتقل می‌کند (تا آن زمان از خط عادی ارسال می‌شود).</p>
        <div class="tpl-item">
            <div class="tpl-head"><b>۶. سفارش جدید (به ادمین)</b><span class="muted">— ۴ متغیر ({0} شماره سفارش، {1} مشتری، {2} اقلام، {3} مبلغ)</span><button type="button" class="copy-btn" data-copy="سفارش جدید {0} - مشتری: {1} - اقلام: {2} - مبلغ: {3} تومان">کپی متن</button></div>
            <div class="tpl-box">سفارش جدید {0} - مشتری: {1} - اقلام: {2} - مبلغ: {3} تومان</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۷. کاربر جدید (به ادمین)</b><span class="muted">— ۳ متغیر ({0} نام، {1} نام کاربری، {2} موبایل)</span><button type="button" class="copy-btn" data-copy="کاربر جدید ثبت‌نام کرد: {0} - کاربری: {1} - موبایل: {2}">کپی متن</button></div>
            <div class="tpl-box">کاربر جدید ثبت‌نام کرد: {0} - کاربری: {1} - موبایل: {2}</div>
        </div>
        <div class="tpl-item">
            <div class="tpl-head"><b>۸. پیام چت جدید (به ادمین)</b><span class="muted">— ۲ متغیر ({0} نام فرستنده، {1} متن پیام)</span><button type="button" class="copy-btn" data-copy="پیام چت از {0}: {1} - پاسخ در پنل ابزارسازی شرق">کپی متن</button></div>
            <div class="tpl-box">پیام چت از {0}: {1} - پاسخ در پنل ابزارسازی شرق</div>
        </div>

        <div class="warn-box">
            <b>⚠️ نکات مهم هنگام ثبت</b>
            <ul>
                <li>متن الگو را دقیقاً همان‌طور که هست کپی کنید — تعداد و ترتیب {0} و {1} و {2} باید همین باشد.</li>
                <li>داخل متن الگو لینک نگذارید؛ سامانهٔ خط خدماتی لینک را رد می‌کند.</li>
                <li>متن‌ها زیر ۷۰ کاراکتر طراحی شده‌اند تا پیامک تک‌قسمتی بماند و هزینهٔ کمتری داشته باشد.</li>
                <li>الگوی «خوش‌آمد» فقط وقتی استفاده می‌شود که امتیاز خوش‌آمدگویی در باشگاه مشتریان فعال باشد.</li>
            </ul>
        </div>

        <script>
        (function () {
            function fallbackCopy(t) {
                var ta = document.createElement('textarea');
                ta.value = t; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
            }
            document.querySelectorAll('.copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var txt = btn.getAttribute('data-copy') || '';
                    var done = function () {
                        var old = btn.textContent;
                        btn.textContent = 'کپی شد ✓';
                        btn.classList.add('copied');
                        setTimeout(function () { btn.textContent = old; btn.classList.remove('copied'); }, 1600);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(txt).then(done).catch(function () { fallbackCopy(txt); done(); });
                    } else { fallbackCopy(txt); done(); }
                });
            });
        })();
        </script>
    </div>

    <p class="muted settings-foot">پیامک‌های خودکار: خوش‌آمد عضویت، پرداخت موفق سفارش (با اعلام امتیاز) و تغییر دستی امتیاز — ارسال دستی گروهی/تکی: <a href="sms.php">پنل پیامک</a></p>

    <h2>شمارههای اطلاع‌رسانی</h2>
    <label>شماره‌های موبایل ادمین‌ها (برای پیامک سفارش جدید و پیام چت)
        <input type="text" name="notify_admin_phones" dir="ltr" value="<?= e(sv('notify_admin_phones', '09124045217')) ?>" placeholder="09124045217,09121112233">
    </label>
    <p class="muted">چند شماره را با کاما جدا کنید. با ثبت سفارش جدید یا اولین پیام چت مشتری، به این شماره‌ها پیامک می‌رود.</p>
<?php elseif ($active === 'email'): ?>
    <?php $emailLog = email_log_recent(10); ?>
    <h2>تنظیمات ایمیل</h2>
    <p class="muted">ایمیل‌های خودکار سایت (کد ورود، بازیابی رمز، موجودشدن کالا) از همین‌جا ارسال می‌شود. برای ورود به اینباکس (به‌جای اسپم) SMTP را تنظیم و انتخاب کنید.</p>

    <div class="settings-card">
        <h3>✉ روش ارسال و فرستنده</h3>
        <div class="radio-cards">
            <label class="radio-card">
                <input type="radio" name="email_provider" value="mail" <?= sv('email_provider', 'mail') === 'mail' ? 'checked' : '' ?>>
                <span class="rc-title">mail() سرور</span>
                <span class="rc-desc">پیش‌فرض ساده؛ مناسب شروع، اما احتمال رفتن به اسپم بیشتر است.</span>
            </label>
            <label class="radio-card">
                <input type="radio" name="email_provider" value="smtp" <?= sv('email_provider', 'mail') === 'smtp' ? 'checked' : '' ?>>
                <span class="rc-title">SMTP (توصیه‌شده)</span>
                <span class="rc-desc">ارسال از یک سرویس ایمیل واقعی؛ تحویل‌پذیری بهتر.</span>
            </label>
        </div>
        <div class="settings-grid" style="margin-top:.8rem;">
            <label>ایمیل فرستنده (From)
                <input type="text" name="email_from_email" value="<?= e(sv('email_from_email')) ?>" dir="ltr" placeholder="no-reply@abzar-shargh.ir">
            </label>
            <label>نام فرستنده
                <input type="text" name="email_from_name" value="<?= e(sv('email_from_name')) ?>" placeholder="مثلاً ابزارسازی شرق">
            </label>
        </div>
    </div>

    <div class="settings-card">
        <h3>🖥 سرور SMTP</h3>
        <div class="settings-grid">
            <label>سرور (Host)
                <input type="text" name="smtp_host" value="<?= e(sv('smtp_host')) ?>" dir="ltr" placeholder="مثلاً mail.abzar-shargh.ir">
            </label>
            <label>پورت
                <input type="text" name="smtp_port" value="<?= e(sv('smtp_port')) ?>" dir="ltr" placeholder="465 یا 587">
            </label>
            <label>رمزنگاری
                <select name="smtp_secure">
                    <option value="ssl" <?= sv('smtp_secure', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL (پورت 465)</option>
                    <option value="tls" <?= sv('smtp_secure', 'tls') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (پورت 587)</option>
                    <option value="none" <?= sv('smtp_secure', 'tls') === 'none' ? 'selected' : '' ?>>بدون رمزنگاری (پورت 25)</option>
                </select>
            </label>
            <label>نام کاربری
                <input type="text" name="smtp_user" value="<?= e(sv('smtp_user')) ?>" dir="ltr" autocomplete="off">
            </label>
            <label>رمز عبور
                <input type="password" name="smtp_pass" value="" dir="ltr" autocomplete="new-password">
                <span class="hint">خالی بماند = رمز فعلی بدون تغییر می‌ماند.</span>
            </label>
        </div>
    </div>

    <div class="settings-card">
        <h3>🧪 ارسال ایمیل تست</h3>
        <div class="settings-grid">
            <label>ایمیل گیرندهٔ تست
                <input type="text" name="email_test_to" value="" dir="ltr" placeholder="you@example.com">
                <span class="hint">با دکمهٔ ذخیرهٔ پایین صفحه، تست ارسال می‌شود و نتیجهٔ واقعی نمایش داده می‌شود.</span>
            </label>
        </div>
    </div>

    <?php if ($emailLog): ?>
        <h3 style="font-size:1.05rem; margin:1.2rem 0 .4rem;">آخرین ایمیل‌های ارسالی</h3>
        <table class="data-table">
            <thead><tr><th>گیرنده</th><th>رویداد</th><th>موضوع</th><th>وضعیت</th><th>زمان</th></tr></thead>
            <tbody>
            <?php foreach ($emailLog as $lg): ?>
                <tr>
                    <td dir="ltr"><?= e($lg['to_addr']) ?></td>
                    <td><?= e(email_event_label($lg['event'])) ?></td>
                    <td><?= e(mb_substr((string)$lg['subject'], 0, 40)) ?></td>
                    <td><?= $lg['status'] === 'sent' ? '✅ ارسال شد' : '❌ ناموفق' ?> <span class="muted" dir="ltr"><?= e(mb_substr((string)$lg['provider_note'], 0, 60)) ?></span></td>
                    <td dir="ltr"><?= e($lg['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php elseif ($active === 'shipping'): ?>
    <h2>هزینهٔ ارسال و تخفیف</h2>
        <div class="field">
    <label>هزینهٔ ارسال تیپاکس (تومان) — پیش‌فرض</label>
    <input type="number" name="ship_cost_tipax" min="0" step="1000" value="<?= e(sv('ship_cost_tipax', '50000')) ?>">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0 0;font-weight:normal"><input type="checkbox" name="ship_enabled_tipax" value="1" <?= sv('ship_enabled_tipax', '1') === '1' ? 'checked' : '' ?>> این روش ارسال فعال باشد</label>
    <p class="muted">پست تیپاکس: مرسولهٔ ۱ تا ۳ روز کاری</p>
</div>
<div class="field">
    <label>هزینهٔ ارسال پست سفارشی (تومان)</label>
    <input type="number" name="ship_cost_post" min="0" step="1000" value="<?= e(sv('ship_cost_post', '80000')) ?>">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0 0;font-weight:normal"><input type="checkbox" name="ship_enabled_post" value="1" <?= sv('ship_enabled_post', '1') === '1' ? 'checked' : '' ?>> این روش ارسال فعال باشد</label>
    <p class="muted">پست سفارشی: مرسولهٔ ۴ تا ۷ روز کاری، ارزان‌تر از تیپاکس</p>
</div>
<div class="field">
    <label>هزینهٔ پیک فوری شهر تهران (تومان)</label>
    <input type="number" name="ship_cost_express" min="0" step="1000" value="<?= e(sv('ship_cost_express', '200000')) ?>">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0 0;font-weight:normal"><input type="checkbox" name="ship_enabled_express" value="1" <?= sv('ship_enabled_express', '1') === '1' ? 'checked' : '' ?>> این روش ارسال فعال باشد</label>
    <p class="muted">پیک فوری: همان روز یا فردا، فقط شهر تهران</p>
</div>
<div class="field">
    <label>هزینهٔ تحویل درب کارخانه (تومان)</label>
    <input type="number" name="ship_cost_pickup" min="0" step="1000" value="<?= e(sv('ship_cost_pickup', '0')) ?>">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0 0;font-weight:normal"><input type="checkbox" name="pickup_enabled" value="1" <?= sv('pickup_enabled', '1') === '1' ? 'checked' : '' ?>> امکان تحویل حضوری درب کارخانه فعال باشد</label>
    <p class="muted">تحویل حضوری از درب کارخانه — معمولاً ۰ تومان (رایگان)</p>
</div>

    <p class="muted">مثلاً عدد ۲۰۰۰۰۰ یعنی برای کل سفارش (مستقل از تعداد اقلام) ۲۰۰٬۰۰۰ تومان هزینهٔ ارسال گرفته می‌شود. صفر یعنی ارسال رایگان.</p>
    
    <p class="muted">تخفیف روی همهٔ سفارش‌ها اعمال می‌شود (۰ تا ۱۰۰).</p>
    <label>توضیح ارسال (نمایش در صفحهٔ پرداخت)
        <textarea name="shipping_note" rows="2"><?= e(sv('shipping_note', 'ارسال به سراسر کشور.')) ?></textarea>
    </label>
    
    <p class="muted">چند شماره را با کاما جدا کنید. با ثبت سفارش جدید یا اولین پیام چت مشتری، به این شماره‌ها پیامک می‌رود.</p>

<?php elseif ($active === 'payment'): ?>
    <h2>درگاه پرداخت</h2>
    <label>درگاه فعال
        <select name="payment_gateway">
            <option value="zarinpal" <?= sv('payment_gateway', 'zarinpal') === 'zarinpal' ? 'selected' : '' ?>>زرین‌پال</option>
            <option value="bitpay" <?= sv('payment_gateway') === 'bitpay' ? 'selected' : '' ?>>بیت‌پی (bitpay.ir)</option>
        </select>
    </label>
    <p class="muted">درگاه انتخابی هنگام کلیک «پرداخت» توسط مشتری استفاده می‌شود.</p>

    <div id="zarinpal-box">
        <h3 class="sub-title">زرین‌پال</h3>
        <label>مرچنت‌کد (Merchant ID)
            <input type="text" name="merchant_id" value="<?= e(sv('merchant_id')) ?>" dir="ltr" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </label>
        <label>حالت پرداخت زرین‌پال
            <select name="payment_mode">
                <option value="mock" <?= sv('payment_mode', 'mock') === 'mock' ? 'selected' : '' ?>>شبیه‌سازی محلی (تست آفلاین)</option>
                <option value="sandbox" <?= sv('payment_mode') === 'sandbox' ? 'selected' : '' ?>>محیط آزمایشی زرین‌پال (Sandbox)</option>
                <option value="production" <?= sv('payment_mode') === 'production' ? 'selected' : '' ?>>پرداخت واقعی (Production)</option>
            </select>
        </label>
        <p class="muted">راهنما: برای تست واقعی، مرچنت‌کد sandbox را از زرین‌پال دریافت و حالت را روی «محیط آزمایشی» بگذارید. برای راه‌اندازی نهایی، مرچنت‌کد اصلی را وارد و حالت را روی «پرداخت واقعی» قرار دهید.</p>
    </div>

    <div id="bitpay-box">
        <h3 class="sub-title">بیت‌پی (bitpay.ir) — درگاه ریالی شاپرکی</h3>
        <label>کلید API
            <input type="text" name="bitpay_api" value="<?= e(sv('bitpay_api')) ?>" dir="ltr" placeholder="کلید API از پنل بیت‌پی">
        </label>
        <label>محیط
            <select name="bitpay_env">
                <option value="test" <?= sv('bitpay_env', 'test') === 'test' ? 'selected' : '' ?>>آزمایشی (payment-test)</option>
                <option value="production" <?= sv('bitpay_env') === 'production' ? 'selected' : '' ?>>واقعی (پرداخت قطعی)</option>
            </select>
        </label>
        <p class="muted">
            مبلغ سفارش به تومان ذخیره و هنگام ارسال به‌صورت خودکار به «ریال» (×۱۰) تبدیل می‌شود.
            پس از پرداخت، کاربر با trans_id و id_get به سایت برمی‌گردد و تراکنش به‌صورت خودکار تأیید و سفارش «پرداخت‌شده» می‌شود.
            کلید API را از پنل بیت‌پی (bitpay.ir) دریافت کنید.
        </p>
    </div>
<?php elseif ($active === 'analytics'): ?>
    <h2>گوگل آنالیز، بینگ و ابزارهای وبمستر</h2>
    <p class="muted">کدهای رهگیری را وارد کنید؛ بلافاصله در همهٔ صفحات سایت (قبل از &lt;/head&gt;) قرار می‌گیرند.</p>

    <h3 class="sub-title">Google Analytics 4</h3>
    <label>شناسهٔ اندازه‌گیری (Measurement ID)
        <input type="text" name="ga_measurement_id" value="<?= e(sv('ga_measurement_id')) ?>" dir="ltr" placeholder="G-XXXXXXXXXX">
    </label>

    <h3 class="sub-title">Google Tag Manager (اختیاری)</h3>
    <label>شناسهٔ کانتینر GTM
        <input type="text" name="gtm_container_id" value="<?= e(sv('gtm_container_id')) ?>" dir="ltr" placeholder="GTM-XXXXXXX">
    </label>

    <h3 class="sub-title">Google Ads (اختیاری)</h3>
    <label>شناسهٔ تبدیل Ads
        <input type="text" name="google_ads_id" value="<?= e(sv('google_ads_id')) ?>" dir="ltr" placeholder="AW-XXXXXXXXX">
    </label>

    <h3 class="sub-title">تأیید مالکیت (Webmaster Tools)</h3>
    <div class="form-row">
        <label>کد تأیید Google Search Console
            <input type="text" name="google_site_verification" value="<?= e(sv('google_site_verification')) ?>" dir="ltr" placeholder="مقدار content متا تگ">
        </label>
        <label>کد تأیید Bing Webmaster
            <input type="text" name="bing_verification" value="<?= e(sv('bing_verification')) ?>" dir="ltr" placeholder="مقدار content متا تگ">
        </label>
    </div>
    <p class="muted">نقشهٔ سایت: آدرس <strong><?= e(BASE_URL) ?>/sitemap.php</strong> را در Google Search Console و Bing Webmaster Tools ثبت کنید.</p>
<?php elseif ($active === 'ai'): ?>
    <h2>هوش مصنوعی (دستیار محتوا)</h2>
    <p class="muted">اتصال به یک API سازگار با OpenAI برای تولید متن (توضیح محصول، مقاله، محتوای آموزشی، گزارش). می‌توانید از OpenAI یا سرویس‌های ایرانی سازگار (مثل API سازگار) استفاده کنید.</p>
    <label>نام سرویس (فقط برای نمایش)
        <input type="text" name="ai_provider" value="<?= e(sv('ai_provider')) ?>" placeholder="مثلاً OpenAI / سرویس ایرانی">
    </label>
    <label>کلید API
        <input type="password" name="ai_api_key" value="<?= e(sv('ai_api_key')) ?>" dir="ltr" autocomplete="off">
    </label>
    <div class="form-row">
        <label>آدرس پایهٔ API (Base URL)
            <input type="text" name="ai_base_url" value="<?= e(sv('ai_base_url', 'https://api.openai.com/v1')) ?>" dir="ltr" placeholder="https://api.openai.com/v1">
        </label>
        <label>مدل
            <input type="text" name="ai_model" value="<?= e(sv('ai_model', 'gpt-4o-mini')) ?>" dir="ltr" placeholder="gpt-4o-mini">
        </label>
    </div>
    <p class="muted">بعد از ذخیره، از منوی پنل ← <a href="ai_assistant.php">دستیار هوش مصنوعی</a> استفاده کنید.</p>
    <div class="form-row" style="margin-top:1rem;">
        <button type="submit" name="action" value="test_ai" class="btn btn-accent">تست اتصال</button>
        <button type="submit" name="action" value="fetch_models" class="btn btn-ghost">دریافت لیست مدل‌ها</button>
    </div>
    <?php if (!empty($aiTestResult)): ?>
        <div class="alert <?= $aiTestResult['ok'] ? 'alert-success' : 'alert-error' ?>" style="margin-top:1rem;">
            <?= e($aiTestResult['ok'] ? 'اتصال برقرار است: ' . mb_substr($aiTestResult['text'], 0, 100) : $aiTestResult['error']) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($aiModelList)): ?>
        <div class="backup-box" style="margin-top:1rem;">
            <h3 style="font-size:1rem; margin-bottom:.5rem;">مدل‌های موجود (<?= count($aiModelList) ?>)</h3>
            <p class="muted" style="margin-bottom:.5rem;">نام مدل را در فیلد «مدل» بالا وارد کنید. مدل‌های رایگان: <?= e(implode('، ', array_filter($aiModelList, function ($m) { return stripos($m, 'free') !== false; }))) ?: '—' ?></p>
            <div style="max-height:180px; overflow-y:auto; font-size:.8rem; direction:ltr; text-align:left; line-height:1.7;"><?= e(implode("\n", $aiModelList)) ?></div>
        </div>
    <?php endif; ?>
<?php elseif ($active === 'backup'): ?>
    <h2>پشتیبان‌گیری و بازگردانی</h2>
    <p class="muted">یک نسخهٔ کامل از دیتابیس (محصولات، سفارش‌ها، کاربران، تنظیمات و…) بگیرید یا از یک بکاپ قبلی بازگردانی کنید.</p>

    <div class="backup-box">
        <h3 style="font-size:1.05rem; margin-bottom:.6rem;">دانلود بکاپ</h3>
        <p class="muted" style="margin-bottom:.8rem;">یک فایل با فرمت مخصوص این فروشگاه دانلود می‌شود. آن را در جای امن نگه دارید.</p>
        <button type="submit" name="action" value="download" class="btn btn-accent">⬇ دانلود بکاپ کامل</button>
    </div>

    <div class="backup-box" style="margin-top:1.2rem;">
        <h3 style="font-size:1.05rem; margin-bottom:.6rem;">بازگردانی بکاپ</h3>
        <p class="muted" style="margin-bottom:.8rem;">فایل بکاپ را انتخاب کنید. <strong>توجه:</strong> بازگردانی، داده‌های فعلی را کاملاً جایگزین می‌کند.</p>
        <label>فایل بکاپ
            <input type="file" name="backup_file" accept=".sql,.sqlite,.bak,application/octet-stream">
        </label>
        <button type="submit" name="action" value="restore" class="btn btn-danger" onclick="return confirm('همهٔ داده‌های فعلی با بکاپ جایگزین می‌شوند. ادامه می‌دهید؟')">بازگردانی از فایل</button>
    </div>
<?php endif; ?>

    <button type="submit" class="btn btn-accent">ذخیرهٔ «<?= e($tabs[$active]) ?>»</button>
</form>

<?php if ($active === 'theme'): ?>
<script>
(function () {
    function hex(v) {
        v = (v || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(v) || /^#[0-9a-fA-F]{3}$/.test(v) ? v : null;
    }
    function applyPreview() {
        var accent = hex(document.getElementById('theme_accent').value) || '#0f228c';
        var navy = hex(document.getElementById('theme_navy').value) || '#06163a';
        var r = Math.max(0, Math.min(28, parseInt(document.getElementById('theme_radius').value, 10) || 0));
        var pv = document.getElementById('themePreview');
        pv.style.setProperty('--accent', accent);
        pv.style.setProperty('--navy', navy);
        pv.style.setProperty('--radius', r + 'px');
    }
    document.querySelectorAll('[data-color-for]').forEach(function (picker) {
        var target = document.getElementById(picker.getAttribute('data-color-for'));
        picker.addEventListener('input', function () { target.value = picker.value; applyPreview(); });
        target.addEventListener('change', function () { if (hex(target.value)) picker.value = target.value; applyPreview(); });
    });
    var range = document.querySelector('[data-radius-range]');
    var num = document.getElementById('theme_radius');
    range.addEventListener('input', function () { num.value = range.value; applyPreview(); });
    num.addEventListener('change', function () { range.value = num.value; applyPreview(); });

    // کلیک روی یک تم آماده → پر کردن فیلدها + پیش‌نمایش
    document.querySelectorAll('[data-preset]').forEach(function (card) {
        card.addEventListener('click', function () {
            var accentEl = document.getElementById('theme_accent');
            var accentDarkEl = document.getElementById('theme_accent_dark');
            var navyEl = document.getElementById('theme_navy');
            var radiusEl = document.getElementById('theme_radius');
            accentEl.value = card.getAttribute('data-accent');
            accentDarkEl.value = card.getAttribute('data-accent-dark');
            navyEl.value = card.getAttribute('data-navy');
            radiusEl.value = card.getAttribute('data-radius');
            if (range) { range.value = card.getAttribute('data-radius'); }
            // همگام‌سازی picker های رنگی
            document.querySelectorAll('[data-color-for]').forEach(function (picker) {
                var targetId = picker.getAttribute('data-color-for');
                if (targetId === 'theme_accent' && hex(accentEl.value)) picker.value = accentEl.value;
                if (targetId === 'theme_accent_dark' && hex(accentDarkEl.value)) picker.value = accentDarkEl.value;
                if (targetId === 'theme_navy' && hex(navyEl.value)) picker.value = navyEl.value;
            });
            // هایلایت تم انتخاب‌شده
            document.querySelectorAll('.preset-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
            applyPreview();
        });
    });

    // کلیک روی یک مدل انیمیشن → پر کردن فیلد مخفی + هایلایت
    document.querySelectorAll('[data-anim]').forEach(function (card) {
        card.addEventListener('click', function () {
            var animInput = document.getElementById('theme_hero_anim');
            if (animInput) { animInput.value = card.getAttribute('data-anim'); }
            document.querySelectorAll('.anim-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
        });
    });

    // کلیک روی یک بک‌گراند → پر کردن فیلد مخفی + هایلایت
    document.querySelectorAll('[data-hero-bg]').forEach(function (card) {
        card.addEventListener('click', function () {
            var bgInput = document.getElementById('theme_hero_bg');
            if (bgInput) { bgInput.value = card.getAttribute('data-hero-bg'); }
            document.querySelectorAll('.bg-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
        });
    });

    applyPreview();
})();
</script>
<?php endif; ?>
<script>
// همگام‌سازی color picker در همهٔ تب‌ها (مثل رنگ فاکتور)
(function () {
    function hex(v) {
        v = (v || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(v) || /^#[0-9a-fA-F]{3}$/.test(v) ? v : null;
    }
    document.querySelectorAll('[data-color-for]').forEach(function (picker) {
        var target = document.getElementById(picker.getAttribute('data-color-for'));
        if (!target) { return; }
        picker.addEventListener('input', function () { target.value = picker.value; });
        target.addEventListener('change', function () { if (hex(target.value)) picker.value = target.value; });
    });
})();
</script>
<script>
// نمایش/پنهان بخش درگاه بر اساس درگاه فعال
(function () {
    var sel = document.querySelector('select[name="payment_gateway"]');
    var zb = document.getElementById('zarinpal-box');
    var bb = document.getElementById('bitpay-box');
    if (!sel || !zb || !bb) { return; }
    function sync() {
        var v = sel.value;
        zb.style.display = (v === 'zarinpal') ? '' : 'none';
        bb.style.display = (v === 'bitpay') ? '' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>


<?php require __DIR__ . '/_footer.php'; ?>
