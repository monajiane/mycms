<?php
/**
 * track.php — رهگیری عمومی سفارش (با کد پیگیری/شماره سفارش + شماره موبایل)
 * ------------------------------------------------------------------
 * بدون نیاز به ورود: کاربر شماره سفارش و شماره موبایلش را وارد می‌کند
 * تا وضعیت مرسوله (آماده‌سازی / آماده ارسال / تحویل به پست) را ببیند.
 */
require_once __DIR__ . '/config.php';

if (!tracking_enabled()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$info  = '';
$order = null;
$events = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $orderId = trim($_POST['order_id'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');

        if ($orderId === '' || $phone === '') {
            $error = 'شمارهٔ سفارش و شمارهٔ موبایل را وارد کنید.';
        } else {
            $st = db()->prepare("SELECT * FROM orders WHERE id = ?");
            $st->execute([(int)$orderId]);
            $order = $st->fetch();

            $normPhone = sms_normalize_phone($phone);
            $storedPhone = $order['customer_phone'] ?? '';

            if (!$order) {
                $error = 'سفارشی با این شماره پیدا نشد.';
            } elseif ($normPhone === null || sms_normalize_phone($storedPhone) !== $normPhone) {
                $error = 'شمارهٔ موبایل با شمارهٔ ثبت‌شده در سفارش مطابقت ندارد.';
            } else {
                $events = order_tracking_events((int)$order['id']);
                if (!$events) {
                    $info = 'هنوز وضعیت ارسالی برای این سفارش ثبت نشده است.';
                }
            }
        }
    }
}

$pageTitle = 'رهگیری سفارش';
require __DIR__ . '/includes/header.php';
?>

<section class="container track-wrap">
    <h1 class="page-title">رهگیری سفارش</h1>
    <p class="muted">شمارهٔ سفارش و شمارهٔ موبایلی که هنگام خرید ثبت کرده‌اید را وارد کنید تا وضعیت مرسوله را ببینید.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-info"><?= e($info) ?></div><?php endif; ?>

    <form method="post" action="track.php" class="auth-form track-form" novalidate>
        <?= csrf_field() ?>
        <label>شمارهٔ سفارش
            <input type="text" name="order_id" value="<?= e($_POST['order_id'] ?? '') ?>" dir="ltr" inputmode="numeric" placeholder="مثلاً 25" required autofocus>
        </label>
        <label>شمارهٔ موبایل ثبت‌شده
            <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" dir="ltr" placeholder="09123456789" required>
        </label>
        <button type="submit" class="btn btn-accent btn-block">مشاهدهٔ وضعیت</button>
    </form>

    <?php if ($order && $events): ?>
        <div class="track-result">
            <h2>سفارش #<?= (int)$order['id'] ?></h2>
            <div class="track-meta">
                <span>وضعیت پرداخت: <strong class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></strong></span>
                <span>تاریخ ثبت: <?= e(persian_date($order['created_at'])) ?></span>
            </div>
            <ol class="track-timeline">
                <?php foreach ($events as $ev): ?>
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
        </div>
    <?php elseif ($order && $info): ?>
        <div class="track-result">
            <h2>سفارش #<?= (int)$order['id'] ?></h2>
            <p>وضعیت فعلی: <strong><?= e(tracking_label(order_tracking_status((int)$order['id'])['status'] ?? 'processing')) ?></strong></p>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
