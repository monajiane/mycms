<?php
/**
 * includes/sms.php — سامانهٔ پیامک فروشگاه
 * ------------------------------------------------------------------
 * دو حالت ارسال:
 *   kavenegar → وب‌سرویس کاوه‌نگار (REST API واقعی)
 *   log       → حالت آزمایشی؛ پیام فقط در جدول sms_log ثبت می‌شود
 *
 * همهٔ ارسال‌ها (موفق و ناموفق) در جدول sms_log ثبت می‌شوند تا از
 * پنل مدیریت (admin/sms.php) قابل مشاهده باشند.
 */

require_once __DIR__ . '/functions.php';

/** متن‌های آمادهٔ رویدادها با جای‌نگهدارها */
function sms_template($event, array $vars = [])
{
    $store = setting('store_name', STORE_NAME);
    $vars['{store}'] = $store;

    $templates = [
        // خوش‌آمد عضویت (هدیه ثبت‌نام در صورت فعال بودن در پنل)
        'welcome' => '{name} عزیز؛ به باشگاه مشتریان {store} خوش آمدید.'
            . ((int)setting('loyalty_welcome_bonus', '0') > 0
                ? ' ' . (int)setting('loyalty_welcome_bonus', '0') . ' امتیاز هدیه به حساب شما اضافه شد.'
                : ''),
        // پرداخت موفق سفارش
        'order_paid' => '{name} عزیز؛ سفارش شماره {order_id} شما در {store} با موفقیت پرداخت شد.',
        // تغییر دستی امتیاز
        'points_adjusted' => '{name} عزیز؛ موجودی امتیاز شما در {store} به‌روزرسانی شد. موجودی فعلی: {points} امتیاز.',
            // پاداش معرفی (اولین خرید مهمان)
        'referral_earned' => '{name} عزیز؛ دوست شما با کد معرف شما به {store} پیوست و اولین خریدش را انجام داد. {points} امتیاز هدیه به حساب شما اضافه شد.',
        // عضویت مهمان با کد معرف
        'referral_joined' => 'به {store} خوش آمدید! عضویت شما با کد معرف ثبت شد.',
        // کد ورود یک‌بارمصرف (OTP)
        'otp_code' => 'کد ورود شما به {store}: {code}',
        // کد بازیابی رمز عبور
        'password_reset' => '{store} عزیز؛ کد بازیابی رمز عبور شما: {code} (تا ۱۰ دقیقه اعتبار دارد)',
        // موجودشدن دوبارهٔ کالا
        'stock_back' => '{store} عزیز؛ محصول «{name}» دوباره موجود شد. برای خرید همین حالا اقدام کنید.',
        // اطلاع سفارش جدید به ادمین‌ها (الگوی ملی‌پیامک: {0}=شماره سفارش {1}=مشتری {2}=اقلام {3}=مبلغ)
        'admin_new_order' => 'سفارش جدید #{order_id} ثبت شد - مشتری: {customer} - اقلام: {items} - مبلغ: {total} تومان.',
        // اطلاع ثبت‌نام کاربر جدید به ادمین‌ها ({0}=نام {1}=کاربری {2}=موبایل)
        'admin_new_user' => 'کاربر جدید در فروشگاه ثبت‌نام کرد: {name} - کاربری: {username} - موبایل: {phone}',
        // اطلاع پیام چت جدید به ادمین‌ها ({0}=نام {1}=متن پیام)
        'admin_new_chat' => 'پیام چت از {name}: {snippet} - پاسخ در پنل ابزارسازی شرق',
];

    // الگوی order_paid به دلیل تعداد متغیرها جدا ساخته می‌شود
    if ($event === 'order_paid') {
        $msg = '{name} عزیز؛ سفارش شماره {order_id} شما در {store} پرداخت شد.';
        $pts = (int)($vars['{points}'] ?? 0);
        $msg .= $pts > 0
            ? ' ' . $pts . ' امتیاز به حساب شما اضافه شد.'
            : '';
        foreach ($vars as $k => $v) {
            $msg = str_replace($k, (string)$v, $msg);
        }
        return $msg;
    }

    $tpl = isset($templates[$event]) ? $templates[$event] : '';
    foreach ($vars as $k => $v) {
        $tpl = str_replace($k, (string)$v, $tpl);
    }
    return preg_replace('/\{[a-z_]+\}/', '', $tpl);
}

