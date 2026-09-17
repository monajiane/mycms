<?php
/**
 * admin/index.php — داشبورد
 */
$pageTitle = 'داشبورد';
require __DIR__ . '/_header.php';

// =============== پاک‌سازی داده‌های تست ===============
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'reset_test_data') {
    if (!function_exists('csrf_verify') || !csrf_verify()) {
        flash('error', 'نشست منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: index.php'); exit;
    }

    $cleared = [];
    foreach (['order_items', 'orders', 'custom_requests', 'messages', 'visits'] as $_t) {
        try {
            $cleared[$_t] = (int)db()->exec("DELETE FROM " . $_t);
        } catch (Throwable $e) { /* جدول وجود ندارد — نادیده */ }
    }
    // صفر شدن شمارندهٔ شناسه‌ها
    if (!defined('DB_DRIVER') || DB_DRIVER !== 'mysql') {
        try { db()->exec("DELETE FROM sqlite_sequence WHERE name IN ('order_items','orders','custom_requests','messages','visits')"); } catch (Throwable $e) {}
    } else {
        foreach (['order_items', 'orders', 'custom_requests', 'messages', 'visits'] as $_t) {
            try { db()->exec("ALTER TABLE " . $_t . " AUTO_INCREMENT = 1"); } catch (Throwable $e) {}
        }
    }
    // لاگ فایل متنی
    @unlink(__DIR__ . '/../data/logs/app.log');
    log_event('ADMIN: داده‌های تست (سفارش/چت/بازدید/لاگ) از داشبورد پاک شد — شروع عملیاتی', 'warn');
    flash('success', 'پاک‌سازی کامل شد: سفارش‌ها، چت‌ها، بازدیدها و لاگ‌ها حذف و شمارنده‌ها صفر شدند. محصولات، کاربران و تنظیمات دست‌نخورده ماندند.');
    header('Location: index.php'); exit;
}

