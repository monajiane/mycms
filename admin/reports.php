<?php
/**
 * admin/reports.php — گزارش‌های پیشرفته فروش
 * ------------------------------------------------------------------
 * - فیلتر بازهٔ زمانی (شمسی یا میلادی) روی created_at میلادی اعمال می‌شود
 * - کارت‌های خلاصه: درآمد، تعداد سفارش، میانگین ارزش سفارش، مشتری یکتا
 * - گزارش فروش بر اساس دسته‌بندی
 * - نمودار درآمد ۱۲ ماه اخیر (Chart.js)
 * - جدول ۱۰ محصول پرفروش
 */
$pageTitle = 'گزارش‌های فروش';
require __DIR__ . '/_header.php';

/* ============================= توابع کمکی ============================= */

/** تبدیل ارقام فارسی/عربی به لاتین */
function reports_normalize_digits($s)
{
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

/** تبدیل تاریخ جلالی → میلادی (الگوریتم استاندارد معکوس gregorian_to_jalali) */
function reports_jalali_to_gregorian($jy, $jm, $jd)
{
    $jy = (int)$jy; $jm = (int)$jm; $jd = (int)$jd;
    $jy += 1595;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33)) * 8 + ((int)((($jy % 33) + 3) / 4)) + $jd
          + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int)(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) $gd -= $sal[$gm];
    return [$gy, $gm, $gd];
}

/**
 * تحلیل ورودی تاریخ کاربر → تاریخ میلادی (Y-m-d) یا null.
 * - خالی → null (بدون محدودیت)
 * - YYYY/MM/DD یا YYYY-MM-DD با سال ۱۳۰۰ تا ۱۴۹۹ → شمسی در نظر گرفته می‌شود
 * - سایر حالت‌ها → میلادی (خروجی input[type=date] یا strtotime)
 */
function reports_parse_date($input)
{
    $input = trim(reports_normalize_digits((string)$input));
    if ($input === '') {
        return null;
    }
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $input, $m)) {
        $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
        if ($y >= 1300 && $y <= 1499) {
            list($gy, $gm, $gd) = reports_jalali_to_gregorian($y, $mo, $d);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        }
        if (checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        return null;
    }
    $ts = strtotime($input);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** برچسب فارسی ماه از کلید YYYY-MM میلادی */
function reports_month_label($ym)
{
    $parts = explode('-', $ym);
    if (count($parts) < 2) return $ym;
    list($jy, $jm) = gregorian_to_jalali((int)$parts[0], (int)$parts[1], 1);
    $names = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
              7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
    return ($names[$jm] ?? $jm) . ' ' . $jy;
}

/* ============================= ورودی‌ها و فیلتر ============================= */

$fromInput = isset($_GET['from']) ? (string)$_GET['from'] : '';
$toInput   = isset($_GET['to'])   ? (string)$_GET['to']   : '';
$topBy     = (isset($_GET['top_by']) && $_GET['top_by'] === 'qty') ? 'qty' : 'amount';

$fromDate = reports_parse_date($fromInput);
$toDate   = reports_parse_date($toInput);

// ساخت شرط‌های WHERE روی created_at میلادی
$where   = [];
$params  = [];
if ($fromDate) { $where[] = "o.created_at >= ?"; $params[] = $fromDate . ' 00:00:00'; }
if ($toDate)   { $where[] = "o.created_at <= ?"; $params[] = $toDate . ' 23:59:59'; }
$whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

/* ============================= کارت‌های خلاصه ============================= */

$st = db()->prepare(
    "SELECT
        COALESCE(SUM(o.total_amount), 0) AS revenue,
        COUNT(*) AS order_count,
        COUNT(DISTINCT o.user_id) AS registered,
        COUNT(DISTINCT CASE WHEN o.user_id IS NULL THEN o.customer_phone END) AS guests
     FROM orders o
     WHERE o.status = 'paid'" . $whereSql
);
$st->execute($params);
$sum = $st->fetch();

$revenue        = (int)$sum['revenue'];
$orderCount     = (int)$sum['order_count'];
$uniqueCustomers = (int)$sum['registered'] + (int)$sum['guests'];
$avgOrder       = $orderCount > 0 ? (int)round($revenue / $orderCount) : 0;

/* ============================= گزارش بر اساس دسته‌بندی ============================= */

$st = db()->prepare(
    "SELECT
        COALESCE(c.name, 'بدون دسته') AS category_name,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.price * oi.quantity) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE o.status = 'paid'" . $whereSql . "
     GROUP BY c.id, c.name
     ORDER BY revenue DESC"
);
$st->execute($params);
$byCategory = $st->fetchAll();

