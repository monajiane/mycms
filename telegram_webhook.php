<?php
/**
 * telegram_webhook.php — Telegram bot admin panel v2
 * ------------------------------------------------------------------
 * این ربات، پنل ادمین از راه دور فروشگاه است. دستورها فقط برای
 * شناسه‌های ثبت‌شده در tg_chat_admin_ids مجاز هستند.
 *
 * URL: https://abzar-shargh.ir/telegram_webhook.php
 * Register: https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://abzar-shargh.ir/telegram_webhook.php
 *
 * دستورها:
 *   /start, /help, /menu   معرفی و منوی اصلی
 *   /chatid                 دریافت شناسهٔ چت
 *   /ping, /uptime          بررسی فعال‌بودن سایت و دیتابیس
 *   /stats                  آمار لحظه‌ای فروشگاه
 *   /orders [N]             آخرین N سفارش (پیش‌فرض ۵)
 *   /order ID               جزئیات یک سفارش + دکمه‌های تغییر وضعیت
 *   /status ID new_status   تغییر وضعیت سفارش (pending/processing/shipped/delivered/cancelled)
 *   /products [N]           آخرین N محصول
 *   /product ID             جزئیات + دکمهٔ فعال/غیرفعال‌سازی
 *   /toggle ID              فعال/غیرفعال‌کردن محصول
 *   /users [N]              آخرین N کاربر
 *   /user ID                جزئیات یک کاربر
 *   /find متن               جست‌وجوی محصول بر اساس نام
 *   /reply userId متن       ارسال پاسخ به کاربر در سیستم پیام‌رسانی داخلی
 *   /broadcast متن          ارسال به همهٔ ادمین‌ها (برای هماهنگی)
 *   /settings               نمایش تنظیمات کلیدی سایت
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/telegram.php';

header('Content-Type: text/plain; charset=utf-8');
ignore_user_abort(true);

$raw = file_get_contents('php://input');
if ($raw === '' || $raw === false) { echo 'no_input • kb-build 2026-09-09-h'; exit; } // نشانگر نسخه — برای تشخیص آپلود موفق
$upd = json_decode($raw, true);
if (!is_array($upd)) { echo 'bad_json'; exit; }

// ----- استخراج پیام -----
$msg = $upd['message'] ?? $upd['edited_message'] ?? null;
$cb = $upd['callback_query'] ?? null;
// callback_query: وقتی کاربر روی دکمهٔ شیشه‌ای کلیک می‌کند
if ($cb) {
    $chatId = (string)($cb['message']['chat']['id'] ?? '');
    $data   = (string)($cb['data'] ?? '');
    $cbId   = (string)($cb['id'] ?? '');
    if (!in_array($chatId, tg_admin_chat_ids(), true)) {
        // سکوت برای غیرادمین
        tg_answer_callback($cbId, 'دسترسی ندارید');
        echo 'ok'; exit;
    }
    // پاسخ سریع به تلگرام (بدون پیام)
    tg_answer_callback($cbId);
    tg_handle_callback($chatId, $data);
    echo 'ok'; exit;
}
if (!$msg) { echo 'ignored'; exit; }
$chatId = (string)($msg['chat']['id'] ?? '');
$text   = trim((string)($msg['text'] ?? ''));
$from   = $msg['from'] ?? [];
$firstName = (string)($from['first_name'] ?? '');
$isAdmin = in_array($chatId, tg_admin_chat_ids(), true);

// ----- نگاشت دکمه‌های شیشه‌ای پایین چت به دستور -----
// وقتی کاربر دکمه را لمس می‌کند، متن دکمه به‌عنوان پیام می‌رسد؛ اینجا به دستور تبدیل می‌شود
$btnMap = [
    '📊 آمار'       => '/stats',
    '📦 سفارش‌ها'   => '/orders',
    '🛍 محصولات'    => '/products',
    '👥 کاربران'    => '/users',
    '🔍 جست‌وجو'    => '/find',
    '⚠️ موجودی کم'  => '/products low',
    '🏓 وضعیت سایت' => '/ping',
    '⚙️ تنظیمات'    => '/settings',
    '🔔 هشدارها'    => '/alert list',
    '❓ راهنما'      => '/help',
    '🆔 شناسه من'   => '/chatid',
];
if (isset($btnMap[$text])) { $text = $btnMap[$text]; }

// ----- دستورهای عمومی (برای همه) -----
if (preg_match('#^/start\b#i', $text)) {
    $reply = "👋 سلام " . ($firstName ? " <b>" . htmlspecialchars($firstName) . "</b>" : '') . "!\n" .
             "این ربات، <b>پنل ادمین فروشگاه ابزار شرق</b> است.\n\n" .
             "اگر مدیر هستید، /menu را بزنید.\n" .
             "برای دریافت شناسه چت خود: /chatid";
    if ($isAdmin) {
        tg_send($chatId, $reply, tg_panel_reply_keyboard());
    } else {
        tg_send($chatId, $reply);
    }
    echo 'ok'; exit;
}
if (preg_match('#^/(chatid|myid)\b#i', $text)) {
    tg_send($chatId, "🆔 شناسهٔ چت شما: <code>" . htmlspecialchars($chatId, ENT_QUOTES) . "</code>\n\nاگر مدیر هستید، این شناسه را در تنظیمات تلگرام ادمین سایت وارد کنید.");
    echo 'ok'; exit;
}
if (preg_match('#^/help\b#i', $text)) {
    tg_send($chatId, tg_help_text());
    echo 'ok'; exit;
}

// ----- دروازهٔ ادمین -----
if (!$isAdmin) {
    // برای غیرادمین سکوت می‌کنیم تا ربات خصوصی بماند
    echo 'ok'; exit;
}

// ----- حالت «در انتظار پاسخ» — پاسخ به مشتری از داخل ربات -----
$pendingKey = 'tg_pending_reply_' . $chatId;
$pending = tg_setting($pendingKey, '');
if ($pending !== '') {
    $pParts = explode('|', $pending);
    $pUid = (int)($pParts[0] ?? 0);
    $pRootId = (int)($pParts[1] ?? 0);
    $pTs  = (int)($pParts[2] ?? 0);
    if ($pTs < time() - 1800) {
        tg_set_setting($pendingKey, '');
        tg_send($chatId, "⌛ حالت پاسخ به کاربر #$pUid منقضی شد (۳۰ دقیقه). برای پاسخ دوباره روی دکمهٔ ✍️ پاسخ در ربات بزنید.");
    } elseif (preg_match('#^/cancel\b#i', $text)) {
        tg_set_setting($pendingKey, '');
        tg_send($chatId, "↩️ حالت پاسخ لغو شد.");
        echo 'ok'; exit;
    } elseif ($text !== '' && $text[0] !== '/') {
        // متن آزاد = بدنهٔ پاسخ به مشتری (داخل همان گفتگو)
        try {
            $pSubject = 'پاسخ پشتیبان';
            if ($pRootId > 0) {
                $pst = db()->prepare("SELECT subject FROM messages WHERE id = ? AND user_id = ?");
                $pst->execute([$pRootId, $pUid]);
                $srow = $pst->fetch(PDO::FETCH_ASSOC);
                if ($srow && trim((string)$srow['subject']) !== '') $pSubject = (string)$srow['subject'];
            }
            send_message($pUid, $pSubject, $text, 1, $pRootId > 0 ? $pRootId : null);
            tg_set_setting($pendingKey, '');
            tg_send($chatId, "✅ پاسخ شما به کاربر #$pUid ارسال شد" . ($pRootId > 0 ? " (داخل گفتگو #" . $pRootId . " + اطلاع‌رسانی به مشتری)" : "") . ":\n\n" . htmlspecialchars(mb_substr($text, 0, 500), ENT_QUOTES, 'UTF-8'));
        } catch (Throwable $e) {
            tg_send($chatId, "❌ ارسال پاسخ ناموفق بود: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        echo 'ok'; exit;
    }
    // اگر دستور تایپ شد، پردازش عادی ادامه می‌یابد و حالت باقی می‌ماند
}

// =============== گزارش ۷ روز / بی‌پاسخ‌ها / جست‌وجوی کاربر / ویژه / حراج ===============
if (preg_match('#^/week\b#i', $text)) { tg_send_week_report($chatId); echo 'ok'; exit; }
if (preg_match('#^/todo\b#i', $text)) { tg_send_todo_list($chatId); echo 'ok'; exit; }
if (preg_match('#^/finduser\s+(.+)$#si', $text, $mFu)) { tg_find_users($chatId, trim($mFu[1])); echo 'ok'; exit; }
if (preg_match('#^/finduser\s*$#i', $text)) { tg_send($chatId, "🔍 عبارت جست‌وجو را بنویسید:\n<code>/finduser 0912</code> یا <code>/finduser نام</code>"); echo 'ok'; exit; }
if (preg_match('#^/feature\s+(\d+)$#i', $text, $mFt)) { tg_toggle_featured($chatId, (int)$mFt[1]); echo 'ok'; exit; }
if (preg_match('#^/sale\s+(\d+)\s+(.+)$#si', $text, $mSa)) { tg_set_sale($chatId, (int)$mSa[1], trim($mSa[2])); echo 'ok'; exit; }

// =============== منوی اصلی ===============
if (preg_match('#^/(menu|panel)\b#i', $text)) {
    tg_send($chatId, "🛠 <b>پنل ادمین ابزار شرق</b>\n\nدرخواست‌تان را با لمس دکمهٔ پایین چت بفرستید:", tg_panel_reply_keyboard());
    echo 'ok'; exit;
}

// =============== /ping, /uptime ===============
if (preg_match('#^/(ping|uptime)\b#i', $text)) {
    $t0 = microtime(true);
    $dbOk = false; $dbMsg = 'نامشخص';
    try {
        $r = db()->query("SELECT 1")->fetchColumn();
        $dbOk = ((string)$r === '1');
        $dbMsg = $dbOk ? 'پاسخ‌گو ✅' : 'پاسخ غیرعادی ❌';
    } catch (Throwable $e) { $dbMsg = 'خطا: ' . $e->getMessage(); }
    $latency = round((microtime(true) - $t0) * 1000);
    $site = defined('BASE_URL') ? BASE_URL : 'https://abzar-shargh.ir';
    $code = @file_get_contents($site, false, stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true]]));
    $siteOk = $code !== false && strlen((string)$code) > 100;
    $kb = tg_keyboard_json([[
        ['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:stats'],
        ['text' => '📦 سفارش‌ها', 'callback_data' => 'cmd:orders:5'],
    ], [
        ['text' => '🛍 محصولات', 'callback_data' => 'cmd:products:5'],
        ['text' => '👥 کاربران', 'callback_data' => 'cmd:users:5'],
    ]]);
    tg_send($chatId,
        "🏓 <b>وضعیت سایت</b>\n\n" .
        "• سایت: " . ($siteOk ? "فعال ✅" : "غیرفعال ❌") . "\n" .
        "• دیتابیس: $dbMsg\n" .
        "• تأخیر کوئری: {$latency} میلی‌ثانیه\n" .
        "• سرور: " . php_uname('s') . " " . php_uname('m') . "\n" .
        "• زمان پاسخ: " . date('Y-m-d H:i:s'),
        ['reply_markup' => $kb]
    );
    echo 'ok'; exit;
}

// =============== /stats ===============
if (preg_match('#^/stats\b#i', $text)) {
    tg_send_stats($chatId);
    echo 'ok'; exit;
}

// =============== /orders [N] ===============
if (preg_match('#^/orders?\s*(\d*)$#i', $text, $m)) {
    $n = max(1, min(20, (int)($m[1] ?: 5)));
    tg_send_orders_list($chatId, $n);
    echo 'ok'; exit;
}

// =============== /order ID ===============
if (preg_match('#^/order\s+(\d+)$#i', $text, $m)) {
    tg_send_order_detail($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== /status ID new_status ===============
if (preg_match('#^/status\s+(\d+)\s+([a-z_]+)$#i', $text, $m)) {
    tg_change_order_status($chatId, (int)$m[1], strtolower($m[2]));
    echo 'ok'; exit;
}

// =============== /products [N|low] ===============
if (preg_match('#^/products?\s*(low|\d*)$#i', $text, $m)) {
    if (strtolower((string)$m[1]) === 'low') {
        tg_send_low_stock_list($chatId);
        echo 'ok'; exit;
    }
    $n = max(1, min(20, (int)($m[1] ?: 5)));
    tg_send_products_list($chatId, $n);
    echo 'ok'; exit;
}

// =============== /product ID ===============
if (preg_match('#^/product\s+(\d+)$#i', $text, $m)) {
    tg_send_product_detail($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== /toggle ID ===============
if (preg_match('#^/toggle\s+(\d+)$#i', $text, $m)) {
    tg_toggle_product($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== /users [N] ===============
if (preg_match('#^/users?\s*(\d*)$#i', $text, $m)) {
    $n = max(1, min(20, (int)($m[1] ?: 5)));
    tg_send_users_list($chatId, $n);
    echo 'ok'; exit;
}

// =============== /user ID ===============
if (preg_match('#^/user\s+(\d+)$#i', $text, $m)) {
    tg_send_user_detail($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== /find (بدون متن — راهنما) ===============
if (preg_match('#^/find\s*$#i', $text)) {
    tg_send($chatId, "🔍 نام محصول (یا بخشی از آن) را بنویسید:\n<code>/find مته</code>");
    echo 'ok'; exit;
}

// =============== /find متن ===============
if (preg_match('#^/find\s+(.+)$#si', $text, $m)) {
    tg_find_products($chatId, trim($m[1]));
    echo 'ok'; exit;
}

// =============== /reply userId متن ===============
if (preg_match('#^/reply\s+(\d+)\s+(.+)$#su', $text, $m)) {
    $userId = (int)$m[1];
    $body   = trim($m[2]);
    if ($userId > 0 && $body !== '' && function_exists('send_message')) {
        try {
            send_message($userId, 'پاسخ پشتیبان', $body, 1, null);
            tg_send($chatId, "✅ پاسخ به کاربر #$userId ارسال شد.");
        } catch (Throwable $t) {
            tg_send($chatId, "❌ خطا: " . $t->getMessage());
        }
    } else {
        tg_send($chatId, "فرمت: /reply <userId> <پیام>");
    }
    echo 'ok'; exit;
}

// =============== /broadcast متن ===============
if (preg_match('#^/broadcast\s+(.+)$#su', $text, $m)) {
    $msg = trim($m[1]);
    $count = tg_notify_admins("📣 <b>پیام داخلی</b>\n\n" . htmlspecialchars($msg, ENT_QUOTES), 'broadcast');
    tg_send($chatId, "✅ پیام به $count ادمین ارسال شد.");
    echo 'ok'; exit;
}

// =============== /settings ===============
if (preg_match('#^/settings\b#i', $text)) {
    tg_send_settings($chatId);
    echo 'ok'; exit;
}

// =============== /stock ID N ===============
if (preg_match('#^/stock\s+(\d+)\s+(\d+)$#i', $text, $m)) {
    tg_set_stock($chatId, (int)$m[1], (int)$m[2]);
    echo 'ok'; exit;
}

// =============== /price ID N ===============
if (preg_match('#^/price\s+(\d+)\s+(\d+)$#i', $text, $m)) {
    tg_set_price($chatId, (int)$m[1], (int)$m[2]);
    echo 'ok'; exit;
}

// =============== /ban userId دلیل ===============
if (preg_match('#^/ban\s+(\d+)\s*(.*)$#si', $text, $m)) {
    tg_ban_user($chatId, (int)$m[1], trim($m[2]));
    echo 'ok'; exit;
}

// =============== /unban userId ===============
if (preg_match('#^/unban\s+(\d+)$#i', $text, $m)) {
    tg_unban_user($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== /alert add productId threshold ===============
if (preg_match('#^/alert\s+add\s+(\d+)\s+(\d+)$#i', $text, $m)) {
    tg_alert_add($chatId, (int)$m[1], (int)$m[2]);
    echo 'ok'; exit;
}
if (preg_match('#^/alert\s+list\b#i', $text)) {
    tg_alert_list($chatId);
    echo 'ok'; exit;
}
if (preg_match('#^/alert\s+remove\s+(\d+)$#i', $text, $m)) {
    tg_alert_remove($chatId, (int)$m[1]);
    echo 'ok'; exit;
}

// =============== ناشناس ===============
tg_send($chatId, "❓ دستور ناشناخته.\n\n" . tg_help_text());
echo 'ok';
exit;


// =====================================================================
// پیاده‌سازی دستورها
// =====================================================================

function tg_help_text() {
    return "📚 <b>راهنمای دستورها</b>\n\n" .
        "<b>اطلاعاتی:</b>\n" .
        "• دکمه‌های شیشه‌ای پایین چت — لمس کنید تا دستور ارسال شود\n" .
        "• گزارش‌ها: /week — فروش ۷ روز | /todo — گفتگوهای بی‌پاسخ\n" .
        "• کاربر: /finduser 0912 | ویژه: /feature ID | حراج: /sale ID مبلغ\n" .
        "• /menu — منوی اصلی با دکمه\n" .
        "• /ping — وضعیت سایت و دیتابیس\n" .
        "• /stats — آمار لحظه‌ای فروشگاه\n" .
        "• /settings — تنظیمات کلیدی سایت\n\n" .
        "<b>سفارش‌ها:</b>\n" .
        "• /orders [N] — آخرین N سفارش (پیش‌فرض ۵)\n" .
        "• /order ID — جزئیات یک سفارش\n" .
        "• /status ID new — تغییر وضعیت\n" .
        "  مقادیر مجاز: pending, processing, shipped, delivered, cancelled\n\n" .
        "<b>محصولات:</b>\n" .
        "• /products [N] — آخرین N محصول\n" .
        "• /product ID — جزئیات + دکمهٔ فعال/غیرفعال\n" .
        "• /toggle ID — فعال/غیرفعال‌سازی سریع\n" .
        "• /find متن — جست‌وجوی محصول\n\n" .
        "<b>کاربران:</b>\n" .
        "• /users [N] — آخرین N کاربر\n" .
        "• /user ID — جزئیات کاربر\n\n" .
        "<b>ارتباطات:</b>\n" .
        "• /reply userId متن — پاسخ به کاربر\n" .
        "• /broadcast متن — ارسال به همهٔ ادمین‌ها\n";
}

/** کیبورد شیشه‌ای پایین چت (ReplyKeyboardMarkup) — لمس دکمه = ارسال دستور، بدون تایپ */
function tg_panel_reply_keyboard() {
    $rows = [
        [['text' => '📊 آمار'], ['text' => '📦 سفارش‌ها']],
        [['text' => '🛍 محصولات'], ['text' => '👥 کاربران']],
        [['text' => '🔍 جست‌وجو'], ['text' => '⚠️ موجودی کم']],
        [['text' => '🏓 وضعیت سایت'], ['text' => '⚙️ تنظیمات']],
        [['text' => '🔔 هشدارها'], ['text' => '❓ راهنما']],
    ];
    return ['reply_markup' => json_encode([
        'keyboard'                => $rows,
        'resize_keyboard'         => true,
        'is_persistent'           => true,
        'input_field_placeholder' => 'یا دستور را تایپ کنید…',
    ], JSON_UNESCAPED_UNICODE)];
}

