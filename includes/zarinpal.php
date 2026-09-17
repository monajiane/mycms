<?php
/**
 * includes/zarinpal.php — کلاس اتصال به درگاه پرداخت زرین‌پال (REST v4)
 * ------------------------------------------------------------------
 * از ۱۴۰۲ مبالغ به «تومان» ارسال می‌شود.
 * حالت mock برای تست آفلاین جریان کامل پرداخت بدون اینترنت است.
 */

class ZarinPal
{
    private $merchantId;
    private $mode;

    const SANDBOX_REQUEST = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
    const SANDBOX_VERIFY  = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
    const SANDBOX_PAY     = 'https://sandbox.zarinpal.com/pg/StartPay/%s';

    const PROD_REQUEST    = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    const PROD_VERIFY     = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    const PROD_PAY        = 'https://payment.zarinpal.com/pg/StartPay/%s';

    public function __construct($merchantId = null, $mode = null)
    {
        $this->merchantId = $merchantId !== null ? $merchantId : setting('merchant_id', ZARINPAL_MERCHANT);
        $this->mode       = $mode !== null ? $mode : setting('payment_mode', PAYMENT_MODE);
    }

    private function isSandbox()
    {
        return $this->mode === 'sandbox';
    }

    /**
     * درخواست ساخت تراکنش.
     * @return array ['ok'=>bool, 'authority'=>string, 'error'=>string]
     */
    public function request($amount, $description, $callbackUrl)
    {
        $amount = (int)$amount;

        if ($this->mode === 'mock') {
            // شبیه‌سازی محلی: authority ساختگی برگردان
            return ['ok' => true, 'authority' => 'MOCK-' . strtoupper(bin2hex(random_bytes(8)))];
        }

        $url = $this->isSandbox() ? self::SANDBOX_REQUEST : self::PROD_REQUEST;
        $data = [
            'merchant_id'  => $this->merchantId,
            'amount'       => $amount,
            'currency'     => (AMOUNT_UNIT === 'rial' ? 'IRR' : 'IRT'),
            'callback_url' => $callbackUrl,
            'description'  => mb_substr($description, 0, 255),
        ];

        $resp = $this->call($url, $data);
        if ($resp === false) {
            return ['ok' => false, 'error' => 'خطا در ارتباط با سرور زرین‌پال'];
        }
        if (!empty($resp['data']['authority']) && $resp['data']['code'] == 100) {
            return ['ok' => true, 'authority' => $resp['data']['authority']];
        }
        $code = $resp['errors']['code'] ?? ($resp['data']['code'] ?? 'نامشخص');
        return ['ok' => false, 'error' => 'زرین‌پال: ' . $this->errorMessage($code)];
    }

    /**
     * تأیید تراکنش پس از بازگشت کاربر.
     * @return array ['ok'=>bool, 'ref_id'=>string, 'error'=>string]
     */
    public function verify($amount, $authority)
    {
        $amount = (int)$amount;

        if ($this->mode === 'mock') {
            return ['ok' => true, 'ref_id' => 'MOCK-' . strtoupper(bin2hex(random_bytes(6)))];
        }

        $url = $this->isSandbox() ? self::SANDBOX_VERIFY : self::PROD_VERIFY;
        $data = [
            'merchant_id' => $this->merchantId,
            'amount'      => $amount,
            'authority'   => $authority,
        ];

        $resp = $this->call($url, $data);
        if ($resp === false) {
            return ['ok' => false, 'error' => 'خطا در ارتباط با سرور زرین‌پال'];
        }
        if (($resp['data']['code'] ?? null) == 100) {
            return ['ok' => true, 'ref_id' => $resp['data']['ref_id']];
        }
        $code = $resp['errors']['code'] ?? ($resp['data']['code'] ?? 'نامشخص');
        return ['ok' => false, 'error' => 'زرین‌پال: ' . $this->errorMessage($code)];
    }

    /** آدرس هدایت کاربر به صفحهٔ پرداخت */
    public function payUrl($authority)
    {
        $tpl = $this->isSandbox() ? self::SANDBOX_PAY : self::PROD_PAY;
        return sprintf($tpl, $authority);
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
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $err) {
            return false;
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : false;
    }

    private function errorMessage($code)
    {
        $map = [
            '-9'  => 'خطای اعتبارسنجی داده‌ها',
            '-10' => 'مرچنت کد نامعتبر است',
            '-11' => 'مرچنت کد فعال نیست',
            '-12' => 'مبلغ بیش از حد مجاز است',
            '-15' => 'تراکنش نامعتبر است',
            '-16' => 'مبلغ کمتر از حد مجاز است',
            '-50' => 'مبلغ پرداخت‌شده با مبلغ تراکنش مطابقت ندارد',
            '-51' => 'پرداخت ناموفق بود',
            '-54' => 'تراکنش مرجع نامعتبر است',
            '101'=> 'پرداخت قبلاً تأیید شده است',
            '100'=> 'عملیات موفق',
        ];
        return isset($map[(string)$code]) ? $map[(string)$code] : ('کد خطای ' . $code);
    }
}
