<?php
/**
 * shop.php — فروشگاه (فهرست کامل محصولات + جستجو + فیلتر دسته‌بندی)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'فروشگاه | ابزارسازی شرق';
$pageDescription = 'فروشگاه اینترنتی ابزارسازی شرق؛ مشاهده، مقایسه و خرید ابزار دقیق صنعتی، ابزار برشی، ابزار اندازه‌گیری و تجهیزات ایمنی با ضمانت اصالت.';

// فیلتر دسته‌بندی و جستجو
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = ['p.active = 1'];
$params = [];
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$where[] = product_visibility_clause('p');
$whereSql = 'WHERE ' . implode(' AND ', $where);

// صفحه‌بندی
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$countSt = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countSt->execute($params);
$totalProducts = (int)$countSt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$st = db()->prepare("SELECT p.*, c.name AS category_name FROM products p
                     LEFT JOIN categories c ON c.id = p.category_id
                     $whereSql ORDER BY p.featured DESC, p.id DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$products = $st->fetchAll();

/** ساخت URL صفحه‌بندی با حفظ فیلترها */
function shop_page_url($pageNum)
{
    $qs = $_GET;
    $qs['page'] = $pageNum;
    return BASE_URL . '/shop.php?' . http_build_query($qs);
}

// لینک‌های صفحه‌بندی برای موتورهای جستجو (rel prev/next)
$pageHeadExtra = '';
if ($totalPages > 1) {
    if ($page > 1) {
        $pageHeadExtra .= '<link rel="prev" href="' . e(shop_page_url($page - 1)) . '">' . "\n";
    }
    if ($page < $totalPages) {
        $pageHeadExtra .= '<link rel="next" href="' . e(shop_page_url($page + 1)) . '">' . "\n";
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="hero-eyebrow">فروشگاه</p>
        <h1 class="page-title page-title-lg">همهٔ محصولات</h1>
        <p class="hero-tagline">جستجو، مقایسه و خرید ابزار دقیق صنعتی</p>
        <form class="search-form" method="get" action="shop.php">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="جستجوی ابزار…" aria-label="جستجو">
            <button type="submit" class="btn btn-accent">جستجو</button>
        </form>
    </div>
</section>

<section class="container" id="categories">
    <div class="cat-strip">
        <a href="shop.php" class="cat-chip <?= $categoryId === 0 ? 'active' : '' ?>">همه</a>
        <?php foreach ($categories as $c): ?>
            <a href="shop.php?category=<?= (int)$c['id'] ?>" class="cat-chip <?= $categoryId === (int)$c['id'] ? 'active' : '' ?>">
                <?= e($c['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="container">
    <?php if (!$products): ?>
        <div class="empty-state">
            <p>محصولی یافت نشد.</p>
            <a href="shop.php" class="btn btn-accent">مشاهدهٔ همهٔ محصولات</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <article class="product-card">
                    <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-thumb">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="thumb-placeholder"><?= product_placeholder_icon($p['name']) ?></div>
                        <?php endif; ?>
                        <?php if (!product_in_stock($p['id'])): ?>
                            <span class="thumb-out-stock">ناموجود</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-body">
                        <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-name"><?= e($p['name']) ?></a>
                        <div class="product-meta"><?= e($p['category_name'] ?? '—') ?></div>
                        <?= price_or_login('<div class="product-price">' . price_html_discounted($p) . '</div>') ?>
                        <?php if (prices_hidden_for_guests()): ?>
                        <?php elseif (!product_in_stock($p['id'])): ?>
                            <span class="stock stock-out">ناموجود</span>
                            <a href="product.php?id=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-block btn-sm">🔔 موجود شد خبرم کن</a>
                        <?php else: ?>
                            <form method="post" action="cart.php" class="add-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-accent btn-block">افزودن به سبد</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="صفحه‌بندی محصولات">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?= e(shop_page_url($page - 1)) ?>">قبلی &raquo;</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?= $i === $page ? 'active' : '' ?>" href="<?= e(shop_page_url($i)) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-link" href="<?= e(shop_page_url($page + 1)) ?>">&laquo; بعدی</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