/** نرمال‌سازی شماره موبایل ایران به فرمت ۹۸۹xxxxxxxxx */
function sms_normalize_phone($phone)
{
    $p = trim((string)$phone);
    $digits = preg_replace('/[^0-9]/', '', this_to_en_digits($p));
    if (strlen($digits) === 11 && strpos($digits, '09') === 0) {
        return '98' . substr($digits, 1);
    }
    if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
        return '98' . $digits;
    }
    if (strlen($digits) === 12 && strpos($digits, '98') === 0) {
        return $digits;
    }
    return null;
}

/** تبدیل ارقام فارسی/عربی به انگلیسی */
function this_to_en_digits($s)
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, (string)$s));
}

/**
 * ارسال پیامک (ورودی هر فرمت شماره‌ای قابل قبول است)
 * @param string $phone شماره موبایل (هر فرمتی)
 * @param string $message متن کامل پیام (برای خط عادی)
 * @param string $event کلید رویداد (otp_code, order_paid, ...)
 * @param array  $baseVars متغیرهای الگوی خط خدماتی اشتراکی (به ترتیب) — فقط برای melipayamak
 * @return array ['ok' => bool, 'status' => string]
 */
function sms_send($phone, $message, $event = 'custom', array $baseVars = [])
{
    $normalized = sms_normalize_phone($phone);
    $provider = setting('sms_provider', 'log');
    $sender = setting('sms_sender', '');

    if (!$normalized) {
        sms_log_write($phone, $message, $event, 'failed', 'شماره موبایل نامعتبر است');
        return ['ok' => false, 'status' => 'invalid_number'];
    }

    $result = ['ok' => false, 'status' => 'skipped'];

    switch ($provider) {
        case 'kavenegar':
            if (setting('sms_api_key', '') !== '') {
                $result = sms_send_kavenegar($normalized, $message, $sender);
            }
            break;
        case 'melipayamak':
            // خط خدماتی اشتراکی (BaseServiceNumber با الگوی تأییدشده) — اولویت دارد؛
            // اگر خط عادی توسط مشتری بلاک شده باشد، خط خدماتی رساندن پیام را تضمین می‌کند.
            // انتخاب خط در تنظیمات: shared = خط خدماتی اشتراکی، dedicated = خط اختصاصی (عادی)
            $lineMode = setting('sms_line_mode', ((int)setting('sms_base_enabled', '0') === 1) ? 'shared' : 'dedicated');
            if ($lineMode === 'shared') {
                $bodyId = (int)setting('sms_base_bodyid_' . $event, '0');
                if ($bodyId > 0 && !empty($baseVars)) {
                    $base = sms_send_melipayamak_base($normalized, $bodyId, $baseVars);
                    if ($base['ok']) {
                        $result = $base;
                        break;
                    }
                    // جایگزین: ارسال از خط عادی تا پیامک از دست نرود
                    $fb = sms_send_melipayamak($normalized, $message, $sender);
                    $result = ['ok' => $fb['ok'], 'status' => 'base(' . $base['status'] . ') | ' . $fb['status']];
                    break;
                }
            }
            $result = sms_send_melipayamak($normalized, $message, $sender);
            break;
        case 'melipayamak_api':
            // کنسول ملی پیامک (REST با API Key/UUID)
            $result = sms_send_melipayamak_api($normalized, $message, $sender);
            break;
        case 'smsir':
            $result = sms_send_smsir($normalized, $message, $sender);
            break;
        case 'farazsms':
            $result = sms_send_farazsms($normalized, $message, $sender);
            break;
        case 'custom':
            $result = sms_send_custom($normalized, $message, $sender);
            break;
        default:
            // حالت آزمایشی: بدون ارسال واقعی، فقط ثبت در لاگ
            $result = ['ok' => true, 'status' => 'logged (حالت آزمایشی - بدون ارسال واقعی)'];
    }

    sms_log_write($phone, $message, $event, $result['ok'] ? 'sent' : 'failed', $result['status']);
    return $result;
}

