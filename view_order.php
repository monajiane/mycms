<?php
/**
 * view_order.php — مشاهدهٔ جزئیات یک سفارش توسط مشتری
 */
require_once __DIR__ . '/config.php';

if (!is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php?next=' . urlencode('/view_order.php?id=' . (int)($_GET['id'] ?? 0)));
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = get_order($orderId);

// مشتری فقط می‌تواند سفارش‌های خودش را ببیند
if (!$order || (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '/account.php');
    exit;
}

$items = order_items($orderId);
$trackEvents = tracking_enabled() ? order_tracking_events($orderId) : [];

$pageTitle = 'جزئیات سفارش #' . $orderId;
require __DIR__ . '/includes/header.php';
?>

<section class="container order-detail">
    <a href="account.php" class="back-link">→ بازگشت به حساب</a>
    <h1 class="page-title">سفارش #<?= (int)$order['id'] ?></h1>
    <p style="margin-bottom:1rem;"><a href="invoice.php?id=<?= (int)$order['id'] ?>" class="btn btn-accent">🖨 دانلود فاکتور (PDF)</a></p>

    <div class="order-detail-grid">
        <div class="order-status-box">
            <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span>
            <dl class="result-meta">
                <dt>تاریخ ثبت</dt><dd><?= e(persian_date($order['created_at'] ?? null, true)) ?></dd>
                <dt>کد پیگیری (Ref ID)</dt><dd><?= e($order['ref_id'] ?: '—') ?></dd>
                <dt>مبلغ کل</dt><dd><?= e(fmt_price($order['total_amount'])) ?></dd>
                <dt>آدرس تحویل</dt><dd><?= e($order['address'] ?: '—') ?></dd>
                <?php if (!empty($order['postal_code'])): ?>
                    <dt>کد پستی</dt><dd><?= e($order['postal_code']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <div class="order-items-box">
            <h2>اقلام سفارش</h2>
            <table class="cart-table">
                <thead><tr><th>محصول</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['product_name']) ?><?= !empty($it['variant_name']) ? ' <span class="cart-variant">' . e($it['variant_name']) . '</span>' : '' ?></td>
                        <td><?= e(fmt_price($it['price'])) ?></td>
                        <td><?= (int)$it['quantity'] ?></td>
                        <td><?= e(fmt_price($it['price'] * $it['quantity'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($trackEvents): ?>
                <h2 style="margin-top:1.4rem;">وضعیت ارسال</h2>
                <ol class="track-timeline">
                    <?php foreach ($trackEvents as $ev): ?>
                        <li class="track-step track-step--<?= e($ev['status']) ?>">
                            <span class="track-dot"></span>
                            <div class="track-step-body">
                                <strong><?= e(tracking_label($ev['status'])) ?></strong>
                                <?php if (!empty($ev['note'])): ?><span class="track-note"><?= e($ev['note']) ?></span><?php endif; ?>
                                <time><?= e(persian_date($ev['created_at'], true)) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
