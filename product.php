<?php
/**
 * product.php — صفحهٔ جزئیات محصول
 */
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = get_product($id);
if (!$product || !can_view_product($product)) {
    header('Location: index.php');
    exit;
}

// ---- ثبت نظر / امتیاز ----
$reviewError = '';
$reviewsEnabled = setting_bool('reviews_enabled', true);
if (!$reviewsEnabled && ($_POST['action'] ?? '') === 'review') {
    $reviewError = 'ثبت نظر موقتاً غیرفعال است.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (!is_customer_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php?next=' . urlencode('/product.php?id=' . $product['id']));
        exit;
    }
    if (!csrf_verify()) {
        $reviewError = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $text   = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $reviewError = 'لطفاً امتیاز (۱ تا ۵ ستاره) را انتخاب کنید.';
        } elseif ($text === '') {
            $reviewError = 'متن نظر نمی‌تواند خالی باشد.';
        } elseif (!ip_rate_limit('review', 5, 3600)) {
            $reviewError = 'تعداد نظرات ارسالی شما بیش از حد مجاز است؛ یک ساعت دیگر تلاش کنید.';
        } elseif (submit_review($product['id'], customer_id(), $rating, $text)) {
            flash('success', 'نظر شما با موفقیت ثبت شد. متشکریم!');
            header('Location: ' . BASE_URL . '/product.php?id=' . $product['id'] . '#reviews');
            exit;
        } else {
            $reviewError = 'خطا در ثبت نظر. لطفاً دوباره تلاش کنید.';
        }
    }
}

$ratingInfo = get_average_rating($product['id']);
$reviews    = get_product_reviews($product['id']);
$hasReviewed = is_customer_logged_in() ? has_user_reviewed($product['id'], customer_id()) : false;

// ---- ثبت درخواست اطلاع‌رسانی موجودشدن کالا ----
$notifyError = '';
$notifyDone  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stock_notify') {
    if (!csrf_verify()) {
        $notifyError = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } elseif (!ip_rate_limit('stockn', 5, 3600)) {
        $notifyError = 'تعداد درخواست‌های شما بیش از حد مجاز است؛ یک ساعت دیگر تلاش کنید.';
    } else {
        $contact   = trim($_POST['contact'] ?? '');
        $variantId = (int)($_POST['variant_id'] ?? 0);
        if (is_customer_logged_in()) {
            // کاربر واردشده: بدون پرسیدن دوباره، از تلفن/ایمیل ثبت‌شدهٔ حسابش استفاده کن
            $cu = current_customer();
            $candidates = [];
            if ($cu) {
                $storedPhone = trim((string)($cu['phone'] ?? ''));
                $storedEmail = trim((string)($cu['email'] ?? ''));
                if ($storedPhone !== '') {
                    $candidates[] = $storedPhone; // اولویت با پیامک
                }
                if ($storedEmail !== '') {
                    $candidates[] = $storedEmail;
                }
            }
            if (empty($candidates) && $contact !== '') {
                $candidates[] = $contact; // حساب بدون اطلاعات تماس: از ورودی فرم استفاده کن
            }
            $res = ['ok' => false, 'error' => 'هیچ شماره یا ایمیلی در حساب شما ثبت نشده است.'];
            foreach ($candidates as $cand) {
                $res = stock_notify_register($product['id'], $cand, $variantId ?: null);
                if ($res['ok'] || ($res['reason'] ?? '') === 'duplicate') {
                    break; // موفق یا قبلاً ثبت‌شده → ادامه نده
                }
            }
        } else {
            $res = stock_notify_register($product['id'], $contact, $variantId ?: null);
        }
        if ($res['ok']) {
            $notifyDone = true;
        } else {
            $notifyError = $res['error'];
        }
    }
}