/* ============================= نمودار درآمد ۱۲ ماه اخیر ============================= */

$monthKeys  = [];
$monthLabels = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-{$i} months"));
    $monthKeys[]  = $ym;
    $monthLabels[] = reports_month_label($ym);
}
$chartFrom = date('Y-m-01', strtotime('-11 months'));

$ymExpr = (DB_DRIVER === 'mysql') ? "DATE_FORMAT(o.created_at, '%Y-%m')" : "strftime('%Y-%m', o.created_at)";
$st = db()->prepare(
    "SELECT $ymExpr AS ym, COALESCE(SUM(o.total_amount), 0) AS revenue
     FROM orders o
     WHERE o.status = 'paid' AND o.created_at >= ?
     GROUP BY ym
     ORDER BY ym"
);
$st->execute([$chartFrom]);
$monthMap = [];
foreach ($st->fetchAll() as $r) {
    $monthMap[$r['ym']] = (int)$r['revenue'];
}
$chartData = [];
foreach ($monthKeys as $k) {
    $chartData[] = isset($monthMap[$k]) ? $monthMap[$k] : 0;
}

/* ============================= ۱۰ محصول پرفروش ============================= */

$orderBy = ($topBy === 'qty') ? 'qty_sold DESC, revenue DESC' : 'revenue DESC, qty_sold DESC';
$st = db()->prepare(
    "SELECT
        oi.product_name,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.price * oi.quantity) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status = 'paid'" . $whereSql . "
     GROUP BY oi.product_id, oi.product_name
     ORDER BY $orderBy
     LIMIT 10"
);
$st->execute($params);
$topProducts = $st->fetchAll();
?>

