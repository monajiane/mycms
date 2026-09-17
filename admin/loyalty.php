<?php
/**
 * admin/loyalty.php — مدیریت امتیاز و باشگاه مشتریان
 * افزودن/کسر دستی امتیاز، مشاهدهٔ سطح عضویت و تاریخچهٔ تراکنش‌ها
 *
 * وابستگی: توابع امتیاز (user_points/add_points/loyalty_tier/points_transactions)
 * در includes/functions.php و جدول points_transactions + ستون users.points
 * (مطابق مستندات loyalty.md). در صورت نبودن، صفحه راهنمای نصب نشان می‌دهد.
 */
$pageTitle = 'باشگاه مشتریان و امتیاز';
require __DIR__ . '/_header.php';

// ---- بررسی آماده بودن زیرساخت امتیاز ----
$pointsFunctionsReady = function_exists('user_points')
    && function_exists('add_points')
    && function_exists('loyalty_tier')
    && function_exists('points_transactions');
$pointsTableReady = false;
try {
    db()->query("SELECT COUNT(*) FROM points_transactions");
    $pointsTableReady = true;
} catch (Throwable $t) {
    $pointsTableReady = false;
}

if (!$pointsFunctionsReady || !$pointsTableReady) {
    echo '<h1 class="page-title">باشگاه مشتریان و امتیاز</h1>';
    echo '<div class="alert alert-error"><p>زیرساخت امتیاز هنوز راه‌اندازی نشده است. لطفاً تغییرات مستندشده در <code>loyalty.md</code> را اعمال کنید: ستون <code>users.points</code>، جدول <code>points_transactions</code> و توابع امتیاز در <code>includes/functions.php</code>.</p></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

/** برچسب فارسی نوع تراکنش امتیاز */

// ---- افزودن / کسر دستی امتیاز ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: loyalty.php');
        exit;
    }
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    $op     = ($_POST['op'] ?? 'add') === 'sub' ? 'sub' : 'add';
    $desc   = trim($_POST['description'] ?? '');

    if ($userId <= 0 || $amount <= 0) {
        flash('error', 'کاربر و مقدار امتیاز را به‌درستی وارد کنید.');
    } else {
        $chk = db()->prepare("SELECT id, username FROM users WHERE id = ?");
        $chk->execute([$userId]);
        $target = $chk->fetch();
        if (!$target) {
            flash('error', 'کاربر موردنظر یافت نشد.');
        } else {
            $delta = ($op === 'sub') ? -$amount : $amount;
            $note  = $desc !== '' ? $desc : (($op === 'sub') ? 'کسر دستی امتیاز توسط مدیر' : 'افزایش دستی امتیاز توسط مدیر');
            if (add_points($userId, $delta, 'manual', $note, null, (int)$_SESSION['admin_id'])) {
                // ---- پیامک اطلاع‌رسانی تغییر امتیاز ----
                if (isset($_POST['notify_sms']) && trim($_POST['notify_sms'] ?? '') !== '') {
                    sms_send(
                        trim($_POST['notify_sms']),
                        sms_template('points_adjusted', [
                            '{name}'   => $target['full_name'] ?: $target['username'],
                            '{points}' => user_points($userId),
                        ]),
                        'points_adjusted'
                    );
                }
                flash('success', 'امتیاز کاربر «' . $target['username'] . '» ' . (($op === 'sub') ? 'کسر' : 'افزایش') . ' شد (مقدار: ' . $amount . ' امتیاز).');
            } else {
                // ناموفق = موجودی کافی نبود (کسر از پنل دیگر موجودی را منفی نمی‌کند)
                flash('error', 'عملیات انجام نشد؛ موجودی امتیاز کاربر برای کسر کافی نیست یا خطایی رخ داد.');
            }
        }
    }
    header('Location: loyalty.php');
    exit;
}

