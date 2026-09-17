<?php
/**
 * includes/checkout_steps.php — شمارندهٔ ۵ مرحله‌ای خرید
 * متغیر ورودی: $checkoutStep = 1..5 (پیش‌فرض ۲)
 */
$checkoutStep = max(1, min(5, (int)($checkoutStep ?? 2)));
$steps = [
    1 => ['num' => '۱', 'label' => 'سبد',    'url' => 'cart.php'],
    2 => ['num' => '۲', 'label' => 'ورود',   'url' => null],
    3 => ['num' => '۳', 'label' => 'آدرس',   'url' => null],
    4 => ['num' => '۴', 'label' => 'ارسال',  'url' => null],
    5 => ['num' => '۵', 'label' => 'پرداخت', 'url' => null],
];
?>
<ol class="checkout-steps checkout-steps-5" aria-label="مراحل خرید">
    <?php foreach ($steps as $i => $s):
        $state = $i < $checkoutStep ? 'done' : ($i === $checkoutStep ? 'active' : 'pending');
    ?>
        <li class="<?= e($state) ?>">
            <?php if ($s['url']): ?>
                <a href="<?= e($s['url']) ?>"><span class="step-num"><?= e($s['num']) ?></span><span class="step-label"><?= e($s['label']) ?></span></a>
            <?php else: ?>
                <span class="step-num"><?= e($s['num']) ?></span><span class="step-label"><?= e($s['label']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
