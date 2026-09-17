<?php
/**
 * includes/email.php — لایهٔ ارسال ایمیل سایت
 * ------------------------------------------------------------------
 * دو حالت ارسال (تنظیم email_provider):
 *   - mail  : تابع داخلی PHP با هدرهای استاندارد UTF-8 (پیش‌فرض)
 *   - smtp  : کلاینت SMTP مستقیم (SSL/TLS/بدون رمز) با پشتیبانی AUTH LOGIN
 * همهٔ ارسال‌ها در جدول email_log ثبت می‌شوند و نتیجهٔ واقعی برمی‌گردد.
 */

/** ساخت جدول لاگ ایمیل در صورت نبود (سازگار MySQL/SQLite) */
function email_log_ensure()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
            db()->exec("CREATE TABLE IF NOT EXISTS email_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_addr TEXT NOT NULL,
                subject TEXT NOT NULL DEFAULT '',
                event TEXT NOT NULL DEFAULT 'custom',
                status TEXT NOT NULL DEFAULT 'sent',
                provider_note TEXT NOT NULL DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        } else {
            db()->exec("CREATE TABLE IF NOT EXISTS email_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                to_addr VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NOT NULL DEFAULT '',
                event VARCHAR(40) NOT NULL DEFAULT 'custom',
                status VARCHAR(20) NOT NULL DEFAULT 'sent',
                provider_note VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Throwable $t) {
        error_log('[email] ensure log table failed: ' . $t->getMessage());
    }
}

