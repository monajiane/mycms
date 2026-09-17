<?php
/**
 * includes/functions.php — توابع کمکی مشترک
 */

/** خروجی امن متن (جلوگیری از XSS) */
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** تبدیل ارقام لاتین به فارسی برای نمایش یکدست RTL */
function fa_digits($str)
{
    return strtr((string)$str, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

/** خواندن همهٔ تنظیمات فروشگاه به‌صورت آرایهٔ کلید→مقدار */
function get_settings()
{
    static $cache = null;
    if ($cache !== null) {
        return array_merge($cache, $GLOBALS['_settings_override'] ?? []);
    }
    $defaults = [
        'store_name'     => STORE_NAME,
        'store_tagline'  => STORE_TAGLINE,
        'merchant_id'    => ZARINPAL_MERCHANT,
        'payment_mode'   => PAYMENT_MODE,
        'shipping_note'  => '',
        'notify_admin_phones' => '09124045217',
        'company_about'  => 'ابزارسازی شرق با بیش از سه دهه تجربه در تولید، تأمین و بازسازی ابزارآلات صنعتی، یکی از نام‌های معتبر در حوزهٔ ابزار دقیق کشور است. ما با تکیه بر دانش فنی، ماشین‌آلات پیشرفته و تیمی از متخصصان، ابزارهای استاندارد و سفارشی را برای صنایع فلزکاری، قالب‌سازی، ماشین‌کاری و تولید طراحی و عرضه می‌کنیم.',
        'company_address' => 'تهران، جاده قدیم کرج، شهرک صنعتی چهاردانگه، خیابان صنعت، پلاک ۱۲',
        'company_phone'   => '۰۲۱-۵۵۰۰۱۲۳۴',
        'company_email'   => 'info@abzarsazi-shargh.ir',
        'company_city'    => 'تهران',
        'company_region'  => 'IR-07',
        'company_lat'     => '35.6892',
        'company_lng'     => '51.3890',
        'company_hours'   => 'شنبه تا چهارشنبه ۸:۰۰ تا ۱۷:۰۰',
    ];
    $cache = $defaults;
    try {
        $rows = db()->query("SELECT key, value FROM settings")->fetchAll();
        foreach ($rows as $r) {
            $cache[$r['key']] = $r['value'];
        }
    } catch (Throwable $t) {
        // جدول هنوز ساخته نشده؛ از پیش‌فرض‌ها استفاده می‌کنیم
    }
    return array_merge($cache, $GLOBALS['_settings_override'] ?? []);
}

/** ابطال کش تنظیمات: کش استاتیک get_settings را پاک می‌کند */
function get_settings_fresh()
{
    clear_settings_cache();
}

/** پاک‌سازی کش استاتیک get_settings (برای دیدن فوری تغییرات در همان درخواست) */
function clear_settings_cache()
{
    // کش static داخل تابع از بیرون قابل دسترس نیست؛
    // به همین دلیل set_setting پس از هر تغییر، مقدار جدید را در یک لایهٔ override ذخیره می‌کنیم.
    // این تابع فعلاً no-op است و override layer در set_setting کار می‌کند.
}

/** خواندن تنظیمات بدون استفاده از کش */
function get_settings_uncached()
{
    $defaults = get_setting_defaults();
    try {
        $rows = db()->query("SELECT key, value FROM settings")->fetchAll();
        foreach ($rows as $r) {
            $defaults[$r['key']] = $r['value'];
        }
    } catch (Throwable $t) {
        // در صورت نبود جدول، پیش‌فرض‌ها برگردانده می‌شوند
    }
    return $defaults;
}

/** فقط پیش‌فرض‌های ثابت تنظیمات */
function get_setting_defaults()
{
    static $d = null;
    if ($d !== null) {
        return $d;
    }
    $d = [
        'store_name'     => STORE_NAME,
        'store_tagline'  => STORE_TAGLINE,
        'site_logo'      => '',
        'site_favicon'   => '',
        'home_hero_image' => '',
        'theme_hero_anim' => 'orbit',
        'theme_hero_bg'   => 'default',
        'home_cta_primary_url'   => 'shop.php',
        'home_cta_secondary_url' => 'about.php',
        'home_show_eyebrow'  => '1',
        'home_show_tagline'  => '1',
        'home_show_cta'      => '1',
        'home_show_stats'    => '1',
        'home_show_featured' => '1',
        'home_show_categories' => '1',
        'home_show_about'    => '1',
        'home_categories_align' => 'center',
        'reviews_enabled' => '1',
        'hide_prices_guests' => '0',
        'otp_login_enabled' => '0',
        'otp_channel' => 'sms',
        'order_tracking_enabled' => '1',
        'ga_measurement_id' => '',
        'gtm_container_id' => '',
        'bing_verification' => '',
        'google_site_verification' => '',
        'google_ads_id' => '',
        'merchant_id'    => ZARINPAL_MERCHANT,
        'payment_mode'   => PAYMENT_MODE,
        'shipping_note'  => '',
        'notify_admin_phones' => '09124045217',
        'company_city'   => 'تهران',
        'company_region' => 'IR-07',
        'company_lat'    => '35.6892',
        'company_lng'    => '51.3890',
        'loyalty_welcome_bonus' => '0',
        'loyalty_earn_rate'     => '10000',
        'sms_provider'          => 'log',
        'sms_api_key'           => '',
        'sms_sender'            => '',
        'sms_username'          => '',
        'sms_password'          => '',
        'sms_custom_url'        => '',
        'sms_custom_method'     => 'POST',
        'sms_custom_params'     => 'to={to}&message={message}&sender={sender}',
        'ai_provider'           => '',
        'ai_api_key'            => '',
        'ai_base_url'           => 'https://api.openai.com/v1',
        'ai_model'              => 'gpt-4o-mini',
        'enamad_image'          => '',
        'enamad_link'           => '',
        'enamad_code'           => '',
        'payment_gateway'       => 'zarinpal',
        'bitpay_api'            => '',
        'bitpay_env'            => 'test',
        'invoice_color'         => '#06163a',
        'invoice_note'          => 'با تشکر از خرید شما',
        'invoice_show_logo'     => '1',
    ];
    return $d;
}

/** خواندن یک تنظیم مشخص */
function setting($key, $default = '')
{
    $s = get_settings();
    return isset($s[$key]) ? $s[$key] : $default;
}

/**
 * خواندن یک تنظیم به‌صورت بولی (۰/۱).
 * مقادیر '1', 'on', 'true', 'yes' → true؛ بقیه false.
 */
function setting_bool($key, $default = true)
{
    $s = get_settings();
    if (!isset($s[$key]) || $s[$key] === '') {
        return (bool)$default;
    }
    return in_array(strtolower((string)$s[$key]), ['1', 'on', 'true', 'yes'], true);
}

/** رنگ برند به‌صورت آرایهٔ R/G/B (برای ساخت توکن‌های CSS داینامیک) */
function theme_hex_to_rgb($hex)
{
    $hex = trim((string)$hex);
    if (!preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $hex, $m)) {
        return null;
    }
    $h = $m[1];
    if (strlen($h) === 3) {
        $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    }
    return [
        'r' => hexdec(substr($h, 0, 2)),
        'g' => hexdec(substr($h, 2, 2)),
        'b' => hexdec(substr($h, 4, 2)),
    ];
}

/** ذخیرهٔ یک تنظیم (سازگار با sqlite و mysql) */
function set_setting($key, $value)
{
    $pdo = db();
    if (DB_DRIVER === 'mysql') {
        $st = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE value = VALUES(value)");
    } else {
        $st = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)
                             ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    }
    $st->execute([$key, $value]);
    // لایهٔ override درون-درخواستی: مقدار جدید بلافاصله توسط setting()/get_settings() دیده می‌شود
    $GLOBALS['_settings_override'][$key] = $value;
}

/** اعتبارسنجی و ذخیرهٔ تم (رنگ‌ها + گردی گوشه‌ها) با برگشت امن به پیش‌فرض */
function save_theme_settings(array $post)
{
    $defaults = [
        'theme_accent'       => '#0f228c',
        'theme_accent_dark'  => '#0a1a6b',
        'theme_navy'         => '#06163a',
        'theme_radius'       => '12',
        'theme_hero_anim'    => 'orbit',
        'theme_hero_bg'      => 'default',
    ];
    foreach ($defaults as $key => $def) {
        if ($key === 'theme_hero_anim') {
            $presets = hero_animation_presets();
            $val = isset($presets[$post[$key] ?? '']) ? ($post[$key] ?? 'orbit') : 'orbit';
            set_setting($key, $val);
            continue;
        }
        if ($key === 'theme_hero_bg') {
            $presets = hero_background_presets();
            $val = isset($presets[$post[$key] ?? '']) ? ($post[$key] ?? 'default') : 'default';
            set_setting($key, $val);
            continue;
        }
        $val = trim($post[$key] ?? $def);
        if ($key === 'theme_radius') {
            $val = (string)max(0, min(28, (int)$val));
        } elseif (!theme_hex_to_rgb($val)) {
            $val = $def; // مقدار نامعتبر → پیش‌فرض
        }
        set_setting($key, $val);
    }
}

/**
 * مدل‌های انیمیشن هیرو (انتخابی در پنل).
 * @return array  key => [نام فارسی، توضیح]
 */
function hero_animation_presets()
{
    return [
        'orbit'     => ['مداری (پیش‌فرض)', 'دایره‌های شناور دورانی + هالهٔ تپنده'],
        'particles' => ['ذرات شناور', 'ذرات ریز بالارونده مثل گردوغبار صنعتی'],
        'grid'      => ['شبکهٔ تپنده', 'نقاط شبکه‌ای با موج نور'],
        'scan'      => ['پرتو اسکن', 'خط نور افقی که بالا-پایین اسکن می‌کند'],
        'aurora'    => ['شفق قطبی', 'موج‌های نورانی رنگین‌کمانی آرام در پس‌زمینه'],
        'spotlight' => ['نورافکن', 'پرتو نور نرم که هیرو را جاروب می‌کند'],
        'bubbles'   => ['حباب‌های نوری', 'گوی‌های شفاف آرام بالا می‌آیند'],
        'glitch'    => ['گلیچ دیجیتال', 'تیتر با جرقه‌های رنگی دیجیتال می‌لرزد'],
        'wave'      => ['موج صنعتی', 'موج‌های نرم آبی در پایین هیرو حرکت می‌کنند'],
        'none'      => ['بدون انیمیشن', 'هیرو ساده و ایستا'],
    ];
}

/**
 * مدل‌های بک‌گراند فیدشده (گرادیان محو) هیرو.
 * هر مدل یک گرادیان CSS چندلایهٔ نرم و محو است.
 * @return array  key => [نام فارسی، گرادیان CSS]
 */
function hero_background_presets()
{
    return [
        'default' => [
            'سفید نرم (پیش‌فرض)',
            'radial-gradient(60% 80% at 50% 0%, rgba(15,34,140,.16), transparent 62%), radial-gradient(38% 55% at 82% 18%, rgba(15,34,140,.10), transparent 70%), linear-gradient(180deg, #ffffff, var(--bg))',
        ],
        'dawn' => [
            'طلوع آبی-بنفش',
            'radial-gradient(50% 70% at 20% 10%, rgba(124,58,237,.18), transparent 60%), radial-gradient(45% 60% at 85% 25%, rgba(15,34,140,.16), transparent 65%), linear-gradient(180deg, #eef2ff, #ffffff)',
        ],
        'mist' => [
            'مِه خاکستری',
            'radial-gradient(60% 80% at 50% 0%, rgba(51,65,85,.14), transparent 60%), radial-gradient(40% 55% at 75% 15%, rgba(100,116,139,.10), transparent 70%), linear-gradient(180deg, #f8fafc, #eef1f6)',
        ],
        'aurora' => [
            'شفق سبز-فیروزه‌ای',
            'radial-gradient(55% 70% at 15% 15%, rgba(13,148,136,.20), transparent 62%), radial-gradient(50% 65% at 85% 20%, rgba(15,118,110,.16), transparent 66%), linear-gradient(180deg, #ecfdf5, #ffffff)',
        ],
        'ember' => [
            'آتش کهربایی',
            'radial-gradient(55% 70% at 20% 10%, rgba(217,119,6,.20), transparent 62%), radial-gradient(45% 60% at 85% 25%, rgba(190,18,60,.14), transparent 65%), linear-gradient(180deg, #fff7ed, #ffffff)',
        ],
    ];
}

/**
 * پالت‌های رنگی آماده (تم) برای انتخاب سریع در پنل.
 * هر پالت شامل accent / accent-dark / navy / radius است.
 * @return array  key => [name, accent, accentDark, navy, radius]
 */
function theme_presets()
{
    return [
        'blue'    => ['آبی شرکتی (پیش‌فرض)', '#0f228c', '#0a1a6b', '#06163a', '12'],
        'emerald' => ['سبز زمردی',           '#0f766e', '#115e59', '#042f2e', '12'],
        'crimson' => ['قرمز یاقوتی',         '#be123c', '#9f1239', '#4c0519', '12'],
        'violet'  => ['بنفش سلطنتی',         '#7c3aed', '#6d28d9', '#2e1065', '12'],
        'amber'   => ['کهربایی (طلایی)',     '#d97706', '#b45309', '#451a03', '12'],
        'teal'    => ['فیروزه‌ای',           '#0d9488', '#0f766e', '#134e4a', '12'],
        'slate'   => ['خاکستری مدرن',        '#334155', '#1e293b', '#0f172a', '12'],
        'rose'    => ['گل‌بهی',              '#e11d48', '#be123c', '#4c0519', '12'],
    ];
}

/**
 * اعمال یک پالت از پیش‌تعریف‌شده در تنظیمات.
 * @param string $presetKey کلید پالت (از theme_presets)
 * @return bool
 */
function apply_theme_preset($presetKey)
{
    $presets = theme_presets();
    if (!isset($presets[$presetKey])) {
        return false;
    }
    [, $accent, $accentDark, $navy, $radius] = $presets[$presetKey];
    set_setting('theme_accent', $accent);
    set_setting('theme_accent_dark', $accentDark);
    set_setting('theme_navy', $navy);
    set_setting('theme_radius', (string)$radius);
    return true;
}

/** تولید بلاک <style> متغیرهای CSS از تنظیمات تم (خروجی: رشتهٔ استایل یا '' ) */
function theme_css_vars()
{
    $accent = setting('theme_accent', '');
    if (!theme_hex_to_rgb($accent)) {
        return ''; // تم سفارشی ثبت نشده؛ style.css پیش‌فرض استفاده می‌شود
    }
    $accentDark = setting('theme_accent_dark', '');
    $navy = setting('theme_navy', '');
    $radius = (int)setting('theme_radius', '12');
    $css = ':root{';
    if (theme_hex_to_rgb($accent)) {
        $css .= "--accent:{$accent};";
    }
    if (theme_hex_to_rgb($accentDark)) {
        $css .= "--accent-dark:{$accentDark};";
    }
    if (theme_hex_to_rgb($navy)) {
        $css .= "--navy:{$navy};";
    }
    $css .= "--radius:{$radius}px;";
    $css .= '--radius-sm:' . max(0, $radius - 4) . 'px;';
    $css .= '--radius-lg:' . ($radius + 4) . 'px;';
    $css .= '}';
    return $css;
}

/** قالب‌بندی قیمت به تومان */
function fmt_price($amount)
{
    return fa_digits(number_format((int)$amount, 0, '.', ',')) . ' تومان';
}