/** ارسال از طریق وب‌سرویس کاوه‌نگار */
function sms_send_kavenegar($receptor98, $message, $sender = '')
{
    $apiKey = setting('sms_api_key', '');
    $url = 'https://api.kavenegar.com/v1/' . rawurlencode($apiKey) . '/sms/send.json';
    $params = [
        'receptor' => $receptor98,
        'message'  => $message,
    ];
    if ($sender !== '') {
        $params['sender'] = $sender;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => 'curl error: ' . $err];
    }
    $json = json_decode($body, true);
    $code = isset($json['return']['status']) ? (int)$json['return']['status'] : $http;
    if ($code === 200) {
        return ['ok' => true, 'status' => 'kavenegar OK'];
    }
    return ['ok' => false, 'status' => 'HTTP ' . $http . ' / status ' . $code];
}

/** ارسال از طریق کنسول ملی پیامک (REST با API Key/UUID) — console.melipayamak.com */
function sms_send_melipayamak_api($receptor98, $message, $sender = '')
{
    $apiKey = setting('sms_api_key', '');
    if ($apiKey === '') {
        return ['ok' => false, 'status' => 'melipayamak_api: کلید API خالی است'];
    }
    $from = $sender !== '' ? $sender : setting('sms_sender', '');
    if ($from === '') {
        return ['ok' => false, 'status' => 'melipayamak_api: شمارهٔ فرستنده تنظیم نشده است'];
    }

    $url = 'https://console.melipayamak.com/api/send/simple/' . rawurlencode($apiKey);
    $body = json_encode([
        'from' => $from,
        'to'   => sms_strip_country($receptor98), // شمارهٔ ۱۰ رقمی (0912…)
        'text' => $message,
    ]);
    $resp = sms_http_post_json($url, $body);
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'melipayamak_api: ' . $resp['err']];
    }
    $json = json_decode($resp['body'], true);
    if ($json && isset($json['recId'])) {
        return ['ok' => true, 'status' => 'melipayamak_api OK (recId ' . (string)$json['recId'] . ')'];
    }
    // خطا: پیام خطا از سرویس (مثلاً عدم اعتبار کلید)
    $msg = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : trim((string)$resp['body']);
    return ['ok' => false, 'status' => 'melipayamak_api: ' . $msg];
}

/** ارسال از طریق ملی‌پنل (ملی پیامک) — REST وب‌سرویس */
function sms_send_melipayamak($receptor98, $message, $sender = '')
{
    $username = setting('sms_username', '');
    $password = setting('sms_password', '');
    if ($username === '' || $password === '') {
        return ['ok' => false, 'status' => 'melipayamak: نام کاربری/رمز خالی است'];
    }
    $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
    $body = json_encode([
        'username' => $username,
        'password' => $password,
        'to'       => sms_strip_country($receptor98), // 0912…
        'from'     => $sender,
        'text'     => $message,
        'isflash'  => false,
    ]);
    $resp = sms_http_post_json($url, $body);
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'melipayamak: ' . $resp['err']];
    }
    $json = json_decode($resp['body'], true);
    $ret = isset($json['RetStatus']) ? (int)$json['RetStatus'] : -1;
    $value = isset($json['Value']) ? trim((string)$json['Value']) : '';
    if ($ret === 1) {
        // Value = recId برای پیگیری تحویل (GetDeliveries)
        return ['ok' => true, 'status' => 'melipayamak OK' . ($value !== '' ? ' (recId ' . $value . ')' : ''), 'recId' => $value];
    }
    return ['ok' => false, 'status' => 'melipayamak RetStatus ' . $ret . (isset($json['StrRetStatus']) ? ' (' . $json['StrRetStatus'] . ')' : '')];
}