/** لیست محصولات با موجودی کم (برای /products low و دکمهٔ شیشه‌ای) */
function tg_send_low_stock_list($chatId) {
    try {
        $st = db()->query("SELECT id, name, stock, active FROM products WHERE stock IS NOT NULL AND stock <= 3 ORDER BY stock ASC LIMIT 20");
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "✅ همهٔ محصولات موجودی کافی دارند."); return; }
        $text = "⚠️ <b>محصولات با موجودی کم</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $emoji = $r['active'] ? '🟢' : '🔴';
            $text .= "$emoji <b>#" . $r['id'] . "</b> " . htmlspecialchars((string)$r['name'], ENT_QUOTES) . "\n   📦 موجودی: " . fa_digits((int)$r['stock']) . "\n\n";
            $rows_kb[] = [['text' => "$emoji #" . $r['id'] . " • " . htmlspecialchars(mb_substr((string)$r['name'], 0, 18), ENT_QUOTES), 'callback_data' => 'cmd:product:' . $r['id']]];
        }
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ " . $e->getMessage());
    }
}

function tg_main_menu_keyboard() {
    return ['reply_markup' => tg_keyboard_json([
        [
            ['text' => '📊 آمار', 'callback_data' => 'cmd:stats'],
            ['text' => '🏓 وضعیت سایت', 'callback_data' => 'cmd:ping'],
        ],
        [
            ['text' => '📦 سفارش‌ها', 'callback_data' => 'cmd:orders:5'],
            ['text' => '🛍 محصولات', 'callback_data' => 'cmd:products:5'],
        ],
        [
            ['text' => '👥 کاربران', 'callback_data' => 'cmd:users:5'],
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'cmd:settings'],
        ],
        [
            ['text' => '🔍 جست‌وجوی محصول', 'callback_data' => 'cmd:find'],
            ['text' => '❓ راهنما', 'callback_data' => 'cmd:help'],
        ],
    ])];
}