/** پیام فلش (یک‌باره) */
function flash($key, $msg)
{
    $_SESSION['flash'][$key] = $msg;
}
function get_flash($key)
{
    if (!empty($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}

/** اسلاگ فارسی/انگلیسی */
function make_slug($text)
{
    $text = trim($text);
    $text = preg_replace('/\s+/u', '-', $text);
    $text = preg_replace('/[^a-zA-Z0-9آ-ی\-]/u', '', $text);
    $text = trim($text, '-');
    return $text ?: 'item-' . time();
}

/** سبد خرید (session) */
function cart()
{
    if (empty($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}
function cart_add($product_id, $qty = 1, $variant_id = 0)
{
    $c = &$_SESSION['cart'];
    $product_id = (int)$product_id;
    $variant_id = (int)$variant_id;
    $p = get_product($product_id);
    if (!$p || !can_view_product($p)) {
        return false;
    }
    // اگر وارینت انتخاب شده، بررسی معتبربودن و موجودی آن
    if ($variant_id > 0) {
        $v = get_variant($variant_id);
        if (!$v || (int)$v['product_id'] !== $product_id || (int)$v['active'] !== 1) {
            return false;
        }
        if ((int)$v['stock'] <= 0) {
            return false;
        }
    } else {
        // بدون وارینت: موجودی خود محصول
        if ((int)$p['stock'] <= 0) {
            return false;
        }
    }
    $qty = max(1, (int)$qty);
    $key = cart_key($product_id, $variant_id);
    if (isset($c[$key])) {
        $c[$key] += $qty;
    } else {
        $c[$key] = $qty;
    }
    return true;
}

/** کلید یکتای هر قلم سبد = product_id یا product_id:variant_id */
function cart_key($product_id, $variant_id = 0)
{
    $product_id = (int)$product_id;
    $variant_id = (int)$variant_id;
    return $variant_id > 0 ? $product_id . ':' . $variant_id : (string)$product_id;
}

/** تجزیهٔ کلید سبد به [product_id, variant_id] */
function cart_key_parts($key)
{
    $parts = explode(':', (string)$key, 2);
    return [(int)$parts[0], isset($parts[1]) ? (int)$parts[1] : 0];
}

function cart_update($product_id, $qty, $variant_id = 0)
{
    $key = cart_key($product_id, $variant_id);
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        $_SESSION['cart'][$key] = (int)$qty;
    }
}
function cart_remove($product_id, $variant_id = 0)
{
    unset($_SESSION['cart'][cart_key($product_id, $variant_id)]);
}
function cart_count()
{
    return array_sum(array_map('intval', cart()));
}
function cart_items()
{
    if (empty($_SESSION['cart'])) {
        return [];
    }
    $items = [];
    foreach ($_SESSION['cart'] as $key => $qty) {
        $qty = max(0, (int)$qty);
        if ($qty <= 0) {
            continue;
        }
        [$pid, $vid] = cart_key_parts($key);
        $st = db()->prepare("SELECT p.* FROM products p WHERE p.id = ? AND p.active = 1 AND " . product_visibility_clause('p'));
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            continue;
        }
        $variant = null;
        $variantName = '';
        if ($vid > 0) {
            $variant = get_variant($vid);
            if (!$variant || (int)$variant['product_id'] !== $pid || (int)$variant['active'] !== 1) {
                continue;
            }
            $variantName = $variant['name'];
        }
        $price = price_final(variant_price($p, $variant)); // قیمت نهایی با تخفیف تکی + همگانی
        $stock = $variant ? (int)$variant['stock'] : (int)$p['stock'];
        $p['qty'] = $qty;
        $p['variant_id'] = $vid;
        $p['variant_name'] = $variantName;
        $p['price'] = $price; // قیمت نهایی (با وارینت)
        $p['stock'] = $stock; // موجودی نهایی (با وارینت)
        $p['line_total'] = $qty * $price;
        $p['cart_key'] = $key;
        $items[] = $p;
    }
    return $items;
}
function cart_total()
{
    $t = 0;
    foreach (cart_items() as $it) {
        $t += $it['line_total'];
    }
    return $t;
}

/* ===================== هزینهٔ ارسال و تخفیف ===================== */

/** نرخ ثابت ارسال به ازای هر سفارش (تومان). از تنظیمات خوانده می‌شود. */
function shipping_rate($method = null)
{
    if ($method === null || $method === '') {
        $method = 'tipax';
    }
    $method = (string)$method;
    // روش غیرفعال: هزینه صفر در نظر گرفته می‌شود (نباید انتخاب شود)
    if (!ship_method_enabled($method)) {
        return 0;
    }
    // برای تحویل درب کارخانه همیشه رایگان
    if ($method === 'pickup') {
        return 0;
    }
    // ابتدا مقدار اختصاصی این روش؛ در غیاب آن، به مقدار flat_rate قدیمی برگرد
    $specificKey = 'ship_cost_' . $method;
    $specific = setting($specificKey, '');
    if ($specific !== '' && $specific !== null) {
        return max(0, (int)$specific);
    }
    return max(0, (int)setting('shipping_flat_rate', 0));
}

/**
 * آیا روش ارسال فعال است؟
 * برای pickup از pickup_enabled قدیمی استفاده می‌کند؛
 * برای بقیه از کلید ship_enabled_<method>.
 */
function ship_method_enabled($method)
{
    $method = (string)$method;
    if ($method === '' || $method === 'default') {
        return true;
    }
    if ($method === 'pickup') {
        return ((int)setting('pickup_enabled', 1)) === 1;
    }
    return ((int)setting('ship_enabled_' . $method, 1)) === 1;
}

/**
 * لیست روش‌های ارسال فعال (برای استفاده در فیلتر validMethods).
 */
function enabled_ship_methods()
{
    $all = ['tipax', 'post', 'express', 'pickup'];
    $out = [];
    foreach ($all as $m) {
        if (ship_method_enabled($m)) $out[] = $m;
    }
    return $out ?: ['tipax'];
}

/** آیا گزینهٔ «تحویل درب کارخانه» فعال است؟ */
function pickup_enabled()
{
    return ((int)setting('pickup_enabled', 1)) === 1;
}

/** آدرس تحویل حضوری از تنظیمات. */
function pickup_address()
{
    return (string)setting('pickup_address', '');
}

/** ساعات کاری تحویل حضوری. */
function pickup_hours()
{
    return (string)setting('pickup_hours', '');
}

/**
 * هزینهٔ ارسال بر اساس روش انتخابی.
 * @param string|null $method یکی از pickup/tipax/post/express (null => نرخ پیش‌فرض).
 */
function shipping_for_method($method = null)
{
    if (!ship_method_enabled($method)) {
        return 0;
    }
    if ($method === 'pickup') {
        return 0;
    }
    return shipping_cost(null, $method);
}

/** برچسب فارسی روش ارسال. */
function shipping_method_label($method)
{
    $labels = [
        'tipax'   => 'پست تیپاکس',
        'post'    => 'پست پیشتاز',
        'express' => 'پیک فوری شهر تهران',
        'pickup'  => 'تحویل درب کارخانه (رایگان)',
    ];
    return $labels[$method] ?? ($method ?: 'پست تیپاکس');
}

/**
 * هزینهٔ ارسال سفارش — نرخ ثابت برای کل سبد (مستقل از تعداد اقلام).
 * @param int|null $itemCount برای سازگاری با فراخوانی‌های قبلی؛ نادیده گرفته می‌شود.
 */
function shipping_cost($itemCount = null, $method = null)
{
    $rate = shipping_rate($method);
    return $rate > 0 ? $rate : 0;
}

/**
 * درصد تخفیف سراسری فروشگاه (0 تا 100) از تنظیمات.
 */
function global_discount_percent()
{
    $p = (int)setting('global_discount', 0);
    return max(0, min(100, $p));
}

/**
 * محاسبهٔ تخفیف روی یک مبلغ (تومان) با یک درصد.
 */
function discount_amount_for($amount, $percent)
{
    $percent = max(0, min(100, (int)$percent));
    return (int)floor($amount * $percent / 100);
}

/**
 * یک کوپن تخفیف با کد آن را برمی‌گرداند (در صورت فعال و منقضی‌نشده).
 */
function get_coupon($code)
{
    $code = trim((string)$code);
    if ($code === '') {
        return null;
    }
    try {
        $st = db()->prepare("SELECT * FROM coupons WHERE code = ?");
        $st->execute([$code]);
        $c = $st->fetch();
    } catch (Throwable $t) {
        return null; // جدول کوپن هنوز ساخته نشده
    }
    if (!$c) {
        return null;
    }
    if ((int)$c['active'] !== 1) {
        return null;
    }
    // تاریخ شروع: پیش از این لحظه، کوپن هنوز معتبر نیست
    if (!empty($c['starts_at'])) {
        $ts = strtotime($c['starts_at']);
        if ($ts !== false && $ts > time()) {
            return null;
        }
    }
    if (!empty($c['expires_at'])) {
        $ts = strtotime($c['expires_at']);
        if ($ts !== false && $ts < time()) {
            return null;
        }
    }
    if (!empty($c['max_uses'])) {
        $used = (int)($c['used_count'] ?? 0);
        if ($used >= (int)$c['max_uses']) {
            return null;
        }
    }
    return $c;
}

/**
 * مبلغ تخفیف یک کوپن روی مبلغ مشخص.
 * - percent → درصدی از مبلغ
 * - fixed   → مبلغ ثابت (بیش از جمع اقلام نمی‌شود)
 */
function coupon_discount_amount($coupon, $amount)
{
    $amount = max(0, (int)$amount);
    if (!$coupon) {
        return 0;
    }
    if ($coupon['type'] === 'percent') {
        $d = discount_amount_for($amount, $coupon['value']);
    } else {
        $d = (int)$coupon['value'];
    }
    return min($d, $amount);
}

/**
 * تخفیف کوپن روی جمع اقلام.
 * تخفیف همگانی از قبل داخل قیمت هر قلم (price_final در cart_items) اعمال شده است؛
 * اینجا فقط کوپن محاسبه می‌شود تا دوباره‌اعمالی رخ ندهد.
 * @return array{ global:int, coupon:int, total:int }
 */
function apply_discount($subtotal)
{
    $subtotal = max(0, (int)$subtotal);

    $couponAmt = 0;
    $coupon = get_coupon($_SESSION['coupon_code'] ?? '');
    if ($coupon && !coupon_valid_for_order($coupon, $subtotal)) {
        $coupon = null; // حداقل خرید یا سقف هر کاربر رعایت نشده
    }
    if ($coupon) {
        $couponAmt = coupon_discount_amount($coupon, $subtotal);
    }

    return [
        'global' => 0,
        'coupon' => $couponAmt,
        'total'  => min($couponAmt, $subtotal),
    ];
}

/** کد کوپن فعال در سشن سبد خرید */
function active_coupon()
{
    return get_coupon($_SESSION['coupon_code'] ?? '');
}

/** آیا مبلغ سبد خرید به حداقل مبلغ لازم برای کوپن رسیده است؟ */
function coupon_min_order_ok($coupon, $subtotal)
{
    if (!$coupon) {
        return false;
    }
    $min = (int)($coupon['min_order_amount'] ?? 0);
    if ($min <= 0) {
        return true;
    }
    return (int)$subtotal >= $min;
}

/** تعداد دفعات استفادهٔ کوپن توسط یک کاربر (سفارش‌های لغوشده شمرده نمی‌شوند) */
function coupon_used_by_user($coupon, $userId = null)
{
    if (!$coupon) {
        return 0;
    }
    $userId = $userId !== null ? (int)$userId : customer_id();
    if ($userId <= 0) {
        return 0;
    }
    $st = db()->prepare("SELECT COUNT(*) FROM orders WHERE coupon_code = ? AND user_id = ? AND status != 'cancelled'");
    $st->execute([$coupon['code'], $userId]);
    return (int)$st->fetchColumn();
}

/** اعتبارسنجی کامل کوپن برای یک سفارش (حداقل خرید + سقف هر کاربر) */
function coupon_valid_for_order($coupon, $subtotal, $userId = null)
{
    if (!$coupon) {
        return false;
    }
    if (!coupon_min_order_ok($coupon, $subtotal)) {
        return false;
    }
    $limit = (int)($coupon['per_user_limit'] ?? 0);
    if ($limit > 0) {
        if (coupon_used_by_user($coupon, $userId) >= $limit) {
            return false;
        }
    }
    return true;
}

/** دسته‌بندی‌ها */
function get_categories()
{
    return db()->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active=1 AND " . product_visibility_clause('p') . ") AS cnt
                        FROM categories c ORDER BY c.name")->fetchAll();
}

/** محصول پیشنهادی/شاخص */
function get_product($id)
{
    $st = db()->prepare("SELECT p.*, c.name AS category_name FROM products p
                         LEFT JOIN categories c ON c.id = p.category_id
                         WHERE p.id = ?");
    $st->execute([(int)$id]);
    return $st->fetch();
}

/**
 * لیست وارینت‌های فعال یک محصول (مرتب بر اساس sort_order).
 * قیمت نهایی هر وارینت = قیمت پایه + price_delta (می‌تواند منفی باشد).
 */
function product_variants($productId)
{
    $st = db()->prepare("SELECT * FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort_order, id");
    $st->execute([(int)$productId]);
    $list = [];
    foreach ($st->fetchAll() as $v) {
        $list[] = $v;
    }
    return $list;
}

/** یک وارینت خاص با شناسه */
function get_variant($variantId)
{
    $st = db()->prepare("SELECT * FROM product_variants WHERE id = ?");
    $st->execute([(int)$variantId]);
    return $st->fetch() ?: null;
}

/** همهٔ وارینت‌های محصول (شامل غیرفعال‌ها — برای پنل ادمین) */
function product_variants_all($productId)
{
    $st = db()->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id");
    $st->execute([(int)$productId]);
    return $st->fetchAll();
}

/**
 * آیا محصول وارینت دارد؟ (وارینت‌های فعال)
 */
function product_has_variants($productId)
{
    return count(product_variants($productId)) > 0;
}

/**
 * قیمت نهایی یک وارینت = قیمت پایهٔ محصول + تغییر قیمت وارینت.
 * @param array $product محصول
 * @param array|null $variant وارینت (یا null برای قیمت پایه)
 */
function variant_price($product, $variant = null)
{
    $base = (int)$product['price'];
    // قیمت ویژهٔ محصول (تخفیف تکی) — فقط وقتی از قیمت اصلی کمتر باشد
    if (!empty($product['sale_price']) && (int)$product['sale_price'] > 0 && (int)$product['sale_price'] < $base) {
        $base = (int)$product['sale_price'];
    }
    if ($variant && isset($variant['price_delta'])) {
        return max(0, $base + (int)$variant['price_delta']);
    }
    return max(0, $base);
}

/**
 * قیمت نهایی واحد پس از تخفیف همگانی فروشگاه.
 * تخفیف همگانی از تنظیمات global_discount روی قیمت لیست اعمال می‌شود؛
 * همین تابع در سبد/تسویه‌حساب استفاده می‌شود تا نمایش و محاسبه همیشه یکسان باشد.
 */
function price_final($listPrice)
{
    $listPrice = max(0, (int)$listPrice);
    $gp = global_discount_percent();
    if ($gp > 0 && $listPrice > 0) {
        $listPrice = max(0, $listPrice - discount_amount_for($listPrice, $gp));
    }
    return $listPrice;
}

/**
 * قیمت اصلی محصول بدون اعمال تخفیف تکی (فقط قیمت پایه + دلتای وارینت).
 * برای نمایش قیمت خط‌خورده و محاسبهٔ درصد تخفیف واقعی.
 */
function product_original_price($product, $variant = null)
{
    $base = (int)$product['price'];
    if ($variant && isset($variant['price_delta'])) {
        return max(0, $base + (int)$variant['price_delta']);
    }
    return max(0, $base);
}

/**
 * اطلاعات قیمت یک محصول/وارینت برای نمایش.
 * @return array{original:int, list:int, final:int, percent:int, has_sale:bool}
 */
function price_display_info($product, $variant = null)
{
    $original = product_original_price($product, $variant);
    $list = variant_price($product, $variant);
    $final = price_final($list);
    $percent = ($original > 0 && $final < $original) ? (int)round((($original - $final) / $original) * 100) : 0;
    $hasSale = !empty($product['sale_price']) && (int)$product['sale_price'] > 0 && (int)$product['sale_price'] < (int)$product['price'];
    return ['original' => $original, 'list' => $list, 'final' => $final, 'percent' => $percent, 'has_sale' => $hasSale];
}

/**
 * HTML قیمت برای کارت محصول: قیمت خط‌خورده + قیمت نهایی + بج درصد تخفیف.
 */
function price_html_discounted($product, $variant = null)
{
    $info = price_display_info($product, $variant);
    $html = '';
    if ($info['percent'] > 0) {
        $html .= '<del class="price-old">' . e(fmt_price($info['original'])) . '</del> ';
    }
    $html .= '<span class="price-new">' . e(fmt_price($info['final'])) . '</span>';
    if ($info['percent'] > 0) {
        $html .= ' <span class="price-badge">' . e(fa_digits((string)$info['percent'])) . '٪ تخفیف</span>';
    }
    return $html;
}

/**
 * آدرس تصویر محصول: اگر آدرس کامل (http/https) باشد همان را برمی‌گرداند،
 * وگرنه مسیر نسبی فایل آپلودشده را به BASE_URL می‌چسباند.
 */
/** آیکون SVG ابزار برای placeholder تصویر محصول */
function product_placeholder_icon(string $name = ''): string
{
    $letter = mb_strtoupper(mb_substr(trim($name), 0, 1, 'UTF-8'), 'UTF-8');
    $icons = [
        'drill'  => '<path d="M6 18l4-8m2-3h7a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-4M9 9V5a1 1 0 0 1 1-1h5" stroke-width="1.6" stroke-linecap="round"/><circle cx="17" cy="10.5" r="1.2"/>',
        'wrench' => '<path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 0 0 5.6-6L14.6 12l-2.6-2.6 2.7-3.1z" stroke-width="1.6" stroke-linejoin="round"/>',
        'gauge'  => '<circle cx="12" cy="13" r="8" stroke-width="1.6"/><path d="M12 13l4-4M8 19h8" stroke-width="1.6" stroke-linecap="round"/>',
        'helmet' => '<path d="M4 16a8 8 0 0 1 16 0m-16 0h16m-14 0v2m12-2v2M9 8V6a3 3 0 0 1 6 0v2" stroke-width="1.6" stroke-linecap="round"/>',
        'gear'   => '<circle cx="12" cy="12" r="3.2" stroke-width="1.6"/><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6l2.1 2.1m0-12.8l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke-width="1.6" stroke-linecap="round"/>',
    ];
    // انتخاب آیکون بر اساس حرف اول (پخش ثابت و زیبا بین محصولات)
    $map = ['ا' => 'drill', 'د' => 'drill', 'س' => 'wrench', 'ک' => 'gauge', 'ع' => 'helmet', 'م' => 'gear', 'پ' => 'wrench', 'ت' => 'gauge', 'ف' => 'drill', 'ج' => 'wrench'];
    $first = mb_substr($letter, 0, 1, 'UTF-8');
    $key = $map[$first] ?? array_keys($icons)[abs(crc32($name ?: 'x')) % count($icons)];
    $paths = $icons[$key];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" role="img" aria-hidden="true">' . $paths . '</svg>';
}
function product_image_url($image)
{
    $image = trim((string)$image);
    if ($image === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $image) || strpos($image, 'data:') === 0) {
        return $image;
    }
    return BASE_URL . '/' . ltrim($image, '/');
}

/**
 * تغییر اندازهٔ تصویر با GD (در صورت موجود بودن).
 * اگر GD نبود، فایل اصلی برگردانده می‌شود (بدون resize).
 * @param string $src   مسیر فایل مبدأ (uploads/...)
 * @param int    $maxW  حداکثر عرض
 * @param int    $maxH  حداکثر ارتفاع
 * @return string|null  مسیر فایل خروجی نسبت به ریشه، یا null در خطا
 */
function resize_image($src, $maxW = 400, $maxH = 400)
{
    $root = dirname(__DIR__);
    $full = $root . '/' . ltrim($src, '/');
    if (!is_file($full) || !function_exists('imagecreatetruecolor')) {
        return null; // GD در دسترس نیست یا فایل موجود نیست
    }
    $info = @getimagesize($full);
    if (!$info) {
        return null;
    }
    [$w, $h, $type] = $info;
    if ($w <= 0 || $h <= 0) {
        return null;
    }
    $ratio = min($maxW / $w, $maxH / $h, 1.0); // هرگز بزرگ‌نمایی نمی‌کنیم
    if ($ratio >= 1.0) {
        return $src; // از قبل کوچک‌تر یا مساوی است
    }
    $newW = (int)round($w * $ratio);
    $newH = (int)round($h * $ratio);

    // عکس‌های بزرگ (مثلاً ۱۲ مگاپیکسل دوربین موبایل) هنگام decode در GD حافظهٔ زیادی می‌خواهند؛
    // موقتاً سقف حافظه را بالا می‌بریم تا Fatal «memory exhausted» رخ ندهد.
    $curLimit = trim((string)@ini_get('memory_limit'));
    if ($curLimit !== '-1' && $curLimit !== '') {
        $unit = strtolower(substr($curLimit, -1));
        $curBytes = (int)$curLimit * ($unit === 'g' ? 1073741824 : ($unit === 'm' ? 1048576 : ($unit === 'k' ? 1024 : 1)));
        if ($curBytes > 0 && $curBytes < 536870912) { // کمتر از 512M
            @ini_set('memory_limit', '512M');
        }
    }

    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($full); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($full);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($full);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($full); break;
        default: return null;
    }
    if (!$img) {
        return null;
    }
    $canvas = imagecreatetruecolor($newW, $newH);
    // حفظ شفافیت PNG/GIF/WebP
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $extByType = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $ext = $extByType[$type] ?? 'jpg';
    $outRel = 'uploads/' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_resized.' . $ext;
    $outFull = $root . '/' . $outRel;
    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = @imagejpeg($canvas, $outFull, 88); break;
        case IMAGETYPE_PNG:  $ok = @imagepng($canvas, $outFull, 8);  break;
        case IMAGETYPE_GIF:  $ok = @imagegif($canvas, $outFull);     break;
        case IMAGETYPE_WEBP: $ok = @imagewebp($canvas, $outFull, 88);break;
    }
    imagedestroy($canvas);
    imagedestroy($img);
    return $ok ? $outRel : null;
}

