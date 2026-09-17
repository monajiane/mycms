<?php
/**
 * admin/orders.php — مدیریت سفارش‌ها (فهرست، جزئیات، تغییر وضعیت)
 */
$pageTitle = 'مدیریت سفارش‌ها';
require __DIR__ . '/_header.php';

// تغییر وضعیت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $allowed = ['pending', 'paid', 'failed', 'cancelled'];
    $status = in_array($_POST['status'], $allowed, true) ? $_POST['status'] : 'pending';
    $orderId = (int)$_POST['order_id'];

    // وضعیت قبلی را بخوان تا در صورت لغو/شکست، اثرات کوپن و امتیاز بازگردانده شود
    $old = db()->prepare("SELECT status, coupon_code, points_redeemed, user_id FROM orders WHERE id = ?");
    $old->execute([$orderId]);
    $prev = $old->fetch();
    $wasPaid = $prev && in_array($prev['status'], ['paid'], true);

    db()->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);

    // اگر سفارش از «پرداخت‌شده» به «لغو/شکست» رفت، کوپن و امتیاز بازخریدی را برگردان
    if ($wasPaid && in_array($status, ['cancelled', 'failed'], true)) {
        // بازگرداندن تعداد استفادهٔ کوپن
        if (!empty($prev['coupon_code'])) {
            db()->prepare("UPDATE coupons SET used_count = used_count - 1 WHERE code = ? AND used_count > 0")
                ->execute([$prev['coupon_code']]);
        }
        // بازگرداندن امتیاز بازخریدی به کاربر (در صورت وجود)
        if (!empty($prev['user_id']) && (int)$prev['points_redeemed'] > 0) {
            add_points((int)$prev['user_id'], (int)$prev['points_redeemed'], 'refund', 'بازگرداندن امتیاز سفارش لغوشده', $orderId);
        }
    }

    flash('success', 'وضعیت سفارش به‌روزرسانی شد.');
    header('Location: orders.php' . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
    exit;
}

// افزودن رویداد رهگیری (وضعیت ارسال)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order_id'], $_POST['track_status'])) {
    $allowedStatus = array_keys(tracking_statuses());
    $tStatus = in_array($_POST['track_status'], $allowedStatus, true) ? $_POST['track_status'] : 'processing';
    $tOrderId = (int)$_POST['track_order_id'];
    $tNote = trim($_POST['track_note'] ?? '');
    order_tracking_add($tOrderId, $tStatus, $tNote);
    flash('success', 'وضعیت ارسال ثبت شد.');
    header('Location: orders.php?id=' . $tOrderId);
    exit;
}

// نمایش جزئیات یک سفارش
$detail = null;
if (isset($_GET['id'])) {
    $st = db()->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([(int)$_GET['id']]);
    $detail = $st->fetch();
    $items = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items->execute([(int)$_GET['id']]);
    $detailItems = $items->fetchAll();
}

if ($detail) {
    ?>
    <h1 class="page-title">جزئیات سفارش #<?= (int)$detail['id'] ?></h1>
    <a href="orders.php" class="btn btn-ghost btn-sm">→ بازگشت به فهرست</a>
    <a href="invoice.php?id=<?= (int)$detail['id'] ?>" class="btn btn-accent btn-sm">🖨 فاکتور / PDF</a>

    <div class="dash-panel">
        <dl class="detail-meta">
            <dt>مشتری</dt><dd><?= e($detail['customer_name']) ?></dd>
            <dt>تلفن</dt><dd><?= e($detail['customer_phone']) ?></dd>
            <dt>ایمیل</dt><dd><?= e($detail['customer_email'] ?: '—') ?></dd>
            <dt>آدرس</dt><dd><?= e($detail['address']) ?></dd>
            <dt>کد پستی</dt><dd><?= e($detail['postal_code'] ?: '—') ?></dd>
            <dt>مبلغ</dt><dd><?= e(fmt_price($detail['total_amount'])) ?></dd>
            <dt>تاریخ</dt><dd><?= e($detail['created_at']) ?></dd>
            <?php if ($detail['ref_id']): ?><dt>کد پیگیری</dt><dd><?= e($detail['ref_id']) ?></dd><?php endif; ?>
            <dt>وضعیت فعلی</dt><dd><span class="status status-<?= e($detail['status']) ?>"><?= e(status_label($detail['status'])) ?></span></dd>
        </dl>
    </div>

    <div class="dash-panel">
        <h2>اقلام سفارش</h2>
        <table class="data-table">
            <thead><tr><th>محصول</th><th>قیمت</th><th>تعداد</th><th>جمع</th></tr></thead>
            <tbody>
            <?php foreach ($detailItems as $it): ?>
                <tr>
                    <td><?= e($it['product_name']) ?><?= !empty($it['variant_name']) ? ' <span class="cart-variant">' . e($it['variant_name']) . '</span>' : '' ?></td>
                    <td><?= e(fmt_price($it['price'])) ?></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td><?= e(fmt_price($it['price'] * $it['quantity'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="dash-panel">
        <h2>وضعیت ارسال (رهگیری)</h2>
        <?php $trackEvents = order_tracking_events((int)$detail['id']); ?>
        <?php if ($trackEvents): ?>
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
        <?php else: ?>
            <p class="muted">هنوز وضعیتی ثبت نشده است.</p>
        <?php endif; ?>

        <form method="post" action="orders.php?id=<?= (int)$detail['id'] ?>" class="admin-form inline-form" style="margin-top:1rem;">
            <input type="hidden" name="track_order_id" value="<?= (int)$detail['id'] ?>">
            <select name="track_status">
                <?php foreach (tracking_statuses() as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="track_note" placeholder="توضیح اختیاری (مثلاً کد رهگیری پست)" style="flex:1;">
            <button type="submit" class="btn btn-accent btn-sm">ثبت وضعیت</button>
        </form>
    </div>

    <form method="post" action="orders.php?id=<?= (int)$detail['id'] ?>" class="admin-form inline-form">
        <input type="hidden" name="order_id" value="<?= (int)$detail['id'] ?>">
        <select name="status">
            <?php foreach (['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت شده', 'failed' => 'ناموفق', 'cancelled' => 'لغو شده'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $detail['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-accent">به‌روزرسانی وضعیت</button>
    </form>
    <?php
} else {
    $orders = db()->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();
    ?>
    <h1 class="page-title">مدیریت سفارش‌ها</h1>

    <table class="data-table">
        <thead><tr><th>#</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
        <tbody>
        <?php if (!$orders): ?>
            <tr><td colspan="6" class="muted">هنوز سفارشی ثبت نشده است.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= (int)$o['id'] ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= e(fmt_price($o['total_amount'])) ?></td>
                <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                <td><?= e($o['created_at']) ?></td>
                <td><a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-ghost btn-sm">جزئیات</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

require __DIR__ . '/_footer.php';