// آمار کلی
$totalProducts = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders   = (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingRequests = (int)db()->query("SELECT COUNT(*) FROM custom_requests WHERE status = 'pending'")->fetchColumn();
$paidRevenue   = (int)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'paid'")->fetchColumn();

// آمار تحلیلی بازدید — یکتای روزانه بر اساس IP (رفرش صفحه شمرده نمی‌شود)
$visitsToday = 0;
$visitsTotal = 0;
$visitsWeekly = [];
$weekDays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

// مقداردهی اولیه آرایه هفتگی
for ($i = 0; $i < 7; $i++) {
    $visitsWeekly[$i] = 0;
}

try {
    $visitsToday = (int)db()->query("SELECT COUNT(*) FROM visits WHERE day = '" . date('Y-m-d') . "'")->fetchColumn();
    $visitsTotal = (int)db()->query("SELECT COUNT(*) FROM visits")->fetchColumn();
    $weekStart = date('Y-m-d', strtotime('-6 days'));
    $rows = db()->query("SELECT day, COUNT(*) AS c FROM visits WHERE day >= '" . $weekStart . "' GROUP BY day")->fetchAll();
    foreach ($rows as $r) {
        $d = strtotime((string)$r['day']);
        if ($d !== false) {
            $visitsWeekly[(int)date('w', $d)] += (int)$r['c'];
        }
    }
} catch (Throwable $t) {
    // جدول بازدید هنوز ساخته نشده → صفر
}

// آمار جغرافیا/منبع/دستگاه بازدیدکننده‌ها (از جدول visits)
$geoCountries = [];
$geoSources = [];
$geoDevices = [];
try {
    // تکمیل خودکار کشور بازدیدکننده‌های بدون کشور (کش در دیتابیس)
    visit_geo_enrich(100);
    $geoCountries = db()->query("SELECT country, COUNT(*) AS c FROM visits WHERE country IS NOT NULL GROUP BY country ORDER BY c DESC LIMIT 8")->fetchAll();
    $geoSources = db()->query("SELECT source, COUNT(*) AS c FROM visits GROUP BY source ORDER BY c DESC")->fetchAll();
    $geoDevices = db()->query("SELECT device, COUNT(*) AS c FROM visits GROUP BY device ORDER BY c DESC")->fetchAll();
} catch (Throwable $t) {
    // جدول هنوز ساخته نشده
}
$geoMaxCountry = 0;
foreach ($geoCountries as $g) { $geoMaxCountry = max($geoMaxCountry, (int)$g['c']); }
$geoMaxSource = 0;
foreach ($geoSources as $g) { $geoMaxSource = max($geoMaxSource, (int)$g['c']); }
$geoMaxDevice = 0;
foreach ($geoDevices as $g) { $geoMaxDevice = max($geoMaxDevice, (int)$g['c']); }

// موجودی رو به اتمام
$lowStock = db()->query("SELECT * FROM products WHERE stock <= 3 AND active = 1 ORDER BY stock ASC LIMIT 5")->fetchAll();
// سفارش‌های اخیر
$recentOrders = db()->query("SELECT * FROM orders ORDER BY id DESC LIMIT 6")->fetchAll();
// آمار تکمیلی
$totalUsers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$totalArticles = (int)db()->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$unreadMsgs = (int)db()->query("SELECT COUNT(*) FROM messages WHERE from_admin = 0 AND is_read = 0")->fetchColumn();
$pendingCoupons = (int)db()->query("SELECT COUNT(*) FROM coupons WHERE active = 1")->fetchColumn();
$todayOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
$todayRevenue = (int)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE date(created_at) = date('now') AND status = 'paid'")->fetchColumn();
?>

<h1 class="page-title">داشبورد</h1>

<?php if ($m = get_flash('success')): ?>
<div style="background:rgba(22,163,74,.10);border:1px solid rgba(22,163,74,.4);color:#166534;padding:.7rem 1rem;border-radius:10px;margin-bottom:1rem;"><?= e($m) ?></div>
<?php endif; ?>
<?php if ($m = get_flash('error')): ?>
<div style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.4);color:#991b1b;padding:.7rem 1rem;border-radius:10px;margin-bottom:1rem;"><?= e($m) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-num"><?= $totalProducts ?></span><span class="stat-label">محصول</span><a href="products.php" class="stat-link">مدیریت →</a></div>
    <div class="stat-card"><span class="stat-num"><?= $totalOrders ?></span><span class="stat-label">سفارش</span><a href="orders.php" class="stat-link">مشاهده →</a></div>
    <div class="stat-card"><span class="stat-num"><?= $pendingOrders ?></span><span class="stat-label">در انتظار پرداخت</span><a href="orders.php" class="stat-link">بررسی →</a></div>
    <div class="stat-card"><span class="stat-num"><?= $pendingRequests ?></span><span class="stat-label">درخواست سفارشی در انتظار</span><a href="requests.php" class="stat-link">بررسی →</a></div>
    <div class="stat-card stat-card--accent"><span class="stat-num"><?= e(fmt_price($paidRevenue)) ?></span><span class="stat-label">درآمد پرداخت‌شده</span></div>
    <div class="stat-card"><span class="stat-num"><?= $visitsToday ?></span><span class="stat-label">بازدیدکنندهٔ یکتای امروز</span></div>
    <div class="stat-card"><span class="stat-num"><?= $visitsTotal ?></span><span class="stat-label">مجموع بازدیدکنندهٔ یکتا</span></div>
</div>

<!-- لینک‌های سریع -->
<div class="quick-actions">
    <a href="product_edit.php" class="qa-btn">＋ محصول جدید</a>
    <a href="article_edit.php" class="qa-btn">＋ مقالهٔ جدید</a>
    <a href="coupons.php" class="qa-btn">＋ کوپن تخفیف</a>
    <a href="sms.php" class="qa-btn">✉ ارسال پیامک</a>
    <a href="invoice.php?id=<?= (int)($recentOrders[0]['id'] ?? 0) ?>" class="qa-btn qa-btn--muted">🖨 فاکتور آخرین سفارش</a>
    <a href="settings.php?tab=backup" class="qa-btn qa-btn--muted">⬇ بکاپ دیتابیس</a>
</div>

