<?php
/**
 * config.php — پیکربندی اصلی فروشگاه ابزارسازی شرق
 * ------------------------------------------------------------------
 * این فایل نقطهٔ ورود مشترک همهٔ صفحات است. مقادیر دیتابیس و درگاه
 * پرداخت اینجا تعریف می‌شوند. برای استقرار واقعی، فقط همین فایل و
 * تنظیمات داخل پنل مدیریت را ویرایش کنید.
 */

// ---- ثابت‌های سیستمی ----
define('LOG_VISITS', true);  // فعال‌سازی ثبت بازدیدها در لاگ

// ---- امنیت سشن: پرچم‌های امن کوکی -------
// فقط از طریق HTTP قابل دسترسی است (در برابر XSS/دسترسی JS محافظت می‌کند)
$GLOBALS['_cookie_secure'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// ---- جلوگیری از دسترسی مستقیم ----
if (session_status() === PHP_SESSION_NONE) {
    // HttpOnly: دسترسی جاوااسکریپت به کوکی غیرممکن می‌شود
    ini_set('session.cookie_httponly', '1');
    // SameSite=Lax: محافظت در برابر CSRF از سایت‌های ثالث
    ini_set('session.cookie_samesite', 'Lax');
    // Secure: فقط روی HTTPS ارسال شود
    ini_set('session.cookie_secure', $GLOBALS['_cookie_secure'] ? '1' : '0');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (bool)$GLOBALS['_cookie_secure'],
    ]);
    session_start();
}

// ---- آدرس پایهٔ سایت (برای ساخت URL بازگشت پرداخت) ----
function base_url()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    // پوشهٔ admin همیشه یک سطح زیر ریشهٔ پروژه است → پسوند /admin را حذف می‌کنیم
    // تا BASE_URL همواره به ریشهٔ پروژه اشاره کند (نه به /admin).
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    if ($dir === '' || $dir === '.') {
        $dir = '';
    }
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}
define('BASE_URL', rtrim(base_url(), '/'));

// ---- دیتابیس ----
// دو حالت پشتیبانی می‌شود:
//   sqlite  → بدون نیاز به سرور دیتابیس، فوراً اجرا می‌شود (برای تست محلی)
//   mysql   → برای هاست واقعی (cPanel / دایرکت‌ادمین)
define('DB_DRIVER', 'sqlite');

define('DB_SQLITE_PATH', __DIR__ . '/data/store.sqlite'); // برای sqlite

define('DB_HOST', 'localhost');   // برای mysql
define('DB_NAME', 'abzarsazi');   // برای mysql
define('DB_USER', 'root');        // برای mysql
define('DB_PASS', '');            // برای mysql
define('DB_CHARSET', 'utf8mb4');

// ---- فروشگاه ----
define('STORE_NAME', 'ابزارسازی شرق');
define('STORE_TAGLINE', 'ابزار دقیق، صنعت مطمئن');

// ---- سئو و موقعیت جغرافیایی (GEO) ----
define('SITE_LOCALE', 'fa_IR');          // زبان/منطقه برای og:locale
define('SITE_DEFAULT_IMAGE', '');        // تصویر پیش‌فرض اشتراک‌گذاری (og:image)؛ خالی = بدون تصویر
define('SITE_THEME_COLOR', '#c9a04a');   // رنگ تم برای theme-color و نوار مرورگر موبایل

// ---- درگاه پرداخت زرین‌پال ----
//   mock       → شبیه‌سازی محلی (بدون نیاز به اینترنت/مرچنت) برای تست کامل جریان پرداخت
//   sandbox    → تست واقعی روی محیط آزمایشی زرین‌پال
//   production → پرداخت واقعی
define('PAYMENT_MODE', 'mock');
define('ZARINPAL_MERCHANT', '');   // مرچنت‌کد را از پنل زرین‌پال وارد کنید
// از سال ۱۴۰۲ زرین‌پال مبالغ را به «تومان» می‌پذیرد.
define('AMOUNT_UNIT', 'toman');

// ---- بارگذاری هسته ----
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/sms.php';
require __DIR__ . '/includes/email.php';
require __DIR__ . '/includes/page_builder_render.php';
require __DIR__ . '/includes/payment_common.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/page_content.php';

// ---- محدودساز سراسری ضد شلیک درخواست (هر IP: حداکثر ۳۰۰ درخواست در دقیقه) ----
// در صورت عبور، تا پایان پنجرهٔ ۶۰ ثانیه‌ای پاسخ 429 می‌گیرد؛ localhost مستثنی است.
if (PHP_SAPI !== 'cli' && !ip_rate_limit('global', 300, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: text/plain; charset=utf-8');
    exit('تعداد درخواست‌های شما بیش از حد مجاز است؛ چند لحظه بعد دوباره تلاش کنید.');
}
