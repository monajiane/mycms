<?php
/**
 * includes/payment_common.php — توابع مشترک پس از پرداخت
 * ------------------------------------------------------------------
 *  - complete_paid_order(): علامت‌گذاری سفارش به‌عنوان paid + کسر موجودی + امتیاز + معرف
 *  - fail_order(): ثبت خطای پرداخت در سفارش (سبد خرید حفظ می‌شود)
 *  - log_event(): ثبت رویداد در لاگ
 *
 *  نکتهٔ مهم: این فایل به‌هیچ‌وجه سبد خرید را در شکست پاک نمی‌کند؛
 *  پاک‌سازی سبد فقط در complete_paid_order() (موفق) انجام می‌شود.
 */

if (!function_exists('log_event')) {
    function log_event($message, $level = 'info')
    {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
        @file_put_contents($dir . '/store.log', $line, FILE_APPEND);
    }
}

/**
 * پایان موفق پرداخت: سفارش paid، کسر موجودی، امتیاز وفاداری، معرف، پاک‌سازی سبد.
 *
 * @param array $order   ردیف سفارش از orders
 * @param string $refId  شناسهٔ مرجع (ref_id زرین‌پال یا trans_id بیت‌پی)
 * @return array         ['ok'=>bool, 'order_id'=>int]
 */
function complete_paid_order(array $order, $refId = '')
{
    $orderId = (int)$order['id'];
    $userId  = (int)($order['user_id'] ?? 0);
    $total   = (int)($order['total_amount'] ?? 0);

    // ۱) به‌روزرسانی سفارش
    $st = db()->prepare("UPDATE orders SET status = 'paid', ref_id = ?, payment_note = 'پرداخت موفق' WHERE id = ? AND status != 'paid'");
    $st->execute([(string)$refId, $orderId]);

    // ۲) کسر موجودی محصولات + اطلاع‌رسانی «موجود شد»
    $it = db()->prepare("SELECT product_id, quantity, variant_id FROM order_items WHERE order_id = ?");
    $it->execute([$orderId]);
    foreach ($it->fetchAll() as $row) {
        $pid  = (int)$row['product_id'];
        $qty  = max(1, (int)$row['quantity']);
        $vid  = (int)($row['variant_id'] ?? 0);
        try {
            if ($vid > 0) {
                $u = db()->prepare("UPDATE product_variants SET stock = MAX(0, stock - ?) WHERE id = ?");
                $u->execute([$qty, $vid]);
            } else {
                $u = db()->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?");
                $u->execute([$qty, $pid]);
            }
        } catch (Throwable $e) {
            log_event('stock update failed order=' . $orderId . ' pid=' . $pid . ' err=' . $e->getMessage(), 'error');
        }
        // اطلاع‌رسانی به کاربرانی که برای «موجود شدن» ثبت‌نام کرده‌اند
        if (function_exists('stock_notify_flush')) {
            try { stock_notify_flush($pid); } catch (Throwable $e) {}
        }
    }

    // ۳) امتیاز وفاداری
    if ($userId > 0 && $total > 0 && function_exists('add_points') && function_exists('points_earned_for_amount')) {
        try {
            $pts = (int)points_earned_for_amount($total);
            if ($pts > 0) {
                add_points($userId, $pts, 'order_paid', 'امتیاز بابت سفارش #' . $orderId, $orderId);
            }
        } catch (Throwable $e) {
            log_event('points award failed order=' . $orderId . ' err=' . $e->getMessage(), 'error');
        }
    }

    // ۴) پاداش معرف (اولین خرید موفق)
    if ($userId > 0 && function_exists('referral_award_on_first_purchase')) {
        try { referral_award_on_first_purchase($order); } catch (Throwable $e) {
            log_event('referral award failed order=' . $orderId . ' err=' . $e->getMessage(), 'error');
        }
    }

    // ۵) پاک‌سازی سبد خرید فقط در موفقیت
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    if (function_exists('log_event')) {
        log_event('order paid id=' . $orderId . ' user=' . $userId . ' total=' . $total . ' ref=' . $refId, 'info');
    }

    // اطلاع‌رسانی تلگرام (سفارش پرداخت‌شده)
    if (function_exists('tg_notify_admins')) {
        try {
            $_tgUser = $userId > 0 ? ('کاربر #' . $userId) : 'مهمان';
            try {
                if ($userId > 0) {
                    $_ust = db()->prepare('SELECT full_name, username, phone FROM users WHERE id = ?');
                    $_ust->execute([$userId]);
                    $_ur = $_ust->fetch(PDO::FETCH_ASSOC);
                    if ($_ur) {
                        $_tgUser = ($_ur['full_name'] ?: $_ur['username'] ?: $_ur['phone']) ?: $_tgUser;
                    }
                }
            } catch (Throwable $_e) {}
            $_tgTotal = number_format((int)$total) . ' تومان';
            $_tgMethod = (string)($order['payment_method'] ?? '-');
            $_tgBase = defined('BASE_URL') ? BASE_URL : 'https://abzar-shargh.ir';
            $_tgText = "🛒 <b>سفارش جدید پرداخت‌شده</b>\n" .
                       "شماره: <code>#" . (int)$orderId . "</code>\n" .
                       "کاربر: " . htmlspecialchars($_tgUser, ENT_QUOTES, 'UTF-8') . "\n" .
                       "مبلغ: <b>" . $_tgTotal . "</b>\n" .
                       "روش پرداخت: " . htmlspecialchars($_tgMethod, ENT_QUOTES, 'UTF-8') . "\n" .
                       "مرجع: <code>" . htmlspecialchars((string)$refId, ENT_QUOTES, 'UTF-8') . "</code>\n" .
                       "زمان: " . date('Y-m-d H:i:s') . "\n" .
                       "🔗 " . $_tgBase . '/admin/orders.php?id=' . (int)$orderId;
            $_kb = json_encode(['inline_keyboard' => [
                [ ['text' => '📦 مشاهده سفارش', 'url' => $_tgBase . '/admin/orders.php?id=' . (int)$orderId] ],
            ]], JSON_UNESCAPED_UNICODE);
            if (function_exists('tg_enabled') && tg_enabled()) {
                foreach (tg_admin_chat_ids() as $_cid) {
                    if (function_exists('tg_send')) {
                        @tg_send($_cid, $_tgText, ['reply_markup' => $_kb]);
                    }
                }
            } else {
                @tg_notify_admins($_tgText, 'order_paid');
            }
        } catch (Throwable $_e) { /* never break checkout flow */ }
    }

    return ['ok' => true, 'order_id' => $orderId];
}

/**
 * ثبت شکست پرداخت. سبد خرید دست‌نخورده می‌ماند.
 */
function fail_order(array $order, $error = '')
{
    $orderId = (int)$order['id'];
    $st = db()->prepare("UPDATE orders SET status = 'failed', payment_note = ? WHERE id = ?");
    $st->execute([(string)$error, $orderId]);
    if (function_exists('log_event')) {
        log_event('order failed id=' . $orderId . ' err=' . $error, 'warn');
    }
    return ['ok' => false, 'order_id' => $orderId, 'error' => $error];
}
