<?php
/**
 * bitpay_verify.php — بازگشت کاربر از درگاه بیت‌پی (bitpay.ir) و تأیید تراکنش
 * ------------------------------------------------------------------
 * بیت‌پی پس از پرداخت، کاربر را با این پارامترها به redirect برمی‌گرداند:
 *   trans_id = شناسهٔ تراکنش بانکی (جدید)
 *   id_get   = همان trans_id که هنگام send گرفتیم (در authority سفارش ذخیره شده)
 *   factorId = همان شناسهٔ سفارش ما (order id)
 *
 * بنابراین برای یافتن سفارش، به ترتیب از factorId → id_get → trans_id استفاده می‌کنیم.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bitpay.php';
require_once __DIR__ . '/includes/payment_common.php';

// لاگ ورودی برای ریشه‌یابی
log_event('BITPAY_CALLBACK GET=' . json_encode($_GET, JSON_UNESCAPED_UNICODE), 'info');

$trans_id = $_GET['trans_id'] ?? '';
$id_get   = $_GET['id_get'] ?? '';
$factorId = (int)($_GET['factorId'] ?? 0);

if ($trans_id === '' || $id_get === '') {
    log_event('BITPAY_CALLBACK missing params: trans_id=' . var_export($trans_id, true) . ' id_get=' . var_export($id_get, true), 'error');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// یافتن سفارش: اول با factorId (مطمئن‌ترین)، سپس با id_get، سپس با trans_id
$order = null;
if ($factorId > 0) {
    $st = db()->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([$factorId]);
    $order = $st->fetch();
}
if (!$order) {
    $st = db()->prepare("SELECT * FROM orders WHERE authority = ?");
    $st->execute([(string)$id_get]);
    $order = $st->fetch();
}
if (!$order) {
    $st = db()->prepare("SELECT * FROM orders WHERE authority = ?");
    $st->execute([(string)$trans_id]);
    $order = $st->fetch();
}

if (!$order) {
    log_event('BITPAY_CALLBACK order not found (trans_id=' . $trans_id . ', id_get=' . $id_get . ', factorId=' . $factorId . ')', 'error');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$orderId = (int)$order['id'];
$bp = new BitPay();
$res = $bp->verify($trans_id, $id_get);

log_event('BITPAY_VERIFY order=' . $orderId . ' result=' . json_encode($res, JSON_UNESCAPED_UNICODE), 'info');

if ($res['ok'] && BitPay::isSuccessStatus($res['status'])) {
    if ($order['status'] !== 'paid') {
        complete_paid_order($order, (string)$trans_id);
    }
} elseif (BitPay::isCancelStatus($res['status'])) {
    fail_order($order, 'انصراف کاربر از پرداخت');
} else {
    fail_order($order, $res['error'] ?: ('پرداخت بیت‌پی ناموفق (status=' . $res['status'] . ')'));
}

header('Location: ' . BASE_URL . '/order_result.php?id=' . $orderId);
exit;
