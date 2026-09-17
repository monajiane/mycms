<?php
/**
 * verify.php — بازگشت کاربر از درگاه زرین‌پال و تأیید تراکنش
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/zarinpal.php';
require_once __DIR__ . '/includes/payment_common.php';

$authority = $_GET['Authority'] ?? ($_GET['authority'] ?? '');
$status    = $_GET['Status'] ?? ($_GET['status'] ?? '');

$st = db()->prepare("SELECT * FROM orders WHERE authority = ?");
$st->execute([$authority]);
$order = $st->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$orderId = $order['id'];
$zp = new ZarinPal();

if (strtoupper($status) === 'OK') {
    $result = $zp->verify($order['total_amount'], $authority);
    if ($result['ok']) {
        complete_paid_order($order, $result['ref_id']);
    } else {
        fail_order($order, $result['error']);
    }
} else {
    fail_order($order, 'انصراف کاربر از پرداخت');
}

header('Location: ' . BASE_URL . '/order_result.php?id=' . $orderId);
exit;
