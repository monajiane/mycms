<?php
/**
 * torob.php — فید محصولات برای ترب (Torob) — XML داینامیک
 * ------------------------------------------------------------------
 * فرمت استاندارد فید فروشندگان ترب. قیمت‌ها مستقل از تنظیم
 * «پنهان‌کردن قیمت از مهمان» و مستقیم از دیتابیس خوانده می‌شوند
 * (ربات ترب به‌عنوان فروشندهٔ تأییدشده به قیمت دسترسی دارد).
 *
 * فیلدها: code, name, price (تومان), category, image, url, stock, sku
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

// محدودساز ضد شلیک: حداکثر ۲۰ درخواست فید در دقیقه برای هر IP
if (!ip_rate_limit('torob', 20, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

// کش خروجی (TTL ۱۰ دقیقه) — ربات‌ها با هر درخواست به دیتابیس نمی‌زنند
$torobCache = __DIR__ . '/data/cache-torob.xml';
if (is_file($torobCache) && time() - (int)@filemtime($torobCache) < 600) {
    echo (string)@file_get_contents($torobCache);
    exit;
}
ob_start();

$base = rtrim(BASE_URL, '/');

// دسته‌ها: id => name
$cats = [];
try {
    foreach (db()->query("SELECT id, name FROM categories ORDER BY id")->fetchAll() as $c) {
        $cats[(int)$c['id']] = $c['name'];
    }
} catch (Throwable $e) {
    // بدون دسته
}

// محصولات عمومی و فعال
$rows = [];
try {
    $rows = db()->query(
        "SELECT id, name, slug, price, category_id, stock, image, description
         FROM products
         WHERE (owner_user_id IS NULL OR owner_user_id = 0) AND active = 1
         ORDER BY id ASC"
    )->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

function torob_escape($s)
{
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// کمینهٔ قیمت و مجموع موجودی وارینت‌ها (در صورت وجود)
function torob_variant_info($productId)
{
    static $cache = [];
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }
    $res = ['min_price' => null, 'stock' => 0];
    try {
        $st = db()->prepare(
            "SELECT MIN(price_delta) AS min_delta, SUM(stock) AS stock
             FROM product_variants
             WHERE product_id = ? AND active = 1"
        );
        $st->execute([(int)$productId]);
        $r = $st->fetch();
        if ($r && $r['min_delta'] !== null) {
            $res['min_price'] = (int)$r['min_delta']; // دلتای کمینه
            $res['stock'] = (int)$r['stock'];
        }
    } catch (Throwable $e) {
        // جدول وارینت موجود نیست
    }
    $cache[$productId] = $res;
    return $res;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<products>' . "\n";

foreach ($rows as $p) {
    $pid     = (int)$p['id'];
    $price   = (int)$p['price'];
    $stock   = (int)$p['stock'];
    $vi      = torob_variant_info($pid);

    // اگر وارینت دارد، کمینهٔ قیمت و مجموع موجودی را بده
    if ($vi['min_price'] !== null) {
        $price = $price + $vi['min_price'];
        $stock = $vi['stock'];
    }
    // قیمت نهایی با اعمال تخفیف همگانی و قیمت ویژهٔ محصول
    $price = price_final($price);

    $url = $base . '/product.php?id=' . $pid;
    $categoryName = isset($cats[(int)$p['category_id']]) ? $cats[(int)$p['category_id']] : '';
    $image = trim((string)$p['image']);
    $imageUrl = $image !== '' ? product_image_url($image) : '';

    echo "  <product>\n";
    echo "    <code>" . $pid . "</code>\n";
    echo "    <name>" . torob_escape($p['name']) . "</name>\n";
    echo "    <price>" . $price . "</price>\n";
    if ($categoryName !== '') {
        echo "    <category>" . torob_escape($categoryName) . "</category>\n";
    }
    if ($imageUrl !== '') {
        echo "    <image>" . torob_escape($imageUrl) . "</image>\n";
    }
    echo "    <url>" . torob_escape($url) . "</url>\n";
    echo "    <stock>" . $stock . "</stock>\n";
    if (!empty($p['slug'])) {
        echo "    <sku>" . torob_escape($p['slug']) . "</sku>\n";
    }
    echo "  </product>\n";
}

echo '</products>' . "\n";

// ذخیرهٔ خروجی در کش برای ۱۰ دقیقه
$xml = ob_get_clean();
echo $xml;
@file_put_contents($torobCache, $xml, LOCK_EX);