/** تبدیل ارقام فارسی/عربی به لاتین (برای ذخیرهٔ شماره‌ها و کدها) */
function sms_en_digits($s)
{
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($ar, $en, str_replace($fa, $en, (string)$s));
}

/**
 * همگام‌سازی خودکار کد الگوهای خدماتی (bodyId) از پنل ملی‌پیامک.
 * الگوهای تأییدشدهٔ حساب خوانده می‌شود و بر اساس عنوان/متن، به رویدادهای سایت نگاشت می‌شود.
 * @return array ['ok' => bool, 'msg' => string]
 */
function sms_base_autosync()
{
    if (!class_exists('SoapClient')) {
        return ['ok' => false, 'msg' => 'افزونهٔ SOAP روی سرور فعال نیست؛ با پشتیبانی هاست تماس بگیرید.'];
    }
    $username = setting('sms_username', '');
    $password = setting('sms_password', '');
    if ($username === '' || $password === '') {
        return ['ok' => false, 'msg' => 'ابتدا نام کاربری و رمز وب‌سرویس ملی‌پیامک را در کارت «حساب ملی‌پیامک» ذخیره کنید.'];
    }
    try {
        $client = new SoapClient('https://api.payamak-panel.com/post/SharedService.asmx?wsdl', [
            'encoding' => 'UTF-8', 'trace' => 1, 'exceptions' => true, 'connection_timeout' => 15,
        ]);
        $res = $client->GetSharedServiceBody(['username' => $username, 'password' => $password]);
        $r = $res->GetSharedServiceBodyResult ?? $res;
    } catch (Throwable $t) {
        return ['ok' => false, 'msg' => 'خطا در اتصال به ملی‌پیامک: ' . $t->getMessage()];
    }

    // نتیجه ممکن است رشتهٔ JSON یا آبجکت/آرایه با هر ساختاری باشد — پیمایش بازگشتی تا رسیدن به رکوردهای BodyID
    $raw = null;
    if (is_string($r)) {
        $raw = json_decode($r, true);
    } else {
        $raw = json_decode(json_encode($r), true); // آبجکت → آرایهٔ عمیق
    }
    $flat = [];
    $walk = function ($node) use (&$walk, &$flat) {
        if (!is_array($node)) return;
        if (isset($node['BodyID'])) {
            $flat[] = $node;
            return;
        }
        foreach ($node as $child) $walk($child);
    };
    $walk($raw);

    $need = ['otp_code' => 1, 'password_reset' => 1, 'order_paid' => 3, 'stock_back' => 1, 'welcome' => 2, 'admin_new_order' => 4, 'admin_new_user' => 3, 'admin_new_chat' => 2];
    $labels = ['otp_code' => 'کد ورود', 'password_reset' => 'بازیابی رمز', 'order_paid' => 'پرداخت سفارش', 'stock_back' => 'موجودشدن کالا', 'welcome' => 'خوش‌آمد', 'admin_new_order' => 'اطلاع سفارش به ادمین', 'admin_new_user' => 'اطلاع کاربر جدید به ادمین', 'admin_new_chat' => 'اطلاع چت به ادمین'];
    $found = [];
    foreach ($flat as $it) {
        $it = (array)$it;
        $bid = (int)($it['BodyID'] ?? 0);
        $status = (int)($it['BodyStatus'] ?? 0);
        $title = (string)($it['Title'] ?? '');
        $body = (string)($it['Body'] ?? '');
        if ($bid <= 0 || $status !== 1) continue;
        $hay = $title . ' ' . $body;
        preg_match_all('/\{(\d+)\}/', $body, $m);
        $nvars = !empty($m[1]) ? (max($m[1]) + 1) : 0;
        $event = '';
        if (stripos($hay, 'otp') !== false || stripos($hay, 'کد ورود') !== false || stripos($hay, 'کد تایید') !== false) {
            $event = 'otp_code';
        } elseif (stripos($hay, 'بازیابی') !== false) {
            $event = 'password_reset';
        } elseif (stripos($hay, 'موجود') !== false) {
            $event = 'stock_back';
        } elseif (stripos($hay, 'خوش آمد') !== false || stripos($hay, 'خوش‌آمد') !== false || stripos($hay, 'عضویت') !== false) {
            $event = 'welcome';
        } elseif (stripos($hay, 'سفارش جدید') !== false) {
            // قبل از الگوی مشتری «سفارش/پرداخت» چک شود — کلمهٔ «سفارش» مشترک است
            $event = 'admin_new_order';
        } elseif (stripos($hay, 'کاربر جدید') !== false || stripos($hay, 'ثبت‌نام کرد') !== false) {
            $event = 'admin_new_user';
        } elseif (stripos($hay, 'پیام چت') !== false) {
            $event = 'admin_new_chat';
        } elseif (stripos($hay, 'پرداخت') !== false || stripos($hay, 'سفارش') !== false) {
            $event = 'order_paid';
        }
        if ($event === '' || !isset($need[$event])) continue;
        $score = abs($nvars - $need[$event]);
        if (!isset($found[$event]) || $score < $found[$event]['score']) {
            $found[$event] = ['bid' => $bid, 'score' => $score, 'title' => $title];
        }
    }

    $applied = [];
    foreach ($found as $event => $f) {
        set_setting('sms_base_bodyid_' . $event, (string)$f['bid']);
        $applied[] = $labels[$event] . ' = ' . $f['bid'];
    }
    if ($applied) {
        return ['ok' => true, 'msg' => 'کدهای الگو از ملی‌پیامک خوانده و ثبت شد: ' . implode('، ', $applied)];
    }
    return ['ok' => false, 'msg' => 'الگوی تأییدشدهٔ منطبقی در پنل ملی‌پیامک پیدا نشد. ابتدا الگوها را طبق راهنمای پایین ثبت و تأیید کنید.'];
}
/**
 * ارسال از خط خدماتی اشتراکی ملی‌پیامک (BaseServiceNumber) با الگوی تأییدشده.
 * متغیرها به ترتیب و جدا با «;» ارسال می‌شوند؛ متن کامل الگو در سامانهٔ ملی‌پیامک تعریف شده است.
 * راهنما: login.melipayamak.com/Files/webservice-SharedNumber.pdf
 */
