<?php
/**
 * admin/invoice.php — فاکتور فروش (قابل چاپ / ذخیره PDF)
 * با استفاده از قابلیت چاپ مرورگر (فونت فارسی کامل پشتیبانی می‌شود)
 */
$pageTitle = 'فاکتور';
require __DIR__ . '/_header.php';

$orderId = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$orderId]);
$order = $st->fetch();

if (!$order) {
    echo '<div class="alert alert-error">سفارش یافت نشد.</div>';
    require __DIR__ . '/_footer.php';
    exit;
}

$items = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$orderId]);
$items = $items->fetchAll();

$storeName = setting('store_name', STORE_NAME);
$storeAddr = setting('company_address', '');
$storePhone = setting('company_phone', '');
$storeEmail = setting('company_email', '');
$invColor = setting('invoice_color', '#06163a');
$invNote = setting('invoice_note', 'با تشکر از خرید شما');
$showLogo = setting_bool('invoice_show_logo', true);
$logo = setting('site_logo', '');
?>
<style>
.invoice-wrap { max-width: 820px; margin: 0 auto; }
.invoice-actions { display: flex; gap: .6rem; margin-bottom: 1.2rem; }
@media print {
    .admin-sidebar, .admin-topbar, .invoice-actions { display: none !important; }
    .admin-main { margin: 0 !important; padding: 0 !important; }
    body { background: #fff !important; }
    .invoice { box-shadow: none !important; border: none !important; }
}
.invoice {
    background: #fff; border: 1px solid var(--line); border-radius: 12px;
    padding: 2.2rem; box-shadow: var(--shadow-soft); color: #0e1729;
}
.invoice-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid <?= e($invColor) ?>; padding-bottom: 1.2rem; margin-bottom: 1.5rem; }
.invoice-store { display: flex; align-items: center; gap: .8rem; }
.invoice-store .inv-logo { width: 52px; height: 52px; object-fit: contain; }
.invoice-store h2 { margin: 0; color: <?= e($invColor) ?>; font-size: 1.4rem; }
.invoice-store p { margin: .2rem 0; font-size: .82rem; color: var(--ink-soft); }
.invoice-title { text-align: left; }
.invoice-title h1 { margin: 0; font-size: 1.6rem; color: <?= e($invColor) ?>; }
.invoice-title .inv-no { font-size: .9rem; color: var(--ink-soft); }
.invoice-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 1.5rem; font-size: .88rem; }
.invoice-meta dt { font-weight: 700; color: <?= e($invColor) ?>; margin-bottom: .2rem; }
.invoice-meta dd { margin: 0 0 .4rem; }
.invoice table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.invoice th { background: <?= e($invColor) ?>; color: #fff; padding: .6rem .8rem; text-align: right; }
.invoice td { padding: .55rem .8rem; border-bottom: 1px solid var(--line); }
.invoice .num { text-align: left; }
.invoice-totals { margin-top: 1.2rem; margin-inline-start: auto; width: 280px; font-size: .92rem; }
.invoice-totals .row { display: flex; justify-content: space-between; padding: .3rem 0; }
.invoice-totals .row.grand { border-top: 2px solid <?= e($invColor) ?>; margin-top: .4rem; padding-top: .6rem; font-weight: 800; color: <?= e($invColor) ?>; font-size: 1.05rem; }
.invoice-foot { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--line); font-size: .78rem; color: var(--ink-soft); text-align: center; }
</style>

<div class="invoice-wrap">
    <div class="invoice-actions">
        <a href="orders.php?id=<?= (int)$orderId ?>" class="btn btn-ghost">→ بازگشت به سفارش</a>
        <button class="btn btn-accent" onclick="window.print()">🖨 چاپ / ذخیره PDF</button>
    </div>

    <div class="invoice">
        <div class="invoice-head">
            <div class="invoice-store">
                <?php if ($showLogo && $logo !== ''): ?>
                    <img src="<?= e(product_image_url($logo)) ?>" alt="لوگو" class="inv-logo">
                <?php endif; ?>
                <div>
                    <h2><?= e($storeName) ?></h2>
                    <?php if ($storeAddr): ?><p><?= e($storeAddr) ?></p><?php endif; ?>
                    <?php if ($storePhone): ?><p dir="ltr"><?= e(fa_digits($storePhone)) ?></p><?php endif; ?>
                    <?php if ($storeEmail): ?><p dir="ltr"><?= e($storeEmail) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="invoice-title">
                <h1>فاکتور فروش</h1>
                <span class="inv-no">شماره: <?= (int)$order['id'] ?> — <?= e(persian_date($order['created_at'], true)) ?></span>
            </div>
        </div>

        <div class="invoice-meta">
            <div>
                <dl>
                    <dt>مشتری</dt>
                    <dd><?= e($order['customer_name']) ?></dd>
                    <?php if ($order['customer_phone']): ?><dd dir="ltr"><?= e($order['customer_phone']) ?></dd><?php endif; ?>
                    <?php if ($order['customer_email']): ?><dd dir="ltr"><?= e($order['customer_email']) ?></dd><?php endif; ?>
                    <?php if ($order['address']): ?><dd><?= e($order['address']) ?></dd><?php endif; ?>
                </dl>
            </div>
            <div>
                <dl>
                    <dt>وضعیت</dt>
                    <dd><?= e(status_label($order['status'])) ?></dd>
                    <?php if ($order['ref_id']): ?><dt>کد پیگیری</dt><dd dir="ltr"><?= e($order['ref_id']) ?></dd><?php endif; ?>
                    <?php if ($order['coupon_code']): ?><dt>کوپن</dt><dd><?= e($order['coupon_code']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>#</th><th>شرح کالا</th><th class="num">قیمت واحد</th><th class="num">تعداد</th><th class="num">جمع</th></tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($items as $it): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= e($it['product_name']) ?><?= !empty($it['variant_name']) ? ' — ' . e($it['variant_name']) : '' ?></td>
                    <td class="num"><?= e(fmt_price($it['price'])) ?></td>
                    <td class="num"><?= fa_digits((int)$it['quantity']) ?></td>
                    <td class="num"><?= e(fmt_price($it['price'] * $it['quantity'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-totals">
            <div class="row"><span>جمع کالاها</span><span><?= e(fmt_price($order['subtotal'])) ?></span></div>
            <?php if ((int)$order['discount_amount'] > 0): ?>
            <div class="row"><span>تخفیف</span><span><?= e(fmt_price($order['discount_amount'])) ?></span></div>
            <?php endif; ?>
            <?php if ((int)$order['points_redeemed'] > 0): ?>
            <div class="row"><span>امتیاز بازخریدی</span><span><?= e(fmt_price($order['points_redeemed'])) ?></span></div>
            <?php endif; ?>
            <div class="row"><span>هزینهٔ ارسال</span><span><?= e(fmt_price($order['shipping_amount'])) ?></span></div>
            <div class="row grand"><span>مبلغ قابل پرداخت</span><span><?= e(fmt_price($order['total_amount'])) ?></span></div>
        </div>

        <div class="invoice-foot">
            <?= e($invNote) ?><br>
            <?= e($storeName) ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