<!-- نمودار بازدید هفتگی -->
<div class="dash-cols">
    <section class="dash-panel">
        <h2>نمودار بازدید هفتگی</h2>
        <p class="muted" style="margin-bottom:.6rem;">بر اساس IP یکتا در هر روز — رفرش صفحه یا ورود دوباره در همان روز شمرده نمی‌شود.</p>
        <div class="chart-container">
            <canvas id="visitsChart" height="300"></canvas>
        </div>
    </section>

    <section class="dash-panel">
        <h2>وضعیت کلی</h2>
        <ul class="status-list">
            <li><span>سفارش امروز</span><strong><?= $todayOrders ?></strong></li>
            <li><span>درآمد امروز</span><strong><?= e(fmt_price($todayRevenue)) ?></strong></li>
            <li><span>کاربران ثبت‌نام‌شده</span><strong><?= $totalUsers ?></strong></li>
            <li><span>مقالات</span><strong><?= $totalArticles ?></strong></li>
            <li><span>کوپن‌های فعال</span><strong><?= $pendingCoupons ?></strong></li>
            <li><span>پیام‌های خوانده‌نشده</span><strong class="<?= $unreadMsgs > 0 ? 'text-danger' : '' ?>"><?= $unreadMsgs ?></strong></li>
        </ul>
        <a href="reports.php" class="btn btn-ghost btn-sm" style="margin-top:.8rem;">گزارش کامل فروش →</a>
    </section>
</div>

