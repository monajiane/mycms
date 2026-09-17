<?php
/**
 * checkout.php — فرم ثبت سفارش و ایجاد سفارش در دیتابیس
 */
require_once __DIR__ . '/config.php';

$items = cart_items();
$total = cart_total();
$subtotal = $total;
$discount = apply_discount($subtotal);
$grandTotal = max(0, $subtotal + $shipping - $discount['total']);

// روش ارسال انتخاب‌شده (برای نمایش اولیه؛ در POST توسط سرور بازمحاسبه می‌شود)
$shipMethod = isset($_GET['ship']) ? (string)$_GET['ship'] : (isset($_POST['ship_method']) ? (string)$_POST['ship_method'] : 'tipax');
$validMethods = enabled_ship_methods();
if (!in_array($shipMethod, $validMethods, true)) { $shipMethod = $validMethods[0] ?? 'tipax'; }
$shipping = shipping_for_method($shipMethod);

// اطلاعات مشتری واردشده برای پیش‌پر کردن فرم
$customer = is_customer_logged_in() ? current_customer() : null;
$customerPoints = $customer ? user_points((int)$customer['id']) : 0;

if (!$items) {
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

// ---- خرید فقط برای کاربران واردشده (بدون مهمان) ----
if (!$customer && ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'place')) {
    $pageTitle = 'ورود برای ثبت سفارش';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="container">
        <div class="alert alert-info">
            <h1 class="page-title">برای ثبت سفارش ابتدا وارد شوید</h1>
            <p>برای پیگیری سفارش، دریافت امتیاز باشگاه مشتریان و تکمیل خرید، باید وارد حساب خود شوید.</p>
            <p>
                <a href="login.php?next=<?= e(urlencode('/checkout.php')) ?>" class="btn btn-accent">ورود به حساب</a>
                <a href="register.php?next=<?= e(urlencode('/checkout.php')) ?>" class="btn btn-ghost">ثبت‌نام</a>
            </p>
        </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];

// اعمال/حذف کوپن
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['coupon_code']);
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_coupon') {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: ' . BASE_URL . '/checkout.php');
        exit;
    }
    $code = trim($_POST['coupon_code'] ?? '');
    $coupon = get_coupon($code);
    if ($coupon && coupon_valid_for_order($coupon, $subtotal)) {
        $_SESSION['coupon_code'] = $code;
        flash('success', 'کوپن تخفیف اعمال شد.');
    } else {
        unset($_SESSION['coupon_code']);
        flash('error', 'کد کوپن نامعتبر، منقضی‌شده یا شرایط آن (حداقل خرید/سقف استفاده) برآورده نشده است.');
    }
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

// اعمال/حذف امتیاز باشگاه
if (isset($_GET['remove_points'])) {
    unset($_SESSION['points_redeemed']);
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_points') {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
        header('Location: ' . BASE_URL . '/checkout.php');
        exit;
    }
    if ($customer) {
        $requested = (int)($_POST['points'] ?? 0);
        $maxUsable = min($customerPoints, max(0, $subtotal + $shipping - $discount['total']));
        $points = max(0, min($requested, $maxUsable));
        if ($points > 0) {
            $_SESSION['points_redeemed'] = $points;
            flash('success', 'امتیاز به مبلغ ' . fmt_price($points) . ' تخفیف تبدیل شد.');
        } else {
            unset($_SESSION['points_redeemed']);
            flash('error', 'امتیاز قابل استفاده ندارید یا مقدار واردشده نامعتبر است.');
        }
    }
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

// بازمحاسبه پس از تغییر احتمالی سشن (کوپن / امتیاز)
$discount = apply_discount($subtotal);
$activeCoupon = active_coupon();
$pointsRedeemed = min((int)($_SESSION['points_redeemed'] ?? 0), $customerPoints);
$pointsDiscount = min($pointsRedeemed, max(0, $subtotal + $shipping - $discount['total']));
$grandTotal = max(0, $subtotal + $shipping - $discount['total'] - $pointsDiscount);