function email_log_write($to, $subject, $event, $status, $note = '')
{
    try {
        email_log_ensure();
        db()->prepare("INSERT INTO email_log (to_addr, subject, event, status, provider_note)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute([(string)$to, (string)$subject, (string)$event, (string)$status, (string)$note]);
    } catch (Throwable $t) {
        error_log('[email] log failed: ' . $t->getMessage());
    }
}

/** آخرین رکوردهای لاگ ایمیل برای پنل مدیریت */
function email_log_recent($limit = 30)
{
    $limit = max(1, min(300, (int)$limit));
    try {
        return db()->query("SELECT * FROM email_log ORDER BY id DESC LIMIT $limit")->fetchAll();
    } catch (Throwable $t) {
        return [];
    }
}

/** برچسب رویدادهای ایمیل برای نمایش در پنل */
function email_event_label($event)
{
    $map = [
        'otp_code'       => 'کد ورود',
        'password_reset' => 'بازیابی رمز',
        'stock_back'     => 'موجودشدن کالا',
        'welcome'        => 'خوش‌آمد عضویت',
        'order_paid'     => 'پرداخت سفارش',
        'test'           => 'تست پنل',
        'custom'         => 'سایر',
    ];
    return $map[$event] ?? (string)$event;
}

/** انکود UTF-8 مطابق RFC 2047 برای Subject و نام فرستنده */
function email_rfc2047($text)
{
    return '=?UTF-8?B?' . base64_encode((string)$text) . '?=';
}

/** ارسال با mail() داخلی + هدرهای استاندارد (بهبود تحویل‌پذیری نسبت به قبل) */
function email_send_via_mail($to, $subject, $body, $fromEmail, $fromName)
{
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . 'From: ' . email_rfc2047($fromName) . " <{$fromEmail}>\r\n"
        . "Reply-To: {$fromEmail}\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(10)) . "@abzar-shargh.ir>\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'X-Mailer: AbzarShargh-Mailer';
    $extra = '-f' . $fromEmail;
    $ok = @mail($to, email_rfc2047($subject), $body, $headers, $extra);
    return ['ok' => (bool)$ok, 'status' => $ok ? 'mail() OK' : 'mail() failed (بررسی لاگ سرور / MTA)'];
}

/** خواندن پاسخ چندخطی SMTP (تا رسیدن «XXX ') */
function _smtp_read($fp)
{
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $data;
}

function _smtp_cmd($fp, $cmd, $expect, &$trace)
{
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $resp = _smtp_read($fp);
    $code = (int)substr($resp, 0, 3);
    $trace .= '> ' . substr($cmd ?? '(read)', 0, 40) . ' => ' . substr($resp, 0, 180);
    return [$code, $resp];
}

/**
 * ارسال با کلاینت SMTP مستقیم.
 * @return array ['ok' => bool, 'status' => string]
 */
function email_send_via_smtp($to, $subject, $body, $fromEmail, $fromName)
{
    $host   = trim(setting('smtp_host', ''));
    $secure = setting('smtp_secure', 'tls');           // none | ssl | tls
    $port   = (int)setting('smtp_port', '');
    if ($port <= 0) $port = $secure === 'ssl' ? 465 : ($secure === 'tls' ? 587 : 25);
    $user = trim(setting('smtp_user', ''));
    $pass = setting('smtp_pass', '');

    $trace = '';
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$fp) {
        return ['ok' => false, 'status' => "smtp connect fail: {$errno} {$errstr}"];
    }
    stream_set_timeout($fp, 15);

    try {
        [$c] = _smtp_cmd($fp, null, [220], $trace);
        if ($c !== 220) return ['ok' => false, 'status' => "smtp greeting {$c} | " . substr($trace, -160)];

        _smtp_cmd($fp, 'EHLO abzar-shargh.ir', null, $trace);

        if ($secure === 'tls') {
            [$c] = _smtp_cmd($fp, 'STARTTLS', 220, $trace);
            if ($c !== 220) return ['ok' => false, 'status' => "starttls fail {$c} | " . substr($trace, -160)];
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return ['ok' => false, 'status' => 'tls handshake fail'];
            }
            _smtp_cmd($fp, 'EHLO abzar-shargh.ir', null, $trace);
        }

        if ($user !== '') {
            [$c] = _smtp_cmd($fp, 'AUTH LOGIN', 334, $trace);
            if ($c !== 334) return ['ok' => false, 'status' => "auth not offered {$c} | " . substr($trace, -160)];
            _smtp_cmd($fp, base64_encode($user), 334, $trace);
            [$c] = _smtp_cmd($fp, base64_encode($pass), 235, $trace);
            if ($c !== 235) return ['ok' => false, 'status' => "smtp auth fail {$c} | " . substr($trace, -160)];
        }

        [$c] = _smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250], $trace);
        if ($c !== 250) return ['ok' => false, 'status' => "mail from rejected {$c} | " . substr($trace, -160)];

        [$c] = _smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251], $trace);
        if ($c !== 250 && $c !== 251) return ['ok' => false, 'status' => "rcpt rejected {$c} | " . substr($trace, -160)];

        [$c] = _smtp_cmd($fp, 'DATA', 354, $trace);
        if ($c !== 354) return ['ok' => false, 'status' => "data not accepted {$c} | " . substr($trace, -160)];

        $headers = 'From: ' . email_rfc2047($fromName) . " <{$fromEmail}>\r\n"
            . "To: <{$to}>\r\n"
            . 'Subject: ' . email_rfc2047($subject) . "\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(10)) . "@abzar-shargh.ir>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'X-Mailer: AbzarShargh-Mailer';
        $payload = $headers . "\r\n\r\n" . chunk_split(base64_encode($body));
        // نقطهٔ ابتدای خط باید دوتا شود (dot-stuffing)
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($fp, $payload . "\r\n.\r\n");
        $resp = _smtp_read($fp);
        $c = (int)substr($resp, 0, 3);
        $trace .= '=> DATA result: ' . substr($resp, 0, 180);
        fwrite($fp, "QUIT\r\n");

        if ($c >= 250 && $c < 260) {
            return ['ok' => true, 'status' => 'smtp OK (' . $c . ')'];
        }
        return ['ok' => false, 'status' => "data rejected {$c} | " . substr($trace, -200)];
    } finally {
        @fclose($fp);
    }
}

/**
 * نقطهٔ ورود واحد ارسال ایمیل سایت.
 * @param string $to      ایمیل گیرنده
 * @param string $subject موضوع
 * @param string $body    متن پیام (plain text UTF-8)
 * @param string $event   کلید رویداد برای لاگ (otp_code, password_reset, test, ...)
 * @return array ['ok' => bool, 'status' => string]
 */
function email_send($to, $subject, $body, $event = 'custom')
{
    $to = strtolower(trim((string)$to));
    email_log_ensure();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        email_log_write($to, $subject, $event, 'failed', 'ایمیل نامعتبر است');
        return ['ok' => false, 'status' => 'invalid_email'];
    }

    $fromEmail = trim(setting('email_from_email', '')) !== '' ? trim(setting('email_from_email', '')) : 'no-reply@abzar-shargh.ir';
    $fromName  = trim(setting('email_from_name', ''))  !== '' ? trim(setting('email_from_name', ''))  : setting('store_name', STORE_NAME);
    $provider  = setting('email_provider', 'mail');

    if ($provider === 'smtp' && $host = trim(setting('smtp_host', ''))) {
        $res = email_send_via_smtp($to, $subject, $body, $fromEmail, $fromName);
    } else {
        $res = email_send_via_mail($to, $subject, $body, $fromEmail, $fromName);
    }

    email_log_write($to, $subject, $event, $res['ok'] ? 'sent' : 'failed', $res['status']);
    return $res;
}
