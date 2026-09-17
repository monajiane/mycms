<?php
/**
 * admin/marketing.php — ابزارهای بازاریابی
 * ۱) خبرنامه (Newsletter): لیست + ارسال دسته‌جمعی
 * ۲) Affiliate: کد معرف + گزارش
 * ۳) A/B Test: آزمایش دو نسخه از صفحه
 */
$pageTitle = 'بازاریابی';
require __DIR__ . '/_header.php';

$tab = $_GET['tab'] ?? 'newsletter';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $a = $_POST['action'] ?? '';
    if ($a === 'send_newsletter') {
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        if ($subject === '' || $body === '') {
            flash('error', 'موضوع و متن لازم است.');
        } else {
            $rows = db()->query("SELECT email FROM newsletter WHERE active = 1")->fetchAll();
            $count = 0;
            foreach ($rows as $r) {
                if (email_send($r['email'], $subject, $body, 'newsletter')) $count++;
            }
            flash('success', "خبرنامه به $count نفر ارسال شد.");
        }
    } elseif ($a === 'add_subscriber') {
        $email = trim($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                db()->prepare("INSERT INTO newsletter (email, active, created_at) VALUES (?, 1, NOW())")->execute([$email]);
                flash('success', 'ایمیل اضافه شد.');
            } catch (Throwable $t) { flash('error', 'این ایمیل قبلاً ثبت شده.'); }
        } else { flash('error', 'ایمیل نامعتبر.'); }
    } elseif ($a === 'del_subscriber') {
        db()->prepare("DELETE FROM newsletter WHERE id = ?")->execute([(int)$_POST['id']]);
        flash('success', 'حذف شد.');
    } elseif ($a === 'add_abtest') {
        $name = trim($_POST['name'] ?? '');
        $aUrl = trim($_POST['variant_a'] ?? '');
        $bUrl = trim($_POST['variant_b'] ?? '');
        if ($name && $aUrl && $bUrl) {
            db()->prepare("INSERT INTO ab_tests (name, variant_a, variant_b, started_at) VALUES (?,?,?,NOW())")
                ->execute([$name, $aUrl, $bUrl]);
            flash('success', 'آزمایش ساخته شد.');
        }
    } elseif ($a === 'end_abtest') {
        db()->prepare("UPDATE ab_tests SET ended_at = NOW() WHERE id = ?")->execute([(int)$_POST['id']]);
        flash('success', 'پایان یافت.');
    }
    header('Location: marketing.php?tab=' . $tab);
    exit;
}

$subscribers = $tab === 'newsletter' ? db()->query("SELECT * FROM newsletter ORDER BY id DESC LIMIT 500")->fetchAll() : [];
$abTests = $tab === 'abtest' ? db()->query("SELECT * FROM ab_tests ORDER BY id DESC")->fetchAll() : [];
$referrals = $tab === 'affiliate' ? db()->query("SELECT u.id, u.username, u.full_name, COUNT(r.id) AS cnt, COALESCE(SUM(r.commission),0) AS total FROM users u LEFT JOIN referrals r ON r.referrer_id = u.id GROUP BY u.id HAVING cnt > 0 ORDER BY cnt DESC LIMIT 50")->fetchAll() : [];

// — آمار کلی برای کارت‌های بالای صفحه —
try { $newsTotal = (int)db()->query("SELECT COUNT(*) FROM newsletter WHERE active = 1")->fetchColumn(); }
catch (Throwable $t) { $newsTotal = 0; }
try { $newsAll = (int)db()->query("SELECT COUNT(*) FROM newsletter")->fetchColumn(); }
catch (Throwable $t) { $newsAll = 0; }
try { $abActive = (int)db()->query("SELECT COUNT(*) FROM ab_tests WHERE ended_at IS NULL")->fetchColumn(); }
catch (Throwable $t) { $abActive = 0; }
try { $refStats = db()->query("SELECT COUNT(*) AS c, COALESCE(SUM(commission),0) AS s FROM referrals")->fetch();
    $refCount = (int)($refStats['c'] ?? 0); $refSum = (float)($refStats['s'] ?? 0); }
