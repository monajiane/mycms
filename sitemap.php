<?php
/**
 * sitemap.php — نقشهٔ سایت پویا (XML) برای موتورهای جستجو.
 * فقط محصولات عمومی (غیرخصوصی) و صفحات ثابت را فهرست می‌کند.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

// محدودساز ضد شلیک + کش خروجی (TTL ۳۰ دقیقه)
if (!ip_rate_limit('sitemap', 20, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}
$smCache = __DIR__ . '/data/cache-sitemap.xml';
if (is_file($smCache) && time() - (int)@filemtime($smCache) < 1800) {
    echo (string)@file_get_contents($smCache);
    exit;
}
ob_start();

$base = rtrim(BASE_URL, '/');
$urls = [];

// صفحات ثابت
$staticPages = ['index.php', 'shop.php', 'knowledge.php', 'about.php'];
foreach ($staticPages as $p) {
    $file = __DIR__ . '/' . $p;
    $urls[] = [
        'loc'        => $base . '/' . $p,
        'lastmod'    => is_file($file) ? date('c', filemtime($file)) : null,
        'changefreq' => 'weekly',
        'priority'   => '0.8',
    ];
}

// محصولات عمومی
try {
    $st = db()->query(
        "SELECT id, created_at FROM products
         WHERE (owner_user_id IS NULL OR owner_user_id = 0) AND active = 1
         ORDER BY id DESC"
    );
    foreach ($st->fetchAll() as $row) {
        $urls[] = [
            'loc'        => $base . '/product.php?id=' . (int)$row['id'],
            'lastmod'    => !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : null,
            'changefreq' => 'weekly',
            'priority'   => '0.9',
        ];
    }
} catch (Throwable $e) {
    // بدون محصول؛ ادامه می‌دهیم
}

// مقالات دانشنامه
try {
    $st = db()->query("SELECT id, created_at FROM articles WHERE published = 1 ORDER BY id DESC");
    foreach ($st->fetchAll() as $row) {
        $urls[] = [
            'loc'        => $base . '/article.php?id=' . (int)$row['id'],
            'lastmod'    => !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : null,
            'changefreq' => 'monthly',
            'priority'   => '0.7',
        ];
    }
} catch (Throwable $e) {
    // بدون مقاله؛ ادامه می‌دهیم
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . e($u['loc']) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . e($u['lastmod']) . '</lastmod>' . "\n";
    }
    if (!empty($u['changefreq'])) {
        echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    }
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";

$xml = ob_get_clean();
echo $xml;
@file_put_contents($smCache, $xml, LOCK_EX);