/**
 * پردازش فایل آپلودشدهٔ تصویر (لوگو/تصویر) + تغییر اندازه در صورت بزرگ بودن.
 * @param array  $file      همان $_FILES[...]
 * @param int    $maxW      حداکثر عرض
 * @param int    $maxH      حداکثر ارتفاع
 * @param int    $maxBytes  حداکثر حجم (بایت)
 * @return array            [ok:bool, path:string|null, error:string]
 */
function process_image_upload(array $file, $maxW = 400, $maxH = 400, $maxBytes = 5242880)
{
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, null, 'فایلی انتخاب نشده است.'];
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return [false, null, 'خطا در دریافت فایل (کد ' . (int)($file['error'] ?? 0) . ').'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return [false, null, 'حجم فایل بیش از حد مجاز (حداکثر ' . round($maxBytes / 1048576, 1) . ' مگابایت).'];
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($file['tmp_name']) ?: $file['type']) : $file['type'];
    if (!isset($allowed[$mime])) {
        return [false, null, 'فرمت تصویر مجاز نیست (فقط JPG/PNG/WebP/GIF).'];
    }
    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $destRel = 'uploads/' . $filename;
    $root = dirname(__DIR__);
    $dest = $root . '/' . $destRel;
    if (!is_dir($root . '/uploads')) {
        @mkdir($root . '/uploads', 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, null, 'ذخیرهٔ فایل ناموفق بود.'];
    }
    // تغییر اندازه در صورت بزرگ بودن (GD در دسترس باشد)
    $resized = resize_image($destRel, $maxW, $maxH);
    if ($resized !== null && $resized !== $destRel) {
        // نسخهٔ تغییر اندازه‌شده ذخیره شد؛ فایل بزرگ اصلی را حذف می‌کنیم
        @unlink($dest);
        return [true, $resized, ''];
    }
    return [true, $destRel, ''];
}

/** تصاویر گالری یک محصول (به ترتیب) */
function product_images($productId)
{
    $st = db()->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $st->execute([(int)$productId]);
    return $st->fetchAll();
}

/**
 * لیست کامل آدرس تصاویر یک محصول: ابتدا تصویر اصلی (کاور) و سپس تصاویر گالری.
 * برای گالری ورق‌زدنی در صفحهٔ محصول استفاده می‌شود.
 */
function product_image_list($product)
{
    $list = [];
    if (!empty($product['image'])) {
        $list[] = $product['image'];
    }
    foreach (product_images($product['id']) as $img) {
        $list[] = $img['image'];
    }
    return array_values(array_unique($list));
}

/** افزودن یک تصویر به گالری محصول */
function add_product_image($productId, $path)
{
    $st = db()->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_images WHERE product_id = ?))");
    $st->execute([(int)$productId, $path, (int)$productId]);
}

/**
 * پاک‌سازی امن HTML (برای خروجی ادیتور). تگ‌های خطرناک و هندلرهای رویداد
 * حذف می‌شوند؛ تگ‌های متنی و لینک/تصویر حفظ می‌شوند.
 * iframe فقط از دامنه‌های ویدیوی مجاز (آپارات و یوتیوب) مجاز است.
 */
function sanitize_html($html)
{
    $html = (string)$html;
    // حذف کامل تگ‌های خطرناک (با محتوا) — به‌جز iframe که جداگانه بررسی می‌شود
    $html = preg_replace('#<\s*(script|style|object|embed|form|input|link|meta|svg|math)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style|object|embed|form|input|link|meta|svg|math)[^>]*/?>#is', '', $html);
    // iframe: فقط دامنه‌های مجاز نگه داشته و هندلرهای رویدادشان حذف می‌شود
    $html = preg_replace_callback('#<iframe\b[^>]*>.*?</iframe>#is', function ($m) {
        $tag = $m[0];
        if (preg_match('#https?://(www\.)?(aparat\.com|youtube\.com|youtube-nocookie\.com|youtu\.be)#i', $tag)) {
            return preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $tag);
        }
        return ''; // iframe ناشناس حذف می‌شود
    }, $html);
    // حذف هندلرهای رویداد on*="..."
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    // خنثی‌سازی javascript: در href/src
    $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2#i', '$1="#"', $html);
    return $html;
}

/**
 * تولید HTML نمایش ویدیوی مقاله:
 * - مسیر آپلودشده (uploads/...) یا URL مستقیم mp4/webm → تگ <video>
 * - لینک آپارات/یوتیوب → iframe امن (responsive)
 */
function article_video_embed($video)
{
    $video = trim((string)$video);
    if ($video === '') {
        return '';
    }
    // لینک آپارات
    if (preg_match('#aparat\.com/(v/|video/video/embed/videohash/)?([A-Za-z0-9]+)#i', $video, $m)) {
        $hash = $m[2];
        return '<div class="video-embed"><iframe src="https://www.aparat.com/video/video/embed/videohash/' . e($hash) . '/vt/frame" title="ویدیو" allowfullscreen="allowfullscreen" loading="lazy"></iframe></div>';
    }
    // لینک یوتیوب
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $video, $m)) {
        $id = $m[1];
        return '<div class="video-embed"><iframe src="https://www.youtube.com/embed/' . e($id) . '" title="ویدیو" allowfullscreen="allowfullscreen" loading="lazy"></iframe></div>';
    }
    // فایل ویدیو (آپلودشده یا URL مستقیم mp4/webm)
    return '<div class="video-embed"><video controls playsinline preload="metadata" src="' . e(product_image_url($video)) . '"></video></div>';
}

/**
 * نمایش توضیحات محصول: اگر HTML باشد پاک‌سازی و خروجی داده می‌شود،
 * وگرنه متن ساده با شکستن خطوط نمایش داده می‌شود.
 */
function render_description($text)
{
    $text = (string)$text;
    if (trim($text) === '') {
        return '';
    }
    // اگر هیچ تگ HTML نداشته باشد، متن ساده است
    if (strip_tags($text) === $text) {
        return nl2br(e($text));
    }
    return sanitize_html($text);
}

/* ===================== دانشنامه (مقالات) ===================== */

/** دریافت یک مقالهٔ منتشرشده با شناسه یا slug */
function get_article($idOrSlug)
{
    if (is_numeric($idOrSlug)) {
        $st = db()->prepare("SELECT * FROM articles WHERE id = ? AND published = 1");
        $st->execute([(int)$idOrSlug]);
    } else {
        $st = db()->prepare("SELECT * FROM articles WHERE slug = ? AND published = 1");
        $st->execute([$idOrSlug]);
    }
    return $st->fetch();
}

/** دریافت یک مقاله با شناسه (حتی منتشرنشده، برای پنل مدیریت) */
function get_article_admin($id)
{
    $st = db()->prepare("SELECT * FROM articles WHERE id = ?");
    $st->execute([(int)$id]);
    return $st->fetch();
}

/** لیست مقالات منتشرشده (برای صفحهٔ عمومی دانشنامه) */
function get_articles($limit = 0)
{
    $sql = "SELECT * FROM articles WHERE published = 1 ORDER BY id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    return db()->query($sql)->fetchAll();
}

/**
 * خلاصهٔ مقاله: اگر excerpt پر باشد همان، وگرنه از متن (بدون تگ HTML)
 * به‌اندازهٔ $length کاراکتر برش خورده و با «…» ختم می‌شود.
 */