<style>
    .chart-wrap { position: relative; height: 320px; }
    .filter-bar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
    .filter-bar .field { display: flex; flex-direction: column; gap: 0.35rem; font-size: .85rem; color: var(--ink-soft); }
    .filter-bar input[type="text"],
    .filter-bar select {
        min-width: 200px; padding: 0.55rem 0.8rem; border: 1px solid var(--line);
        border-radius: var(--radius); font: inherit; background: #fff; color: var(--ink);
    }
    .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: var(--accent); }
    .filter-note { font-size: .85rem; color: var(--ink-soft); margin-top: 0.5rem; }
    .report-rank { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px;
        border-radius: 50%; background: #eef2f9; color: var(--navy); font-weight: 700; font-size: .8rem; }
</style>

<h1 class="page-title">گزارش‌های فروش</h1>

<form method="get" action="reports.php" class="dash-panel">
    <div class="filter-bar">
        <div class="field">
            <label for="f_from">از تاریخ</label>
            <input type="text" id="f_from" name="from" value="<?= e($fromInput) ?>" placeholder="مثال: 1403/01/01 یا 2024-03-20">
        </div>
        <div class="field">
            <label for="f_to">تا تاریخ</label>
            <input type="text" id="f_to" name="to" value="<?= e($toInput) ?>" placeholder="مثال: 1403/06/01 یا 2024-08-22">
        </div>
        <div class="field">
            <label for="f_top">رتبه‌بندی پرفروش بر اساس</label>
            <select id="f_top" name="top_by">
                <option value="amount" <?= $topBy === 'amount' ? 'selected' : '' ?>>مبلغ فروش</option>
                <option value="qty" <?= $topBy === 'qty' ? 'selected' : '' ?>>تعداد فروش</option>
            </select>
        </div>
        <button type="submit" class="btn btn-accent">اعمال فیلتر</button>
        <a href="reports.php" class="btn btn-ghost">پاک کردن</a>
    </div>
    <div class="filter-note">
        هر دو قالب شمسی (مثل ۱۴۰۳/۰۶/۰۱) و میلادی (مثل 2024-08-22) پذیرفته می‌شود.
        <?php if ($fromDate || $toDate): ?>
            بازهٔ فعال:
            از <?= e($fromDate ? persian_date($fromDate) : 'ابتدا') ?>
            تا <?= e($toDate ? persian_date($toDate) : 'امروز') ?>
        <?php else: ?>
            هم‌اکنون بدون فیلتر (همهٔ زمان‌ها) محاسبه می‌شود.
        <?php endif; ?>
    </div>
</form>

<!-- کارت‌های خلاصه -->
<div class="stat-grid">
    <div class="stat-card"><span class="stat-num"><?= e(fmt_price($revenue)) ?></span><span class="stat-label">درآمد کل (پرداخت‌شده)</span></div>
    <div class="stat-card"><span class="stat-num"><?= fa_digits(number_format($orderCount)) ?></span><span class="stat-label">سفارش پرداخت‌شده</span></div>
    <div class="stat-card"><span class="stat-num"><?= e(fmt_price($avgOrder)) ?></span><span class="stat-label">میانگین ارزش سفارش</span></div>
    <div class="stat-card"><span class="stat-num"><?= fa_digits(number_format($uniqueCustomers)) ?></span><span class="stat-label">مشتری یکتا</span></div>
</div>

<!-- نمودار درآمد ماهانه -->
<section class="dash-panel">
    <h2>درآمد ماهانه (۱۲ ماه اخیر)</h2>
    <div class="chart-wrap">
        <canvas id="revenueChart"></canvas>
    </div>
</section>

<!-- گزارش بر اساس دسته‌بندی -->
<section class="dash-panel">
    <h2>فروش بر اساس دسته‌بندی</h2>
    <?php if (!$byCategory): ?>
        <p class="muted">در بازهٔ انتخاب‌شده فروشی ثبت نشده است.</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th>دسته‌بندی</th><th>تعداد فروش</th><th>مبلغ فروش</th></tr></thead>
            <tbody>
            <?php foreach ($byCategory as $c): ?>
                <tr>
                    <td><?= e($c['category_name']) ?></td>
                    <td><?= fa_digits(number_format((int)$c['qty_sold'])) ?></td>
                    <td><?= e(fmt_price($c['revenue'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- جدول محصولات پرفروش -->
<section class="dash-panel">
    <h2>۱۰ محصول پرفروش</h2>
    <?php if (!$topProducts): ?>
        <p class="muted">در بازهٔ انتخاب‌شده فروشی ثبت نشده است.</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>محصول</th><th>تعداد فروش</th><th>مبلغ فروش</th></tr></thead>
            <tbody>
            <?php $rank = 1; foreach ($topProducts as $p): ?>
                <tr>
                    <td><span class="report-rank"><?= $rank++ ?></span></td>
                    <td><?= e($p['product_name']) ?></td>
                    <td><?= fa_digits(number_format((int)$p['qty_sold'])) ?></td>
                    <td><?= e(fmt_price($p['revenue'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<script>
(function () {
    const el = document.getElementById('revenueChart');
    if (!el || typeof Chart === 'undefined') return;

    const fmtToman = (v) => Number(v).toLocaleString('fa-IR') + ' تومان';

    new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'درآمد (تومان)',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    rtl: true,
                    callbacks: {
                        label: (ctx) => fmtToman(ctx.parsed.y)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => fmtToman(v) }
                }
            }
        }
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