function sms_send_melipayamak_base($receptor98, $bodyId, array $vars = [])
{
    $username = setting('sms_username', '');
    $password = setting('sms_password', '');
    if ($username === '' || $password === '') {
        return ['ok' => false, 'status' => 'melipayamak_base: نام کاربری/رمز خالی است'];
    }
    $url = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
    $body = json_encode([
        'username' => $username,
        'password' => $password,
        'text'     => implode(';', array_map('strval', $vars)),
        'to'       => sms_strip_country($receptor98), // 0912…
        'bodyId'   => (int)$bodyId,
    ]);
    $resp = sms_http_post_json($url, $body);
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'melipayamak_base: ' . $resp['err']];
    }
    $json = json_decode($resp['body'], true);
    $ret   = isset($json['RetStatus']) ? (int)$json['RetStatus'] : -1;
    $value = isset($json['Value']) ? trim((string)$json['Value']) : '';
    // موفقیت: RetStatus=1 و Value برابر recId با بیش از ۱۵ رقم
    if ($ret === 1 && preg_match('/^\d{16,}$/', $value)) {
        return ['ok' => true, 'status' => 'melipayamak_base OK (recId ' . $value . ')'];
    }
    return ['ok' => false, 'status' => 'melipayamak_base RetStatus ' . $ret . ($value !== '' ? ' Value=' . $value : '') . (isset($json['StrRetStatus']) ? ' (' . $json['StrRetStatus'] . ')' : '')];
}