function article_excerpt($article, $length = 160)
{
    $text = trim((string)($article['excerpt'] ?? ''));
    if ($text === '') {
        $text = trim(strip_tags((string)($article['content'] ?? '')));
    }
    if (function_exists('mb_substr') && mb_strlen($text, 'UTF-8') > $length) {
        return mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
    return $text;
}

/** بررسی لاگین بودن مدیر */
function is_logged_in()
{
    return !empty($_SESSION['admin_id']);
}

/**
 * محافظ Rate Limiting برای ورود مشتری (wrapper روی rate_limit_check).
 * در login.php استفاده می‌شود تا تلاش‌های ورود محدود شود.
 */
function login_rate_limit($username)
{
    return rate_limit_check($username, 5, 900); // ۵ تلاش در ۱۵ دقیقه
}

/** آیا کاربری با این نام کاربری وجود دارد؟ */
function user_exists($username)
{
    $st = db()->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $st->execute([(string)$username]);
    return (int)$st->fetchColumn() > 0;
}

/* ===================== بازیابی رمز عبور (بدون SMTP) ===================== */

/**
 * یافتن کاربر مشتری با نام کاربری (برای شروع بازیابی رمز).
 */
function find_recovery_user($username)
{
    $username = trim((string)$username);
    if ($username === '') {
        return null;
    }
    $st = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'customer'");
    $st->execute([$username]);
    return $st->fetch() ?: null;
}

/**
 * بررسی صحت ایمیل ثبت‌شده برای کاربر (مرحلهٔ احراز هویت بازیابی).
 * بدون نیاز به SMTP: ایمیل به‌عنوان راز هویتی مقایسه می‌شود.
 *
 * @return array|null  در صورت تطبیق، آرایهٔ کاربر؛ در غیر این صورت null
 */
function verify_recovery_email($username, $email)
{
    $user = find_recovery_user($username);
    if (!$user) {
        return null;
    }
    $stored = trim((string)($user['email'] ?? ''));
    $given   = trim((string)$email);
    if ($stored === '' || $given === '' || strtolower($stored) !== strtolower($given)) {
        return null;
    }
    return $user;
}

/**
 * ثبت رمز عبور جدید پس از احراز هویت موفق بازیابی.
 *
 * @return bool  true در صورت موفقیت
 */
function recovery_reset_password($userId, $newPassword)
{
    $userId = (int)$userId;
    $newPassword = (string)$newPassword;
    if ($userId <= 0 || strlen($newPassword) < 6) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $st = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
    $st->execute([$hash, $userId]);
    return $st->rowCount() > 0;
}

/** شناسهٔ مشتری واردشده (کاربر فروشگاه، مستقل از مدیر) */
function customer_id()
{
    return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** آیا مشتری وارد شده است؟ */
function is_customer_logged_in()
{
    return customer_id() !== null;
}

/** آیا قیمت‌ها برای مهمان پنهان است؟ (فقط کاربران واردشده قیمت می‌بینند) */
function prices_hidden_for_guests()
{
    return setting_bool('hide_prices_guests', false) && !is_customer_logged_in();
}
function price_or_login($priceHtml, $extraClass = '')
{
    if (prices_hidden_for_guests()) {
        return '<a href="login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/') . '" class="price-login-hint ' . e($extraClass) . '">برای مشاهدهٔ قیمت وارد شوید</a>';
    }
    return $priceHtml;
}

/** اطلاعات حساب مشتری واردشده */
function current_customer()
{
    $id = customer_id();
    if ($id === null) {
        return null;
    }
    $st = db()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** سفارش‌های یک مشتری */
function customer_orders($userId)
{
    $st = db()->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $st->execute([(int)$userId]);
    return $st->fetchAll();
}

/* ===================== ورود با OTP (رمز یک‌بارمصرف) ===================== */

/** ساخت کد OTP تصادفی ۵ رقمی */
function otp_generate_code()
{
    return (string)random_int(10000, 99999);
}

/** تولید و ذخیرهٔ OTP برای یک شناسه (شماره یا ایمیل) */
function otp_create($identifier, $channel = 'sms', $ttl = 300)
{
    $identifier = strtolower(trim((string)$identifier));
    // غیرفعال‌سازی کدهای قبلی
    db()->prepare("UPDATE otp_codes SET used = 1 WHERE identifier = ? AND used = 0")->execute([$identifier]);
    $code = otp_generate_code();
    $ttl  = max(60, (int)$ttl); // حداقل ۱ دقیقه
    $expires = date('Y-m-d H:i:s', time() + $ttl);
    db()->prepare("INSERT INTO otp_codes (identifier, channel, code, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$identifier, $channel, $code, $expires]);
    return $code;
}

/** تأیید کد OTP برای یک شناسه */
function otp_verify($identifier, $code, $channel = 'sms')
{
    $identifier = strtolower(trim((string)$identifier));
    $st = db()->prepare("SELECT * FROM otp_codes WHERE identifier = ? AND used = 0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
    $st->execute([$identifier, date('Y-m-d H:i:s')]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'کد منقضی شده یا نامعتبر است.'];
    }
    $attempts = (int)$row['attempts'];
    if ($attempts >= 5) {
        db()->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([(int)$row['id']]);
        return ['ok' => false, 'error' => 'تعداد تلاش‌های مجاز تمام شد. دوباره کد بگیرید.'];
    }
    if (!hash_equals((string)$row['code'], (string)$code)) {
        db()->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?")->execute([(int)$row['id']]);
        return ['ok' => false, 'error' => 'کد واردشده صحیح نیست.'];
    }
    db()->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([(int)$row['id']]);
    return ['ok' => true];
}

/** آیا OTP برای ورود فعال است؟ */
function otp_login_enabled()
{
    return setting_bool('otp_login_enabled', false);
}

/** یافتن کاربر با شماره موبایل یا ایمیل (برای ورود OTP) */
function find_user_by_identifier($identifier)
{
    $identifier = strtolower(trim((string)$identifier));
    if ($identifier === '') {
        return null;
    }
    if (preg_match('/^0?9[0-9]{9}$/', sms_normalize_phone($identifier) ? substr(sms_normalize_phone($identifier), 2) : '') ) {
        // شماره موبایل
        $st = db()->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
        $st->execute([$identifier]);
        $u = $st->fetch();
        if ($u) return $u;
    }
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->execute([$identifier]);
        $u = $st->fetch();
        if ($u) return $u;
    }
    // به‌عنوان نام کاربری
    $st = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $st->execute([$identifier]);
    return $st->fetch() ?: null;
}

/** ارسال کد OTP از طریق پیامک */
function otp_send_sms($phone, $code)
{
    $msg = sms_template('otp_code', ['{code}' => $code]);
    return sms_send($phone, $msg, 'otp_code', [(string)$code]);
}

/** ارسال کد OTP از طریق ایمیل (لایهٔ ایمیل سایت: mail یا SMTP) */
function otp_send_email($email, $code)
{
    $store = setting('store_name', STORE_NAME);
    $subject = 'کد ورود شما به ' . $store;
    $body = "سلام،\n\nکد ورود شما: {$code}\n\nاین کد تا ۵ دقیقه معتبر است.\n\n— {$store}";
    $res = email_send($email, $subject, $body, 'otp_code');
    log_event('OTP_EMAIL to=' . $email . ' code=' . $code . ' result=' . $res['status'], 'info');
    return $res['ok'];
}

/* ===================== بازیابی رمز عبور دوکاناله (ایمیل/پیامک) ===================== */

/**
 * ارسال کد بازیابی رمز عبور از کانال انتخابی کاربر.
 * @param string $channel 'sms' یا 'email'
 * @param string $target  شمارهٔ نرمال‌شدهٔ ۹۸… یا ایمیل
 * @return bool
 */
function recovery_send_code($channel, $target, $code)
{
    $store = setting('store_name', STORE_NAME);
    if ($channel === 'sms') {
        sms_send($target, sms_template('password_reset', ['{code}' => $code]), 'password_reset', [(string)$code]);
        log_event('RECOVERY_SMS to=' . $target . ' code=' . $code, 'info');
        return true;
    }
    if ($channel === 'email') {
        $subject = 'کد بازیابی رمز عبور — ' . $store;
        $body = "سلام،\n\nکد بازیابی رمز عبور شما: {$code}\n\nاین کد تا ۱۰ دقیقه معتبر است.\n\n— {$store}";
        email_send($target, $subject, $body, 'password_reset');
        log_event('RECOVERY_EMAIL to=' . $target . ' code=' . $code, 'info');
        return true;
    }
    return false;
}

/** پوشاندن بخشی از شماره/ایمیل برای نمایش امن */
function mask_contact($channel, $target)
{
    $t = (string)$target;
    if ($channel === 'sms') {
        $local = sms_strip_country($t); // 0912…
        if (strlen($local) >= 8) {
            return substr($local, 0, 4) . '***' . substr($local, -3);
        }
        return $local !== '' ? $local : '—';
    }
    if (filter_var($t, FILTER_VALIDATE_EMAIL)) {
        $at = strpos($t, '@');
        return substr($t, 0, 1) . '***' . substr($t, $at);
    }
    return $t !== '' ? $t : '—';
}

/* ===================== رهگیری سفارش ===================== */

/** وضعیت‌های استاندارد رهگیری سفارش */
function tracking_statuses()
{
    return [
        'processing' => 'در حال آماده‌سازی',
        'ready'      => 'آمادهٔ ارسال',
        'shipped'    => 'تحویل به پست داده شد',
        'delivered'  => 'تحویل شده',
        'cancelled'  => 'لغو شده',
    ];
}

/** برچسب فارسی یک وضعیت رهگیری */
function tracking_label($status)
{
    $map = tracking_statuses();
    return $map[$status] ?? (string)$status;
}

/** آخرین وضعیت رهگیری یک سفارش */
function order_tracking_status($orderId)
{
    $st = db()->prepare("SELECT * FROM order_tracking WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([(int)$orderId]);
    return $st->fetch();
}

/** همهٔ رخدادهای رهگیری یک سفارش (از قدیم به جدید) */
function order_tracking_events($orderId)
{
    $st = db()->prepare("SELECT * FROM order_tracking WHERE order_id = ? ORDER BY id ASC");
    $st->execute([(int)$orderId]);
    return $st->fetchAll();
}

/** افزودن یک رویداد رهگیری جدید */
function order_tracking_add($orderId, $status, $note = '')
{
    db()->prepare("INSERT INTO order_tracking (order_id, status, note) VALUES (?, ?, ?)")
        ->execute([(int)$orderId, $status, trim((string)$note)]);
    return true;
}

/** آیا رهگیری سفارش فعال است؟ */
function tracking_enabled()
{
    return setting_bool('order_tracking_enabled', true);
}

/* ===================== اطلاع‌رسانی موجودشدن کالا ===================== */

/** موجودی مؤثر یک محصول (با درنظرگرفتن وارینت‌ها) */
function product_effective_stock($productId)
{
    $p = get_product($productId);
    if (!$p) {
        return 0;
    }
    $variants = product_variants($productId);
    if (count($variants) > 0) {
        // اگر وارینت دارد: موجودی = مجموع موجودی وارینت‌های فعال
        $sum = 0;
        foreach ($variants as $v) {
            $sum += (int)$v['stock'];
        }
        return $sum;
    }
    return (int)$p['stock'];
}

/** آیا محصول (با درنظرگرفتن وارینت‌ها) موجود است؟ */
function product_in_stock($productId)
{
    return product_effective_stock($productId) > 0;
}

/** ثبت درخواست اطلاع‌رسانی (شماره موبایل یا ایمیل). از تکراری‌شدن جلوگیری می‌کند. */
function stock_notify_register($productId, $contact, $variantId = null)
{
    $productId = (int)$productId;
    $contact = strtolower(trim((string)$contact));
    if ($contact === '') {
        return ['ok' => false, 'reason' => 'empty', 'error' => 'شماره موبایل یا ایمیل را وارد کنید.'];
    }

    $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);
    $phone = null;
    $email = null;
    if ($isEmail) {
        $email = $contact;
    } else {
        $phone = sms_normalize_phone($contact);
        if ($phone === null) {
            return ['ok' => false, 'reason' => 'invalid', 'error' => 'شماره موبایل نامعتبر است (مثال: 09123456789).'];
        }
    }
    $variantId = $variantId ? (int)$variantId : null;

    // جلوگیری از ثبت تکراری
    $st = db()->prepare("SELECT id FROM stock_notifications WHERE product_id = ? AND variant_id IS ? AND notified = 0 AND (phone = ? OR email = ?)");
    $st->execute([$productId, $variantId, $phone, $email]);
    if ($st->fetch()) {
        return ['ok' => false, 'reason' => 'duplicate', 'error' => 'شما قبلاً برای این محصول درخواست اطلاع‌رسانی ثبت کرده‌اید.'];
    }

    db()->prepare("INSERT INTO stock_notifications (product_id, variant_id, phone, email) VALUES (?, ?, ?, ?)")
        ->execute([$productId, $variantId, $phone, $email]);
    return ['ok' => true];
}

/**
 * پس از موجودشدن کالا، اطلاع‌رسانی‌های در انتظار را ارسال و علامت‌گذاری می‌کند.
 * @return int تعداد اطلاع‌رسانی‌های ارسال‌شده
 */
function stock_notify_flush($productId)
{
    $productId = (int)$productId;
    if (!product_in_stock($productId)) {
        return 0;
    }
    $p = get_product($productId);
    if (!$p) {
        return 0;
    }
    $st = db()->prepare("SELECT * FROM stock_notifications WHERE product_id = ? AND notified = 0");
    $st->execute([$productId]);
    $rows = $st->fetchAll();

    $sent = 0;
    $store = setting('store_name', STORE_NAME);
    foreach ($rows as $r) {
        $label = $p['name'];
        if (!empty($r['variant_id'])) {
            $v = get_variant($r['variant_id']);
            if ($v) {
                $label .= ' (' . $v['name'] . ')';
            }
        }
        $msg = sms_template('stock_back', ['{name}' => $label]);
        $ok = false;
        if (!empty($r['phone'])) {
            $res = sms_send($r['phone'], $msg, 'stock_back', [$label]);
            $ok = !empty($res['ok']);
        } elseif (!empty($r['email'])) {
            $subject = 'موجودشدن کالا در ' . $store;
            $body = "سلام،\n\nمحصول «{$label}» دوباره موجود شد.\n\n— {$store}";
            $res = email_send($r['email'], $subject, $body, 'stock_back');
            $ok = !empty($res['ok']);
        }
        if ($ok) {
            db()->prepare("UPDATE stock_notifications SET notified = 1 WHERE id = ?")->execute([(int)$r['id']]);
            $sent++;
        }
    }
    return $sent;
}

/** آمار باشگاه مشتریان (تعداد سفارش، مجموع خرید، امتیاز) */
function customer_stats($userId)
{
    $st = db()->prepare("SELECT COUNT(*) AS cnt,
                         COALESCE(SUM(CASE WHEN status='paid' THEN total_amount ELSE 0 END), 0) AS spent
                         FROM orders WHERE user_id = ?");
    $st->execute([(int)$userId]);
    $r = $st->fetch();
    $spent = (int)$r['spent'];
    $points = user_points((int)$userId);
    return [
        'orders' => (int)$r['cnt'],
        'spent'  => $spent,
        'points' => $points,
        'tier'   => loyalty_tier($points),
    ];
}

/** یک سفارش مشخص */
function get_order($id)
{
    $st = db()->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([(int)$id]);
    return $st->fetch();
}

/** اقلام یک سفارش */
function order_items($orderId)
{
    $st = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $st->execute([(int)$orderId]);
    return $st->fetchAll();
}

/** تبدیل تاریخ میلادی به شمسی (جلالی) برای نمایش */
function persian_date($datetime, $withTime = false)
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $g = getdate($ts);
    list($jy, $jm, $jd) = gregorian_to_jalali($g['year'], $g['mon'], $g['mday']);
    $out = $jy . '/' . str_pad($jm, 2, '0', STR_PAD_LEFT) . '/' . str_pad($jd, 2, '0', STR_PAD_LEFT);
    $out = fa_digits($out);
    if ($withTime) {
        $out .= ' ' . fa_digits(str_pad($g['hours'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($g['minutes'], 2, '0', STR_PAD_LEFT));
    }
    return $out;
}

/** تبدیل میلادی → جلالی (الگوریتم استاندارد) */
function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100)
          + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/** برچسب فارسی وضعیت سفارش */
function status_label($status)
{
    $map = [
        'pending'   => 'در انتظار پرداخت',
        'paid'      => 'پرداخت شده',
        'failed'    => 'ناموفق',
        'cancelled' => 'لغو شده',
    ];
    return isset($map[$status]) ? $map[$status] : $status;
}

/* ===================== درخواست ابزار سفارشی ===================== */

/** برچسب فارسی وضعیت درخواست سفارشی */
function request_status_label($status)
{
    $map = [
        'pending'  => 'در انتظار بررسی',
        'approved' => 'تأیید شده',
        'rejected' => 'رد شده',
    ];
    return isset($map[$status]) ? $map[$status] : $status;
}

/** آیا محصول برای مشتری فعلی قابل مشاهده است؟ (عمومی یا مالکیت خصوصی) */
function can_view_product($product)
{
    $owner = isset($product['owner_user_id']) ? (int)$product['owner_user_id'] : 0;
    if ($owner <= 0) {
        return true;
    }
    return customer_id() === $owner;
}

/** شرط SQL برای مشاهدهٔ محصول: عمومی یا متعلق به مشتری فعلی */
function product_visibility_clause($alias = 'p')
{
    $uid = customer_id();
    $base = '(' . $alias . '.owner_user_id IS NULL OR ' . $alias . '.owner_user_id = 0';
    if ($uid === null) {
        return $base . ')';
    }
    return $base . ' OR ' . $alias . '.owner_user_id = ' . (int)$uid . ')';
}

/** درخواست‌های سفارشی یک مشتری */
function customer_requests($userId)
{
    $st = db()->prepare("SELECT * FROM custom_requests WHERE user_id = ? ORDER BY id DESC");
    $st->execute([(int)$userId]);
    return $st->fetchAll();
}

/** یک درخواست سفارشی با شناسه */
function get_request($id)
{
    $st = db()->prepare("SELECT * FROM custom_requests WHERE id = ?");
    $st->execute([(int)$id]);
    return $st->fetch();
}

/** آدرس دانلود فایل درخواست (PDF/DXF/JPG/PNG) */
function request_file_url($file)
{
    return product_image_url($file);
}

/* ===================== امنیت CSRF ===================== */

/* ===================== کپچا ===================== */

/** تولید کد کپچای جدید و ذخیره در نشست */
function captcha_generate()
{
    // فقط جمع: در محیط RTL تفریق دوجهته خوانده می‌شود («5 - 19» از راست «19 - 5» خوانده
    // می‌شود) و کاربر جواب اشتباه می‌زند؛ جمع جابه‌جایی‌پذیر است و هیچ ابهامی ندارد.
    $a = random_int(2, 19);
    $b = random_int(1, 19);
    $answer = $a + $b;
    $text = "$a + $b";
    $_SESSION['captcha'] = ['answer' => $answer, 'text' => $text, 't' => time()];
    return $text;
}

/** تأیید پاسخ کپچای کاربر */
function captcha_verify($input)
{
    if (empty($_SESSION['captcha'])) {
        return false;
    }
    $c = $_SESSION['captcha'];
    // انقضای ۱۵ دقیقه‌ای
    if (time() - (int)$c['t'] > 900) {
        unset($_SESSION['captcha']);
        return false;
    }
    // پاسخ درست + یک‌بارمصرف — ارقام فارسی/عربی و فاصله‌ها نرمال می‌شوند
    $input = this_to_en_digits(trim((string)($input ?? '')));
    $ok = $input !== '' && (int)$input === (int)$c['answer'];
    unset($_SESSION['captcha']);
    return $ok;
}

/** خروجی تصویر PNG کپچا (مستقیم به مرورگر) */
function captcha_image()
{
    if (!function_exists('imagecreatetruecolor')) {
        http_response_code(500);
        exit('GD not available');
    }
    $text = captcha_generate();
    $w = 160; $h = 56;
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 245, 247, 252);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    // خطوط نویز
    for ($i = 0; $i < 6; $i++) {
        $c = imagecolorallocate($img, random_int(140, 210), random_int(140, 210), random_int(190, 235));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }
    // نقاط نویز
    for ($i = 0; $i < 60; $i++) {
        $c = imagecolorallocate($img, random_int(120, 200), random_int(120, 200), random_int(180, 230));
        imagesetpixel($img, random_int(0, $w), random_int(0, $h), $c);
    }
    // رسم متن (تعداد ارقام بر اساس متن)
    $digits = preg_replace('/[^0-9+\-]/', '', $text);
    $len = mb_strlen($digits);
    $fontSize = 20;
    $x = ($w - $len * 18) / 2;
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($digits, $i, 1);
        $color = imagecolorallocate($img, random_int(20, 80), random_int(40, 110), random_int(90, 170));
        $angle = random_int(-14, 14);
        // فونت سیستمی (بدون وابستگی)
        $font = __DIR__ . '/../assets/fonts/arial.ttf';
        if (!is_file($font)) {
            // fallback: استفاده از فونت TTF موجود در ویندوز
            $win = 'C:/Windows/Fonts/arial.ttf';
            $font = is_file($win) ? $win : null;
        }
        if ($font) {
            imagettftext($img, $fontSize, $angle, (int)$x, 38, $color, $font, $ch);
        } else {
            imagestring($img, 5, (int)$x, 18, $ch, $color);
        }
        $x += 18;
    }
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    imagepng($img);
    imagedestroy($img);
    exit;
}

/** فیلد HTML کپچا برای فرم‌ها */
function captcha_field()
{
    return '<div class="captcha-row">'
        . '<img src="' . e(BASE_URL) . '/captcha.php?r=' . substr((string)time(), -6) . '" alt="کپچا" class="captcha-img" width="160" height="56">'
        . '<input type="text" name="captcha" inputmode="numeric" autocomplete="off" required placeholder="حاصل عبارت بالا" aria-label="کپچا">'
        . '</div>';
}

/* ===================== کپچا ===================== */

/** توکن CSRF */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** فیلد مخفی توکن CSRF */
function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** بررسی توکن CSRF ورودی */
function csrf_verify()
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/**
 * محدودیت نرخ ساده مبتنی بر نشست (session) برای فرم‌های حساس (ثبت‌نام/کد معرف و…).
 * بدون نیاز به جدول دیتابیس؛ برای SQLite و MySQL هر دو کار می‌کند.
 * @param string $key    کلید یکتا برای شمارنده (مثلاً 'register')
 * @param int    $max    حداکثر تلاش مجاز در بازهٔ $window ثانیه
 * @param int    $window بازهٔ زمانی به ثانیه (پیش‌فرض 60)
 * @return bool          true اگر اجازهٔ تلاش هست؛ false اگر محدود شده
 */
function rate_limit_attempt($key, $max = 5, $window = 60)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true; // بدون نشست فعال نمی‌توان محدود کرد؛ اجازه بده
    }
    $now = time();
    $bucket = $_SESSION['rl'][$key] ?? ['n' => 0, 't' => $now];
    // اگر پنجره گذشته، شمارنده را نو کن
    if ($now - $bucket['t'] > $window) {
        $bucket = ['n' => 0, 't' => $now];
    }
    if ($bucket['n'] >= $max) {
        return false; // محدود شده
    }
    $bucket['n']++;
    $_SESSION['rl'][$key] = $bucket;
    return true;
}

/**
 * ثانیه‌های باقی‌مانده تا رفع محدودیت نرخ (برای نمایش پیام).
 */
function rate_limit_remaining($key, $window = 60)
{
    $bucket = $_SESSION['rl'][$key] ?? null;
    if (!$bucket) {
        return 0;
    }
    return max(0, $window - (time() - $bucket['t']));
}

/* ===================== امنیت Rate Limiting ===================== */

/**
 * مسیر فایل ذخیرهٔ تلاش‌های ورود (مستقل از نوع دیتابیس).
 * از یک فایل JSON داخل پوشهٔ data استفاده می‌کنیم تا نیاز به تغییر اسکیمای
 * sqlite/mysql نباشد و در هر دو حالت یکسان کار کند.
 */
function rate_limit_store_path()
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $path = $dir . '/login_attempts.json';
    return $path;
}

/**
 * بررسی و ثبت تلاش‌های ورود (Rate Limiting).
 * کلید = IP + نام کاربری؛ حداکثر $max_attempts تلاش در $window_seconds ثانیه.
 *
 * @param string $username نام کاربری ورودی
 * @param int    $max_attempts حداکثر تلاش مجاز (پیش‌فرض ۵)
 * @param int    $window_seconds بازهٔ زمانی به ثانیه (پیش‌فرض ۹۰۰ = ۱۵ دقیقه)
 * @return bool  true اگر مجاز باشد، false اگر از حد عبور کرده (باید مسدود شود)
 */
function rate_limit_check($username, $max_attempts = 5, $window_seconds = 900)
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key  = hash('sha256', $ip . '|' . strtolower(trim($username)));
    $path = rate_limit_store_path();

    $now = time();
    $data = ['key' => $key, 'count' => 0, 'first_at' => $now, 'blocked_until' => 0];

    // خواندن وضعیت قبلی
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $existing = $raw ? json_decode($raw, true) : [];
        if (is_array($existing) && isset($existing[$key])) {
            $rec = $existing[$key];
            if (
                is_array($rec)
                && isset($rec['blocked_until'])
                && (int)$rec['blocked_until'] > $now
            ) {
                // هنوز مسدود است → اجازه نمی‌دهیم
                $data = $rec;
                return false;
            }
            // بازیابی شمارنده اگر پنجره هنوز باز است
            if (
                is_array($rec)
                && isset($rec['first_at'])
                && ($now - (int)$rec['first_at']) < $window_seconds
            ) {
                $data = [
                    'key'           => $key,
                    'count'         => (int)($rec['count'] ?? 0) + 1,
                    'first_at'      => (int)$rec['first_at'],
                    'blocked_until' => 0,
                ];
            } else {
                // پنجره منقضی شده → شمارنده از نو
                $data = [
                    'key'           => $key,
                    'count'         => 1,
                    'first_at'      => $now,
                    'blocked_until' => 0,
                ];
            }
        } else {
            $data = ['key' => $key, 'count' => 1, 'first_at' => $now, 'blocked_until' => 0];
        }
    } else {
        $data = ['key' => $key, 'count' => 1, 'first_at' => $now, 'blocked_until' => 0];
    }

    // اگر از حد عبور کرد، مسدود کن
    if ($data['count'] > $max_attempts) {
        $data['blocked_until'] = $now + $window_seconds;
    }

    // ذخیرهٔ وضعیت
    $store = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $store = $raw ? json_decode($raw, true) : [];
        if (!is_array($store)) {
            $store = [];
        }
    }
    $store[$key] = $data;
    @file_put_contents($path, json_encode($store), LOCK_EX);

    return $data['count'] <= $max_attempts;
}

