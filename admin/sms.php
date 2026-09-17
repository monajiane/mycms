<?php
/**
 * admin/sms.php — پنل پیامک فروشگاه
 * ------------------------------------------------------------------
 * - ارسال پیامک تکی و گروهی (همهٔ مشتریان / بر اساس سطح عضویت)
 * - تنظیمات سرویس (کاوه‌نگار یا حالت آزمایشی)
 * - لاگ کامل همهٔ ارسال‌های سامانه (خودکار و دستی)
 */
$pageTitle = 'پنل پیامک';
require __DIR__ . '/_header.php';

// ---- اطمینان از وجود جدول لاگ ----
$smsTableReady = false;
try {
    db()->query("SELECT COUNT(*) FROM sms_log");
    $smsTableReady = true;
} catch (Throwable $t) {
    $smsTableReady = false;
}
if (!$smsTableReady) {
    echo '<h1 class="page-title">پنل پیامک</h1>';
    echo '<div class="alert alert-error"><p>جدول <code>sms_log</code> هنوز ساخته نشده است. یک بار صفحهٔ اصلی سایت را باز کنید تا مهاجرت دیتابیس خودکار اجرا شود.</p></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

/** دریافت لیست گیرندگان بر اساس مخاطب انتخابی */
function sms_recipients($audience, $tierFilter = '')
{
    if ($audience === 'all') {
        return db()->query("SELECT id, username, full_name, phone FROM users WHERE role='customer' ORDER BY id")
                    ->fetchAll();
    }
    // فیلتر سطح عضویت (بر اساس آستانه‌های loyalty_tier: برنز<500، نقره 500..1999، طلا >=2000)
    list($min, $max) = [0, PHP_INT_MAX];
    if ($tierFilter === 'silver') { $min = 500; $max = 1999; }
    if ($tierFilter === 'gold')   { $min = 2000; $max = PHP_INT_MAX; }
    if ($tierFilter === 'bronze') { $min = 0; $max = 499; }

    $st = db()->prepare("SELECT id, username, full_name, phone, COALESCE(points,0) AS points
                         FROM users WHERE role='customer' ORDER BY id");
    $st->execute();
    $out = [];
    foreach ($st->fetchAll() as $u) {
        $p = (int)$u['points'];
        if ($p >= $min && $p <= $max && !empty($u['phone'])) {
            $out[] = $u;
        }
    }
    return $out;
}

// ---- پردازش ارسال ----
$sentCount = 0; $failCount = 0; $lastMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_single' || $action === 'send_bulk') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            flash('error', 'متن پیام خالی است.');
        } elseif (mb_strlen($message) > 600) {
            flash('error', 'متن پیام بیش از حد بلند است (حداکثر ۶۰۰ کاراکتر).');
        } else {
            if ($action === 'send_single') {
                $phone = trim($_POST['phone'] ?? '');
                $r = sms_send($phone, $message, 'admin_manual');
                $r['ok'] ? $sentCount++ : $failCount++;
                $lastMessage = $message;
                flash($r['ok'] ? 'success' : 'error', 'ارسال به «' . $phone . '»: ' . $r['status']);
            } else {
                $audience = ($_POST['audience'] ?? '') === 'tier' ? 'tier' : 'all';
                $tierFilter = in_array($_POST['tier_filter'] ?? '', ['bronze', 'silver', 'gold'], true) ? $_POST['tier_filter'] : '';
                $recipients = sms_recipients($audience, $tierFilter);
                foreach ($recipients as $u) {
                    if (empty($u['phone'])) { continue; }
                    // جای‌گذاری نام مشتری در متن
                    $msg = str_replace('{name}', $u['full_name'] ?: $u['username'], $message);
                    $r = sms_send($u['phone'], $msg, 'admin_bulk');
                    $r['ok'] ? $sentCount++ : $failCount++;
                }
                $lastMessage = $message;
                flash(count($recipients) > 0 ? 'success' : 'error',
                    count($recipients) . ' گیرنده پیدا شد؛ ارسال انجام شد. (موفق: ' . $sentCount . '، ناموفق: ' . $failCount . ')');
            }
        }
    }
    header('Location: sms.php');
    exit;
}