/** ارسال از طریق sms.ir (REST با توکن) */
function sms_send_smsir($receptor98, $message, $sender = '')
{
    $token = setting('sms_api_key', '');
    if ($token === '') {
        return ['ok' => false, 'status' => 'sms.ir: توکن خالی است'];
    }
    $url = 'https://api.sms.ir/v1/send/bulk';
    $body = json_encode([
        'lineNumber' => $sender,
        'messageText' => $message,
        'mobiles' => [sms_strip_country($receptor98)],
    ]);
    $resp = sms_http_post_json($url, $body, ['x-api-key: ' . $token]);
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'sms.ir: ' . $resp['err']];
    }
    $json = json_decode($resp['body'], true);
    $status = isset($json['status']) ? (int)$json['status'] : -1;
    if ($status === 1) {
        return ['ok' => true, 'status' => 'sms.ir OK'];
    }
    return ['ok' => false, 'status' => 'sms.ir status ' . $status . (isset($json['message']) ? ' ' . $json['message'] : '')];
}

/** ارسال از طریق فراز اس‌ام‌اس (وب‌سرویس REST) */
function sms_send_farazsms($receptor98, $message, $sender = '')
{
    $username = setting('sms_username', '');
    $password = setting('sms_password', '');
    $from = $sender !== '' ? $sender : setting('sms_sender', '');
    if ($username === '' || $password === '') {
        return ['ok' => false, 'status' => 'farazsms: نام کاربری/رمز خالی است'];
    }
    // فراز اس‌ام‌اس از REST با پارامترهای فرم استفاده می‌کند
    $url = 'https://rest.ippanel.com/v1/messages';
    $payload = http_build_query([
        'op'        => 'send',
        'uname'     => $username,
        'pass'      => $password,
        'message'   => $message,
        'to'        => sms_strip_country($receptor98),
        'from'      => $from,
    ]);
    $resp = sms_http_post_form($url, $payload);
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'farazsms: ' . $resp['err']];
    }
    $code = (int)trim($resp['body']);
    if (strpos($resp['body'], 'OK') !== false || ($code > 0 && $code < 100000)) {
        return ['ok' => true, 'status' => 'farazsms OK (' . trim($resp['body']) . ')'];
    }
    return ['ok' => false, 'status' => 'farazsms resp ' . trim($resp['body'])];
}

/** ارسال از طریق درگاه سفارشی (URL قابل تنظیم توسط کاربر) */
function sms_send_custom($receptor98, $message, $sender = '')
{
    $url = setting('sms_custom_url', '');
    if ($url === '') {
        return ['ok' => false, 'status' => 'custom: آدرس وب‌سرویس خالی است'];
    }
    $method = strtoupper(setting('sms_custom_method', 'POST'));
    $paramsTpl = setting('sms_custom_params', 'to={to}&message={message}&sender={sender}');
    $params = str_replace(
        ['{to}', '{message}', '{sender}', '{api_key}', '{username}', '{password}'],
        [$receptor98, rawurlencode($message), rawurlencode($sender), setting('sms_api_key', ''), setting('sms_username', ''), setting('sms_password', '')],
        $paramsTpl
    );
    // جای‌گذاری مقدار خام برای {message} (بدون urlencode در صورت وجود) — از rawurlencode استفاده شده؛
    // برای {to} و {sender} هم ممکن است کاربر بخواهد خام باشد، ولی امن‌تر encode شده است.

    if ($method === 'GET') {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $full = $url . $sep . $params;
        $resp = sms_http_get($full);
    } else {
        $resp = sms_http_post_form($url, $params);
    }
    if ($resp['err'] !== null) {
        return ['ok' => false, 'status' => 'custom: ' . $resp['err']];
    }
    return ['ok' => true, 'status' => 'custom OK (' . substr(trim($resp['body']), 0, 80) . ')'];
}