// ---- حذف/پیش‌فرض‌کردن آدرس (دکمه‌های داخل فرم با formaction به همین صفحه) ----
if ($customer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addr_action'])) {
    if (!csrf_verify()) {
        flash('error', 'نشست شما منقضی شده است؛ دوباره تلاش کنید.');
    } else {
        $aid = (int)($_GET['addr_id'] ?? 0);
        if (($_GET['addr_action'] ?? '') === 'delete') {
            address_delete((int)$customer['id'], $aid);
            flash('success', 'آدرس حذف شد.');
        } elseif (($_GET['addr_action'] ?? '') === 'default') {
            address_set_default((int)$customer['id'], $aid);
            flash('success', 'آدرس پیش‌فرض به‌روزرسانی شد.');
        }
    }
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place') {
    if (!csrf_verify()) {
        $errors[] = 'نشست شما منقضی شده است؛ لطفاً دوباره سفارش را ثبت کنید.';
    }
    if (!ip_rate_limit('order', 20, 3600)) {
        $errors[] = 'تعداد سفارش‌های ارسالی بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.';
    }
    if (!is_customer_logged_in()) {
        $errors[] = 'برای ثبت سفارش باید وارد حساب خود شوید.';
    }
    // آدرس: یا انتخاب از دفترچه یا فرم آدرس جدید
    $addrId    = (int)($_POST['address_id'] ?? 0);
    $savedAddr = ($customer && $addrId > 0) ? user_address((int)$customer['id'], $addrId) : null;
    if ($savedAddr) {
        $name    = (string)$savedAddr['full_name'];
        $phone   = (string)$savedAddr['phone'];
        $address = (string)$savedAddr['address'];
        $postal  = (string)($savedAddr['postal_code'] ?? '');
    } else {
        $name    = trim($_POST['a_name'] ?? '');
        $phone   = trim($_POST['a_phone'] ?? '');
        $address = trim($_POST['a_address'] ?? '');
        $postal  = trim($_POST['a_postal'] ?? '');
    }
    $email   = trim($_POST['email'] ?? '');

    if ($name === '') {
        $errors[] = 'نام و نام خانوادگی الزامی است.';
    }
    if ($phone === '' || !preg_match('/^[0-9+\- ]{6,20}$/', $phone)) {
        $errors[] = 'شماره تماس معتبر وارد کنید.';
    }
    if ($address === '') {
        $errors[] = 'آدرس تحویل الزامی است.';
    }

    if (!$errors) {
        // بازمحاسبهٔ هزینهٔ ارسال بر اساس روش انتخابی
        $postMethod = isset($_POST['ship_method']) ? (string)$_POST['ship_method'] : 'tipax';
        if (!in_array($postMethod, $validMethods, true)) { $postMethod = $validMethods[0] ?? 'tipax'; }
        $shipping = shipping_for_method($postMethod);
        $shipMethod = $postMethod;
        $pointsDiscount = min($pointsRedeemed, max(0, $subtotal + $shipping - $discount['total']));
        $grandTotal = max(0, $subtotal + $shipping - $discount['total'] - $pointsDiscount);

        // ذخیرهٔ آدرس جدید اگر کاربر خواسته باشد (خرابی آن سفارش را خراب نمی‌کند)
        if ($customer && !$savedAddr && isset($_POST['save_addr'])) {
            try {
                address_save((int)$customer['id'], [
                    'title'       => trim($_POST['a_title'] ?? ''),
                    'full_name'   => $name,
                    'phone'       => $phone,
                    'postal_code' => $postal,
                    'address'     => $address,
                ], isset($_POST['a_default']));
            } catch (Throwable $ta) {
                // ادامهٔ ثبت سفارش
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("INSERT INTO orders
                (user_id, customer_name, customer_phone, customer_email, address, postal_code, subtotal, shipping_amount, discount_amount, coupon_code, points_redeemed, total_amount, status, ship_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $st->execute([
                customer_id(),
                $name, $phone, $email, $address, $postal,
                $subtotal, $shipping, $discount['total'],
                !empty($_SESSION['coupon_code']) ? $_SESSION['coupon_code'] : null,
                $pointsDiscount,
                $grandTotal,
                $shipMethod,
            ]);
            $orderId = $pdo->lastInsertId();

            // شمارش مصرف کوپن
            if ($activeCoupon && !empty($_SESSION['coupon_code'])) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?")
                    ->execute([$_SESSION['coupon_code']]);
            }

            $stItem = $pdo->prepare("INSERT INTO order_items
                (order_id, product_id, product_name, price, quantity, variant_id, variant_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($items as $it) {
                $stItem->execute([
                    $orderId,
                    $it['id'],
                    $it['name'],
                    $it['price'],
                    $it['qty'],
                    !empty($it['variant_id']) ? (int)$it['variant_id'] : null,
                    $it['variant_name'] ?? null,
                ]);
                // کسر موجودی (وارینت یا محصول)
                if (!empty($it['variant_id'])) {
                    $pdo->prepare("UPDATE product_variants SET stock = MAX(0, stock - ?) WHERE id = ?")
                        ->execute([$it['qty'], (int)$it['variant_id']]);
                } else {
                    $pdo->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?")
                        ->execute([$it['qty'], (int)$it['id']]);
                }
            }

            $pdo->commit();
            unset($_SESSION['coupon_code']); // پاک کردن کوپن پس از استفاده
            unset($_SESSION['points_redeemed']); // پاک کردن امتیاز مصرفی پس از ثبت سفارش

            // رویداد اولیهٔ رهگیری: در حال آماده‌سازی
            try {
                order_tracking_add($orderId, 'processing', 'سفارش ثبت شد و در حال آماده‌سازی است.');
            } catch (Throwable $te) {
                // رهگیری نباید ثبت سفارش را خراب کند
            }

            // پیامک اطلاع سفارش جدید به ادمین‌ها (با نام محصولات فروخته‌شده)
            try {
                $itemNames = [];
                foreach ($items as $it) {
                    $label = (string)$it['name'];
                    if (!empty($it['variant_name'])) {
                        $label .= ' (' . $it['variant_name'] . ')';
                    }
                    $itemNames[] = $label . ' ×' . (int)$it['qty'];
                }
                $itemsText = implode('، ', array_slice($itemNames, 0, 4));
                if (count($itemNames) > 4) {
                    $itemsText .= ' +' . (count($itemNames) - 4) . ' مورد دیگر';
                }
                sms_notify_admins(
                    sms_template('admin_new_order', [
                        '{order_id}' => (string)$orderId,
                        '{customer}' => $name,
                        '{items}'    => $itemsText,
                        '{total}'    => number_format((int)$grandTotal),
                    ]),
                    'admin_new_order',
                    [(string)$orderId, $name, $itemsText, number_format((int)$grandTotal)]
                );
            } catch (Throwable $tn) {
                // نوتیف نباید ثبت سفارش را خراب کند
            }
        } catch (Throwable $t) {
            $pdo->rollBack();
            $errors[] = 'خطا در ثبت سفارش: ' . $t->getMessage();
        }

        if (!$errors) {
            // سبد خالی نشود — فقط پس از پرداخت موفق در complete_paid_order() خالی می‌شود
            header('Location: ' . BASE_URL . '/payment.php?id=' . $orderId);
            exit;
        }
    }
}

$pageTitle = 'ثبت سفارش و پرداخت';
require __DIR__ . '/includes/header.php';
?>

<section class="container checkout">
    <h1 class="page-title" style="font-size:1.15rem;margin:6px 0 8px">ثبت سفارش</h1>

    <?php $checkoutStep = 2; include __DIR__ . '/includes/checkout_steps.php'; ?>

    <?php if ($customer): ?>
        <div class="alert alert-success">شما با حساب «<?= e($customer['full_name'] ?: $customer['username']) ?>» وارد شده‌اید. این سفارش به حساب شما متصل می‌شود.</div>
    <?php else: ?>
        <div class="alert alert-info">
            برای ثبت سفارش در باشگاه مشتریان و پیگیری آسان‌تر، <a href="login.php?next=<?= e(urlencode('/checkout.php')) ?>">وارد شوید</a> یا <a href="register.php">ثبت‌نام کنید</a>.
        </div>
    <?php endif; ?>
    <div class="checkout-compact">
    <div class="checkout-grid">
        <form method="post" action="checkout.php" class="checkout-form" novalidate id="checkout-form">
            <input type="hidden" name="action" value="place">
            <?= csrf_field() ?>
            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div id="addrError" class="alert alert-error" style="display:none"></div>

            <section id="panel-1" class="step-panel is-visible">
                <h2 class="step-q">👤 برای ادامه، چطور وارد شوید؟</h2>
                <p class="step-hint">شماره موبایل کافیست — کد تأیید پیامک می‌شود.</p>
                <div class="step-actions">
                    <a href="login.php?next=<?= e(urlencode('/checkout.php')) ?>" class="btn btn-ghost btn-block">🔐 ورود با رمز عبور</a>
                    <a href="otp_login.php?next=<?= e(urlencode('/checkout.php')) ?>" class="btn btn-accent btn-block">📱 ورود سریع با کد پیامکی (OTP)</a>
                </div>
            </section>

            <section id="panel-2" class="step-panel">
            <?php
            $addresses   = $customer ? user_addresses((int)$customer['id']) : [];
            $selectedAddr = isset($_POST['address_id'])
                ? (int)$_POST['address_id']
                : ($addresses ? (int)$addresses[0]['id'] : 0);
            ?>
                <h2 class="step-q">📍 آدرس ارسال</h2>
                <p class="step-hint">یک آدرس انتخاب کنید یا جدید وارد کنید.</p>
            <?php if ($customer && !$addresses): ?>
                <div class="addr-empty">
                    <div class="addr-empty-icon">📍</div>
                    <h3>هنوز آدرسی ثبت نکرده‌اید</h3>
                    <p>یک بار آدرس‌تان را ثبت کنید؛ دفعات بعد فقط از لیست انتخابش می‌کنید.</p>
                </div>
            <?php endif; ?>

            <?php if ($customer && $addresses): ?>
                <input type="hidden" name="address_id" value="0">
                <div class="addr-list">
                    <?php foreach ($addresses as $ad): ?>
                        <div class="addr-item">
                            <label class="addr-card<?= $selectedAddr === (int)$ad['id'] ? ' selected' : '' ?>">
                                <input type="radio" name="address_id" value="<?= (int)$ad['id'] ?>" <?= $selectedAddr === (int)$ad['id'] ? 'checked' : '' ?>>
                                <span class="addr-body">
                                    <span class="addr-title">📍 <?= e($ad['title'] ?: 'آدرس ' . fa_digits((string)$ad['id'])) ?><?php if ((int)$ad['is_default'] === 1): ?><span class="addr-badge">پیش‌فرض</span><?php endif; ?></span>
                                    <span class="addr-name"><?= e($ad['full_name']) ?> — <?= e($ad['phone']) ?></span>
                                    <span class="addr-text"><?= e($ad['address']) ?><?= $ad['postal_code'] ? ' — کدپستی ' . e(fa_digits((string)$ad['postal_code'])) : '' ?></span>
                                </span>
                            </label>
                            <div class="addr-actions">
                                <button type="submit" class="btn btn-ghost btn-sm" formaction="checkout.php?addr_action=delete&amp;addr_id=<?= (int)$ad['id'] ?>" onclick="return confirm('این آدرس حذف شود؟')">حذف</button>
                                <?php if ((int)$ad['is_default'] !== 1): ?>
                                    <button type="submit" class="btn btn-ghost btn-sm" formaction="checkout.php?addr_action=default&amp;addr_id=<?= (int)$ad['id'] ?>">پیش‌فرض کن</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($customer): ?>
                <details class="addr-new" <?= $addresses ? '' : 'open' ?>>
                    <summary><?= $addresses ? '＋ افزودن آدرس جدید' : '✍️ ثبت آدرس جدید' ?></summary>
                    <div class="addr-form">
                        <label>نام گیرنده *<input type="text" name="a_name" value="<?= e($_POST['a_name'] ?? $customer['full_name'] ?? '') ?>"></label>
                        <label>شماره تماس *<input type="tel" name="a_phone" value="<?= e($_POST['a_phone'] ?? $customer['phone'] ?? '') ?>"></label>
                        <label>آدرس کامل *<textarea name="a_address" rows="2"><?= e($_POST['a_address'] ?? '') ?></textarea></label>
                        <label class="addr-check"><input type="checkbox" name="save_addr" checked> ذخیره برای خریدهای بعدی</label>
                        <label class="addr-check"><input type="checkbox" name="a_default" checked> پیش‌فرض من باشد</label>
                    </div>
                </details>
            <?php endif; ?>

                <button type="button" class="btn btn-accent btn-block btn-next">بعدی: ارسال ←</button>
                <button type="button" class="btn btn-ghost btn-block btn-prev">→ بازگشت</button>
            </section>

            <section id="panel-3" class="step-panel">
                <h2 class="step-q">🚚 روش ارسال</h2>
                <p class="step-hint">سریع‌ترین گزینه پیش‌فرض است. «تحویل درب کارخانه» بدون هزینهٔ ارسال است.</p>
                <div class="ship-options">
                    <?php if (in_array('tipax', $validMethods, true)): ?>
<label class="addr-card<?= $shipMethod === 'tipax' ? ' selected' : '' ?>">
                        <input type="radio" name="ship_method" value="tipax" <?= $shipMethod === 'tipax' ? 'checked' : '' ?>>
                        <span class="addr-body">
                            <span class="addr-title">📦 پست تیپاکس</span>
                            <span class="addr-name">۲ تا ۴ روز کاری</span>
                            <span class="ship-price"><?= shipping_for_method('tipax') > 0 ? e(fmt_price(shipping_for_method('tipax'))) . ' تومان' : 'رایگان' ?></span>
                        </span>
                    </label>
<?php endif; ?>
                    <?php if (in_array('post', $validMethods, true)): ?>
<label class="addr-card<?= $shipMethod === 'post' ? ' selected' : '' ?>">
                        <input type="radio" name="ship_method" value="post" <?= $shipMethod === 'post' ? 'checked' : '' ?>>
                        <span class="addr-body">
                            <span class="addr-title">✉️ پست پیشتاز</span>
                            <span class="addr-name">۳ تا ۵ روز کاری</span>
                            <span class="ship-price"><?= shipping_for_method('post') > 0 ? e(fmt_price(shipping_for_method('post'))) . ' تومان' : 'رایگان' ?></span>
                        </span>
                    </label>
<?php endif; ?>
                    <?php if (in_array('express', $validMethods, true)): ?>
<label class="addr-card<?= $shipMethod === 'express' ? ' selected' : '' ?>">
                        <input type="radio" name="ship_method" value="express" <?= $shipMethod === 'express' ? 'checked' : '' ?>>
                        <span class="addr-body">
                            <span class="addr-title">🚀 پیک فوری تهران</span>
                            <span class="addr-name">ارسال همان روز</span>
                            <span class="ship-price"><?= shipping_for_method('express') > 0 ? e(fmt_price(shipping_for_method('express'))) . ' تومان' : 'رایگان' ?></span>
                        </span>
                    </label>
<?php endif; ?>
                    <?php if (pickup_enabled()): ?>
                    <label class="addr-card pickup<?= $shipMethod === 'pickup' ? ' selected' : '' ?>">
                        <input type="radio" name="ship_method" value="pickup" <?= $shipMethod === 'pickup' ? 'checked' : '' ?>>
                        <span class="addr-body">
                            <span class="addr-title">🏭 تحویل درب کارخانه</span>
                            <span class="addr-name">بدون هزینهٔ ارسال</span>
                            <span class="ship-price free">رایگان</span>
                        </span>
                    </label>
                    <?php endif; ?>
                </div>
                <?php if (pickup_enabled() && pickup_address()): ?>
                <details class="pickup-info" id="pickupInfo" <?= $shipMethod === 'pickup' ? 'open' : '' ?> style="margin-top:8px">
                    <summary>📍 آدرس و ساعات تحویل حضوری</summary>
                    <div style="padding:8px 12px;font-size:.82rem;line-height:1.9;border:1px dashed var(--line);border-radius:6px;margin-top:6px;background:#fbfcfe">
                        <strong>آدرس:</strong> <?= e(pickup_address()) ?><br>
                        <?php if (pickup_hours()): ?><strong>ساعات:</strong> <?= e(pickup_hours()) ?><?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
                <button type="button" class="btn btn-accent btn-block btn-next">بعدی: بازبینی ←</button>
                <button type="button" class="btn btn-ghost btn-block btn-prev">→ بازگشت</button>
            </section>

            <section id="panel-4" class="step-panel">
                <h2 class="step-q">✅ بازبینی نهایی</h2>
                <div class="review-row"><span>📍 ارسال به:</span><strong id="reviewAddr">—</strong></div>
                <div class="review-row"><span>🚚 روش ارسال:</span><strong id="reviewShip">—</strong></div>
                <div class="review-row"><span>💰 مبلغ قابل پرداخت:</span><strong id="reviewTotal">—</strong></div>
                <label>ایمیل برای فاکتور (اختیاری)
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? $customer['email'] ?? '') ?>" placeholder="example@mail.com">
                </label>
                <button type="button" class="btn btn-accent btn-block btn-next">بعدی: پرداخت ←</button>
                <button type="button" class="btn btn-ghost btn-block btn-prev">→ بازگشت</button>
            </section>

            <section id="panel-5" class="step-panel">
                <h2 class="step-q">💳 پرداخت</h2>
                <p class="step-hint">با کلیک، سفارش ثبت و به درگاه بانکی هدایت می‌شوید.</p>
                <div class="review-row big"><span>💰 مبلغ نهایی:</span><strong id="reviewTotal2">—</strong></div>
                <button type="submit" class="btn btn-accent btn-block">🔒 ثبت سفارش و پرداخت</button>
                <button type="button" class="btn btn-ghost btn-block btn-prev">→ بازگشت</button>
            </section>
        </form>

        <aside class="order-summary compact">
            <h2>خلاصهٔ سفارش</h2>
            <ul>
                <?php foreach ($items as $it): ?>
                    <li>
                        <span><?= e($it['name']) ?><?= !empty($it['variant_name']) ? ' (' . e($it['variant_name']) . ')' : '' ?> × <?= (int)$it['qty'] ?></span>
                        <span><?= e(fmt_price($it['line_total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="summary-line">جمع اقلام: <strong><?= e(fmt_price($subtotal)) ?></strong></div>

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

            <div class="summary-line">هزینهٔ ارسال (ثابت برای کل سفارش): <strong><?= e(fmt_price($shipping)) ?></strong></div>
            <?php if ($pointsDiscount > 0): ?>
                <div class="summary-line summary-discount">تخفیف امتیاز: <strong>- <?= e(fmt_price($pointsDiscount)) ?></strong></div>
            <?php endif; ?>
            <div class="summary-total">قابل پرداخت: <strong><?= e(fmt_price($grandTotal)) ?></strong></div>

            <?php if ($activeCoupon): ?>
                <p class="coupon-applied">کوپن فعال: «<?= e($activeCoupon['code']) ?>»
                    <a href="checkout.php?remove_coupon=1">(حذف)</a></p>
            <?php endif; ?>

            <details style="margin-top:4px;font-size:.8rem">
                <summary>کد تخفیف / استفاده از امتیاز</summary>
                <form method="post" action="checkout.php" class="coupon-form" style="margin-top:4px">
                    <input type="hidden" name="action" value="apply_coupon">
                    <?= csrf_field() ?>
                    <input type="text" name="coupon_code" placeholder="کد تخفیف" value="<?= e($_SESSION['coupon_code'] ?? '') ?>" style="padding:4px 6px;font-size:.8rem;width:60%">
                    <button type="submit" class="btn btn-ghost btn-sm">اعمال</button>
                </form>
                <?php if ($customer): ?>
                    <p style="margin:4px 0 2px">امتیاز شما: <strong><?= fa_digits(number_format((int)$customerPoints, 0, '.', ',')) ?></strong></p>
                    <?php if ($pointsRedeemed > 0): ?>
                        <p class="coupon-applied">مصرفی: <?= fa_digits(number_format((int)$pointsRedeemed, 0, '.', ',')) ?> (<a href="checkout.php?remove_points=1">حذف</a>)</p>
                    <?php endif; ?>
                    <form method="post" action="checkout.php" class="coupon-form" style="margin-top:4px">
                        <input type="hidden" name="action" value="apply_points">
                        <?= csrf_field() ?>
                        <input type="number" name="points" min="1" max="<?= (int)$customerPoints ?>" placeholder="امتیاز" style="padding:4px 6px;font-size:.8rem;width:80px">
                        <button type="submit" class="btn btn-ghost btn-sm">استفاده</button>
                    </form>
                <?php endif; ?>
            </details>

            <?php $fc = get_flash('success'); if ($fc): ?><p class="alert alert-success"><?= e($fc) ?></p><?php endif; ?>
            <?php $fe = get_flash('error'); if ($fe): ?><p class="alert alert-error"><?= e($fe) ?></p><?php endif; ?>

            <p class="shipping-note"><?= e(setting('shipping_note')) ?></p>
        </aside>
    </div>
    </div>
</section>

<script>
(function () {
    var form = document.getElementById('checkout-form');
    if (form) { form.classList.add('js-wizard'); }
    var panels = document.querySelectorAll('.step-panel');
    if (!panels.length) return;
    var steps = document.querySelectorAll('.checkout-steps > li');
    var err = document.getElementById('addrError');
    var totalEl = document.getElementById('reviewTotal');
    var total2El = document.getElementById('reviewTotal2');
    var grandTotal = <?= json_encode((string)fmt_price($grandTotal)) ?>;
<?php $shipRatesJs = ['pickup' => 0]; foreach (['tipax', 'post', 'express'] as $_rm) { if (in_array($_rm, $validMethods, true)) $shipRatesJs[$_rm] = (int)shipping_for_method($_rm); } ?>
    var shipRates = <?= json_encode($shipRatesJs) ?>;
    var subtotalToman = <?= json_encode((int)$subtotal) ?>;
    var discountToman = <?= json_encode((int)$discount['total']) ?>;
    function fmtToman(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' تومان';
    }
    function recalc() {
        var m = document.querySelector('input[name="ship_method"]:checked');
        if (!m) return;
        var rate = shipRates[m.value] != null ? shipRates[m.value] : 0;
        var g = Math.max(0, subtotalToman + rate - discountToman);
        grandTotal = fmtToman(g);
        if (totalEl) totalEl.textContent = grandTotal;
        if (total2El) total2El.textContent = grandTotal;
    }
    document.querySelectorAll('input[name="ship_method"]').forEach(function (r) {
        r.addEventListener('change', recalc);
    });
    recalc();
    var isLoggedIn = <?= $customer ? 'true' : 'false' ?>;
    var current = isLoggedIn ? 2 : 1;
    function show(n) {
        current = Math.max(1, Math.min(5, n));
        for (var i = 0; i < panels.length; i++) {
            panels[i].classList.toggle('is-visible', i === current - 1);
        }
        for (var j = 0; j < steps.length; j++) {
            steps[j].classList.remove('done', 'active', 'pending');
            steps[j].classList.add(j + 1 < current ? 'done' : (j + 1 === current ? 'active' : 'pending'));
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function fillReview() {
        var sel = document.querySelector('input[name="address_id"]:checked');
        var isSaved = sel && parseInt(sel.value, 10) > 0;
        var rv = document.getElementById('reviewAddr');
        if (rv) {
            if (isSaved && sel) {
                var card = sel.closest('.addr-card');
                rv.textContent = (card.querySelector('.addr-title') || {textContent: ''}).textContent.trim() + ' — ' + (card.querySelector('.addr-text') || {textContent: ''}).textContent.trim();
            } else {
                var n = document.getElementsByName('a_name')[0];
                var t = document.getElementsByName('a_address')[0];
                rv.textContent = (n ? n.value : '') + (t ? ' — ' + t.value.slice(0, 80) : '');
            }
        }
        var sm = document.querySelector('input[name="ship_method"]:checked');
        var rs = document.getElementById('reviewShip');
        if (rs && sm) {
            var sc = sm.closest('.addr-card');
            rs.textContent = sc ? sc.querySelector('.addr-title').textContent.trim() : sm.value;
        }
    }
    function validateAddr() {
        var sel = document.querySelector('input[name="address_id"]:checked');
        var isSaved = sel && parseInt(sel.value, 10) > 0;
        if (isSaved) return true;
        var need = ['a_name', 'a_phone', 'a_address'];
        for (var i = 0; i < need.length; i++) {
            var el = document.getElementsByName(need[i])[0];
            if (el && el.value.trim() === '') {
                el.classList.add('input-error');
                el.focus();
                if (err) {
                    err.textContent = 'لطفاً نام، شماره تماس و آدرس را کامل کنید.';
                    err.style.display = 'block';
                }
                return false;
            }
        }
        if (err) { err.style.display = 'none'; }
        return true;
    }
    document.querySelectorAll('.btn-next').forEach(function (b) {
        b.addEventListener('click', function () {
            if (current === 2 && !validateAddr()) return;
            if (current >= 3) fillReview();
            show(current + 1);
        });
    });
    document.querySelectorAll('.btn-prev').forEach(function (b) {
        b.addEventListener('click', function () { show(current - 1); });
    });
    show(current);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