$pageTitle = $product['name'];
$desc = trim(strip_tags((string)$product['description']));
if ($desc !== '') {
    if (function_exists('mb_substr') && mb_strlen($desc, 'UTF-8') > 160) {
        $desc = mb_substr($desc, 0, 160, 'UTF-8') . '…';
    }
    $pageDescription = $desc;
}
if (!empty($product['image'])) {
    $pageImage = product_image_url($product['image']);
}
$pageOgType = 'product';
$pageHeadExtra = product_jsonld($product, $ratingInfo);
$gallery = product_image_list($product);
$variants = product_variants($product['id']);
$hasVariants = count($variants) > 0;
// محدودهٔ قیمت برای نمایش (در صورت وجود وارینت)
$minPrice = $product['price'];
$maxPrice = $product['price'];
$hasSale = !empty($product['sale_price']) && (int)$product['sale_price'] > 0 && (int)$product['sale_price'] < (int)$product['price'];
if ($hasVariants) {
    $prices = [];
    $finals = [];
    $originals = [];
    foreach ($variants as $v) {
        $list = variant_price($product, $v);
        $prices[] = $list;
        $finals[] = price_final($list);
        $originals[] = product_original_price($product, $v);
    }
    $minPrice = min($finals);
    $maxPrice = max($finals);
    $minList = min($prices);
    $maxList = max($prices);
    $minOrig = min($originals);
    $maxOrig = max($originals);
}
require __DIR__ . '/includes/header.php';
?>