/** حذف پیش‌شمارهٔ 98 برای درگاه‌هایی که شمارهٔ 10 رقمی می‌خواهند */
function sms_strip_country($receptor98)
{
    if (strlen($receptor98) === 12 && strpos($receptor98, '98') === 0) {
        return '0' . substr($receptor98, 2);
    }
    return $receptor98;
}

/** POST با JSON */
function sms_http_post_json($url, $jsonBody, $headers = [])
{
    $ch = curl_init();
    $h = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'err' => $body === false ? $err : null];
}

/** POST با form-urlencoded */
function sms_http_post_form($url, $params)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'err' => $body === false ? $err : null];
}

/** GET */
function sms_http_get($url)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'err' => $body === false ? $err : null];
}

/** ثبت در جدول لاگ پیامک */
function sms_log_write($phone, $message, $event, $status, $note = '')
{
    try {
        $st = db()->prepare("INSERT INTO sms_log (phone, message, event, status, provider_note)
                             VALUES (?, ?, ?, ?, ?)");
        $st->execute([(string)$phone, (string)$message, (string)$event, (string)$status, (string)$note]);
        return true;
    } catch (Throwable $t) {
        error_log('[sms] log failed: ' . $t->getMessage());
        return false;
    }
}

/** آخرین رکوردهای لاگ پیامک برای پنل مدیریت */
function sms_log_recent($limit = 60)
{
    $limit = max(1, min(300, (int)$limit));
    try {
        return db()->query("SELECT * FROM sms_log ORDER BY id DESC LIMIT $limit")->fetchAll();
    } catch (Throwable $t) {
        return [];
    }
}

/** برچسب فارسی رویداد پیامک برای نمایش در لاگ */
function sms_event_label($event)
{
    $map = [
        'welcome'         => 'خوش‌آمد عضویت',
        'order_paid'      => 'پرداخت سفارش',
        'points_adjusted' => 'تغییر امتیاز',
        'admin_manual'    => 'دستی (تکی)',
        'admin_bulk'      => 'دستی (گروهی)',
        'referral_earned' => 'پاداش معرفی',
        'referral_joined' => 'عضویت با معرف',
        'otp_code'        => 'کد ورود (OTP)',
        'password_reset'  => 'بازیابی رمز عبور',
        'stock_back'      => 'موجودشدن کالا',
        'admin_new_order' => 'اطلاع سفارش جدید به ادمین',
        'admin_new_chat'  => 'اطلاع پیام چت به ادمین',
        'admin_new_user'  => 'اطلاع ثبت‌نام کاربر جدید',
        'custom'          => 'سایر',
    ];
    return isset($map[$event]) ? $map[$event] : (string)$event;
}

/**
 * ارسال پیامک اطلاع‌رسانی به همهٔ ادمین‌ها (شماره‌ها از تنظیم notify_admin_phones)
 * @param string $message متن کامل پیام
 * @param string $event کلید رویداد (admin_new_order / admin_new_chat / admin_new_user)
 * @param array  $baseVars متغیرهای الگوی خط خدماتی (به ترتیب {0}، {1}، ...) — اختیاری
 * @return int تعداد شماره‌هایی که ارسال برایشان انجام شد
 */
function sms_notify_admins($message, $event, array $baseVars = [])
{
    $raw = (string)setting('notify_admin_phones', '');
    if (trim($raw) === '') {
        return 0;
    }
    $phones = preg_split('/[,،;\s]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    // جداکنندهٔ متغیرهای خط خدماتی «;» است؛ داخل مقادیر نباید بیاید
    $baseVars = array_map(function ($v) { return str_replace(';', '،', (string)$v); }, $baseVars);
    $sent = 0;
    foreach ($phones as $ph) {
        $r = sms_send($ph, $message, $event, $baseVars);
        if (empty($r['ok'])) {
            // خطای گذرای سرویس (مثل RetStatus 35) → یک تلاش مجدد تا اطلاع از دست نرود
            sleep(2);
            $r = sms_send($ph, $message, $event, $baseVars);
        }
        if (!empty($r['ok'])) {
            $sent++;
        }
    }
    return $sent;
}
