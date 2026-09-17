<?php
/**
 * account.php — داشبورد باشگاه مشتریان
 * نمایش اطلاعات حساب، آمار خرید و تاریخچهٔ سفارش‌ها
 */
require_once __DIR__ . '/config.php';

if (!is_customer_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php?next=' . urlencode('/account.php'));
    exit;
}

$user  = current_customer();
$stats = customer_stats((int)$user['id']);
$orders = customer_orders((int)$user['id']);
$requests = customer_requests((int)$user['id']);
// ---- اطلاعات کد معرف ----
try {
    $myRefCode = user_referral_code((int)$user['id']);
    $refStats = db()->prepare("SELECT
            COUNT(*) AS joined,
            COALESCE(SUM(CASE WHEN status = 'rewarded' THEN points_awarded ELSE 0 END), 0) AS earned
        FROM referrals WHERE referrer_id = ?");
    $refStats->execute([(int)$user['id']]);
    $refStats = $refStats->fetch() ?: ['joined' => 0, 'earned' => 0];
} catch (Throwable $rt) {
    $myRefCode = '';
    $refStats = ['joined' => 0, 'earned' => 0];
}

$myPointsTx = [];
try {
    $myPointsTx = points_transactions((int)$user['id'], 15);
} catch (Throwable $t) {
    // ---- اطلاعات کد معرف ----
try {
    $myRefCode = user_referral_code((int)$user['id']);
    $refStats = db()->prepare("SELECT
            COUNT(*) AS joined,
            COALESCE(SUM(CASE WHEN status = 'rewarded' THEN points_awarded ELSE 0 END), 0) AS earned
        FROM referrals WHERE referrer_id = ?");
    $refStats->execute([(int)$user['id']]);
    $refStats = $refStats->fetch() ?: ['joined' => 0, 'earned' => 0];
} catch (Throwable $rt) {
    $myRefCode = '';
    $refStats = ['joined' => 0, 'earned' => 0];
}

$myPointsTx = [];
}

$pageTitle = 'حساب کاربری | باشگاه مشتریان';
require __DIR__ . '/includes/header.php';
?>

<section class="container account">
    <div class="account-head">
        <div>
            <p class="auth-eyebrow">باشگاه مشتریان</p>
            <h1 class="page-title">سلام، <?= e($user['full_name'] ?: $user['username']) ?></h1>
        </div>
        <div class="account-head-actions">
            <a href="custom_request.php" class="btn btn-accent">+ درخواست ابزار سفارشی</a>
            <a href="logout.php" class="btn btn-ghost">خروج از حساب</a>
        </div>
    </div>

    <div class="account-stats">
        <div class="stat-card">
            <span class="stat-num"><?= $stats['orders'] ?></span>
            <span class="stat-label">سفارش ثبت‌شده</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= fa_digits(number_format((int)$stats['points'], 0, '.', ',')) ?></span>
            <span class="stat-label">امتیاز باشگاه</span>
            <span class="stat-sub">سطح: <?= e($stats['tier']['label'] ?? 'برنزی') ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= e(fmt_price($stats['spent'])) ?></span>
            <span class="stat-label">مجموع خرید پرداخت‌شده</span>
        </div>
    </div>

    <div class="account-grid">
        <aside class="account-info">
            <h2>اطلاعات حساب</h2>
            <dl>
                <dt>نام کاربری</dt><dd><?= e($user['username']) ?></dd>
                <dt>نام کامل</dt><dd><?= e($user['full_name'] ?: '—') ?></dd>
                <dt>ایمیل</dt><dd><?= e($user['email'] ?: '—') ?></dd>
                <dt>شماره تماس</dt><dd><?= e($user['phone'] ?? '' ?: '—') ?></dd>
                <dt>تاریخ عضویت</dt><dd><?= e(persian_date($user['created_at'] ?? null)) ?></dd>
            </dl>
            <p class="account-note">هر <?= e(fa_digits(number_format((int)setting('loyalty_earn_rate', '10000'), 0, '.', ','))) ?> تومان خریدِ پرداخت‌شده = ۱ امتیاز؛ هر ۱ امتیاز = ۱ تومان تخفیف در تسویه. سطوح: برنز (تا ۴۹۹ امتیاز)، نقره‌ای (۵۰۰ تا ۱۹۹۹)، طلایی (۲۰۰۰ و بیشتر). سطح فعلی شما: <strong><?= e($stats['tier']['label'] ?? 'برنز') ?></strong>.</p>
        
            <div class="referral-box">
                <h3>دعوت دوستان — امتیاز هدیه بگیرید</h3>
                <p>کد معرف اختصاصی شما:</p>
                <div class="referral-code" dir="ltr"><?= e($myRefCode ?: '—') ?></div>
                <p class="muted">لینک دعوت شما (با کلیک، کپی می‌شود):</p>
                <input class="referral-link" type="text" readonly dir="ltr" value="<?= e(BASE_URL . '/register.php?ref=' . $myRefCode) ?>" onclick="this.select()">
                <p class="muted">با هر عضویت با کد شما و اولین خریدِ پرداخت‌شدهٔ او، <?= e(fa_digits(number_format((int)setting('referral_reward_points', '100'), 0, '.', ','))) ?> امتیاز هدیه می‌گیرید.</p>
                <p>تاکنون <strong><?= fa_digits(number_format((int)$refStats['joined'], 0, '.', ',')) ?></strong> نفر با کد شما عضو شده‌اند و <strong><?= fa_digits(number_format((int)$refStats['earned'], 0, '.', ',')) ?></strong> امتیاز از معرفی گرفته‌اید.</p>
            </div>
</aside>

        <section class="account-orders">
            <h2>تاریخچهٔ سفارش‌ها</h2>
            <?php if (!$orders): ?>
                <div class="empty-state">
                    <p>هنوز سفارشی ثبت نکرده‌اید.</p>
                    <a href="index.php" class="btn btn-accent">شروع خرید</a>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $o): ?>
                        <a href="view_order.php?id=<?= (int)$o['id'] ?>" class="order-row">
                            <span class="order-id">سفارش #<?= (int)$o['id'] ?></span>
                            <span class="order-date"><?= e(persian_date($o['created_at'] ?? null)) ?></span>
                            <span class="order-amount"><?= e(fmt_price($o['total_amount'])) ?></span>
                            <span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="account-points-history">
        <h2>تاریخچهٔ امتیازهای من</h2>
        <?php if (!$myPointsTx): ?>
            <div class="empty-state">
                <p>هنوز امتیازی ثبت نشده. با اولین خریدِ پرداخت‌شده، امتیاز می‌گیرید!</p>
            </div>
        <?php else: ?>
            <table class="points-tx-table">
                <thead>
                    <tr><th>رویداد</th><th>امتیاز</th><th>موجودی</th><th>توضیح</th><th>تاریخ</th></tr>
                </thead>
                <tbody>
                <?php foreach ($myPointsTx as $tx): $amt = (int)$tx['amount']; $isPos = $amt > 0; ?>
                    <tr>
                        <td><?= e(points_txn_label($tx['type'], $amt)) ?></td>
                        <td class="<?= $isPos ? 'pts-pos' : 'pts-neg' ?>"><?= $isPos ? '+' : '' ?><?= fa_digits(number_format($amt, 0, '.', ',')) ?></td>
                        <td><?= fa_digits(number_format((int)$tx['balance_after'], 0, '.', ',')) ?></td>
                        <td><?= e($tx['description'] ?: '-') ?></td>
                        <td><?= e(persian_date($tx['created_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="account-requests">
        <div class="section-head">
            <h2 class="section-title">درخواست‌های ابزار سفارشی</h2>
            <a href="custom_request.php" class="btn btn-ghost btn-sm">+ درخواست جدید</a>
        </div>
        <?php if (!$requests): ?>
            <div class="empty-state">
                <p>هنوز درخواستی ثبت نکرده‌اید. ابزار مخصوص خودتان را سفارش دهید.</p>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($requests as $r): ?>
                    <div class="order-row req-row">
                        <span class="order-id">درخواست #<?= (int)$r['id'] ?></span>
                        <span class="order-name"><?= e($r['title']) ?></span>
                        <span class="order-date"><?= e(persian_date($r['created_at'] ?? null)) ?></span>
                        <span class="req-qty">تعداد: <?= (int)$r['quantity'] ?></span>
                        <span class="status req-<?= e($r['status']) ?>"><?= e(request_status_label($r['status'])) ?></span>
                    </div>
                    <?php if ($r['status'] === 'approved' && $r['product_id']): ?>
                        <div class="req-result">
                            <a href="product.php?id=<?= (int)$r['product_id'] ?>" class="btn btn-accent btn-sm">مشاهدهٔ محصول شما</a>
                            <?php if ($r['price']): ?><span>قیمت نهایی: <strong><?= e(fmt_price($r['price'])) ?></strong></span><?php endif; ?>
                        </div>
                    <?php elseif ($r['status'] === 'rejected' && $r['admin_note']): ?>
                        <div class="req-result req-rejected">پاسخ مدیر: <?= e($r['admin_note']) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
