<?php
/**
 * order_result.php — نمایش نتیجهٔ سفارش پس از پرداخت
 */
require_once __DIR__ . '/config.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$orderId]);
$order = $st->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pageTitle = 'نتیجهٔ سفارش';
require __DIR__ . '/includes/header.php';
?>

<section class="container result">
    <?php if ($order['status'] === 'paid'): ?>
        <div class="result-box success">
            <h1>پرداخت با موفقیت انجام شد ✓</h1>
            <p>سفارش شما با موفقیت ثبت و پرداخت شد.</p>
            <dl class="result-meta">
                <dt>شمارهٔ سفارش</dt><dd><?= (int)$order['id'] ?></dd>
                <dt>کد پیگیری (Ref ID)</dt><dd><?= e($order['ref_id']) ?></dd>
                <dt>مبلغ</dt><dd><?= e(fmt_price($order['total_amount'])) ?></dd>
            </dl>
        </div>
    <?php else: ?>
        <div class="result-box fail">
            <h1>پرداخت ناموفق بود</h1>
            <p><?= e($order['payment_note'] ?: 'پرداخت انجام نشد.') ?></p>
            <p>می‌توانید دوباره تلاش کنید.</p>
        </div>
    <?php endif; ?>
    <a href="<?= e(BASE_URL) ?>/index.php" class="btn btn-accent">بازگشت به فروشگاه</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