/** IP واقعی کاربر — با توجه به Cloudflare از CF-Connecting-IP استفاده می‌کند */
function client_ip()
{
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/** مسیر فایل شمارندهٔ یک سطل/IP (نام فایل هش‌شده؛ قابل حدس نیست) */
function ip_rate_limit_path($bucket, $ip)
{
    static $dir = null;
    if ($dir === null) {
        $dir = __DIR__ . '/../data/rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
    return $dir . '/' . hash('sha256', $bucket . '|' . $ip) . '.json';
}

/**
 * محدودساز نرخ بر پایهٔ IP — ضد شلیک درخواست به سرور (فایل‌محور، مستقل از دیتابیس).
 * اگر از حد عبور کند تا پایان پنجرهٔ زمانی مسدود می‌شود (429).
 * خرابی داخلی = fail-open تا سایت هرگز به‌خاطر لیمیتر بشکند.
 *
 * @param string $bucket نام سطل (global / chatpoll / otpreq / ...)
 * @param int    $max    حداکثر درخواست در پنجره
 * @param int    $window طول پنجره به ثانیه
 * @return bool  true = اجازه؛ false = محدودشده
 */
function ip_rate_limit($bucket, $max, $window)
{
    if (PHP_SAPI === 'cli') {
        return true;
    }
    try {
        $ip = client_ip();
        if ($ip === 'unknown' || $ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        $path = ip_rate_limit_path($bucket, $ip);
        $now  = time();
        $data = ['c' => 0, 't' => $now, 'b' => 0];
        if (is_file($path)) {
            $rec = json_decode((string)@file_get_contents($path), true);
            if (is_array($rec)) {
                $data = array_merge($data, $rec);
            }
        }
        if ((int)$data['b'] > $now) {
            return false; // هنوز در دورهٔ مسدودی
        }
        if ($now - (int)$data['t'] >= $window) {
            $data = ['c' => 0, 't' => $now, 'b' => 0]; // پنجرهٔ جدید
        }
        if ((int)$data['c'] >= $max) {
            $data['b'] = $now + $window;
            @file_put_contents($path, json_encode($data), LOCK_EX);
            return false;
        }
        $data['c']++;
        @file_put_contents($path, json_encode($data), LOCK_EX);
        // پاک‌سازی دوره‌ای فایل‌های کهنه (حداکثر هر ۱۰ دقیقه، فقط در نخستین درخواست پنجره)
        if ((int)$data['c'] === 1) {
            $mark = __DIR__ . '/../data/rl/.lastclean';
            if (!is_file($mark) || $now - (int)@filemtime($mark) > 600) {
                @touch($mark);
                foreach (glob(__DIR__ . '/../data/rl/*.json') ?: [] as $f) {
                    if (@filemtime($f) < $now - 86400) {
                        @unlink($f);
                    }
                }
            }
        }
        return true;
    } catch (Throwable $e) {
        return true; // fail-open
    }
}

/* ===================== آدرس‌های مشتری ===================== */

/** فهرست آدرس‌های ذخیره‌شدهٔ کاربر (پیش‌فرض اول) */
function user_addresses($uid)
{
    $st = db()->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $st->execute([(int)$uid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** یک آدرس متعلق به کاربر (یا null) */
function user_address($uid, $id)
{
    $st = db()->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $st->execute([(int)$id, (int)$uid]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** ذخیرهٔ آدرس جدید؛ اگر اولین آدرس باشد یا makeDefault، پیش‌فرض می‌شود */
function address_save($uid, array $d, $makeDefault = false)
{
    $uid = (int)$uid;
    if ($makeDefault || user_addresses($uid) === []) {
        db()->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$uid]);
        $makeDefault = true;
    }
    $st = db()->prepare("INSERT INTO addresses (user_id, title, full_name, phone, postal_code, address, is_default)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([
        $uid,
        trim((string)($d['title'] ?? '')) ?: null,
        trim((string)($d['full_name'] ?? '')),
        trim((string)($d['phone'] ?? '')),
        trim((string)($d['postal_code'] ?? '')) ?: null,
        trim((string)($d['address'] ?? '')),
        $makeDefault ? 1 : 0,
    ]);
    return (int)db()->lastInsertId();
}

/** حذف آدرس متعلق به کاربر */
function address_delete($uid, $id)
{
    $st = db()->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
    $st->execute([(int)$id, (int)$uid]);
    return $st->rowCount() > 0;
}

/** تعیین آدرس پیش‌فرض */
function address_set_default($uid, $id)
{
    db()->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([(int)$uid]);
    db()->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([(int)$id, (int)$uid]);
}

/* ===================== نظرات و امتیاز محصولات ===================== */

/**
 * ثبت نظر و امتیاز برای یک محصول توسط مشتری واردشده.
 * هر کاربر می‌تواند برای هر محصول فقط یک نظر ثبت کند (امتیاز به‌روزرسانی می‌شود).
 *
 * @return bool  true در صورت ذخیرهٔ موفق
 */
function submit_review($productId, $userId, $rating, $text)
{
    $productId = (int)$productId;
    $userId    = (int)$userId;
    $rating    = max(1, min(5, (int)$rating));
    $text      = trim((string)$text);

    if ($productId <= 0 || $userId <= 0) {
        return false;
    }
    if ($text === '') {
        return false;
    }

    // نظرات جدید/به‌روزشده باید توسط مدیر تأیید شوند (قابل تنظیم)
    $requireApproval = setting_bool('reviews_require_approval', true);
    $newStatus = $requireApproval ? 'pending' : 'approved';

    $pdo = db();
    // بروزرسانی در صورت وجود نظر قبلی، وگرنه درج جدید
    if (DB_DRIVER === 'mysql') {
        $st = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, status) VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), status = VALUES(status), created_at = CURRENT_TIMESTAMP");
        // در MySQL برای پشتیبانی از ON DUPLICATE KEY نیاز به ایندکس یکتا داریم؛
        // ایندکس یکتا روی (product_id, user_id) در schema.sql تعریف می‌شود.
    } else {
        $st = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, status) VALUES (?, ?, ?, ?, ?)
                             ON CONFLICT(product_id, user_id) DO UPDATE SET rating = excluded.rating, comment = excluded.comment, created_at = datetime('now','localtime')");
    }
    $st->execute([$productId, $userId, $rating, $text, $newStatus]);
    return true;
}

/**
 * آیا کاربر مشخص قبلاً برای این محصول نظری ثبت کرده است؟
 */
function has_user_reviewed($productId, $userId)
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM reviews WHERE product_id = ? AND user_id = ?');
    $st->execute([(int)$productId, (int)$userId]);
    return (int)$st->fetchColumn() > 0;
}

/**
 * لیست نظرات تأییدشدهٔ یک محصول (به‌همراه نام کاربر و تاریخ).
 */
function get_product_reviews($productId)
{
    $st = db()->prepare("SELECT r.*, u.username, u.full_name
                         FROM reviews r
                         LEFT JOIN users u ON u.id = r.user_id
                         WHERE r.product_id = ? AND r.status = 'approved'
                         ORDER BY r.id DESC");
    $st->execute([(int)$productId]);
    return $st->fetchAll();
}

/**
 * میانگین امتیاز و تعداد نظرات یک محصول.
 *
 * @return array{ average: float, count: int }
 */
function get_average_rating($productId)
{
    $st = db()->prepare('SELECT COALESCE(AVG(rating), 0) AS avg, COUNT(*) AS cnt FROM reviews WHERE product_id = ? AND status = \'approved\'');
    $st->execute([(int)$productId]);
    $r = $st->fetch();
    return [
        'average' => round((float)$r['avg'], 1),
        'count'   => (int)$r['cnt'],
    ];
}

/**
 * رندر ستاره‌های امتیاز (۵ ستاره) برای نمایش امتیاز متوسط/داده‌شده.
 *
 * @param float $rating امتیاز بین ۰ تا ۵
 * @return string  HTML ستاره‌های پر و خالی
 */
function render_stars($rating)
{
    $rating = max(0, min(5, (float)$rating));
    $full = (int)floor($rating + 0.001);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star' . ($i <= $full ? ' filled' : '') . '">★</span>';
    }
    return $out;
}

/* ===================== SEO ===================== */

/**
 * آدرس کامل (canonical) صفحهٔ فعلی، بدون پارامترهای حساس.
 * فقط پارامترهای id را نگه می‌دارد تا برای صفحات محصول/مقاله یکتا بماند.
 */
function canonical_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $allow = ['id', 'page', 'category'];
    $qs = [];
    foreach ($allow as $k) {
        if (isset($_GET[$k]) && is_scalar($_GET[$k]) && $_GET[$k] !== '') {
            $qs[] = $k . '=' . urlencode((string)$_GET[$k]);
        }
    }
    $url = $scheme . '://' . $host . $uri;
    if ($qs) {
        $url .= '?' . implode('&', $qs);
    }
    return $url;
}

/**
 * تگ‌های فاوآیکون سایت.
 * اگر فاوآیکون اختصاصی تنظیم شده باشد، از آن استفاده می‌کند؛
 * در غیر این صورت یک فاوآیکون SVG درون‌خطی (نشان فروشگاه + رنگ تم) تولید می‌کند.
 */
function favicon_tags($settings = null)
{
    if ($settings === null) {
        $settings = get_settings();
    }
    $fav = $settings['site_favicon'] ?? '';
    if ($fav !== '') {
        $url = product_image_url($fav);
        $ext = strtolower(pathinfo(parse_url($fav, PHP_URL_PATH) ?: $fav, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : ($ext === 'gif' ? 'image/gif' : 'image/x-icon'));
        return '<link rel="icon" type="' . $mime . '" href="' . e($url) . '">' . "\n"
             . '<link rel="shortcut icon" href="' . e($url) . '">' . "\n"
             . '<link rel="apple-touch-icon" href="' . e($url) . '">';
    }

    // فاوآیکون پیش‌فرض: SVG درون‌خطی با حرف اول نام فروشگاه و رنگ تم
    $letter = mb_substr($settings['store_name'] ?? 'S', 0, 1);
    $color  = SITE_THEME_COLOR;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
         . '<rect width="64" height="64" rx="14" fill="' . $color . '"/>'
         . '<text x="32" y="44" font-size="36" font-family="Vazirmatn, Tahoma, sans-serif" font-weight="700" fill="#ffffff" text-anchor="middle">' . e($letter) . '</text>'
         . '</svg>';
    $uri = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    return '<link rel="icon" type="image/svg+xml" href="' . $uri . '">';
}

/**
 * متاتگ‌های جغرافیایی (GEO) برای سئوی محلی.
 * موقعیت، منطقه و مختصات کسب‌وکار را برای موتورهای جستجو اعلام می‌کند.
 */
function geo_meta_tags()
{
    $region = setting('company_region', 'IR-07');
    $city   = setting('company_city', 'تهران');
    $lat    = setting('company_lat', '35.6892');
    $lng    = setting('company_lng', '51.3890');
    return '<meta name="geo.region" content="' . e($region) . '">' . "\n"
        . '    <meta name="geo.placename" content="' . e($city) . '">' . "\n"
        . '    <meta name="geo.position" content="' . e($lat) . ';' . e($lng) . '">' . "\n"
        . '    <meta name="ICBM" content="' . e($lat) . ', ' . e($lng) . '">' . "\n"
        . '    <meta property="og:locale" content="' . e(SITE_LOCALE) . '">' . "\n"
        . '    <meta name="language" content="fa">';
}

/**
 * اسکیمای LocalBusiness (JSON-LD) برای کسب‌وکار محلی: نام، آدرس،
 * مختصات جغرافیایی، تلفن و ساعات کاری.
 */
function local_business_jsonld()
{
    $s = get_settings();
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'LocalBusiness',
        '@id'         => BASE_URL . '/#business',
        'name'        => $s['store_name'],
        'description' => $s['store_tagline'],
        'url'         => BASE_URL . '/',
        'telephone'   => $s['company_phone'],
        'email'       => $s['company_email'],
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $s['company_address'],
            'addressLocality' => setting('company_city', 'تهران'),
            'addressRegion'   => 'تهران',
            'addressCountry'  => 'IR',
        ],
        'geo'         => [
            '@type'     => 'GeoCoordinates',
            'latitude'  => setting('company_lat', '35.6892'),
            'longitude' => setting('company_lng', '51.3890'),
        ],
        'openingHoursSpecification' => [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'],
            'opens'     => '08:00',
            'closes'    => '17:00',
        ],
    ];
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

/**
 * اسکیمای WebSite (JSON-LD) با زبان پیش‌فرض و جستجوی سایت.
 */
function website_jsonld()
{
    $s = get_settings();
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        '@id'         => BASE_URL . '/#website',
        'name'        => $s['store_name'],
        'url'         => BASE_URL . '/',
        'inLanguage'  => 'fa-IR',
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => BASE_URL . '/shop.php?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

/**
 * اسکیمای Product (JSON-LD) شامل قیمت، موجودی، تصویر و امتیاز.
 *
 * @param array $product آرایهٔ محصول
 * @param array|null $ratingInfo خروجی get_average_rating
 */
function product_jsonld($product, $ratingInfo = null)
{
    $url = BASE_URL . '/product.php?id=' . (int)$product['id'];
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $product['name'],
        'url'         => $url,
    ];
    // اگر قیمت برای مهمان پنهان است، offers را در اسکیما منتشر نمی‌کنیم
    if (!prices_hidden_for_guests()) {
        $data['offers'] = [
            '@type'         => 'Offer',
            'priceCurrency' => 'IRR',
            'price'         => (string)price_final((int)$product['price']),
            'availability'  => ((int)$product['stock'] > 0)
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'url'           => $url,
        ];
    }
    if (!empty($product['category_name'])) {
        $data['category'] = $product['category_name'];
    }
    if (!empty($product['image'])) {
        $data['image'] = product_image_url($product['image']);
    }
    if (!empty($product['description'])) {
        $desc = trim(strip_tags((string)$product['description']));
        if ($desc !== '') {
            $data['description'] = $desc;
        }
    }
    if ($ratingInfo && (int)$ratingInfo['count'] > 0) {
        $data['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string)$ratingInfo['average'],
            'reviewCount' => (int)$ratingInfo['count'],
        ];
    }
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

/**
 * اسکیمای Article (JSON-LD) برای مقالات دانشنامه.
 */
function article_jsonld($article)
{
    $url = BASE_URL . '/article.php?id=' . (int)$article['id'];
    $data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'Article',
        'headline'        => $article['title'],
        'url'             => $url,
        'mainEntityOfPage' => $url,
        'inLanguage'      => 'fa-IR',
        'author'          => [
            '@type' => 'Organization',
            'name'  => STORE_NAME,
        ],
    ];
    if (!empty($article['created_at'])) {
        $data['datePublished'] = date('c', strtotime($article['created_at']));
    }
    if (!empty($article['image'])) {
        $data['image'] = product_image_url($article['image']);
    }
    $desc = trim(strip_tags((string)($article['excerpt'] ?? '')));
    if ($desc === '' && !empty($article['content'])) {
        $desc = trim(strip_tags((string)$article['content']));
    }
    if ($desc !== '') {
        $data['description'] = mb_substr($desc, 0, 200, 'UTF-8');
    }
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

/* ===================== لاگ‌نویسی ===================== */

/**
 * تشخیص منبع ورود از HTTP_REFERER.
 * @return string direct|google|bing|yandex|duckduckgo|instagram|telegram|whatsapp|facebook|twitter|pinterest|youtube|aparat|linkedin|referral
 */
function visit_detect_source($referer = null)
{
    $r = strtolower(trim((string)($referer ?? $_SERVER['HTTP_REFERER'] ?? '')));
    if ($r === '') {
        return 'direct';
    }
    $host = strtolower((string)(parse_url($r, PHP_URL_HOST) ?: ''));
    $host = preg_replace('/^www\./', '', $host);
    $selfHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $selfHost = preg_replace('/^www\./', '', $selfHost);
    if ($host === '' || $host === $selfHost) {
        return 'direct'; // ناوبری داخلی
    }
    if (strpos($host, 'google.') !== false) return 'google';
    if (strpos($host, 'bing.') !== false) return 'bing';
    if (strpos($host, 'yandex.') !== false) return 'yandex';
    if (strpos($host, 'duckduckgo') !== false) return 'duckduckgo';
    $social = [
        'instagram' => 'instagram',
        't.me'      => 'telegram',
        'telegram'  => 'telegram',
        'whatsapp'  => 'whatsapp',
        'facebook'  => 'facebook',
        'fb.com'    => 'facebook',
        'twitter'   => 'twitter',
        'x.com'     => 'twitter',
        'pinterest' => 'pinterest',
        'youtube'   => 'youtube',
        'aparat'    => 'aparat',
        'linkedin'  => 'linkedin',
    ];
    foreach ($social as $needle => $key) {
        if (strpos($host, $needle) !== false) {
            return $key;
        }
    }
    return 'referral'; // سایت معرفی‌کنندهٔ دیگر
}

/**
 * تشخیص نوع دستگاه از User-Agent.
 * @return string mobile|tablet|desktop
 */
function visit_detect_device($ua = null)
{
    $ua = (string)($ua ?? $_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return 'desktop';
    }
    if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/Mobile|Android|iPhone|iPod|Opera Mini|IEMobile/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

/**
 * کشور بازدیدکننده از هدر رایگان کلادفلر (CF-IPCountry).
 * @return string کد دوحرفی (IR, US, …) یا XX برای نامشخص
 */
function visit_detect_country()
{
    $c = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    return preg_match('/^[A-Z]{2}$/', $c) ? $c : 'XX';
}

/** پرچم ایموجی کشور از کد دوحرفی */
function country_flag($code)
{
    $code = strtoupper(trim((string)$code));
    if (!preg_match('/^[A-Z]{2}$/', $code) || $code === 'XX' || $code === 'T1') {
        return '🌐';
    }
    return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
}

/**
 * تکمیل کشور بازدیدکننده‌های فاقد کشور با سرویس رایگان ip-api.com (بدون کلید).
 * نتیجه در جدول visits کش می‌شود تا برای هر IP فقط یک‌بار پرسیده شود.
 * اگر سرویس در دسترس نبود، داشبورد بدون مشکل با «نامشخص» نمایش می‌دهد.
 * @return int تعداد IPهای به‌روزشده
 */
function visit_geo_enrich($limit = 100)
{
    try {
        $rows = db()->query("SELECT DISTINCT ip FROM visits WHERE country IS NULL OR country = '' OR country = 'XX' LIMIT " . max(1, (int)$limit))->fetchAll(PDO::FETCH_COLUMN);
        if (!$rows) {
            return 0;
        }
        $ch = curl_init('http://ip-api.com/batch?fields=status,countryCode,query');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(array_values($rows)),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false) {
            return 0;
        }
        $list = json_decode($body, true);
        if (!is_array($list)) {
            return 0;
        }
        $updated = 0;
        $st = db()->prepare("UPDATE visits SET country = ? WHERE ip = ?");
        foreach ($list as $item) {
            if (!is_array($item) || ($item['status'] ?? '') !== 'success') {
                continue;
            }
            $cc = strtoupper(trim((string)($item['countryCode'] ?? '')));
            $ip = (string)($item['query'] ?? '');
            if ($cc === '' || strlen($cc) !== 2 || $ip === '') {
                continue;
            }
            $st->execute([$cc, $ip]);
            $updated++;
        }
        return $updated;
    } catch (Throwable $t) {
        return 0;
    }
}

/** نام فارسی کشورهای رایج (بقیه کد لاتین) */
function country_name_fa($code)
{
    $map = [
        'IR' => 'ایران', 'US' => 'آمریکا', 'DE' => 'آلمان', 'TR' => 'ترکیه',
        'AE' => 'امارات', 'CA' => 'کانادا', 'GB' => 'انگلستان', 'FR' => 'فرانسه',
        'NL' => 'هلند', 'RU' => 'روسیه', 'CN' => 'چین', 'IQ' => 'عراق',
        'AF' => 'افغانستان', 'SA' => 'عربستان', 'QA' => 'قطر', 'KW' => 'کویت',
        'OM' => 'عمان', 'AZ' => 'آذربایجان', 'AM' => 'ارمنستان', 'GE' => 'گرجستان',
        'PK' => 'پاکستان', 'IN' => 'هند', 'IT' => 'ایتالیا', 'ES' => 'اسپانیا',
        'SE' => 'سوئد', 'CH' => 'سوئیس', 'AT' => 'اتریش', 'BE' => 'بلژیک',
        'JP' => 'ژاپن', 'KR' => 'کرهٔ جنوبی', 'AU' => 'استرالیا', 'BR' => 'برزیل',
        'UA' => 'اوکراین', 'IL' => 'اسرائیل', 'MY' => 'مالزی', 'TH' => 'تایلند',
        'EG' => 'مصر', 'SY' => 'سوریه', 'LB' => 'لبنان', 'JO' => 'اردن',
    ];
    $code = strtoupper(trim((string)$code));
    return $map[$code] ?? ($code === 'XX' ? 'نامشخص' : $code);
}

/** برچسب فارسی منبع ورود */
function visit_source_label($source)
{
    $map = [
        'direct'     => 'ورود مستقیم',
        'google'     => 'گوگل',
        'bing'       => 'بینگ',
        'yandex'     => 'یاندکس',
        'duckduckgo' => 'داک‌داک‌گو',
        'instagram'  => 'اینستاگرام',
        'telegram'   => 'تلگرام',
        'whatsapp'   => 'واتساپ',
        'facebook'   => 'فیس‌بوک',
        'twitter'    => 'توییتر (X)',
        'pinterest'  => 'پینترست',
        'youtube'    => 'یوتیوب',
        'aparat'     => 'آپارات',
        'linkedin'   => 'لینکدین',
        'referral'   => 'سایت معرفی‌کننده',
    ];
    return $map[$source] ?? (string)$source;
}

/** برچسب فارسی نوع دستگاه */
function visit_device_label($device)
{
    $map = ['mobile' => 'موبایل', 'tablet' => 'تبلت', 'desktop' => 'دسکتاپ'];
    return $map[$device] ?? (string)$device;
}

/**
 * ثبت یک بازدید واقعی: به‌ازای هر IP فقط یک‌بار در هر روز شمرده می‌شود.
 * رفرش صفحه یا ورود دوباره در همان روز بازدید اضافه نمی‌کند.
 * ربات‌های شناخته‌شده (موتورهای جستجو و…) شمرده نمی‌شوند.
 */
function visit_track($uri = '')
{
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // حذف ربات‌های شناخته‌شده
        if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless/i', $ua)) {
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = 'unknown';
        }
        $day = date('Y-m-d');
        $uri = trim((string)$uri);
        db()->prepare("INSERT OR IGNORE INTO visits (ip, day, uri, country, source, device) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $ip,
                $day,
                mb_substr($uri, 0, 200),
                visit_detect_country(),
                visit_detect_source(),
                visit_detect_device($ua),
            ]);
    } catch (Throwable $t) {
        // شکست رهگیری بازدید نباید سایت را مختل کند
    }
}