// ---- داده‌ها ----
$customers = db()->query("SELECT id, username, full_name, email, COALESCE(points, 0) AS points, created_at
                          FROM users WHERE role = 'customer'
                          ORDER BY COALESCE(points, 0) DESC, id")->fetchAll();

$recentTx = [];
try {
    $recentTx = points_transactions(null, 40);
} catch (Throwable $t) {
    $recentTx = [];
}

$referrals = [];
try {
    $referrals = db()->query("SELECT r.*, u1.username AS referrer_name, u2.username AS invitee_name
        FROM referrals r
        LEFT JOIN users u1 ON u1.id = r.referrer_id
        LEFT JOIN users u2 ON u2.id = r.invitee_id
        ORDER BY r.id DESC LIMIT 50")->fetchAll();
} catch (Throwable $rt) {
    $referrals = [];
}

$totalPoints = (int)db()->query("SELECT COALESCE(SUM(points), 0) FROM users WHERE role = 'customer'")->fetchColumn();
?>

<style>
.tier-badge { display:inline-block; padding:2px 10px; border-radius:12px; color:#fff; font-size:12px; }
.tier-bronze { background:#a0522d; }
.tier-silver { background:#6b7280; }
.tier-gold   { background:#b8860b; }
.pts-pos { color:#15803d; font-weight:600; }
.pts-neg { color:#b91c1c; font-weight:600; }
</style>

<h1 class="page-title">باشگاه مشتریان و امتیاز</h1>

<?php $f = get_flash('success'); if ($f): ?><div class="alert alert-success"><?= e($f) ?></div><?php endif; ?>
<?php $f = get_flash('error'); if ($f): ?><div class="alert alert-error"><?= e($f) ?></div><?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-num"><?= count($customers) ?></span><span class="stat-label">مشتری</span></div>
    <div class="stat-card"><span class="stat-num"><?= fa_digits(number_format($totalPoints, 0, '.', ',')) ?></span><span class="stat-label">مجموع امتیاز فعال</span></div>
</div>

<div class="dash-cols">
    <section class="dash-panel">
        <h2>افزودن / کسر امتیاز</h2>
        <form method="post" action="loyalty.php" class="admin-form inline-form">
            <input type="hidden" name="action" value="adjust">
            <?= csrf_field() ?>
            <input type="number" name="user_id" placeholder="شناسهٔ کاربر" min="1" required>
            <select name="op">
                <option value="add">افزودن</option>
                <option value="sub">کسر</option>
            </select>
            <input type="number" name="amount" placeholder="مقدار امتیاز" min="1" required>
            <input type="text" name="description" placeholder="توضیح (اختیاری)">
            <button type="submit" class="btn btn-accent">ثبت</button>
        </form>
        <p class="muted">نکته: «شناسهٔ کاربر» همان ستون # در جدول مشتریان زیر است.</p>
    </section>
</div>

<div class="dash-panel">
    <h2>مشتریان و سطح عضویت</h2>
    <table class="data-table">
        <thead><tr><th>#</th><th>نام کاربری</th><th>نام کامل</th><th>امتیاز</th><th>سطح</th></tr></thead>
        <tbody>
        <?php if (!$customers): ?>
            <tr><td colspan="5" class="muted">هنوز مشتری‌ای ثبت نشده است.</td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $c): $tier = loyalty_tier((int)$c['points']); ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= e($c['username']) ?></td>
                <td><?= e($c['full_name'] ?: '—') ?></td>
                <td><?= fa_digits(number_format((int)$c['points'], 0, '.', ',')) ?></td>
                <td><span class="tier-badge tier-<?= e($tier['key']) ?>"><?= e($tier['label']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="dash-panel">
    <h2>تاریخچهٔ تراکنش‌های امتیاز</h2>
    <table class="data-table">
        <thead><tr><th>#</th><th>کاربر</th><th>نوع</th><th>مقدار</th><th>موجودی پس از</th><th>توضیح</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php if (!$recentTx): ?>
            <tr><td colspan="7" class="muted">هنوز تراکنشی ثبت نشده است.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentTx as $t): $amt = (int)$t['amount']; ?>
            <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= e($t['username'] ?? ('کاربر #' . (int)$t['user_id'])) ?></td>
                <td><?= e(points_txn_label($t['type'], $amt)) ?></td>
                <td class="<?= $amt > 0 ? 'pts-pos' : 'pts-neg' ?>"><?= $amt > 0 ? '+' : '' ?><?= fa_digits(number_format($amt, 0, '.', ',')) ?></td>
                <td><?= fa_digits(number_format((int)$t['balance_after'], 0, '.', ',')) ?></td>
                <td><?= e($t['description'] ?: '—') ?></td>
                <td><?= e(persian_date($t['created_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-card" style="margin-top:2rem">
    <h2>معرفی دوستان (کد معرف)</h2>
    <p class="muted">هر مشتری کد اختصاصی خود را در «حساب کاربری» می‌بیند. پاداش فقط پس از اولین خریدِ پرداخت‌شدهٔ مهمان پرداخت می‌شود.</p>
    <?php if (!$referrals): ?>
        <p class="muted">هنوز هیچ معارفه‌ای ثبت نشده است.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>معرف</th><th>عضو جدید</th><th>وضعیت</th><th>امتیاز پرداختی</th><th>سفارش</th><th>تاریخ عضویت</th></tr>
        </thead>
        <tbody>
        <?php foreach ($referrals as $r): $st = $r['status'] === 'rewarded'; ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['referrer_name'] ?: ('#' . $r['referrer_id'])) ?></td>
                <td><?= e($r['invitee_name'] ?: ('#' . $r['invitee_id'])) ?></td>
                <td><span class="tier-badge <?= $st ? 'tier-gold' : 'tier-silver' ?>"><?= $st ? 'پاداش داده شد' : 'منتظر اولین خرید' ?></span></td>
                <td><?= fa_digits(number_format((int)$r['points_awarded'], 0, '.', ',')) ?></td>
                <td><?= $r['ref_order_id'] ? '#' . (int)$r['ref_order_id'] : '-' ?></td>
                <td><?= e(persian_date($r['created_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