catch (Throwable $t) { $refCount = 0; $refSum = 0; }
?>

<style>
/* —— بازاریابی: استایل اختصاصی، سوار بر توکن‌های پنل —— */
.mkt-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.4rem; }
.mkt-head h1 { font-size:1.35rem; font-weight:700; color:var(--ink); margin:0 0 .25rem; }
.mkt-head p { margin:0; color:var(--ink-soft); font-size:.88rem; font-weight:300; }
.mkt-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.mkt-stat { position:relative; background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:1.05rem 1.15rem; display:flex; flex-direction:column; gap:.3rem; overflow:hidden; }
.mkt-stat::before { content:""; position:absolute; inset-inline-start:0; top:0; bottom:0; width:3px; background:var(--accent); opacity:.85; }
.mkt-stat.is-navy::before { background:var(--navy); }
.mkt-stat.is-green::before { background:var(--success); }
.mkt-stat.is-amber::before { background:#d99a1a; }
.mkt-stat .k { font-size:.78rem; color:var(--ink-soft); font-weight:400; }
.mkt-stat .v { font-size:1.5rem; font-weight:700; color:var(--ink); line-height:1.2; }
.mkt-stat .u { font-size:.72rem; color:var(--ink-soft); font-weight:300; }

.mkt-tabs { display:flex; gap:.4rem; flex-wrap:wrap; background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:.4rem; margin-bottom:1.5rem; }
.mkt-tabs a { display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1.05rem; border-radius:9px; font-size:.88rem; font-weight:500; color:var(--ink-soft); text-decoration:none; transition:all .15s; }
.mkt-tabs a:hover { background:var(--bg); color:var(--accent); }
.mkt-tabs a.active { background:var(--accent); color:#fff; }
.mkt-tabs a .n { background:rgba(0,0,0,.08); border-radius:999px; padding:.05rem .5rem; font-size:.72rem; }
.mkt-tabs a.active .n { background:rgba(255,255,255,.22); }

.mkt-card { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:1.3rem 1.4rem; margin-bottom:1.3rem; }
.mkt-card > h2 { font-size:1.05rem; font-weight:700; color:var(--ink); margin:0 0 1rem; display:flex; align-items:center; gap:.45rem; }
.mkt-card > h3 { font-size:.95rem; font-weight:600; color:var(--ink); margin:0 0 .8rem; }
.mkt-card-note { color:var(--ink-soft); font-size:.84rem; font-weight:300; margin:-.4rem 0 1rem; line-height:1.8; }
.mkt-card-note code { background:var(--bg); border:1px solid var(--line); border-radius:6px; padding:.05rem .4rem; font-size:.82rem; direction:ltr; display:inline-block; }

.mkt-field { display:flex; flex-direction:column; gap:.35rem; margin-bottom:.9rem; }
.mkt-field > span { font-size:.83rem; font-weight:500; color:var(--ink); }
.mkt-field input[type=text], .mkt-field input[type=email], .mkt-field textarea {
    width:100%; box-sizing:border-box; font:inherit; font-size:.9rem; color:var(--ink);
    background:var(--bg); border:1px solid var(--line); border-radius:9px; padding:.6rem .8rem; transition:border-color .15s, box-shadow .15s;
}
.mkt-field input:focus, .mkt-field textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(15,34,140,.09); }
.mkt-field textarea { resize:vertical; line-height:1.9; }
.mkt-two { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.mkt-inline { display:flex; gap:.6rem; align-items:flex-end; flex-wrap:wrap; }
.mkt-inline .mkt-field { flex:1; min-width:220px; margin-bottom:0; }

.mkt-empty { text-align:center; color:var(--ink-soft); font-size:.88rem; font-weight:300; padding:1.6rem 1rem; background:var(--bg); border:1px dashed var(--line); border-radius:var(--radius); }

.mkt-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.18rem .65rem; border-radius:999px; font-size:.76rem; font-weight:500; }
.mkt-badge.on { background:#e7f1ea; color:var(--success); }
.mkt-badge.off { background:#eee; color:#777; }
.mkt-badge b { width:6px; height:6px; border-radius:50%; background:currentColor; display:inline-block; }

.mkt-table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:var(--radius); }
.mkt-table { width:100%; border-collapse:collapse; background:var(--surface); }
.mkt-table th, .mkt-table td { padding:.75rem .9rem; text-align:right; border-bottom:1px solid var(--line); font-size:.9rem; white-space:nowrap; }
.mkt-table th { font-weight:600; color:var(--ink-soft); background:var(--bg); font-size:.82rem; }
.mkt-table tbody tr:last-child td { border-bottom:none; }
.mkt-table tbody tr:hover td { background:#fafbfd; }
.mkt-table .em { direction:ltr; text-align:left; font-size:.85rem; }
.mkt-num { font-weight:600; color:var(--navy); }
.mkt-del { background:transparent; border:1px solid var(--line); color:var(--danger); width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:1rem; line-height:1; transition:all .15s; }
.mkt-del:hover { background:var(--danger); color:#fff; border-color:var(--danger); }

@media (max-width:640px){
    .mkt-two { grid-template-columns:1fr; }
    .mkt-tabs a { flex:1 1 auto; justify-content:center; }
}
</style>

<div class="marketing">

    <div class="mkt-head">
        <div>
            <h1>📣 بازاریابی</h1>
            <p>خبرنامه، باشگاه معرفی و آزمایش A/B در یک‌جا</p>
        </div>
    </div>

    <div class="mkt-grid">
        <div class="mkt-stat">
            <span class="k">مشترکین فعال خبرنامه</span>
            <span class="v"><?= fa_digits(number_format($newsTotal)) ?></span>
            <span class="u">از مجموع <?= fa_digits(number_format($newsAll)) ?> ایمیل ثبت‌شده</span>
        </div>
        <div class="mkt-stat is-green">
            <span class="k">معرفی‌های موفق</span>
            <span class="v"><?= fa_digits(number_format($refCount)) ?></span>
            <span class="u">مجموع پورسانت <?= fa_digits(number_format($refSum)) ?> تومان</span>
        </div>
        <div class="mkt-stat is-navy">
            <span class="k">آزمایش‌های فعال</span>
            <span class="v"><?= fa_digits(number_format($abActive)) ?></span>
            <span class="u">A/B تست در حال اجرا</span>
        </div>
    </div>

    <nav class="mkt-tabs">
        <a href="?tab=newsletter" class="<?= $tab==='newsletter'?'active':'' ?>">📧 خبرنامه <span class="n"><?= fa_digits(number_format($newsTotal)) ?></span></a>
        <a href="?tab=affiliate" class="<?= $tab==='affiliate'?'active':'' ?>">👥 معرفی (Affiliate) <span class="n"><?= fa_digits(number_format($refCount)) ?></span></a>
        <a href="?tab=abtest" class="<?= $tab==='abtest'?'active':'' ?>">🧪 A/B تست <span class="n"><?= fa_digits(number_format($abActive)) ?></span></a>
    </nav>

    <?php if ($tab === 'newsletter'): ?>

        <div class="mkt-card">
            <h2>✉️ ارسال خبرنامه</h2>
            <p class="mkt-card-note">این پیام برای همهٔ مشترکین فعال ارسال می‌شود. متن را کوتاه و روشن نگه دارید.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_newsletter">
                <label class="mkt-field"><span>موضوع ایمیل</span><input type="text" name="subject" placeholder="مثلاً: جشنواره پایان فصل" required></label>
                <label class="mkt-field"><span>متن ایمیل</span><textarea name="body" rows="9" placeholder="متن خبرنامه…" required></textarea></label>
                <button type="submit" class="btn btn-accent">ارسال به <?= fa_digits(number_format($newsTotal)) ?> مشترک فعال</button>
            </form>
        </div>

        <div class="mkt-card">
            <h2>➕ افزودن مشترک</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_subscriber">
                <div class="mkt-inline">
                    <label class="mkt-field"><span>ایمیل مشترک</span><input type="email" name="email" placeholder="email@example.com" dir="ltr" required></label>
                    <button type="submit" class="btn">افزودن</button>
                </div>
            </form>
        </div>

        <div class="mkt-card">
            <h2>📋 لیست مشترکین <span class="mkt-badge off"><?= fa_digits(number_format(count($subscribers))) ?> مورد</span></h2>
            <?php if (!$subscribers): ?>
                <div class="mkt-empty">هنوز مشترکی ثبت نشده است.</div>
            <?php else: ?>
            <div class="mkt-table-wrap">
                <table class="mkt-table">
                    <thead><tr><th>#</th><th>ایمیل</th><th>تاریخ عضویت</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($subscribers as $s): ?>
                        <tr>
                            <td class="mkt-num"><?= fa_digits((int)$s['id']) ?></td>
                            <td class="em"><?= e($s['email']) ?></td>
                            <td class="muted"><?= e($s['created_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="del_subscriber">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="mkt-del" title="حذف" onclick="return confirm('حذف شود؟')">×</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'affiliate'): ?>

        <div class="mkt-card">
            <h2>👥 گزارش معرفی</h2>
            <p class="mkt-card-note">هر کاربر می‌تواند لینک <code>?ref=USERNAME</code> را منتشر کند. درصد پاداش در <a href="settings.php?tab=loyalty">تنظیمات باشگاه مشتریان</a> تعیین می‌شود.</p>
            <?php if (!$referrals): ?>
                <div class="mkt-empty">هنوز معرفی‌ای ثبت نشده است.</div>
            <?php else: ?>
            <div class="mkt-table-wrap">
                <table class="mkt-table">
                    <thead><tr><th>کاربر</th><th>تعداد معرفی</th><th>مجموع پورسانت</th></tr></thead>
                    <tbody>
                    <?php foreach ($referrals as $r): ?>
                        <tr>
                            <td><?= e($r['full_name'] ?: $r['username']) ?></td>
                            <td class="mkt-num"><?= fa_digits((int)$r['cnt']) ?></td>
                            <td class="mkt-num"><?= fa_digits(number_format((float)$r['total'])) ?> <span class="muted">تومان</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'abtest'): ?>

        <div class="mkt-card">
            <h2>🧪 آزمایش جدید</h2>
            <p class="mkt-card-note">دو نسخه از یک صفحه را وارد کنید و نتیجه را مقایسه کنید.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_abtest">
                <label class="mkt-field"><span>نام آزمایش</span><input type="text" name="name" placeholder="مثلاً: تست دکمهٔ خرید" required></label>
                <div class="mkt-two">
                    <label class="mkt-field"><span>نسخهٔ A</span><input type="text" name="variant_a" placeholder="https://site.com/product?id=1" dir="ltr" required></label>
                    <label class="mkt-field"><span>نسخهٔ B</span><input type="text" name="variant_b" placeholder="https://site.com/p/landing1" dir="ltr" required></label>
                </div>
                <button type="submit" class="btn btn-accent">شروع آزمایش</button>
            </form>
        </div>

        <div class="mkt-card">
            <h2>📊 آزمایش‌ها <span class="mkt-badge off"><?= fa_digits(number_format(count($abTests))) ?> مورد</span></h2>
            <?php if (!$abTests): ?>
                <div class="mkt-empty">هنوز آزمایشی ساخته نشده است.</div>
            <?php else: ?>
            <div class="mkt-table-wrap">
                <table class="mkt-table">
                    <thead><tr><th>نام</th><th>وضعیت</th><th>شروع</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($abTests as $t): ?>
                        <tr>
                            <td><?= e($t['name']) ?></td>
                            <td>
                                <?php if ($t['ended_at']): ?>
                                    <span class="mkt-badge off"><b></b>پایان‌یافته</span>
                                <?php else: ?>
                                    <span class="mkt-badge on"><b></b>فعال</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted"><?= e($t['started_at']) ?></td>
                            <td>
                                <?php if (!$t['ended_at']): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="end_abtest">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">پایان</button>
                                </form>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>