/**
 * ثبت یک رویداد در فایل لاگ (data/logs/app.log).
 * سطح‌ها: debug, info, warn, error.
 */
function log_event($message, $level = 'info')
{
    // ثبت بازدید صفحات
    if (defined('LOG_VISITS') && LOG_VISITS && $level === 'info' && strpos($message, 'VISIT') === 0) {
        // ادامه دارد
    }
    
    $dir = __DIR__ . '/../data/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/app.log';
    $ts = date('Y-m-d H:i:s');
    $line = sprintf("[%s][%s] %s\n", $ts, strtoupper($level), $message);
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/** میان‌بر ثبت خطا */
function log_error($message)
{
    log_event($message, 'error');
}

/* ===================== باشگاه مشتریان (امتیاز) ===================== */

/** امتیاز فعلی یک کاربر */
function user_points($userId)
{
    $st = db()->prepare("SELECT COALESCE(points, 0) FROM users WHERE id = ?");
    $st->execute([(int)$userId]);
    return (int)$st->fetchColumn();
}

/** امتیاز حاصل از یک مبلغ پرداخت‌شده (هر ۱۰٬۰۰۰ تومان = ۱ امتیاز) */
function points_earned_for_amount($amount)
{
    $rate = max(1, (int)setting('loyalty_earn_rate', '10000'));
    return (int)floor(max(0, (int)$amount) / $rate);
}

/** تبدیل امتیاز به مبلغ تخفیف (۱ امتیاز = ۱ تومان) */
function points_to_discount($points)
{
    return max(0, (int)$points);
}

/** سطح عضویت از روی امتیاز */
function loyalty_tier($points)
{
    $points = (int)$points;
    if ($points >= 2000) {
        return ['key' => 'gold',   'label' => 'طلایی'];
    }
    if ($points >= 500) {
        return ['key' => 'silver', 'label' => 'نقره‌ای'];
    }
    return ['key' => 'bronze', 'label' => 'برنزی'];
}

/** تغییر امتیاز کاربر (مثبت = افزودن، منفی = کسر) و ثبت تراکنش */
function add_points($userId, $amount, $type = 'manual', $description = '', $refId = null, $createdBy = null)
{
    $userId = (int)$userId;
    $amount = (int)$amount;
    if ($userId <= 0 || $amount === 0) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($amount < 0) {
            // جلوگیری از دوبارخرج/منفی‌شدن موجودی: کسر فقط تا سقف موجودی انجام می‌شود
            $upd = $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
            $upd->execute([-$amount, $userId, -$amount]);
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                return false; // موجودی کافی نیست
            }
        } else {
            $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE id = ?")
                ->execute([$amount, $userId]);
        }
        $balance = user_points($userId);
        $pdo->prepare("INSERT INTO points_transactions (user_id, amount, balance_after, type, description, ref_id, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $amount, $balance, $type, $description, $refId, $createdBy]);
        $pdo->commit();
        return true;
    } catch (Throwable $t) {
        // نقض ایندکس یکتای (ref_id, type) = رکورد تکراری؛ تراکنش رد می‌شود
        $pdo->rollBack();
        return false;
    }
}

/** مصرف امتیاز (مقدار مثبت از موجودی کم می‌شود) */
function spend_points($userId, $points, $description = '', $refId = null)
{
    $points = max(0, (int)$points);
    if ($points <= 0) {
        return true;
    }
    return add_points($userId, -$points, 'redeem', $description, $refId);
}

