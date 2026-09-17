<?php
/**
 * cart.php — سبد خرید (نمایش، افزودن، حذف، به‌روزرسانی)
 */
require_once __DIR__ . '/config.php';

// پردازش اکشن‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // بررسی CSRF برای همهٔ اکشن‌های تغییر سبد
    if (!csrf_verify()) {
        http_response_code(403);
        flash('error', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $vid = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
    if ($action === 'add' && $pid) {
        $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
        if (cart_add($pid, $qty, $vid)) {
            flash('success', 'محصول به سبد خرید اضافه شد.');
        } else {
            flash('error', 'این محصول یا نوع انتخابی در دسترس نیست.');
        }
        // برگشت به صفحهٔ مبدأ (محصول/فروشگاه) تا کاربر بتواند چند محصول پشت سر هم اضافه کند
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref && (strpos($ref, BASE_URL) === 0 || strpos($ref, '/') === 0) && strpos($ref, '/cart.php') === false && strpos($ref, '/checkout.php') === false) {
            header('Location: ' . $ref);
        } else {
            header('Location: ' . BASE_URL . '/cart.php');
        }
        exit;
    }
    if ($action === 'update' && $pid) {
        cart_update($pid, isset($_POST['qty']) ? (int)$_POST['qty'] : 1, $vid);
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
    if ($action === 'remove' && $pid) {
        cart_remove($pid, $vid);
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
}

$items = cart_items();
$total = cart_total();
$subtotal = $total;
$discount = apply_discount($subtotal);
$shipping = shipping_cost();
$pageTitle = 'سبد خرید';
require __DIR__ . '/includes/header.php';
?>

<section class="container">
    <h1 class="page-title">سبد خرید</h1>

<?php $checkoutStep = 1; include __DIR__ . '/includes/checkout_steps.php'; ?>

    <?php if (!$items): ?>
        <div class="empty-state">
            <p>سبد خرید شما خالی است.</p>
            <a href="index.php" class="btn btn-accent">رفتن به فروشگاه</a>
        </div>
    <?php else: ?>
        <div class="cart-table-wrap">
            <table class="cart-table">
                <thead>
                    <tr><th>محصول</th><th>قیمت</th><th>تعداد</th><th>جمع</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td data-label="محصول">
                            <a href="product.php?id=<?= (int)$it['id'] ?>"><?= e($it['name']) ?></a>
                            <?php if (!empty($it['variant_name'])): ?>
                                <span class="cart-variant">نوع: <?= e($it['variant_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="قیمت"><?= e(fmt_price($it['price'])) ?></td>
                        <td data-label="تعداد">
                            <form method="post" action="cart.php" class="inline-qty">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                                <input type="hidden" name="variant_id" value="<?= (int)($it['variant_id'] ?? 0) ?>">
                                <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= max(1,(int)$it['stock']) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">به‌روزرسانی</button>
                            </form>
                        </td>
                        <td data-label="جمع"><?= e(fmt_price($it['line_total'])) ?></td>
                        <td>
                            <form method="post" action="cart.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                                <input type="hidden" name="variant_id" value="<?= (int)($it['variant_id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary">
            <?php if (global_discount_percent() > 0): ?>
                <div class="summary-line summary-discount">تخفیف همگانی (<?= e(fa_digits((string)global_discount_percent())) ?>٪): اعمال‌شده روی قیمت اقلام</div>
            <?php endif; ?>
            <?php if ($discount['total'] > 0): ?>
                <div class="summary-line summary-discount">
                    تخفیف
                    <?php if ($discount['coupon'] > 0): ?>(کوپن <?= e(fmt_price($discount['coupon'])) ?>)<?php endif; ?>
                    : <strong>- <?= e(fmt_price($discount['total'])) ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($shipping > 0): ?>
                <div class="summary-line">هزینهٔ ارسال: <strong><?= e(fmt_price($shipping)) ?></strong></div>
            <?php endif; ?>
            <div class="summary-total">جمع کل: <strong><?= e(fmt_price(max(0, $subtotal + $shipping - $discount['total']))) ?></strong></div>
            <a href="checkout.php" class="btn btn-accent">ادامه و پرداخت</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