$log = sms_log_recent(80);
$provider = setting('sms_provider', 'log');
$customersCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$withPhone = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer' AND phone IS NOT NULL AND phone != ''")->fetchColumn();
?>

<style>
.sms-status { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; color:#fff; }
.sms-sent   { background:#16a34a; }
.sms-failed { background:#dc2626; }
.sms-audience-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:.8rem; margin-bottom:.8rem; }
.sms-count-hint { font-size:.85rem; color:var(--ink-soft,#64748b); }
</style>

<h1 class="page-title">پنل پیامک</h1>

<?php $f = get_flash('success'); if ($f): ?><div class="alert alert-success"><?= e($f) ?></div><?php endif; ?>
<?php $f = get_flash('error'); if ($f): ?><div class="alert alert-error"><?= e($f) ?></div><?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-num"><?= e($provider === 'kavenegar' ? 'کاوه‌نگار' : 'آزمایشی') ?></span><span class="stat-label">سرویس فعال</span></div>
    <div class="stat-card"><span class="stat-num"><?= $customersCount ?></span><span class="stat-label">مشتری عضو</span></div>
    <div class="stat-card"><span class="stat-num"><?= $withPhone ?></span><span class="stat-label">دارای شماره موبایل</span></div>
</div>

<div class="dash-panel">
    <h2>ارسال پیامک تکی</h2>
    <form method="post" action="sms.php" class="admin-form inline-form">
        <input type="hidden" name="action" value="send_single">
        <input type="text" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required dir="ltr">
        <input type="text" name="message" placeholder="متن پیام..." required>
        <button type="submit" class="btn btn-accent">ارسال</button>
    </form>
</div>

<div class="dash-panel">
    <h2>ارسال گروهی</h2>
    <form method="post" action="sms.php" class="admin-form">
        <input type="hidden" name="action" value="send_bulk">

        <div class="sms-audience-grid">
            <label style="display:flex;align-items:center;gap:.5rem;">
                <input type="radio" name="audience" value="all" checked>
                همهٔ مشتریان (<?= $customersCount ?>)
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;">
                <input type="radio" name="audience" value="tier">
                فقط سطح خاص:
                <select name="tier_filter">
                    <option value="bronze">برنز (کمتر از ۵۰۰ امتیاز)</option>
                    <option value="silver">نقره‌ای (۵۰۰ تا ۱۹۹۹)</option>
                    <option value="gold">طلایی (۲۰۰۰ و بیشتر)</option>
                </select>
            </label>
        </div>

        <label>متن پیام (از {name} برای جای‌گذاری نام مشتری استفاده کنید)
            <textarea name="message" rows="3" required placeholder="{name} عزیز؛ ...">{name} عزیز؛ خبرهای خوب در راه است!</textarea>
        </label>
        <p class="sms-count-hint">به هر گیرنده فقط با شمارهٔ موبایل ثبت‌شده پیام می‌رود.</p>

        <button type="submit" class="btn btn-accent" onclick="return confirm('ارسال گروهی انجام شود؟');">ارسال گروهی</button>
    </form>
</div>

<div class="dash-panel">
    <h2>لاگ پیامک‌ها (۸۰ رکورد اخیر شامل پیامک‌های خودکار سامانه)</h2>
    <table class="data-table">
        <thead><tr><th>#</th><th>شماره</th><th>رویداد</th><th>متن</th><th>وضعیت</th><th>توضیح</th><th>زمان</th></tr></thead>
        <tbody>
        <?php if (!$log): ?>
            <tr><td colspan="7" class="muted">هنوز پیامکی ارسال نشده است.</td></tr>
        <?php endif; ?>
        <?php foreach ($log as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td dir="ltr"><?= e($row['phone']) ?></td>
                <td><?= e(sms_event_label($row['event'])) ?></td>
                <td title="<?= e($row['message']) ?>"><?= e(mb_substr($row['message'], 0, 40)) ?><?= mb_strlen($row['message']) > 40 ? '…' : '' ?></td>
                <td><span class="sms-status <?= $row['status'] === 'sent' ? 'sms-sent' : 'sms-failed' ?>"><?= $row['status'] === 'sent' ? 'ارسال شد' : 'ناموفق' ?></span></td>
                <td><?= e($row['provider_note'] ?: '-') ?></td>
                <td><?= e(persian_date($row['created_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