/** تاریخچهٔ تراکنش‌های امتیاز */
function points_transactions($userId = null, $limit = 50)
{
    $limit = (int)$limit;
    if ($userId) {
        $st = db()->prepare("SELECT t.*, u.username, u.full_name
                             FROM points_transactions t
                             LEFT JOIN users u ON u.id = t.user_id
                             WHERE t.user_id = ?
                             ORDER BY t.id DESC LIMIT $limit");
        $st->execute([(int)$userId]);
        return $st->fetchAll();
    }
    return db()->query("SELECT t.*, u.username, u.full_name
                        FROM points_transactions t
                        LEFT JOIN users u ON u.id = t.user_id
                        ORDER BY t.id DESC LIMIT $limit")->fetchAll();
}

/**
 * برچسب فارسی نوع تراکنش امتیاز برای نمایش در تاریخچهٔ امتیازها.
 * @param string $type نوع تراکنش (order_paid, referral_earned, ...)
 * @param int    $amount مقدار امتیاز (برای تشخیص برداشت/واریز)
 */
function points_txn_label($type, $amount = 0)
{
    $map = [
        'order_paid'      => 'خرید موفق',
        'order_refund'    => 'بازگشت سفارش',
        'refund'          => 'بازگشت سفارش',
        'redeem'          => 'استفاده در خرید',
        'spend'           => 'استفاده در خرید',
        'welcome'         => 'هدیهٔ عضویت',
        'welcome_bonus'   => 'هدیهٔ عضویت',
        'referral_earned' => 'پاداش معرفی',
        'referral_joined' => 'عضویت با معرف',
        'admin_manual'    => 'تغییر توسط مدیریت',
        'manual'          => 'تغییر توسط مدیریت',
    ];
    if (isset($map[$type])) {
        return $map[$type];
    }
    // در صورت ناشناخته بودن نوع: بر اساس علامت مقدار
    if ((int)$amount < 0) {
        return 'برداشت امتیاز';
    }
    return 'افزایش امتیاز';
}

/* ===================== کد معرف (Referral) ===================== */

/** کد اختصاصی معرف کاربر — در اولین نیاز ساخته می‌شود (lazy) */
function user_referral_code($userId)
{
    $userId = (int)$userId;
    $st = db()->prepare("SELECT referral_code FROM users WHERE id = ?");
    $st->execute([$userId]);
    $code = $st->fetchColumn();
    if ($code) {
        return $code;
    }
    // تولید کد ۸ کاراکتری یکتا (حروف بزرگ و رقم، بدون کاراکترهای گمراه‌کننده)
    for ($try = 0; $try < 10; $try++) {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        try {
            db()->prepare("UPDATE users SET referral_code = ? WHERE id = ? AND (referral_code IS NULL OR referral_code = '')")
                ->execute([$code, $userId]);
            if (db()->prepare("SELECT referral_code FROM users WHERE id = ?")->execute([$userId])) {
                $st2 = db()->prepare("SELECT referral_code FROM users WHERE id = ?");
                $st2->execute([$userId]);
                $saved = $st2->fetchColumn();
                if ($saved) {
                    return $saved;
                }
            }
        } catch (Throwable $t) {
            // کد تکراری — دوباره تلاش می‌کنیم
        }
    }
    // fallback بسیار بعید: بر اساس شناسه
    $fallback = 'R' . str_pad((string)$userId, 7, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE users SET referral_code = ? WHERE id = ? AND (referral_code IS NULL OR referral_code = '')")
        ->execute([$fallback, $userId]);
    return $fallback;
}

/** یافتن کاربر با کد معرف (بدون حساسیت به بزرگی/کوچکی حروف) */
function find_user_by_referral_code($code)
{
    $code = strtoupper(trim((string)$code));
    if ($code === '' || !preg_match('/^[A-Z0-9]{3,16}$/', $code)) {
        return null;
    }
    $st = db()->prepare("SELECT id, full_name, username FROM users WHERE UPPER(referral_code) = ? AND role = 'customer'");
    $st->execute([$code]);
    $u = $st->fetch();
    return $u ?: null;
}

/** ثبت رابطهٔ معرفی؛ خروجی: [ok, message] */
function referral_register($inviteeId, $referralCodeInput)
{
    $inviteeId = (int)$inviteeId;
    if ($inviteeId <= 0 || trim((string)$referralCodeInput) === '') {
        return [false, ''];
    }
    $referrer = find_user_by_referral_code($referralCodeInput);
    if (!$referrer) {
        return [false, 'کد معرف نامعتبر است.'];
    }
    if ((int)$referrer['id'] === $inviteeId) {
        return [false, 'نمی‌توانید از کد خودتان استفاده کنید.'];
    }
    try {
        db()->prepare("INSERT INTO referrals (referrer_id, invitee_id, status) VALUES (?, ?, 'registered')")
            ->execute([(int)$referrer['id'], $inviteeId]);
    } catch (Throwable $t) {
        return [false, 'این حساب قبلاً با یک کد معرف ثبت شده است.'];
    }
    // امتیاز فوری مهمان (اختیاری، پیش‌فرض ۰ = غیرفعال)
    $inviteeBonus = max(0, min(100000, (int)setting('referral_invitee_bonus', '0')));
    if ($inviteeBonus > 0) {
        add_points($inviteeId, $inviteeBonus, 'referral_bonus', 'هدیهٔ عضویت با کد معرف', null);
    }
    return [true, 'کد معتبر بود؛ به محض اولین خریدِ پرداخت‌شده، امتیاز هدیه فعال می‌شود.'] ;
}

/** آیا این سفارش، اولین سفارش پرداخت‌شدهٔ صاحبش است؟ */
function referral_is_first_paid_order($order)
{
    $uid = (int)($order['user_id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    $st = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'paid' AND id <= ?");
    $st->execute([$uid, (int)$order['id']]);
    return (int)$st->fetchColumn() === 1;
}

/** اعطای پاداش معرف پس از اولین خرید مهمان — امن در برابر اجرای دوباره */
function referral_award_on_first_purchase(array $order)
{
    $orderId = (int)$order['id'];
    $inviteeId = (int)($order['user_id'] ?? 0);
    if ($inviteeId <= 0) {
        return 0;
    }
    try {
        $st = db()->prepare("SELECT r.id, r.referrer_id, r.status FROM referrals r WHERE r.invitee_id = ? LIMIT 1");
        $st->execute([$inviteeId]);
        $rel = $st->fetch();
    } catch (Throwable $t) {
        return 0; // جدول هنوز ساخته نشده
    }
    if (!$rel || $rel['status'] !== 'registered') {
        return 0; // پاداش قبلاً داده شده یا رابطه‌ای وجود ندارد
    }
    if (!referral_is_first_paid_order($order)) {
        return 0; // فقط اولین خرید
    }
    $reward = max(0, min(1000000, (int)setting('referral_reward_points', '100')));
    if ($reward <= 0) {
        // حتی با پاداش صفر، وضعیت را نهایی می‌کنیم تا بعداً دوباره پردازش نشود
        db()->prepare("UPDATE referrals SET status = 'rewarded', points_awarded = 0, ref_order_id = ? WHERE id = ?")
            ->execute([$orderId, $rel['id']]);
        return 0;
    }
    // ایندکس یونیک روی (ref_id,type) مانع دوبار ثبت همزمان می‌شود
    $ok = add_points((int)$rel['referrer_id'], $reward, 'referral_reward', 'پاداش معرفی دوست جدید (اولین خرید)', $orderId);
    if ($ok) {
        db()->prepare("UPDATE referrals SET status = 'rewarded', points_awarded = ?, rewarded_at = datetime('now','localtime'), ref_order_id = ? WHERE id = ?")
            ->execute([$reward, $orderId, $rel['id']]);
        // پیامک اطلاع‌رسانی به معرف
        $u = db()->prepare("SELECT full_name, username, phone FROM users WHERE id = ?");
        $u->execute([(int)$rel['referrer_id']]);
        if ($ur = $u->fetch()) {
            sms_send(
                $ur['phone'] ?? '',
                sms_template('referral_earned', [
                    '{name}'   => $ur['full_name'] ?: $ur['username'],
                    '{points}' => $reward,
                ]),
                'referral_earned'
            );
        }
        return $reward;
    }
    return 0;
}

/** ارسال یک پیام (مشتری یا ادمین)؛ parent_id برای پاسخ در یک گفتگو */
function send_message($userId, $subject, $body, $fromAdmin = 0, $parentId = null)
{
    db()->prepare("INSERT INTO messages (user_id, parent_id, subject, body, from_admin, is_read)
                   VALUES (?, ?, ?, ?, ?, 0)")
        ->execute([(int)$userId, $parentId, $subject, $body, (int)$fromAdmin]);

    // پاسخ ادمین → اطلاع‌رسانی به مشتری (ایمیل؛ پیامک اختیاری با تنظیم sms_notify_customer_reply)
    if ((int)$fromAdmin === 1) {
        try {
            $cu = db()->prepare("SELECT email, phone, full_name FROM users WHERE id = ?");
            $cu->execute([(int)$userId]);
            $cu_row = $cu->fetch(PDO::FETCH_ASSOC);
            if ($cu_row) {
                $_storeName = setting('store_name', defined('STORE_NAME') ? STORE_NAME : 'فروشگاه');
                $_cLink = (defined('BASE_URL') ? BASE_URL : 'https://abzar-shargh.ir') . '/messages.php' . ($parentId ? '?id=' . (int)$parentId : '');
                $_cName = ($cu_row['full_name'] ?? '') !== '' ? $cu_row['full_name'] : 'مشتری';
                if (!empty($cu_row['email']) && function_exists('email_send')) {
                    email_send(
                        $cu_row['email'],
                        'پاسخ جدید از پشتیبانی ' . $_storeName,
                        "سلام {$_cName} عزیز،\n\nبه پیام شما در پشتیبانی پاسخ داده شد:\n\n" . trim(mb_substr((string)$body, 0, 600)) . "\n\nمشاهدهٔ گفتگو: {$_cLink}\n\n— پشتیبانی {$_storeName}",
                        'admin_reply'
                    );
                }
                if (!empty($cu_row['phone']) && setting('sms_notify_customer_reply', '0') === '1' && function_exists('sms_send')) {
                    sms_send($cu_row['phone'], $_storeName . ': به پیام شما پاسخ داده شد. مشاهده: ' . $_cLink, 'admin_reply', []);
                }
            }
        } catch (Throwable $e) { /* اعلان نباید ثبت پاسخ را خراب کند */ }
    }

    // اولین پیام مشتری در گفتگوی جدید → پیامک به ادمین‌ها
    if ((int)$fromAdmin === 0) {
        try {
            // فقط نخستین پیامِ گفتگوی بی‌پاسخ: اگر در ۲۴ ساعت گذشته گفتگوی دیگری
            // از همین کاربر بدون پاسخ ادمین باز است، پیامک جدید نفرست (جلوی اسپم)
            $msgId = (int)db()->lastInsertId();
            $cutoff = (defined('DB_DRIVER') && DB_DRIVER === 'mysql')
                ? "DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                : "datetime('now', '-24 hours')";
            $chk = db()->prepare("SELECT COUNT(*) FROM messages m
                WHERE m.user_id = ? AND m.parent_id IS NULL AND m.id < ?
                  AND m.created_at > " . $cutoff . "
                  AND NOT EXISTS (
                      SELECT 1 FROM messages r WHERE r.parent_id = m.id AND r.from_admin = 1
                  )");
            $chk->execute([(int)$userId, $msgId]);
            $pendingOthers = (int)$chk->fetchColumn();
            if ($parentId === null && $pendingOthers === 0) {
                $cu = db()->prepare("SELECT full_name, username FROM users WHERE id = ?");
                $cu->execute([(int)$userId]);
                $u = $cu->fetch(PDO::FETCH_ASSOC);
                $who = $u ? ($u['full_name'] ?: $u['username']) : '#' . (int)$userId;
                $snippet = trim(mb_substr($body, 0, 40));
                sms_notify_admins(
                    sms_template('admin_new_chat', [
                        '{name}'    => $who,
                        '{snippet}' => $snippet,
                    ]),
                    'admin_new_chat',
                    [$who, $snippet]
                );
            }
                // اطلاع‌رسانی تلگرام (پیام چت جدید) — در کنار پیامک
                if (function_exists('tg_notify_admins')) {
                    try {
                        $_tgBase = defined('BASE_URL') ? BASE_URL : 'https://abzar-shargh.ir';
                        $_rootId = ($parentId !== null) ? (int)$parentId : (int)$msgId;
                        $_tgSnippet = trim(mb_substr((string)$body, 0, 200));
                        $_tgText = ($parentId !== null ? "💬 <b>پیام جدید مشتری (ادامهٔ گفتگو)</b>" : "💬 <b>پیام چت جدید</b>") . "\n" .
                                    "کاربر: " . htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . " (#" . (int)$userId . ")\n" .
                                    "موضوع: " . htmlspecialchars((string)$subject, ENT_QUOTES, 'UTF-8') . "\n" .
                                    "متن: " . nl2br(htmlspecialchars($_tgSnippet, ENT_QUOTES, 'UTF-8')) . "\n" .
                                    "زمان: " . date('Y-m-d H:i:s') . "\n" .
                                    "🔗 " . $_tgBase . '/admin/messages.php?thread=' . (int)$userId;
                        $_kb = json_encode(['inline_keyboard' => [
                            [ ['text' => '✍️ پاسخ در ربات', 'callback_data' => 'cmd:reply:' . (int)$userId . ':' . ($_rootId ?? 0)] ],
                            [ ['text' => '💻 پنل ادمین', 'url' => $_tgBase . '/admin/messages.php?thread=' . (int)$userId] ],
                        ]], JSON_UNESCAPED_UNICODE);
                        if (function_exists('tg_enabled') && tg_enabled()) {
                            foreach (tg_admin_chat_ids() as $_cid) {
                                if (function_exists('tg_send')) @tg_send($_cid, $_tgText, ['reply_markup' => $_kb]);
                            }
                        } else {
                            @tg_notify_admins($_tgText, 'admin_new_chat');
                        }
                    } catch (Throwable $_e) {}
                }
        } catch (Throwable $t) {
            // خطای نوتیف نباید ارسال پیام را خراب کند
        }
    }
}

/** گفتگوهای یک مشتری (ریشه‌ها) به‌همراه آخرین پیام و شمارش پاسخ‌های خوانده‌نشده */
function user_messages($userId)
{
    $st = db()->prepare("SELECT m.id, m.subject,
            COALESCE(lastmsg.body, m.body) AS last_body,
            COALESCE(lastmsg.created_at, m.created_at) AS last_at,
            COALESCE(lastmsg.from_admin, m.from_admin) AS last_from_admin,
            (SELECT COUNT(*) FROM messages r WHERE r.parent_id = m.id AND r.from_admin = 1 AND r.is_read = 0) AS unread
        FROM messages m
        LEFT JOIN messages lastmsg ON lastmsg.id = (
            SELECT r.id FROM messages r WHERE r.parent_id = m.id ORDER BY r.id DESC LIMIT 1
        )
        WHERE m.user_id = ? AND m.parent_id IS NULL
        ORDER BY COALESCE(lastmsg.id, m.id) DESC");
    $st->execute([(int)$userId]);
    return $st->fetchAll();
}

/** گفتگوهای همهٔ کاربران برای پنل ادمین (ریشه‌ها) */
function admin_messages()
{
    return db()->query("SELECT m.*, u.username, u.full_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.user_id
        WHERE m.parent_id IS NULL
        ORDER BY m.id DESC")->fetchAll();
}

/** علامت‌گذاری خوانده‌شده؛ byAdmin=true یعنی ادمین پیام مشتری را خوانده */
function mark_message_read($threadId, $byAdmin = false, $userId = null)
{
    if ($byAdmin) {
        db()->prepare("UPDATE messages SET is_read = 1 WHERE (id = ? OR parent_id = ?) AND from_admin = 0")
            ->execute([(int)$threadId, (int)$threadId]);
    } else {
        db()->prepare("UPDATE messages SET is_read = 1 WHERE (id = ? OR parent_id = ?) AND user_id = ? AND from_admin = 1")
            ->execute([(int)$threadId, (int)$threadId, (int)$userId]);
    }
}

/** تعداد پاسخ‌های خوانده‌نشدهٔ پشتیبانی برای یک مشتری */
function unread_messages_count($userId)
{
    $st = db()->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND from_admin = 1 AND is_read = 0");
    $st->execute([(int)$userId]);
    return (int)$st->fetchColumn();
}

/** تعداد پیام‌های خوانده‌نشدهٔ مشتریان برای ادمین */
function admin_unread_count()
{
    return (int)db()->query("SELECT COUNT(*) FROM messages WHERE from_admin = 0 AND is_read = 0")->fetchColumn();
}

/** وضعیت بسته‌بودن گفتگو */
function is_thread_closed($threadId)
{
    $st = db()->prepare("SELECT COALESCE(is_closed, 0) FROM messages WHERE id = ?");
    $st->execute([(int)$threadId]);
    return (bool)$st->fetchColumn();
}

/**
 * بستن/باز کردن گفتگو. closer: 'customer' یا 'admin'.
 * is_closed: 0=باز، 1=بسته‌شدهٔ مشتری، 2=بسته‌شدهٔ ادمین.
 */
function set_thread_closed($threadId, $closed, $closer)
{
    $val = 0;
    if ($closer === 'admin') { $val = $closed ? 2 : 0; }
    else { $val = $closed ? 1 : 0; }
    db()->prepare("UPDATE messages SET is_closed = ? WHERE id = ?")
        ->execute([$val, (int)$threadId]);
}



/** همهٔ نظرات برای پنل ادمین (همهٔ وضعیت‌ها؛ pending اول) */
function all_reviews_admin($status = null, $limit = 100)
{
    $limit = max(1, min(300, (int)$limit));
    if ($status === 'pending' || $status === 'approved') {
        $st = db()->prepare("SELECT r.*, u.username, u.full_name, p.name AS product_name
                             FROM reviews r
                             LEFT JOIN users u ON u.id = r.user_id
                             LEFT JOIN products p ON p.id = r.product_id
                             WHERE r.status = ?
                             ORDER BY r.id DESC LIMIT " . $limit);
        $st->execute([$status]);
        return $st->fetchAll();
    }
    return db()->query("SELECT r.*, u.username, u.full_name, p.name AS product_name
                        FROM reviews r
                        LEFT JOIN users u ON u.id = r.user_id
                        LEFT JOIN products p ON p.id = r.product_id
                        ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC LIMIT " . $limit)->fetchAll();
}

/** شمارش نظرات در انتظار تأیید */
function pending_reviews_count()
{
    try {
        return (int)db()->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** تأیید یا بازگرداندن نظر به حالت انتظار توسط ادمین */
function set_review_status($reviewId, $status)
{
    $status = in_array($status, ['approved', 'pending'], true) ? $status : 'approved';
    db()->prepare("UPDATE reviews SET status = ? WHERE id = ?")->execute([$status, (int)$reviewId]);
}


/* ===================== پشتیبان‌گیری و بازگردانی ===================== */

/**
 * ساخت بکاپ کامل دیتابیس.
 * - SQLite: کل فایل دیتابیس به‌صورت خام + هدر مخصوص.
 * - MySQL: خروجی SQL کامل (schema + داده).
 * @return string|null محتوای بکاپ یا null در خطا
 */
function build_database_backup()
{
    $pdo = db();
    if (DB_DRIVER === 'sqlite') {
        $file = DB_SQLITE_PATH;
        if (!is_file($file)) {
            return null;
        }
        // خام خواندن فایل + هدر تا نوع بکاپ مشخص باشد
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        return "ABZARSHA_BACKUP_V1:SQLITE\n" . base64_encode($raw);
    }

    // MySQL: تولید SQL خروجی
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $out = "-- ABZARSHA BACKUP " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $tbl) {
        $out .= "\nDROP TABLE IF EXISTS `$tbl`;\n";
        $create = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_NUM);
        $out .= $create[1] . ";\n";
        $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) { return 'NULL'; }
                return "'" . str_replace("'", "''", (string)$v) . "'";
            }, $row);
            $out .= "INSERT INTO `$tbl` VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    return "ABZARSHA_BACKUP_V1:MYSQL\n" . $out;
}

/**
 * بازگردانی دیتابیس از محتوای بکاپ.
 * @param string $data محتوای فایل بکاپ
 * @return true|string true در موفقیت، یا پیام خطا
 */
function restore_database_backup($data)
{
    $data = (string)$data;
    $header = "ABZARSHA_BACKUP_V1:";
    if (strpos($data, $header) !== 0) {
        return 'فرمت فایل بکاپ معتبر نیست.';
    }
    $typeLine = substr($data, strlen($header), strpos($data, "\n") - strlen($header));
    $body = substr($data, strpos($data, "\n") + 1);
    $typeLine = trim($typeLine);

    $pdo = db();
    try {
        if ($typeLine === 'SQLITE' && DB_DRIVER === 'sqlite') {
            // بکاپ کامل فایل sqlite → جایگزینی مستقیم (فقط در حالت sqlite)
            $decoded = base64_decode($body, true);
            if ($decoded === false) {
                return 'دادهٔ بکاپ خراب است.';
            }
            // بستن اتصال فعلی و جایگزینی فایل
            $file = DB_SQLITE_PATH;
            if (!is_dir(dirname($file))) {
                return 'پوشهٔ دیتابیس موجود نیست.';
            }
            // تست اینکه فایل sqlite سالم است
            $tmp = $file . '.restore.tmp';
            file_put_contents($tmp, $decoded);
            try {
                $test = new PDO('sqlite:' . $tmp);
                $test->query("SELECT 1");
                $test = null;
            } catch (Throwable $e) {
                @unlink($tmp);
                return 'فایل بکاپ sqlite سالم نیست: ' . $e->getMessage();
            }
            @unlink($tmp);
            file_put_contents($file, $decoded);
            return true;
        }

        if ($typeLine === 'MYSQL' && DB_DRIVER === 'mysql') {
            // اجرای SQL خروجی
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            // تقسیم به statement ها (ساده)
            $statements = array_filter(array_map('trim', explode(";\n", $body)));
            foreach ($statements as $stmt) {
                if ($stmt !== '' && strpos($stmt, '--') !== 0) {
                    $pdo->exec($stmt);
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            return true;
        }

        return 'نوع بکاپ با درایور فعلی دیتابیس هماهنگ نیست (' . $typeLine . ' / ' . DB_DRIVER . ').';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/* ===================== پشتیبان‌گیری و بازگردانی ===================== */

/* ===================== دستیار هوش مصنوعی ===================== */

/**
 * فراخوانی API سازگار با OpenAI (chat completions).
 * @param string $systemPrompt نقش سیستم
 * @param string $userPrompt  درخواست کاربر
 * @return array ['ok'=>bool, 'text'=>string, 'error'=>string]
 */
function ai_complete($systemPrompt, $userPrompt)
{
    $apiKey = setting('ai_api_key', '');
    $baseUrl = rtrim(setting('ai_base_url', 'https://api.openai.com/v1'), '/');
    $model = setting('ai_model', 'gpt-4o-mini');

    if ($apiKey === '') {
        return ['ok' => false, 'text' => '', 'error' => 'کلید API هوش مصنوعی تنظیم نشده است. از بخش تنظیمات آن را وارد کنید.'];
    }

    $url = $baseUrl . '/chat/completions';
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => 0.7,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'text' => '', 'error' => 'خطای شبکه: ' . $err];
    }
    $json = json_decode($body, true);
    if ($http >= 400 || !isset($json['choices'][0]['message']['content'])) {
        $msg = isset($json['error']['message']) ? $json['error']['message'] : ('HTTP ' . $http);
        return ['ok' => false, 'text' => '', 'error' => $msg];
    }
    return ['ok' => true, 'text' => $json['choices'][0]['message']['content'], 'error' => ''];
}

/**
 * قالب‌های آمادهٔ دستیار AI (نقش سیستم + راهنمای درخواست).
 * @return array
 */
function ai_presets()
{
    return [
        'product' => [
            'name' => 'توضیح محصول',
            'icon' => '📦',
            'system' => 'تو یک کپیرایتر حرفه‌ای فروشگاه ابزارآلات صنعتی فارسی هستی. متن فارسی، روان و بازاریابی‌گونه بنویس. از ارقام فارسی استفاده کن. حداکثر ۱۵۰ کلمه. ساختار: یک جملهٔ جذاب ابتدایی، ۳ تا ۵ ویژگی کلیدی با بولت، و یک جملهٔ پایانی برای ترغیب خرید.',
            'hint' => 'نام محصول و ویژگی‌هایش را بنویس؛ مثلاً: «دریل شارژی ۱۸ ولت با دو باتری»',
            'starter' => 'برای محصول «دریل شارژی ۱۸ ولت» یک توضیح فروشگاهی حرفه‌ای بنویس. ویژگی‌ها: دو باتری ۲ آمپرساعت، گشتاور ۵۰ نیوتن‌متر، چاک خودکار ۱۳ میلیمتری، مناسب مصارف خانگی و نیمه‌صنعتی.',
        ],
        'article' => [
            'name' => 'مقالهٔ دانشنامه',
            'icon' => '📝',
            'system' => 'تو یک نویسندهٔ فنی فارسی‌زبان در حوزهٔ ابزارآلات صنعتی هستی. مقاله‌ای ساختاریافته با تیترها (##) و پاراگراف‌های کوتاه بنویس. ارقام فارسی. ۴۰۰ تا ۷۰۰ کلمه.',
            'hint' => 'موضوع مقاله را بنویس؛ مثلاً: «راهنمای انتخاب مته برای فلز»',
            'starter' => 'یک مقالهٔ دانشنامه با عنوان «راهنمای کامل انتخاب مته برای فلز، چوب و بتن» بنویس. شامل: انواع مته، معیارهای انتخاب، جدول پیشنهادی، و نکات ایمنی.',
        ],
        'training' => [
            'name' => 'محتوای آموزشی',
            'icon' => '🎓',
            'system' => 'تو یک مربی آموزش صنعتی هستی. محتوای آموزشی گام‌به‌گام با شماره و نکات ایمنی بنویس. فارسی روان با ارقام فارسی. ۳۰۰ تا ۵۰۰ کلمه.',
            'hint' => 'موضوع آموزش را بنویس؛ مثلاً: «نحوهٔ استفاده صحیح از فرز سنگبری»',
            'starter' => 'یک آموزش گام‌به‌گام با عنوان «استفادهٔ ایمن و صحیح از فرز سنگبری» بنویس: آماده‌سازی، مراحل کار، نکات ایمنی، و خطاهای رایج.',
        ],
        'seo' => [
            'name' => 'توضیح سئو (متا)',
            'icon' => '🔎',
            'system' => 'تو متخصص سئوی فارسی هستی. فقط و فقط خروجی زیر را بده، بدون هیچ توضیح اضافه:
عنوان متا: (حداکثر ۶۰ کاراکتر، جذاب، شامل کلمهٔ کلیدی)
توضیح متا: (حداکثر ۱۵۵ کاراکتر، شامل کلمهٔ کلیدی و دعوت به اقدام)
کلمات کلیدی: (۵ تا ۸ عبارت، با کاما جدا شده)',
            'hint' => 'موضوع/محصول را بنویس تا متای سئو تولید شود؛ مثلاً: «دریل شارژی بوش»',
            'starter' => 'متای سئو برای صفحهٔ دسته‌بندی «ابزار برقی» در فروشگاه ابزارسازی شرق (فروش ابزار صنعتی و برقی با ارسال به سراسر کشور) بساز.',
        ],
        'social' => [
            'name' => 'پست اینستاگرام',
            'icon' => '📣',
            'system' => 'تو مدیر شبکه‌های اجتماعی فروشگاه ابزارآلات هستی. یک پست اینستاگرام بنویس: کپشن جذاب (حداکثر ۱۵۰ کلمه، با ارقام فارسی) + ۸ تا ۱۲ هشتگ مرتبط فارسی/انگلیسی در انتها. لحن صمیمی ولی حرفه‌ای.',
            'hint' => 'موضوع پست را بنویس؛ مثلاً: «تخفیف ۱۵٪ ست آچارها»',
            'starter' => 'پست اینستاگرام برای معرفی «ست آچار تخت و رینگی ۲۴ تکه» با تخفیف ویژهٔ این هفته بنویس.',
        ],
        'sms' => [
            'name' => 'پیامک تبلیغاتی',
            'icon' => '📱',
            'system' => 'تو نویسندهٔ پیامک تبلیغاتی هستی. یک پیامک کوتاه حداکثر ۱۲۰ کاراکتر (به‌علاوهٔ لینک فروشگاه در انتها) بنویس. ارقام فارسی. فقط پیامک را بده بدون هیچ توضیح اضافه.',
            'hint' => 'موضوع پیامک را بنویس؛ مثلاً: «جشنوارهٔ فرارسیدن نوروز»',
            'starter' => 'یک پیامک تبلیغاتی برای جشنوارهٔ تخفیف پایان فصل (تا ۲۵٪ تخفیف روی ابزار برقی) بنویس.',
        ],
        'faq' => [
            'name' => 'سؤالات متداول',
            'icon' => '❓',
            'system' => 'تو پشتیبان فروشگاه هستی. ۶ تا ۸ سؤال و جواب متداول (FAQ) در حوزهٔ خواسته‌شده بنویس. فرمت: **س: …** و سپس ج: … فارسی روان با ارقام فارسی.',
            'hint' => 'موضوع FAQ را بنویس؛ مثلاً: «شرایط ارسال و گارانتی»',
            'starter' => 'FAQ دربارهٔ شرایط ارسال، گارانتی و بازگشت کالا در فروشگاه ابزارسازی شرق بنویس.',
        ],
        'report' => [
            'name' => 'گزارش وضعیت فروشگاه',
            'icon' => '📊',
            'system' => 'تو یک تحلیلگر دادهٔ فروشگاه هستی. آمار واقعی دیتابیس به تو داده می‌شود.
قوانین سخت‌گیرانه:
1. فقط از اعدادی که در داده‌های داده‌شده آمده استفاده کن. هرگز عدد، درصد یا آماری را اختراع نکن.
2. اگر عددی در داده‌ها نبود، آن را ذکر نکن و نگو «در دسترس نیست».
3. پیشنهادها را کلی و عملی بده بدون ساخت عددهای جدید.
4. اگر کاربر عددی پرسید که در داده نیست، صادقانه بگو این آمار در داده‌ها موجود نیست.
5. گزارش را فارسی و ساختاریافته بنویس.',
            'hint' => 'سؤال یا درخواست گزارش خود را بنویس (مثلاً: فروش این هفته چقدر بود؟)',
            'starter' => 'یک گزارش کامل وضعیت فروشگاه بنویس: خلاصهٔ فروش، بهترین محصولات، روند ۳۰ روز اخیر، و ۳ پیشنهاد عملی برای رشد فروش.',
            'with_stats' => true,
        ],
        'report_work' => [
            'name' => 'گزارش کار (عمومی)',
            'icon' => '🗂',
            'system' => 'تو یک گزارش‌نویس حرفه‌ای هستی. گزارش رسمی، ساختاریافته (مقدمه، شرح، نتایج، پیشنهادها) با ارقام فارسی بنویس.',
            'hint' => 'شرح کار/فعالیت را بنویس تا گزارش شود.',
            'starter' => '',
        ],
    ];
}


/**
 * دریافت لیست مدل‌های موجود از API (در صورت پشتیبانی endpoint /models).
 * @return array ['ok'=>bool, 'models'=>array, 'error'=>string]
 */
function ai_list_models()
{
    $apiKey = setting('ai_api_key', '');
    if ($apiKey === '') {
        return ['ok' => false, 'models' => [], 'error' => 'کلید API تنظیم نشده است.'];
    }
    $baseUrl = rtrim(setting('ai_base_url', 'https://api.openai.com/v1'), '/');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/models',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http >= 400) {
        return ['ok' => false, 'models' => [], 'error' => 'HTTP ' . $http];
    }
    $json = json_decode($body, true);
    if (!isset($json['data'])) {
        return ['ok' => false, 'models' => [], 'error' => 'پاسخ نامعتبر'];
    }
    $ids = [];
    foreach ($json['data'] as $m) {
        $id = (string)($m['id'] ?? '');
        // فقط مدل‌های متنی مناسب چت (فیلتر موارد خاص مثل embedding/tts/whisper)
        if ($id !== '' && !preg_match('/embedding|tts|whisper|transcribe|rerank|safety|audio/i', $id)) {
            $ids[] = $id;
        }
    }
    return ['ok' => true, 'models' => $ids, 'error' => ''];
}

/**
 * تست اتصال به API با یک پیام ساده.
 * @return array ['ok'=>bool, 'text'=>string, 'error'=>string]
 */
function ai_test_connection()
{
    return ai_complete('فقط کلمه «اتصال برقرار است» را پاسخ بده.', 'تست');
}
/* ===================== دستیار هوش مصنوعی ===================== */

/* ===================== گزارش‌گیری هوش مصنوعی از دیتابیس ===================== */

/**
 * جمع‌آوری آمار و داده‌های فروشگاه از دیتابیس به‌صورت متن ساختاریافته.
 * این داده‌ها به AI داده می‌شوند تا گزارش بدهد (بدون دسترسی مستقیم AI به SQL).
 * @return string متن آمار
 */
function ai_db_stats()
{
    $pdo = db();
    $today = date('Y-m-d');

    $out = [];
    $out[] = "=== آمار فروش (فقط فروش — بدون اطلاعات کاربران) === ({$today}) ===";

    // ---- فقط آمار فروش ----
    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $paidOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $cancelledOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('cancelled','failed')")->fetchColumn();
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='paid'")->fetchColumn();
    $todayOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at)=date('now')")->fetchColumn();
    $todayRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE date(created_at)=date('now') AND status='paid'")->fetchColumn();
    $weekRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE date(created_at)>=date('now','-7 days') AND status='paid'")->fetchColumn();
    $monthRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE date(created_at)>=date('now','-30 days') AND status='paid'")->fetchColumn();

    $out[] = "سفارش‌ها: کل={$totalOrders}، پرداخت‌شده={$paidOrders}، در انتظار={$pendingOrders}، لغو/ناموفق={$cancelledOrders}";
    $out[] = "فروش کل (پرداخت‌شده): {$totalRevenue} تومان";
    $out[] = "فروش امروز: {$todayRevenue} تومان ({$todayOrders} سفارش)";
    $out[] = "فروش ۷ روز اخیر: {$weekRevenue} تومان";
    $out[] = "فروش ۳۰ روز اخیر: {$monthRevenue} تومان";

    // پرفروش‌ترین محصولات (۵ تا)
    $top = $pdo->query("SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.price*oi.quantity) AS rev
                        FROM order_items oi JOIN orders o ON o.id=oi.order_id
                        WHERE o.status='paid' GROUP BY oi.product_name ORDER BY rev DESC LIMIT 5")->fetchAll();
    if ($top) {
        $out[] = "پرفروش‌ترین محصولات:";
        foreach ($top as $t) {
            $out[] = "  - {$t['product_name']}: {$t['qty']} عدد، " . number_format((int)$t['rev']) . " تومان";
        }
    } else {
        $out[] = "پرفروش‌ترین محصولات: هنوز فروشی ثبت نشده";
    }

    // آخرین سفارش‌ها (۵ تا — فقط مبلغ و وضعیت، بدون نام مشتری)
    $recent = $pdo->query("SELECT id, total_amount, status, created_at FROM orders ORDER BY id DESC LIMIT 5")->fetchAll();
    if ($recent) {
        $out[] = "آخرین سفارش‌ها:";
        foreach ($recent as $o) {
            $out[] = "  - #{$o['id']}: " . number_format((int)$o['total_amount']) . " تومان ({$o['status']})";
        }
    }

    $out[] = "توجه: این گزارش فقط شامل داده‌های فروش است و هیچ اطلاعات شخصی یا کاربری در آن نیست.";
    return implode("\n", $out);
}



/* ===================== ساخت محصول با هوش مصنوعی ===================== */

/**
 * از توضیح متنی کاربر، مشخصات ساختاریافتهٔ محصول استخراج می‌کند.
 * @param string $userText توضیح کاربر (نام، قیمت اگر بداند، ویژگی‌ها…)
 * @return array ['ok'=>bool, 'product'=>array{name,price,description,category,stock}, 'error'=>string]
 */
function ai_extract_product($userText)
{
    $systemPrompt = 'تو دستیار فروشگاه ابزارآلات صنعتی هستی. از توضیح متنی کاربر مشخصات محصول را استخراج و تکمیل کن.\n'
        . 'قوانین:\n'
        . '1. قیمت: فقط اگر کاربر گفت؛ اگر نگفته مقدار 0 بگذار (تا مدیر بعداً تعیین کند).\n'
        . '2. موجودی (stock): اگر کاربر گفت استفاده کن، وگرنه 10.\n'
        . '3. نام: کوتاه، فارسی و حرفه‌ای بنویس (حداکثر ۱۰ کلمه).\n'
        . '4. توضیح: فارسی روان و بازاریابی‌گونه، ۲ تا ۴ جمله، با ارقام فارسی.\n'
        . '5. دسته‌بندی: یکی از این‌ها که نزدیک‌تر است انتخاب کن: ابزار برقی، ابزار دستی، تجهیزات ایمنی، اندازه‌گیری، نظافتی — اگر هیچ‌کدام مناسب نبود یک اسم منطقی جدید بگذار.\n'
        . 'خروجی فقط JSON خالص با کلیدهای: {"name": "", "price": عدد, "description": "", "category": "", "stock": عدد}';

    $res = ai_complete($systemPrompt, $userText);
    if (!$res['ok']) {
        return ['ok' => false, 'product' => [], 'error' => $res['error']];
    }
    $content = $res['text'];
    // استخراج JSON (شاید داخل ```json باشد)
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
        $content = $m[1];
    } elseif (preg_match('/\{.*\}/s', $content, $m)) {
        $content = $m[0];
    }
    $prod = json_decode($content, true);
    if (!is_array($prod) || trim((string)($prod['name'] ?? '')) === '') {
        return ['ok' => false, 'product' => [], 'error' => 'پاسخ AI قابل پردازش نبود: ' . mb_substr(strip_tags((string)$content), 0, 150)];
    }
    return [
        'ok' => true,
        'product' => [
            'name' => trim((string)$prod['name']),
            'price' => (int)preg_replace('/[^0-9]/', '', (string)($prod['price'] ?? '0')),
            'description' => trim((string)($prod['description'] ?? '')),
            'category' => trim((string)($prod['category'] ?? '')),
            'stock' => max(0, (int)($prod['stock'] ?? 0)),
        ],
        'error' => '',
    ];
}

/**
 * تولید توضیح حرفه‌ای برای یک محصول واقعی (با دادهٔ واقعی: نام/دسته/قیمت/موجودی).
 * @param array $p ['id'=>, 'name'=>, 'price'=>, 'stock'=>, 'cat'=>]
 * @return array ['ok'=>bool, 'description'=>string, 'error'=>string]
 */
function ai_generate_product_description(array $p)
{
    $priceFa = fa_digits(number_format((int)$p['price'])) . ' تومان';
    $system = 'تو کپیرایتر حرفه‌ای فروشگاه ابزارآلات صنعتی «ابزارسازی شرق» هستی. '
        . 'یک توضیح محصول فروشگاهی (برای صفحهٔ محصول) بنویس. '
        . 'قوانین: فارسی روان و بازاریابی‌گونه؛ ۳ تا ۵ جمله؛ ارقام فارسی؛ '
        . 'از نام و دسته و قیمت داده‌شده استفاده کن ولی قیمت را حتماً در انتهای توضیح با عبارت «قیمت: … تومان» بیاور؛ '
        . 'بدون سلام و احوالپرسی؛ فقط متن توضیح را بده (بدون تیتر و مارک‌داون).';
    $user = "نام محصول: {$p['name']}
"
        . "دسته‌بندی: {$p['cat']}
"
        . "قیمت: {$priceFa}
"
        . "موجودی: " . fa_digits((string)$p['stock']) . " عدد";
    $res = ai_complete($system, $user);
    if (!$res['ok']) {
        return ['ok' => false, 'description' => '', 'error' => $res['error']];
    }
    $text = trim($res['text']);
    // حذف احتمالی کوتیشن دور متن
    $text = preg_replace('/^["\']+|["\']+$/u', '', $text);
    return ['ok' => true, 'description' => $text, 'error' => ''];
}

/**
 * محصولات دارای توضیح کوتاه (نیازمند توضیح بهتر) برای «توضیح سریع».
 * @param int $threshold حداکثر طول توضیح (بایت)
 * @return array
 */
function ai_products_needing_description($threshold = 120)
{
    return db()->query("SELECT p.id, p.name, p.price, p.stock, COALESCE(c.name,'عمومی') AS cat, LENGTH(p.description) AS dlen
                        FROM products p LEFT JOIN categories c ON c.id=p.category_id
                        WHERE p.active=1 AND LENGTH(p.description) < " . (int)$threshold . "
                        ORDER BY LENGTH(p.description) ASC, p.id DESC LIMIT 30")->fetchAll();
}

/**
 * رندر امن مارک‌داون خروجی AI به HTML (بدون HTML خام — همه‌چیز escape می‌شود).
 * پشتیبانی: h1-h3، bold، italic، inline code، code block، ul/ol، جدول ساده، لینک، خط افقی.
 */
function ai_render_markdown($text)
{
    $text = str_replace("\r\n", "\n", (string)$text);
    $lines = explode("\n", $text);
    $html = [];
    $inUl = false; $inOl = false; $inPre = false; $inTable = false;
    $preBuf = [];

    $inline = function ($s) {
        $s = e($s);
        // bold **x** سپس italic *x* سپس `code`
        $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $s);
        $s = preg_replace('/`([^`\n]+)`/u', '<span class="ai-md-code">$1</span>', $s);
        // لینک [txt](url) — فقط http/https
        $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', function ($m) {
            return '<a href="' . e($m[2]) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $s);
        return $s;
    };

    $closeLists = function () use (&$inUl, &$inOl, &$html) {
        if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        if ($inOl) { $html[] = '</ol>'; $inOl = false; }
    };
    $closeTable = function () use (&$inTable, &$html) {
        if ($inTable) { $html[] = '</tbody></table>'; $inTable = false; }
    };

    foreach ($lines as $ln) {
        $line = rtrim($ln);
        // code block
        if (preg_match('/^```/', $line)) {
            if ($inPre) { $html[] = '<pre class="ai-md-pre">' . e(implode("\n", $preBuf)) . '</pre>'; $preBuf = []; $inPre = false; }
            else { $closeLists(); $closeTable(); $inPre = true; }
            continue;
        }
        if ($inPre) { $preBuf[] = $ln; continue; }

        // جدول: | a | b |
        if (preg_match('/^\s*\|.*\|\s*$/', $line)) {
            $cells = array_map('trim', explode('|', trim($line, " |")));
            if (preg_match('/^\s*\|?[\s:|-]+\|?\s*$/', $line) && !preg_match('/[\w\x{0600}-\x{06FF}]/u', $line)) {
                continue; // جدول header separator
            }
            if (!$inTable) {
                $closeLists();
                $html[] = '<table class="ai-md-table"><thead><tr>';
                foreach ($cells as $c) $html[] = '<th>' . $inline($c) . '</th>';
                $html[] = '</tr></thead><tbody>';
                $inTable = true;
            } else {
                $html[] = '<tr>';
                foreach ($cells as $c) $html[] = '<td>' . $inline($c) . '</td>';
                $html[] = '</tr>';
            }
            continue;
        } else { $closeTable(); }

        // headings
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $closeLists();
            $lvl = min(3, strlen($m[1]));
            $html[] = "<h$lvl>" . $inline($m[2]) . "</h$lvl>";
            continue;
        }
        // hr
        if (preg_match('/^\s*([-*_])\s*\1\s*\1[\s*_-]*$/', $line)) {
            $closeLists(); $html[] = '<hr>'; continue;
        }
        // ul
        if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
            if ($inOl) { $html[] = '</ol>'; $inOl = false; }
            if (!$inUl) { $html[] = '<ul>'; $inUl = true; }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        // ol
        if (preg_match('/^\s*(\d+)[.)]\s+(.*)$/', $line, $m)) {
            if ($inUl) { $html[] = '</ul>'; $inUl = false; }
            if (!$inOl) { $html[] = '<ol>'; $inOl = true; }
            $html[] = '<li>' . $inline($m[2]) . '</li>';
            continue;
        }
        if (trim($line) === '') { $closeLists(); continue; }

        // paragraph
        $closeLists();
        $html[] = '<p>' . $inline($line) . '</p>';
    }
    if ($inPre) { $html[] = '<pre class="ai-md-pre">' . e(implode("\n", $preBuf)) . '</pre>'; }
    $closeLists(); $closeTable();
    return implode("\n", $html);
}

/* ==================================================================
 * محافظ دسترسی مدیر (require_admin)
 * ------------------------------------------------------------------
 * اندپوینت‌های پنل (media_api.php، upload_image.php و API ویرایشگر بصری)
 * این تابع را صدا می‌زنند؛ در نسخهٔ قبلی تعریف نشده بود و باعث خطای
 * «Call to undefined function require_admin()» می‌شد.
 * ================================================================== */
if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (is_logged_in()) {
            $role = $_SESSION['admin_role'] ?? null;
            if ($role !== null && !in_array($role, ['admin', 'manager'], true)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            return;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (stripos($accept, 'application/json') !== false || strtolower($xrw) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}