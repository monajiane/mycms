<?php
/**
 * includes/bitpay.php — کلاس اتصال به درگاه پرداخت بیت‌پی (bitpay.ir)
 * ------------------------------------------------------------------
 * درگاه پرداخت ریالی ایرانی (شاپرک). مستندات رسمی:
 * https://github.com/bitpaydotir/BitPayPHPSampleCode
 *
 * جریان:
 *   ۱) request()  → gateway-send   → دریافت trans_id
 *   ۲) هدایت کاربر به gateway-{trans_id}-get
 *   ۳) بازگشت کاربر به redirect با ?trans_id=X&id_get=Y
 *   ۴) verify()   → gateway-result-second → تأیید تراکنش (status=1 یعنی موفق)
 *
 * مبلغ: درگاه بیت‌پی به «ریال» کار می‌کند؛ اینجا از تومان ×۱۰ تبدیل می‌شود.
 */
class BitPay
{
    private $api;
    private $env; // test | production

    const TEST_SEND = 'https://bitpay.ir/payment-test/gateway-send';
    const PROD_SEND = 'https://bitpay.ir/payment/gateway-send';
    const TEST_GET  = 'https://bitpay.ir/payment-test/gateway-result-second';
    const PROD_GET  = 'https://bitpay.ir/payment/gateway-result-second';

    public function __construct($api = null, $env = null)
    {
        $this->api = $api !== null ? $api : setting('bitpay_api', '');
        $this->env = $env !== null ? $env : setting('bitpay_env', 'test');
    }

    private function sendUrl()
    {
        return $this->env === 'production' ? self::PROD_SEND : self::TEST_SEND;
    }

    private function getUrl()
    {
        return $this->env === 'production' ? self::PROD_GET : self::TEST_GET;
    }

    /** آیا تراکنش موفق است؟ (status=1) */
    public static function isSuccessStatus($status)
    {
        return (int)$status === 1;
    }

    /** آیا کاربر انصراف داده؟ (status=11) */
    public static function isCancelStatus($status)
    {
        return (int)$status === 11;
    }

    /**
     * ساخت تراکنش (درخواست پرداخت).
     * @param int    $toman    مبلغ سفارش به تومان (به ریال ×۱۰ تبدیل می‌شود)
     * @param int    $orderId  شناسهٔ سفارش (به‌عنوان factorId)
     * @param string $redirect آدرس بازگشت پس از پرداخت
     * @return array ['ok'=>bool, 'trans_id'=>int, 'pay_url'=>string, 'error'=>string]
     */
    public function request($toman, $orderId, $redirect)
    {
        if ($this->api === '') {
            return ['ok' => false, 'trans_id' => 0, 'pay_url' => '', 'error' => 'کلید API بیت‌پی تنظیم نشده است.'];
        }

        $amount = (int)$toman * 10; // تومان → ریال
        $data = [
            'api'        => $this->api,
            'amount'     => $amount,
            'redirect'   => $redirect,
            'factorId'   => $orderId,
        ];

        $res = $this->call($this->sendUrl(), $data);
        if ($res === false) {
            return ['ok' => false, 'trans_id' => 0, 'pay_url' => '', 'error' => 'خطا در ارتباط با سرور بیت‌پی'];
        }
        $res = trim((string)$res);

        if (is_numeric($res) && (int)$res > 0) {
            $tid = (int)$res;
            return [
                'ok'       => true,
                'trans_id' => $tid,
                'pay_url'  => 'https://bitpay.ir/payment/gateway-' . $tid . '-get',
                'error'    => '',
            ];
        }
        return ['ok' => false, 'trans_id' => 0, 'pay_url' => '', 'error' => 'بیت‌پی: ' . $this->sendError($res)];
    }

    /**
     * تأیید تراکنش پس از بازگشت کاربر.
     * @param string $trans_id شناسهٔ تراکنش (از query بازگشت)
     * @param string $id_get   شناسهٔ دریافت (از query بازگشت)
     * @return array ['ok'=>bool, 'status'=>int, 'amount'=>int, 'card'=>string, 'error'=>string]
     */
    public function verify($trans_id, $id_get)
    {
        $data = [
            'api'      => $this->api,
            'trans_id' => $trans_id,
            'id_get'   => $id_get,
            'json'     => 1,
        ];

        $res = $this->call($this->getUrl(), $data);
        if ($res === false) {
            return ['ok' => false, 'status' => 0, 'amount' => 0, 'card' => '', 'error' => 'خطا در ارتباط با سرور بیت‌پی'];
        }

        $json = json_decode($res, true);
        if (!is_array($json)) {
            return ['ok' => false, 'status' => 0, 'amount' => 0, 'card' => '', 'error' => 'پاسخ نامعتبر از بیت‌پی'];
        }

        return [
            'ok'     => true,
            'status' => (int)($json['status'] ?? -99),
            'amount' => (int)($json['amount'] ?? 0),
            'card'   => (string)($json['cardNum'] ?? ''),
            'error'  => '',
        ];
    }

    private function call($url, $data)
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body === false ? false : $body;
    }

    private function sendError($code)
    {
        $map = [
            '-1' => 'کلید API نامعتبر است',
            '-2' => 'مبلغ ارسالی نامعتبر است (کمتر از ۱۰۰۰ ریال)',
            '-3' => 'آدرس بازگشت (redirect) تعیین نشده است',
            '-4' => 'درگاه یافت نشد (هنوز فعال نشده است)',
            '-5' => 'خطا در اتصال به درگاه پرداخت',
        ];
        return $map[(string)$code] ?? ('کد خطای ' . $code);
    }
}
