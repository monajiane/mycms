<?php
/**
 * payment.php — آغاز تراکنش در درگاه انتخابی (زرین‌پال یا BitPay)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/zarinpal.php';
require_once __DIR__ . '/includes/bitpay.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$orderId]);
$order = $st->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
if ($order['status'] === 'paid') {
    header('Location: ' . BASE_URL . '/order_result.php?id=' . $orderId);
    exit;
}

$gateway = setting('payment_gateway', 'zarinpal'); // zarinpal | bitpay
$description = 'پرداخت سفارش #' . $orderId . ' - ' . STORE_NAME;

// --- درگاه بیت‌پی (bitpay.ir) ---
if ($gateway === 'bitpay') {
    $bp = new BitPay();
    $callback = BASE_URL . '/bitpay_verify.php';
    $result = $bp->request($order['total_amount'], $orderId, $callback);

    if ($result['ok']) {
        // authority = trans_id بیت‌پی (برای رهگیری/بازگشت)
        $upd = db()->prepare("UPDATE orders SET authority = ?, payment_note = ? WHERE id = ?");
        $upd->execute([$result['trans_id'], 'در انتظار پرداخت بیت‌پی', $orderId]);
        header('Location: ' . $result['pay_url']);
        exit;
    }

    $upd = db()->prepare("UPDATE orders SET status = 'failed', payment_note = ? WHERE id = ?");
    $upd->execute([$result['error'], $orderId]);

    $pageTitle = 'خطای پرداخت';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="container">
        <div class="alert alert-error">
            <p>خطا در اتصال به درگاه بیت‌پی:</p>
            <p><?= e($result['error']) ?></p>
            <p>کلید API و محیط بیت‌پی را از پنل مدیریت (تنظیمات ← پرداخت) بررسی کنید.</p>
        </div>
        <a href="<?= e(BASE_URL) ?>/checkout.php" class="btn btn-accent">تلاش مجدد</a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- درگاه زرین‌پال (پیش‌فرض) ---
$zp = new ZarinPal();
$callback = BASE_URL . '/verify.php';

$result = $zp->request($order['total_amount'], $description, $callback);

if ($result['ok']) {
    // ذخیرهٔ authority برای رهگیری
    $upd = db()->prepare("UPDATE orders SET authority = ?, payment_note = ? WHERE id = ?");
    $upd->execute([$result['authority'], 'در انتظار پرداخت', $orderId]);

    if (setting('payment_mode', PAYMENT_MODE) === 'mock') {
        // حالت شبیه‌سازی: بازگشت موفق از درگاه را شبیه‌سازی می‌کنیم
        header('Location: ' . BASE_URL . '/verify.php?Authority=' . urlencode($result['authority']) . '&Status=OK');
    } else {
        header('Location: ' . $zp->payUrl($result['authority']));
    }
    exit;
}

// خطا در ایجاد تراکنش
$upd = db()->prepare("UPDATE orders SET status = 'failed', payment_note = ? WHERE id = ?");
$upd->execute([$result['error'], $orderId]);

$pageTitle = 'خطای پرداخت';
require __DIR__ . '/includes/header.php';
?>
<section class="container">
    <div class="alert alert-error">
        <p>خطا در اتصال به درگاه پرداخت:</p>
        <p><?= e($result['error']) ?></p>
        <p>در صورت تمایل، مرچنت‌کد خود را از پنل مدیریت (تنظیمات) وارد کنید.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/checkout.php" class="btn btn-accent">تلاش مجدد</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