<!-- جغرافیا، منبع ورود و دستگاه‌ها -->
<div class="dash-cols dash-cols-3">
    <section class="dash-panel">
        <h2>کشورهای بازدیدکننده</h2>
        <?php if (!$geoCountries): ?>
            <p class="muted">هنوز بازدیدی ثبت نشده است.</p>
        <?php else: ?>
            <ul class="geo-list">
                <?php foreach ($geoCountries as $g): ?>
                    <li>
                        <span class="geo-flag"><?= country_flag($g['country']) ?></span>
                        <span class="geo-name"><?= e(country_name_fa($g['country'])) ?></span>
                        <span class="geo-bar"><span style="width: <?= $geoMaxCountry > 0 ? round(((int)$g['c'] / $geoMaxCountry) * 100) : 0 ?>%;"></span></span>
                        <span class="geo-count"><?= fa_digits((string)$g['c']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dash-panel">
        <h2>منبع ورود</h2>
        <?php if (!$geoSources): ?>
            <p class="muted">هنوز بازدیدی ثبت نشده است.</p>
        <?php else: ?>
            <ul class="geo-list">
                <?php foreach ($geoSources as $g): ?>
                    <li>
                        <span class="geo-flag src-dot src-<?= e($g['source']) ?>"></span>
                        <span class="geo-name"><?= e(visit_source_label($g['source'])) ?></span>
                        <span class="geo-bar"><span style="width: <?= $geoMaxSource > 0 ? round(((int)$g['c'] / $geoMaxSource) * 100) : 0 ?>%;"></span></span>
                        <span class="geo-count"><?= fa_digits((string)$g['c']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dash-panel">
        <h2>نوع دستگاه</h2>
        <?php if (!$geoDevices): ?>
            <p class="muted">هنوز بازدیدی ثبت نشده است.</p>
        <?php else: ?>
            <?php
            $devLabelsJs = [];
            $devValuesJs = [];
            $devColorsAll = ['mobile' => '#0f228c', 'tablet' => '#f59e0b', 'desktop' => '#16a34a'];
            $devColorsJs = [];
            foreach ($geoDevices as $g) {
                $devLabelsJs[] = visit_device_label($g['device']);
                $devValuesJs[] = (int)$g['c'];
                $devColorsJs[] = $devColorsAll[$g['device']] ?? '#94a3b8';
            }
            ?>
            <div class="chart-container chart-sm">
                <canvas id="devicesChart" height="240"></canvas>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="dash-cols">
    <section class="dash-panel">
        <h2>سفارش‌های اخیر</h2>
        <?php if (!$recentOrders): ?>
            <p class="muted">هنوز سفارشی ثبت نشده است.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>#</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td><?= (int)$o['id'] ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= e(fmt_price($o['total_amount'])) ?></td>
                        <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                        <td><a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-ghost btn-sm">جزئیات</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="dash-panel">
        <h2>موجودی رو به اتمام</h2>
        <?php if (!$lowStock): ?>
            <p class="muted">همهٔ محصولات موجودی کافی دارند.</p>
        <?php else: ?>
            <ul class="low-stock">
                <?php foreach ($lowStock as $p): ?>
                    <li><?= e($p['name']) ?> — <strong><?= (int)$p['stock'] ?></strong> عدد</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- پاکسازی داده ها -->
<section class="dash-panel" style="border:1px solid rgba(148,163,184,.5);margin-top:1.2rem;">
    <h2>🧹 پاکسازی داده ها</h2>
    <p class="muted" style="margin-bottom:.7rem;">حذف سفارش‌ها، سفارش‌های سفارشی، چت‌ها، آمار بازدید و لاگ‌های تست + صفر شدن شمارندهٔ شناسه‌ها. محصولات، کاربران و تنظیمات دست‌نخورده می‌مانند. <b>بازگشت‌ناپذیر است.</b></p>
    <form method="post" onsubmit="return confirm('همهٔ سفارش‌ها و چت‌ها برای همیشه حذف می‌شوند. مطمئنید؟');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_test_data">
        <button type="submit" class="btn" style="background:#dc2626;color:#fff;border:none;padding:.55rem 1.1rem;border-radius:8px;cursor:pointer;font-family:inherit;">🧹 پاکسازی داده ها</button>
    </form>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var faNum = function (n) { return Number(n).toLocaleString('fa-IR'); };
    var tooltipStyle = {
        rtl: true,
        textDirection: 'rtl',
        backgroundColor: '#06163a',
        titleFont: { family: 'Vazirmatn, sans-serif', weight: '700' },
        bodyFont: { family: 'Vazirmatn, sans-serif' },
        padding: 10,
        cornerRadius: 8,
        displayColors: false
    };

    // ---- نمودار بازدید هفتگی: خطی با پرکنندهٔ گرادیان ----
    var ctx = document.getElementById('visitsChart');
    if (ctx) {
        var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        g.addColorStop(0, 'rgba(15, 34, 140, .30)');
        g.addColorStop(1, 'rgba(15, 34, 140, 0)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($weekDays) ?>,
                datasets: [{
                    label: 'بازدیدکنندهٔ یکتا',
                    data: <?= json_encode(array_values($visitsWeekly)) ?>,
                    borderColor: '#0f228c',
                    backgroundColor: g,
                    fill: true,
                    tension: .4,
                    borderWidth: 2.5,
                    pointRadius: 3.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0f228c',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(100,116,139,.14)', drawTicks: false },
                        border: { display: false },
                        ticks: { precision: 0, padding: 8, callback: function (v) { return faNum(v); } }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: { label: function (c) { return 'بازدیدکننده: ' + faNum(c.parsed.y); } }
                    })
                }
            }
        });
    }

    // ---- نمودار دونات دستگاه‌ها ----
    var dctx = document.getElementById('devicesChart');
    if (dctx) {
        var devLabels = <?= json_encode($devLabelsJs ?? []) ?>;
        var devValues = <?= json_encode($devValuesJs ?? []) ?>;
        var devColors = <?= json_encode($devColorsJs ?? []) ?>;
        var devTotal = devValues.reduce(function (a, b) { return a + b; }, 0);
        var centerText = {
            id: 'centerText',
            afterDraw: function (chart) {
                var area = chart.chartArea;
                if (!area) return;
                var c = chart.ctx;
                var x = (area.left + area.right) / 2;
                var y = (area.top + area.bottom) / 2;
                c.save();
                c.textAlign = 'center';
                c.font = '700 22px Vazirmatn, sans-serif';
                c.fillStyle = '#06163a';
                c.fillText(faNum(devTotal), x, y);
                c.font = '500 11px Vazirmatn, sans-serif';
                c.fillStyle = '#5a6472';
                c.fillText('بازدیدکننده', x, y + 17);
                c.restore();
            }
        };
        new Chart(dctx, {
            type: 'doughnut',
            data: {
                labels: devLabels,
                datasets: [{
                    data: devValues,
                    backgroundColor: devColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true,
                        textDirection: 'rtl',
                        labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, font: { family: 'Vazirmatn, sans-serif' } }
                    },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: function (c) {
                                var pct = devTotal > 0 ? Math.round((c.parsed / devTotal) * 100) : 0;
                                return c.label + ': ' + faNum(c.parsed) + ' (' + faNum(pct) + '٪)';
                            }
                        }
                    })
                }
            },
            plugins: [centerText]
        });
    }
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