<section class="container product-detail">
    <div class="detail-grid">
        <div class="detail-thumb">
            <?php if (count($gallery) > 1): ?>
                <div class="gallery" data-gallery>
                    <div class="gallery-viewport">
                        <div class="gallery-track">
                            <?php foreach ($gallery as $g): ?>
                                <img src="<?= e(product_image_url($g)) ?>" alt="<?= e($product['name']) ?>">
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gallery-nav gallery-prev" aria-label="قبلی">‹</button>
                        <button type="button" class="gallery-nav gallery-next" aria-label="بعدی">›</button>
                    </div>
                    <div class="gallery-thumbs">
                        <?php foreach ($gallery as $i => $g): ?>
                            <button type="button" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>">
                                <img src="<?= e(product_image_url($g)) ?>" alt="تصویر <?= $i + 1 ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (count($gallery) === 1): ?>
                <img src="<?= e(product_image_url($gallery[0])) ?>" alt="<?= e($product['name']) ?>" loading="eager" decoding="async">
            <?php else: ?>
                <div class="thumb-placeholder large"><?= product_placeholder_icon($product['name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="detail-info">
            <nav class="breadcrumb">
                <a href="index.php">فروشگاه</a> ›
                <a href="index.php?category=<?= (int)$product['category_id'] ?>"><?= e($product['category_name'] ?? '—') ?></a>
            </nav>
            <h1><?= e($product['name']) ?></h1>
            <div class="product-price big">
                <?php if (prices_hidden_for_guests()): ?>
                    <span class="price-hidden-note">قیمت فقط برای اعضا نمایش داده می‌شود</span>
                <?php elseif ($hasVariants): ?>
                    <?php $pctAll = ($minOrig > 0 && $minPrice < $minOrig) ? (int)round((($minOrig - $minPrice) / $minOrig) * 100) : 0; ?>
                    <?php if ($pctAll > 0): ?><del class="price-old" id="variant-price-orig"><?= e(fmt_price($minOrig)) ?><?= $minOrig !== $maxOrig ? ' تا ' . e(fmt_price($maxOrig)) : '' ?></del><?php endif; ?>
                    <span id="variant-price"><?= e(fmt_price($minPrice)) ?><?= $minPrice !== $maxPrice ? ' تا ' . e(fmt_price($maxPrice)) : '' ?></span>
                    <?php if ($pctAll > 0): ?><span class="price-badge"><?= e(fa_digits((string)$pctAll)) ?>٪ تخفیف</span><?php endif; ?>
                <?php else: ?>
                    <?= price_html_discounted($product) ?>
                <?php endif; ?>
            </div>
            <div class="product-rating-summary">
                <?= render_stars($ratingInfo['average']) ?>
                <span class="rating-text"><?= e(fa_digits(number_format($ratingInfo['average'], 1))) ?> از ۵</span>
                <span class="rating-count">(<?= (int)$ratingInfo['count'] ?> نظر)</span>
            </div>
            <p class="detail-desc"><?= render_description($product['description']) ?></p>
            <div class="stock-line">
                <?php $inStock = product_in_stock($product['id']); ?>
                <?php if ($inStock): ?>
                    <span class="stock stock-in" id="variant-stock"><?= $hasVariants ? 'انتخاب نوع را ببینید' : 'موجود (' . (int)$product['stock'] . ' عدد)' ?></span>
                <?php else: ?>
                    <span class="stock stock-out">ناموجود</span>
                <?php endif; ?>
            </div>
            <?php if (prices_hidden_for_guests()): ?>
                <div class="stock-line">
                    <a href="login.php?next=<?= urlencode('/product.php?id=' . (int)$product['id']) ?>" class="btn btn-accent">ورود برای مشاهدهٔ قیمت و خرید</a>
                </div>
            <?php elseif ($inStock): ?>
                <form method="post" action="cart.php" class="qty-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <?php if ($hasVariants): ?>
                        <label class="qty-label" for="variant">انتخاب نوع</label>
                        <select name="variant_id" id="variant" required class="variant-select">
                            <option value="">— انتخاب کنید —</option>
                            <?php foreach ($variants as $v): ?>
                                <?php $vi = price_display_info($product, $v); ?>
                                <option value="<?= (int)$v['id'] ?>" data-price="<?= (int)$vi['final'] ?>" data-orig="<?= (int)$vi['original'] ?>" data-stock="<?= (int)$v['stock'] ?>" <?= (int)$v['stock'] <= 0 ? 'disabled' : '' ?>>
                                    <?= e($v['name']) ?><?= (int)$v['stock'] <= 0 ? ' (ناموجود)' : '' ?><?= $vi['percent'] > 0 ? ' (' . e(fa_digits((string)$vi['percent'])) . '٪ تخفیف)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <label class="qty-label" for="qty">تعداد</label>
                    <input type="number" id="qty" name="qty" value="1" min="1" max="<?= (int)$product['stock'] ?>">
                    <button type="submit" class="btn btn-accent" id="add-to-cart">افزودن به سبد خرید</button>
                </form>
            <?php else: ?>
                <div class="notify-box">
                    <?php if ($notifyDone): ?>
                        <div class="alert alert-success">درخواست شما ثبت شد. به محض موجودشدن، پیامک/ایمیل اطلاع‌رسانی برایتان ارسال می‌شود. 🙏</div>
                    <?php else: ?>
                        <?php if ($notifyError): ?><div class="alert alert-error"><?= e($notifyError) ?></div><?php endif; ?>
                        <p class="notify-title">🔔 این محصول فعلاً ناموجود است</p>
                        <?php $cuNotify = is_customer_logged_in() ? current_customer() : null;
                              $cuContact = $cuNotify ? (trim((string)($cuNotify['phone'] ?? '')) ?: trim((string)($cuNotify['email'] ?? ''))) : ''; ?>
                        <?php if ($cuNotify && $cuContact !== ''): ?>
                            <p class="muted">به‌محض موجودشدن، اطلاع‌رسانی به اطلاعات ثبت‌شدهٔ حساب شما ارسال می‌شود: <strong dir="ltr"><?= e(filter_var($cuContact, FILTER_VALIDATE_EMAIL) ? $cuContact : fa_digits($cuContact)) ?></strong></p>
                            <form method="post" action="product.php?id=<?= (int)$product['id'] ?>" class="notify-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="stock_notify">
                                <?php if ($hasVariants): ?>
                                    <select name="variant_id" class="variant-select">
                                        <option value="">همهٔ انواع</option>
                                        <?php foreach ($variants as $v): ?>
                                            <option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-accent btn-block">وقتی موجود شد خبرم کن</button>
                            </form>
                        <?php else: ?>
                            <p class="muted">شماره موبایل یا ایمیل خود را بگذارید تا به محض موجودشدن، خبرتان کنیم.</p>
                            <form method="post" action="product.php?id=<?= (int)$product['id'] ?>" class="notify-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="stock_notify">
                                <?php if ($hasVariants): ?>
                                    <select name="variant_id" class="variant-select">
                                        <option value="">همهٔ انواع</option>
                                        <?php foreach ($variants as $v): ?>
                                            <option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <input type="text" name="contact" dir="ltr" placeholder="09123456789 یا you@example.com" required>
                                <button type="submit" class="btn btn-accent btn-block">وقتی موجود شد خبرم کن</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container reviews-section" id="reviews">
    <style>
        .variant-select{width:100%;max-width:320px;padding:.6rem .7rem;border:1px solid var(--line,#d1d5db);border-radius:10px;font:inherit;margin-bottom:.5rem;}
        .product-rating-summary{display:flex;align-items:center;gap:.4rem;margin:-0.6rem 0 .8rem;font-weight:600;}
        .product-rating-summary .star,.reviews-avg .star,.review-stars .star{color:#cbd5e1;font-size:1.05rem;}
        .product-rating-summary .star.filled,.reviews-avg .star.filled,.review-stars .star.filled{color:#f59e0b;}
        .rating-text,.rating-count,.reviews-avg{color:var(--ink-soft);font-size:.9rem;}
        .reviews-section{padding-block:1rem 4rem;}
        .reviews-section .section-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;text-align:start;max-width:none;}
        .review-form-card{background:var(--surface,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:1.4rem;margin-bottom:1.5rem;}
        .review-form{display:flex;flex-direction:column;gap:1rem;}
        .review-form label{display:flex;flex-direction:column;gap:.45rem;font-weight:700;font-size:.92rem;}
        .review-form textarea{border:1px solid var(--line,#d1d5db);border-radius:10px;padding:.65rem .8rem;font:inherit;width:100%;resize:vertical;}
        .review-form .btn{align-self:flex-start;}
        .rating-input{display:inline-flex;gap:.3rem;font-size:1.7rem;margin-top:.25rem;}
        .rating-star{cursor:pointer;line-height:1;}
        .rating-star input{width:1px;height:1px;opacity:0;margin:0;padding:0;border:0;position:static;}
        .rating-star .star{color:#cbd5e1;display:inline-block;transition:color .12s;pointer-events:none;}
        .rating-star.lit .star{color:#f59e0b;}
        .rating-hint{display:inline-block;min-width:160px;font-size:.8rem;color:var(--ink-soft);font-weight:500;margin-inline-start:.5rem;}
        .rating-picker{display:flex;flex-direction:column;gap:.45rem;}
        .review-note{color:var(--ink-soft);font-size:.92rem;margin:0 0 1rem;}
        .reviews-list{display:flex;flex-direction:column;gap:1rem;}
        .review-item{background:var(--surface,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:1.1rem 1.3rem;}
        .review-head{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;margin-bottom:.5rem;}
        .review-author{font-weight:800;color:var(--navy,#0f172a);}
        .review-date{margin-inline-start:auto;color:var(--ink-soft);font-size:.85rem;}
        .review-comment{margin:0;color:var(--ink);line-height:1.8;}
    </style>
    <div class="section-head">
        <h2 class="section-title">نظرات و امتیازها</h2>
        <span class="reviews-avg"><?= render_stars($ratingInfo['average']) ?> <?= e(fa_digits(number_format($ratingInfo['average'], 1))) ?> (<?= (int)$ratingInfo['count'] ?> نظر)</span>
    </div>

    <?php if ($reviewError): ?><div class="alert alert-error"><?= e($reviewError) ?></div><?php endif; ?>

    <?php if (!$reviewsEnabled): ?>
            <div class="alert alert-info">ثبت نظر برای محصولات موقتاً غیرفعال است.</div>
            <?php elseif (is_customer_logged_in()): ?>
        <div class="review-form-card">
            <?php if ($hasReviewed): ?>
                <p class="review-note">شما قبلاً برای این محصول نظر ثبت کرده‌اید. با ارسال دوباره، نظر قبلی شما به‌روزرسانی می‌شود.</p>
            <?php endif; ?>
            <form method="post" action="product.php?id=<?= (int)$product['id'] ?>#reviews" class="review-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review">
                <label class="rating-picker">امتیاز شما <span class="rating-hint" id="ratingHint"></span>
                    <span class="rating-input" id="ratingInput">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="rating-star" data-star="<?= $i ?>"><input type="radio" name="rating" value="<?= $i ?>" required><span class="star">★</span></label>
                        <?php endfor; ?>
                    </span>
                </label>
                <label>متن نظر
                    <textarea name="comment" rows="3" placeholder="تجربهٔ خود را با این محصول بنویسید…" required></textarea>
                </label>
                <button type="submit" class="btn btn-accent">ثبت نظر</button>
            </form>
        </div>
    <?php else: ?>
        <p class="review-note">برای ثبت نظر و امتیاز، <a href="login.php?next=<?= e(urlencode('/product.php?id=' . $product['id'])) ?>">وارد حساب خود شوید</a>.</p>
    <?php endif; ?>

    <?php if (!$reviews): ?>
        <div class="empty-state"><p>هنوز نظری برای این محصول ثبت نشده است. اولین نفر باشید!</p></div>
    <?php else: ?>
        <div class="reviews-list">
            <?php foreach ($reviews as $r): ?>
                <article class="review-item">
                    <div class="review-head">
                        <span class="review-author"><?= e($r['full_name'] ?: $r['username']) ?></span>
                        <span class="review-stars"><?= render_stars((int)$r['rating']) ?></span>
                        <time class="review-date"><?= e(persian_date($r['created_at'] ?? null)) ?></time>
                    </div>
                    <p class="review-comment"><?= e($r['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var input = document.getElementById('ratingInput');
    if (!input) { return; }
    var labels = input.querySelectorAll('.rating-star');
    var hint = document.getElementById('ratingHint');
    var namesFa = ['', 'خیلی بد', 'بد', 'متوسط', 'خوب', 'عالی'];
    function faDigits(s) {
        return String(s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }
    var lastN = -1;
    function lit(n) {
        if (n === lastN) { return; } // بدون re-render اضافه (رفع پرش)
        lastN = n;
        labels.forEach(function (l) {
            var v = parseInt(l.getAttribute('data-star'), 10);
            l.classList.toggle('lit', v <= n);
        });
        if (hint) { hint.textContent = n > 0 ? '(' + faDigits(n) + ' — ' + namesFa[n] + ')' : ''; }
    }
    function checkedValue() {
        var c = input.querySelector('input:checked');
        return c ? parseInt(c.value, 10) : 0;
    }
    labels.forEach(function (l) {
        l.addEventListener('mouseenter', function () { lit(parseInt(l.getAttribute('data-star'), 10)); });
        l.addEventListener('click', function (e) { e.preventDefault(); lit(parseInt(l.getAttribute('data-star'), 10)); l.querySelector('input').checked = true; lit(checkedValue()); });
    });
    input.addEventListener('mouseleave', function () { lit(checkedValue()); });
    input.addEventListener('change', function () { lit(checkedValue()); });
    lit(checkedValue());
})();
</script>
<script>
// وارینت: به‌روزرسانی قیمت و موجودی هنگام انتخاب نوع
(function () {
    var sel = document.getElementById('variant');
    if (!sel) { return; }
    var priceEl = document.getElementById('variant-price');
    var origEl = document.getElementById('variant-price-orig');
    var stockEl = document.getElementById('variant-stock');
    var qtyEl = document.getElementById('qty');
    function faDigits(s) {
        return String(s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }
    function fmt(n) {
        return Number(n).toLocaleString('fa-IR');
    }
    function update() {
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.value) {
            var p = parseInt(opt.getAttribute('data-price'), 10);
            var o = parseInt(opt.getAttribute('data-orig'), 10);
            var st = parseInt(opt.getAttribute('data-stock'), 10);
            if (priceEl) { priceEl.textContent = fmt(p) + ' تومان'; }
            if (origEl) {
                if (o > p) {
                    origEl.textContent = fmt(o) + ' تومان';
                    origEl.style.display = '';
                } else {
                    origEl.style.display = 'none';
                }
            }
            if (stockEl) {
                stockEl.textContent = st > 0 ? 'موجود (' + fmt(st) + ' عدد)' : 'ناموجود';
                stockEl.className = 'stock ' + (st > 0 ? 'stock-in' : 'stock-out');
            }
            if (qtyEl && st > 0) { qtyEl.max = st; }
        }
    }
    sel.addEventListener('change', update);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