function tg_send_stats($chatId) {
    try {
        $db = db();
        $today = date('Y-m-d');
        $stats = [
            'orders_total'    => (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'orders_today'    => (int)$db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn(),
            'orders_pending'  => (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
            'revenue_today'   => (int)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at) = '$today' AND status NOT IN ('cancelled')")->fetchColumn(),
            'revenue_total'   => (int)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn(),
            'users_total'     => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'users_today'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$today'")->fetchColumn(),
            'products_total'  => (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'products_active' => (int)$db->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn(),
            'products_low'    => (int)$db->query("SELECT COUNT(*) FROM products WHERE stock IS NOT NULL AND stock <= 3")->fetchColumn(),
            'unread_messages' => (int)$db->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn(),
        ];
        $text = "📊 <b>آمار لحظه‌ای فروشگاه</b>\n\n" .
            "📦 <b>سفارش‌ها:</b>\n" .
            "• امروز: " . fa_digits($stats['orders_today']) . " سفارش\n" .
            "• در انتظار پردازش: " . fa_digits($stats['orders_pending']) . "\n" .
            "• کل: " . fa_digits($stats['orders_total']) . "\n\n" .
            "💰 <b>درآمد:</b>\n" .
            "• امروز: " . fmt_price($stats['revenue_today']) . "\n" .
            "• کل: " . fmt_price($stats['revenue_total']) . "\n\n" .
            "👥 <b>کاربران:</b>\n" .
            "• امروز: " . fa_digits($stats['users_today']) . "\n" .
            "• کل: " . fa_digits($stats['users_total']) . "\n\n" .
            "🛍 <b>محصولات:</b>\n" .
            "• فعال: " . fa_digits($stats['products_active']) . " از " . fa_digits($stats['products_total']) . "\n" .
            ($stats['products_low'] > 0 ? "⚠️ موجودی کم: " . fa_digits($stats['products_low']) . " قلم\n" : '') .
            ($stats['unread_messages'] > 0 ? "📬 پیام خوانده‌نشده: " . fa_digits($stats['unread_messages']) . "\n" : '');
        $kb = tg_keyboard_json([
            [
                ['text' => '📦 آخرین سفارش‌ها', 'callback_data' => 'cmd:orders:5'],
                ['text' => '🛍 محصولات', 'callback_data' => 'cmd:products:5'],
            ],
            [
                ['text' => '⚠️ موجودی کم', 'callback_data' => 'cmd:products:low'],
                ['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:stats'],
            ],
            [
                ['text' => '📈 گزارش ۷ روز', 'callback_data' => 'cmd:week'],
                ['text' => '💬 بی‌پاسخ‌ها', 'callback_data' => 'cmd:todo'],
            ],
        ]);
        tg_send($chatId, $text, ['reply_markup' => $kb]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا در خواندن آمار: " . $e->getMessage());
    }
}

function tg_send_orders_list($chatId, $n) {
    try {
        $st = db()->prepare("SELECT o.id, o.total_amount, o.status, o.created_at, COALESCE(u.full_name,u.phone,'مهمان') AS customer
                             FROM orders o LEFT JOIN users u ON u.id = o.user_id
                             ORDER BY o.id DESC LIMIT " . (int)$n);
        $st->execute();
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "📭 سفارشی یافت نشد."); return; }
        $text = "📦 <b>آخرین " . fa_digits(count($rows)) . " سفارش</b>\n\n";
        foreach ($rows as $r) {
            $emoji = tg_status_emoji($r['status']);
            $text .= "$emoji <b>#" . $r['id'] . "</b> — " . htmlspecialchars((string)$r['customer'], ENT_QUOTES) . "\n" .
                     "   💰 " . fmt_price((int)($r['total_amount'] ?? 0)) . " • " . tg_status_label($r['status']) . "\n" .
                     "   🕓 " . $r['created_at'] . "\n\n";
        }
        $rows_kb = [];
        foreach ($rows as $r) {
            $rows_kb[] = [['text' => "$emoji #" . $r['id'] . " • " . htmlspecialchars(mb_substr((string)$r['customer'], 0, 14), ENT_QUOTES), 'callback_data' => 'cmd:order:' . $r['id']]];
        }
        $rows_kb[] = [['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:orders:' . $n], ['text' => '📊 آمار', 'callback_data' => 'cmd:stats']];
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_order_detail($chatId, $orderId) {
    try {
        $st = db()->prepare("SELECT o.*, COALESCE(u.full_name,'مهمان') AS customer_name, u.phone AS customer_phone
                             FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?");
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o) { tg_send($chatId, "❌ سفارش #$orderId یافت نشد."); return; }

        // اقلام سفارش
        $items = [];
        try {
            $it = db()->prepare("SELECT oi.*, COALESCE(p.name, oi.name, 'محصول') AS name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
            $it->execute([$orderId]);
            $items = $it->fetchAll();
        } catch (Throwable $e) { /* ignore */ }

        $text = "📦 <b>سفارش #$orderId</b>\n\n" .
            "👤 مشتری: " . htmlspecialchars((string)$o['customer_name'], ENT_QUOTES) . "\n" .
            "📞 تلفن: " . htmlspecialchars((string)($o['customer_phone'] ?? '—'), ENT_QUOTES) . "\n" .
            "💰 مبلغ: " . fmt_price((int)($o['total_amount'] ?? 0)) . "\n" .
            "📊 وضعیت فعلی: " . tg_status_emoji($o['status']) . " " . tg_status_label($o['status']) . "\n" .
            "🏠 آدرس: " . htmlspecialchars((string)($o['address'] ?? '—'), ENT_QUOTES) . "\n" .
            "🕓 ثبت: " . $o['created_at'] . "\n";

        if ($items) {
            $text .= "\n<b>اقلام:</b>\n";
            foreach ($items as $it) {
                $text .= "• " . htmlspecialchars((string)$it['name'], ENT_QUOTES) .
                         " × " . fa_digits((int)($it['qty'] ?? 1)) .
                         " — " . fmt_price((int)($it['price'] ?? 0)) . "\n";
            }
        }

        // دکمه‌های تغییر وضعیت
        $statuses = [
            'pending' => '⏳ در انتظار',
            'processing' => '⚙️ در حال پردازش',
            'shipped' => '🚚 ارسال‌شده',
            'delivered' => '✅ تحویل‌شده',
            'cancelled' => '❌ لغوشده',
        ];
        $cur = $o['status'];
        $rows_kb = [];
        $row = [];
        foreach ($statuses as $key => $label) {
            $labelShort = explode(' ', $label)[0]; // فقط ایموجی
            $row[] = ['text' => $label . ($cur === $key ? ' ✓' : ''), 'callback_data' => 'cmd:status:' . $orderId . ':' . $key];
            if (count($row) === 2) { $rows_kb[] = $row; $row = []; }
        }
        if ($row) $rows_kb[] = $row;
        if (!empty($o['customer_phone'])) {
            $rows_kb[] = [
                ['text' => '💬 پاسخ به مشتری', 'callback_data' => 'cmd:reply:' . ($o['user_id'] ?? 0)],
                ['text' => '📋 کپی شماره', 'callback_data' => 'cmd:copy:' . $o['customer_phone']],
            ];
        }
        $rows_kb[] = [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'cmd:orders:5']];
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_change_order_status($chatId, $orderId, $newStatus) {
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (!in_array($newStatus, $allowed, true)) {
        tg_send($chatId, "❌ وضعیت نامعتبر. مقادیر مجاز: " . implode(', ', $allowed));
        return;
    }
    try {
        $st = db()->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $st->execute([$newStatus, $orderId]);
        if ($st->rowCount() === 0) { tg_send($chatId, "❌ سفارش #$orderId یافت نشد."); return; }
        if (function_exists('log_event')) log_event("tg admin chat=$chatId order=$orderId new_status=$newStatus", 'info');
        $label = tg_status_label($newStatus);
        $emoji = tg_status_emoji($newStatus);
        tg_send($chatId, "$emoji وضعیت سفارش #$orderId به <b>$label</b> تغییر کرد.");
        // اطلاع به مشتری در صورت داشتن chat_id تلگرام (در صورتی که در آینده اضافه شود)
        // فعلاً فقط لاگ می‌کنیم.
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_products_list($chatId, $n) {
    try {
        $sql = "SELECT id, name, price, sale_price, stock, active FROM products ORDER BY id DESC LIMIT " . (int)$n;
        $st = db()->query($sql);
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "📭 محصولی یافت نشد."); return; }
        $text = "🛍 <b>آخرین " . fa_digits(count($rows)) . " محصول</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $status = $r['active'] ? '🟢' : '🔴';
            $text .= "$status <b>#" . $r['id'] . "</b> " . htmlspecialchars((string)$r['name'], ENT_QUOTES) . "\n" .
                     "   💰 " . fmt_price((int)($r['sale_price'] ?? 0) > 0 ? (int)$r['sale_price'] : (int)$r['price']) . " • موجودی: " . fa_digits((int)($r['stock'] ?? 0)) . "\n\n";
            $rows_kb[] = [['text' => "$status #" . $r['id'] . " • " . htmlspecialchars(mb_substr((string)$r['name'], 0, 18)), 'callback_data' => 'cmd:product:' . $r['id']]];
        }
        $rows_kb[] = [['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:products:' . $n]];
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_product_detail($chatId, $productId) {
    try {
        $st = db()->prepare("SELECT * FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $status = !empty($p['active']) ? '🟢 فعال' : '🔴 غیرفعال';
        $text = "🛍 <b>محصول #$productId</b>\n\n" .
            "📝 نام: " . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "\n" .
            "💰 قیمت: " . fmt_price((int)$p['price']) . "\n" .
            ((int)($p['sale_price'] ?? 0) > 0 && (int)$p['sale_price'] < (int)$p['price'] ? "🏷 حراج: " . fmt_price((int)$p['sale_price']) . "\n" : '') .
            "📦 موجودی: " . fa_digits((int)($p['stock'] ?? 0)) . "\n" .
            "📊 وضعیت: $status\n";
        $kb = tg_keyboard_json([
            [
                ['text' => $p['active'] ? '🔴 غیرفعال‌سازی' : '🟢 فعال‌سازی', 'callback_data' => 'cmd:toggle:' . $p['id']],
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => 'cmd:products:5'],
            ],
        ]);
        tg_send($chatId, $text, ['reply_markup' => $kb]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_toggle_product($chatId, $productId) {
    try {
        $st = db()->prepare("SELECT active, name FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $new = empty($p['active']) ? 1 : 0;
        db()->prepare("UPDATE products SET active = ? WHERE id = ?")->execute([$new, $productId]);
        $label = $new ? '🟢 فعال' : '🔴 غیرفعال';
        if (function_exists('log_event')) log_event("tg admin chat=$chatId toggle product=$productId new=$new", 'info');
        tg_send($chatId, "محصول <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> به $label تغییر کرد.");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_users_list($chatId, $n) {
    try {
        $st = db()->query("SELECT id, full_name, phone, created_at FROM users ORDER BY id DESC LIMIT " . (int)$n);
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "📭 کاربری یافت نشد."); return; }
        $text = "👥 <b>آخرین " . fa_digits(count($rows)) . " کاربر</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $display = trim(($r['full_name'] ?? '') . ' ' . ($r['phone'] ?? '')) ?: 'بدون‌نام';
            $text .= "• <b>#" . $r['id'] . "</b> " . htmlspecialchars($display, ENT_QUOTES) . "\n   🕓 " . $r['created_at'] . "\n\n";
            $rows_kb[] = [['text' => "#" . $r['id'] . " • " . htmlspecialchars(mb_substr($display, 0, 18)), 'callback_data' => 'cmd:user:' . $r['id']]];
        }
        $rows_kb[] = [['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:users:' . $n]];
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_user_detail($chatId, $userId) {
    try {
        $st = db()->prepare("SELECT * FROM users WHERE id = ?");
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) { tg_send($chatId, "❌ کاربر #$userId یافت نشد."); return; }
        $orderCount = (int)db()->query("SELECT COUNT(*) FROM orders WHERE user_id = " . (int)$userId)->fetchColumn();
        $totalSpent = (int)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = " . (int)$userId . " AND status NOT IN ('cancelled')")->fetchColumn();
        $text = "👤 <b>کاربر #$userId</b>\n\n" .
            "📝 نام: " . htmlspecialchars((string)($u['full_name'] ?? '—'), ENT_QUOTES) . "\n" .
            "📞 تلفن: " . htmlspecialchars((string)($u['phone'] ?? '—'), ENT_QUOTES) . "\n" .
            "🕓 عضویت: " . $u['created_at'] . "\n" .
            "📦 تعداد سفارش: " . fa_digits($orderCount) . "\n" .
            "💰 مجموع خرید: " . fmt_price($totalSpent) . "\n";
        $rows_kb = [
            [
                ['text' => '📦 سفارش‌های این کاربر', 'callback_data' => 'cmd:user_orders:' . $userId],
                ['text' => '💬 پاسخ', 'callback_data' => 'cmd:reply:' . $userId],
            ],
            [['text' => '🔙 بازگشت', 'callback_data' => 'cmd:users:5']],
        ];
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_find_products($chatId, $query) {
    try {
        $like = '%' . $query . '%';
        $st = db()->prepare("SELECT id, name, price, sale_price, stock, active FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 10");
        $st->execute([$like]);
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "🔍 نتیجه‌ای برای «$query» یافت نشد."); return; }
        $text = "🔍 <b>جست‌وجو: «" . htmlspecialchars($query, ENT_QUOTES) . "»</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $status = $r['active'] ? '🟢' : '🔴';
            $text .= "$status <b>#" . $r['id'] . "</b> " . htmlspecialchars((string)$r['name'], ENT_QUOTES) . "\n" .
                     "   💰 " . fmt_price((int)($r['sale_price'] ?? 0) > 0 ? (int)$r['sale_price'] : (int)$r['price']) . " • موجودی: " . fa_digits((int)($r['stock'] ?? 0)) . "\n\n";
            $rows_kb[] = [['text' => "$status #" . $r['id'] . " • " . htmlspecialchars(mb_substr((string)$r['name'], 0, 18)), 'callback_data' => 'cmd:product:' . $r['id']]];
        }
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_send_settings($chatId) {
    try {
        $keys = ['site_name','contact_phone','contact_email','tg_bot_token','tg_chat_admin_ids','shop_currency','sms_enabled'];
        $lines = [];
        foreach ($keys as $k) {
            $v = (string)setting($k, '');
            if (strlen($v) > 60) $v = mb_substr($v, 0, 57) . '...';
            $lines[] = "• <b>$k</b>: " . ($v !== '' ? "<code>" . htmlspecialchars($v, ENT_QUOTES) . "</code>" : '<i>(خالی)</i>');
        }
        tg_send($chatId, "⚙️ <b>تنظیمات کلیدی سایت</b>\n\n" . implode("\n", $lines));
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** مسیریاب callback_dataهای دکمه‌های شیشه‌ای */
// =============== مپ وضعیت ===============
function tg_status_emoji($s) {
    $map = ['pending' => '⏳', 'processing' => '⚙️', 'shipped' => '🚚', 'delivered' => '✅', 'cancelled' => '❌'];
    return $map[$s] ?? '•';
}
function tg_status_label($s) {
    $map = ['pending' => 'در انتظار', 'processing' => 'در حال پردازش', 'shipped' => 'ارسال‌شده', 'delivered' => 'تحویل‌شده', 'cancelled' => 'لغوشده'];
    return $map[$s] ?? $s;
}

// =============== دستورهای جدید ===============

/** تنظیم سریع موجودی: /stock ID N */
function tg_set_stock($chatId, $productId, $newStock) {
    try {
        $st = db()->prepare("SELECT name, stock FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $old = (int)$p['stock'];
        db()->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$newStock, $productId]);
        if (function_exists('log_event')) log_event("tg admin chat=$chatId stock product=$productId old=$old new=$newStock", 'info');
        tg_send($chatId, "✅ موجودی <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> از " . fa_digits($old) . " به " . fa_digits($newStock) . " تغییر کرد.");
        // بررسی هشدارها
        tg_check_stock_alerts($productId, $newStock);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** تنظیم سریع قیمت: /price ID N */
function tg_set_price($chatId, $productId, $newPrice) {
    try {
        $st = db()->prepare("SELECT name, price FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $old = (int)$p['price'];
        db()->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$newPrice, $productId]);
        if (function_exists('log_event')) log_event("tg admin chat=$chatId price product=$productId old=$old new=$newPrice", 'info');
        tg_send($chatId, "✅ قیمت <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b>:\n" .
            "قبل: " . fmt_price($old) . "\n" .
            "بعد: " . fmt_price($newPrice) . "\n" .
            "تفاوت: " . ($newPrice > $old ? "+" : '') . fmt_price($newPrice - $old));
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** مسدودسازی کاربر: /ban ID دلیل */
function tg_ban_user($chatId, $userId, $reason) {
    try {
        $st = db()->prepare("SELECT full_name, phone, role FROM users WHERE id = ?");
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) { tg_send($chatId, "❌ کاربر #$userId یافت نشد."); return; }
        if (($u['role'] ?? '') === 'admin') { tg_send($chatId, "❌ نمی‌توان حساب ادمین را مسدود کرد."); return; }
        try { db()->query("SELECT banned FROM users LIMIT 1"); }
        catch (Throwable $e) {
            try { db()->exec("ALTER TABLE users ADD COLUMN banned INTEGER NOT NULL DEFAULT 0"); }
            catch (Throwable $e2) { tg_send($chatId, "❌ افزودن ستون banned ممکن نشد: " . htmlspecialchars($e2->getMessage(), ENT_QUOTES)); return; }
        }
        db()->prepare("UPDATE users SET banned = 1 WHERE id = ?")->execute([$userId]);
        if (function_exists('log_event')) log_event("tg admin chat=$chatId ban user=$userId reason=" . substr($reason, 0, 80), 'warn');
        $label = trim(($u['full_name'] ?? '') . ' ' . ($u['phone'] ?? '')) ?: "کاربر #$userId";
        tg_send($chatId, "🚫 کاربر <b>" . htmlspecialchars($label, ENT_QUOTES) . "</b> مسدود شد" . ($reason !== '' ? "\nدلیل: " . htmlspecialchars($reason, ENT_QUOTES) : ''));
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_unban_user($chatId, $userId) {
    try {
        try { db()->query("SELECT banned FROM users LIMIT 1"); }
        catch (Throwable $e) {
            try { db()->exec("ALTER TABLE users ADD COLUMN banned INTEGER NOT NULL DEFAULT 0"); }
            catch (Throwable $e2) { tg_send($chatId, "❌ افزودن ستون banned ممکن نشد: " . htmlspecialchars($e2->getMessage(), ENT_QUOTES)); return; }
        }
        db()->prepare("UPDATE users SET banned = 0 WHERE id = ?")->execute([$userId]);
        $st = db()->prepare("SELECT full_name, phone FROM users WHERE id = ?");
        $st->execute([$userId]);
        $u = $st->fetch();
        $label = $u ? trim(($u['full_name'] ?? '') . ' ' . ($u['phone'] ?? '')) : "کاربر #$userId";
        if (function_exists('log_event')) log_event("tg admin chat=$chatId unban user=$userId", 'info');
        tg_send($chatId, "✅ مسدودیت کاربر <b>" . htmlspecialchars($label, ENT_QUOTES) . "</b> برداشته شد.");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** اطمینان از وجود جدول هشدارها (سازگار با SQLite/MySQL) */
function tg_ensure_alerts_table() {
    static $done = false;
    if ($done) return;
    if (defined('DB_DRIVER') && DB_DRIVER === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS product_stock_alerts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            threshold INT NOT NULL DEFAULT 5,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_triggered_at DATETIME DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS product_stock_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            threshold INTEGER NOT NULL DEFAULT 5,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_triggered_at TEXT DEFAULT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            UNIQUE (product_id)
        )");
    }
    $done = true;
}

/** ثبت هشدار خودکار: /alert add productId threshold */
function tg_alert_add($chatId, $productId, $threshold) {
    try {
        $st = db()->prepare("SELECT name FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        // ایجاد خودکار جدول در صورت نبود
        tg_ensure_alerts_table();
        $ex = db()->prepare("SELECT id FROM product_stock_alerts WHERE product_id = ?");
        $ex->execute([$productId]);
        if ($ex->fetch()) {
            db()->prepare("UPDATE product_stock_alerts SET threshold = ?, enabled = 1 WHERE product_id = ?")
                ->execute([$threshold, $productId]);
        } else {
            db()->prepare("INSERT INTO product_stock_alerts (product_id, threshold) VALUES (?, ?)")
                ->execute([$productId, $threshold]);
        }
        tg_send($chatId, "🔔 هشدار فعال شد: وقتی موجودی <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> کمتر از " . fa_digits($threshold) . " شود، به شما اطلاع می‌دهم.\n\nبرای لغو: <code>/alert remove $productId</code>");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** فهرست هشدارهای فعال */
function tg_alert_list($chatId) {
    try {
        tg_ensure_alerts_table();
        $st = db()->query("SELECT a.id, a.product_id, a.threshold, p.name, p.stock
                           FROM product_stock_alerts a
                           LEFT JOIN products p ON p.id = a.product_id
                           WHERE a.enabled = 1
                           ORDER BY p.stock ASC");
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "📭 هشدار فعالی ثبت نشده. ثبت: <code>/alert add productId threshold</code>"); return; }
        $text = "🔔 <b>هشدارهای فعال</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $text .= "• #" . $r['product_id'] . " " . htmlspecialchars((string)($r['name'] ?? '؟'), ENT_QUOTES) .
                     " — آستانه: " . fa_digits($r['threshold']) . " | فعلی: " . fa_digits((int)($r['stock'] ?? 0)) . "\n";
            $rows_kb[] = [['text' => '🗑 حذف #' . $r['product_id'], 'callback_data' => 'cmd:alert_remove:' . $r['id']]];
        }
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** حذف هشدار: /alert remove ID (شناسهٔ ردیف) */
function tg_alert_remove($chatId, $alertId) {
    try {
        tg_ensure_alerts_table();
        $st = db()->prepare("SELECT product_id FROM product_stock_alerts WHERE id = ?");
        $st->execute([$alertId]);
        $a = $st->fetch();
        if (!$a) { tg_send($chatId, "❌ هشدار #$alertId یافت نشد."); return; }
        db()->prepare("DELETE FROM product_stock_alerts WHERE id = ?")->execute([$alertId]);
        tg_send($chatId, "🗑 هشدار محصول #" . $a['product_id'] . " حذف شد.");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** بررسی هشدارها بعد از تغییر موجودی (توسط توابع دیگر فراخوانی می‌شود) */
function tg_check_stock_alerts($productId, $newStock) {
    try {
        tg_ensure_alerts_table();
        $st = db()->prepare("SELECT threshold FROM product_stock_alerts WHERE product_id = ? AND enabled = 1");
        $st->execute([$productId]);
        $alert = $st->fetch();
        if (!$alert) return;
        if ($newStock > (int)$alert['threshold']) return; // هنوز زیر آستانه نیست
        // هشدار بده
        $st2 = db()->prepare("SELECT name, stock FROM products WHERE id = ?");
        $st2->execute([$productId]);
        $p = $st2->fetch();
        if (!$p) return;
        $text = "🚨 <b>هشدار موجودی</b>\n\n" .
            "محصول: <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> (#$productId)\n" .
            "موجودی فعلی: " . fa_digits((int)$p['stock']) . "\n" .
            "آستانه: " . fa_digits((int)$alert['threshold']) . "\n\n" .
            "اقدام: <code>/stock $productId N</code>";
        tg_notify_admins($text, 'stock_alert');
        // ثبت زمان آخرین هشدار
        db()->prepare("UPDATE product_stock_alerts SET last_triggered_at = " . ((defined('DB_DRIVER') && DB_DRIVER === 'mysql') ? 'NOW()' : "datetime('now')") . " WHERE product_id = ?")
            ->execute([$productId]);
    } catch (Throwable $e) { /* سکوت — هشدار داخلی نباید کاربر را بشکند */ }
}

/** پاسخ سریع به callback_query (بدون ارسال پیام، فقط نمایش toast) */
function tg_answer_callback($callbackQueryId, $text = '', $showAlert = false) {
    $url = tg_api_url('answerCallbackQuery');
    if ($url === '') return;
    $payload = [
        'callback_query_id' => $callbackQueryId,
        'text'              => $text,
        'show_alert'        => $showAlert ? 'true' : 'false',
    ];
    $body = http_build_query($payload, '', '&');
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $body,
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents($url, false, $ctx);
}

/** مسیریاب callback_dataهای دکمه‌های شیشه‌ای */
/** گزارش ۷ روز اخیر: /week */
function tg_send_week_report($chatId) {
    try {
        $rows = db()->query("SELECT DATE(created_at) AS d, COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS s
                             FROM orders
                             WHERE created_at >= datetime('now', '-7 days')
                             GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
        if (!$rows) { tg_send($chatId, "📭 در ۷ روز اخیر سفارشی ثبت نشده."); return; }
        $text = "📈 <b>گزارش ۷ روز اخیر</b>\n\n";
        $best = null; $totC = 0; $totS = 0;
        foreach ($rows as $r) {
            $text .= "📅 " . fa_digits((string)$r['d']) . " — " . fa_digits((int)$r['c']) . " سفارش • " . fmt_price((int)$r['s']) . "\n";
            $totC += (int)$r['c']; $totS += (int)$r['s'];
            if ($best === null || (int)$r['s'] > (int)$best['s']) $best = $r;
        }
        $text .= "\nΣ مجموع: " . fa_digits($totC) . " سفارش • " . fmt_price($totS) . "\n";
        if ($best) $text .= "🏆 بهترین روز: " . fa_digits((string)$best['d']) . " (" . fmt_price((int)$best['s']) . ")\n";
        $kb = tg_keyboard_json([
            [['text' => '📊 آمار کامل', 'callback_data' => 'cmd:stats'], ['text' => '🔄 تازه‌سازی', 'callback_data' => 'cmd:week']],
        ]);
        tg_send($chatId, $text, ['reply_markup' => $kb]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** گفتگوهای در انتظار پاسخ: /todo */
function tg_send_todo_list($chatId) {
    try {
        $rows = db()->query("SELECT m.id AS tid, m.user_id,
                COALESCE(NULLIF(u.full_name,''), NULLIF(u.phone,''), '#' || CAST(m.user_id AS TEXT)) AS who,
                COALESCE(NULLIF(m.subject,''), '(بدون موضوع)') AS subject,
                COALESCE((SELECT r.created_at FROM messages r WHERE r.parent_id = m.id ORDER BY r.id DESC LIMIT 1), m.created_at) AS last_at,
                COALESCE((SELECT r.from_admin FROM messages r WHERE r.parent_id = m.id ORDER BY r.id DESC LIMIT 1), 0) AS last_from_admin
            FROM messages m LEFT JOIN users u ON u.id = m.user_id
            WHERE m.parent_id IS NULL AND m.is_closed = 0
            ORDER BY last_at DESC LIMIT 10")->fetchAll();
        $pending = array_values(array_filter($rows, function ($r) { return (int)$r['last_from_admin'] === 0; }));
        if (!$pending) { tg_send($chatId, "✅ همهٔ گفتگوهای باز پاسخ داده شده‌اند. 👏"); return; }
        $text = "💬 <b>گفتگوهای در انتظار پاسخ</b> (" . fa_digits(count($pending)) . ")\n\n";
        $rows_kb = [];
        foreach ($pending as $r) {
            $text .= "• <b>#" . $r['tid'] . "</b> " . htmlspecialchars((string)$r['who'], ENT_QUOTES) . " — " . htmlspecialchars(mb_substr((string)$r['subject'], 0, 30), ENT_QUOTES) . "\n   🕓 " . $r['last_at'] . "\n\n";
            $rows_kb[] = [['text' => '✍️ پاسخ به ' . mb_substr((string)$r['who'], 0, 14), 'callback_data' => 'cmd:reply:' . $r['user_id'] . ':' . $r['tid']]];
        }
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** جست‌وجوی کاربر: /finduser عبارت */
function tg_find_users($chatId, $query) {
    try {
        $like = '%' . $query . '%';
        $st = db()->prepare("SELECT id, full_name, phone, username FROM users
                             WHERE phone LIKE ? OR full_name LIKE ? OR username LIKE ?
                             ORDER BY id DESC LIMIT 10");
        $st->execute([$like, $like, $like]);
        $rows = $st->fetchAll();
        if (!$rows) { tg_send($chatId, "🔍 کاربری برای «" . htmlspecialchars($query, ENT_QUOTES) . "» یافت نشد."); return; }
        $text = "🔍 <b>کاربران یافت‌شده</b>\n\n";
        $rows_kb = [];
        foreach ($rows as $r) {
            $display = trim(($r['full_name'] ?? '') . ' ' . ($r['phone'] ?? '')) ?: ('@' . ($r['username'] ?? ''));
            $text .= "• <b>#" . $r['id'] . "</b> " . htmlspecialchars($display, ENT_QUOTES) . "\n";
            $rows_kb[] = [['text' => "#" . $r['id'] . " • " . htmlspecialchars(mb_substr($display, 0, 18), ENT_QUOTES), 'callback_data' => 'cmd:user:' . $r['id']]];
        }
        tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** ویژه‌سازی محصول: /feature ID */
function tg_toggle_featured($chatId, $productId) {
    try {
        $st = db()->prepare("SELECT featured, name FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $new = empty($p['featured']) ? 1 : 0;
        db()->prepare("UPDATE products SET featured = ? WHERE id = ?")->execute([$new, $productId]);
        $label = $new ? '⭐ ویژه' : 'عادی';
        tg_send($chatId, "محصول <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> به $label تغییر کرد.");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

/** قیمت حراج: /sale ID مبلغ | /sale ID حذف */
function tg_set_sale($chatId, $productId, $raw) {
    try {
        $st = db()->prepare("SELECT name, price, sale_price FROM products WHERE id = ?");
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) { tg_send($chatId, "❌ محصول #$productId یافت نشد."); return; }
        $raw = trim($raw);
        if ($raw === '' || (int)preg_replace('/[^0-9]/', '', $raw) === 0) {
            db()->prepare("UPDATE products SET sale_price = 0 WHERE id = ?")->execute([$productId]);
            tg_send($chatId, "🏷 حراج <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b> حذف شد.");
            return;
        }
        $new = (int)preg_replace('/[^0-9]/', '', $raw);
        if ($new >= (int)$p['price']) { tg_send($chatId, "❌ قیمت حراج باید کمتر از قیمت اصلی باشد."); return; }
        db()->prepare("UPDATE products SET sale_price = ? WHERE id = ?")->execute([$new, $productId]);
        tg_send($chatId, "🏷 حراج <b>" . htmlspecialchars((string)$p['name'], ENT_QUOTES) . "</b>:\n" .
            "قیمت اصلی: " . fmt_price((int)$p['price']) . "\n" .
            "قیمت حراج: " . fmt_price($new) . "\n" .
            "تخفیف: " . fa_digits((string)round(100 - ($new * 100 / max(1, (int)$p['price'])))) . "٪\n" .
            "حذف: <code>/sale $productId حذف</code>");
    } catch (Throwable $e) {
        tg_send($chatId, "❌ خطا: " . $e->getMessage());
    }
}

function tg_handle_callback($chatId, $data) {
    $parts = explode(':', $data);
    // ساختار داده: cmd:برچسب[:پارامتر...] — کلید case باید «cmd:برچسب» باشد
    // (رفع باگ: قبلاً $cmd = $parts[0] بود که همیشه 'cmd' می‌شد و هیچ caseای اجرا نمی‌شد)
    static $labels = ['stats','ping','settings','help','find','orders','order','status','products','product','toggle','users','user','user_orders','reply','copy','alert_remove','week','todo'];
    $cmd = $data;
    if (isset($parts[1])) {
        if (in_array($parts[1], $labels, true)) {
            // ساختار cmd:برچسب:پارامتر... — با حذف «cmd» پارامترها از parts[1] شروع می‌شوند
            // (رفع باگ: بدنهٔ caseها $parts[1] را به‌عنوان شناسه می‌خوانند ولی قبلاً برچسب آنجا بود)
            $cmd = $parts[0] . ':' . $parts[1];
            array_shift($parts);
        } else {
            $cmd = $parts[0];
        }
    }
    switch ($cmd) {
        case 'cmd:week':
            tg_send_week_report($chatId);
            return;
        case 'cmd:todo':
            tg_send_todo_list($chatId);
            return;
        case 'cmd:stats':            tg_send_stats($chatId); return;
        case 'cmd:ping':             tg_send($chatId, "🏓 pong — سایت فعال است."); return;
        case 'cmd:settings':         tg_send_settings($chatId); return;
        case 'cmd:help':             tg_send($chatId, tg_help_text()); return;
        case 'cmd:orders':           tg_send_orders_list($chatId, (int)($parts[1] ?? 5)); return;
        case 'cmd:order':            tg_send_order_detail($chatId, (int)($parts[1] ?? 0)); return;
        case 'cmd:status':
            // cmd:status:orderId:newStatus
            if (count($parts) >= 3) tg_change_order_status($chatId, (int)$parts[1], strtolower($parts[2]));
            return;
        case 'cmd:products':
            $f = $parts[1] ?? '5';
            if ($f === 'low') {
                // محصولات با موجودی کم
                try {
                    $st = db()->query("SELECT id, name, stock, active FROM products WHERE stock IS NOT NULL AND stock <= 3 ORDER BY stock ASC LIMIT 20");
                    $rows = $st->fetchAll();
                    if (!$rows) { tg_send($chatId, "✅ همهٔ محصولات موجودی کافی دارند."); return; }
                    $text = "⚠️ <b>محصولات با موجودی کم</b>\n\n";
                    $rows_kb = [];
                    foreach ($rows as $r) {
                        $emoji = $r['active'] ? '🟢' : '🔴';
                        $text .= "$emoji <b>#" . $r['id'] . "</b> " . htmlspecialchars((string)$r['name'], ENT_QUOTES) . "\n   📦 موجودی: " . fa_digits((int)$r['stock']) . "\n\n";
                        $rows_kb[] = [['text' => "$emoji #" . $r['id'] . " • " . htmlspecialchars(mb_substr((string)$r['name'], 0, 18)), 'callback_data' => 'cmd:product:' . $r['id']]];
                    }
                    tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
                } catch (Throwable $e) { tg_send($chatId, "❌ " . $e->getMessage()); }
                return;
            }
            tg_send_products_list($chatId, (int)$f);
            return;
        case 'cmd:product':          tg_send_product_detail($chatId, (int)($parts[1] ?? 0)); return;
        case 'cmd:toggle':           tg_toggle_product($chatId, (int)($parts[1] ?? 0)); return;
        case 'cmd:users':            tg_send_users_list($chatId, (int)($parts[1] ?? 5)); return;
        case 'cmd:user':             tg_send_user_detail($chatId, (int)($parts[1] ?? 0)); return;
        case 'cmd:user_orders':
            $uid = (int)($parts[1] ?? 0);
            try {
                $st = db()->prepare("SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10");
                $st->execute([$uid]);
                $rows = $st->fetchAll();
                if (!$rows) { tg_send($chatId, "📭 سفارشی برای کاربر #$uid یافت نشد."); return; }
                $text = "📦 <b>سفارش‌های کاربر #$uid</b>\n\n";
                $rows_kb = [];
                foreach ($rows as $r) {
                    $text .= tg_status_emoji($r['status']) . " <b>#" . $r['id'] . "</b> — " . fmt_price((int)($r['total_amount'] ?? 0)) . " • " . tg_status_label($r['status']) . "\n";
                    $rows_kb[] = [['text' => tg_status_emoji($r['status']) . " سفارش #" . $r['id'], 'callback_data' => 'cmd:order:' . $r['id']]];
                }
                $rows_kb[] = [['text' => '🔙 بازگشت', 'callback_data' => 'cmd:user:' . $uid]];
                tg_send($chatId, $text, ['reply_markup' => tg_keyboard_json($rows_kb)]);
            } catch (Throwable $e) { tg_send($chatId, "❌ " . $e->getMessage()); }
            return;
        case 'cmd:reply':
            $uid = (int)($parts[1] ?? 0);
            $rootId = (int)($parts[2] ?? 0);
            if ($uid > 0) {
                // فعال‌سازی حالت «در انتظار پاسخ» — پیام بعدیِ بدون دستور به کاربر می‌رود
                tg_set_setting('tg_pending_reply_' . $chatId, $uid . '|' . $rootId . '|' . time());
                tg_send($chatId, "✍️ <b>پاسخ به کاربر #$uid</b>" . ($rootId > 0 ? " (گفتگو #" . $rootId . ")" : "") . "\n\nمتن پاسخ را بنویسید و بفرستید — داخل همان گفتگو ثبت می‌شود و مشتری مطلع می‌شود.\nلغو: <code>/cancel</code>");
            } else {
                tg_send($chatId, "❌ شناسهٔ کاربر نامعتبر است.");
            }
            return;
        case 'cmd:copy':
            $val = $parts[1] ?? '';
            tg_send($chatId, "📋 مقدار برای کپی:\n<code>" . htmlspecialchars($val, ENT_QUOTES) . "</code>\n\nروی آن بزنید تا کپی شود.");
            return;
        case 'cmd:find':
            tg_send($chatId, "🔍 نام محصول (یا بخشی از آن) را بنویسید:\n<code>/find مته</code>");
            return;
        case 'cmd:alert_remove':
            tg_alert_remove($chatId, (int)($parts[1] ?? 0));
            return;
    }
}
