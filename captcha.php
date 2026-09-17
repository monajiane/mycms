<?php
/**
 * captcha.php — تولید تصویر کپچا (فرم‌های ورود و ثبت‌نام)
 * ---------------------------------------------------------------
 * مسیر اصلی: PNG با کتابخانهٔ GD (کیفیت بالا، همان همیشه).
 * اگر GD روی سرور در دسترس نبود (مثل XAMPP پیش‌فرض یا بعضی هاست‌ها)،
 * به‌جای خطای ۵۰۰، خودکار تصویر SVG برداری با ارقام هفت‌قسمتی
 * تولید می‌شود — بدون نیاز به GD، فونت یا هر کتابخانهٔ جانبی.
 * منطق تولید و تأیید (captcha_generate / captcha_verify) یکسان است.
 */

require_once __DIR__ . '/config.php';

// محدودساز ضد شلیک: تولید تصویر کپچا حداکثر ۳۰ در دقیقه برای هر IP
if (!ip_rate_limit('captcha', 30, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

if (function_exists('imagecreatetruecolor')) {
    captcha_image(); // مسیر کلاسیک PNG (خروجی و exit داخل تابع انجام می‌شود)
}

captcha_svg_image(); // fallback بدون GD

/**
 * سگمنت‌های هفت‌قسمتی یک نویسه (ارقام ۰-۹ و + و -).
 * جعبهٔ هر نویسه ۲۶×۴۰ است؛ خروجی: آرایه‌ای از [x1,y1,x2,y2].
 * خروجی فقط path است (بدون <text>)، پس متن کپچا از XML قابل استخراج نیست.
 */
function captcha_svg_glyph($ch)
{
    static $S = [
        'A' => [3, 3, 23, 3],
        'B' => [24, 5, 24, 18],
        'C' => [24, 22, 24, 35],
        'D' => [3, 37, 23, 37],
        'E' => [2, 22, 2, 35],
        'F' => [2, 5, 2, 18],
        'G' => [3, 20, 23, 20],
    ];
    static $map = [
        '0' => 'ABCDEF', '1' => 'BC', '2' => 'ABGED', '3' => 'ABGCD',
        '4' => 'FGBC',   '5' => 'AFGCD', '6' => 'AFGEDC', '7' => 'ABC',
        '8' => 'ABCDEFG', '9' => 'ABCFGD',
    ];
    if ($ch === '+') {
        return [[13, 11, 13, 29], [4, 20, 22, 20]];
    }
    if ($ch === '-') {
        return [[4, 20, 22, 20]];
    }
    if (!isset($map[$ch])) {
        return [];
    }
    $segs = [];
    foreach (str_split($map[$ch]) as $k) {
        $segs[] = $S[$k];
    }
    return $segs;
}

/**
 * خروجی SVG کپچا (fallback بدون GD) — مستقیم به مرورگر.
 * پاسخ توسط captcha_generate() در نشست ذخیره می‌شود؛ تأیید تغییری نمی‌کند.
 */
function captcha_svg_image()
{
    $text = captcha_generate();
    $h = 56;
    $chars = str_split(preg_replace('/[^0-9+\-]/', '', $text));
    $n = count($chars);
    if ($n === 0) {
        http_response_code(500);
        exit('captcha error');
    }
    // اندازهٔ داینامیک: حداکثر عبارت «20 + 20» (۵ نویسه) جا می‌شود
    if ($n >= 5) {
        $glyphW = 24;
        $gap = 6;
    } else {
        $glyphW = 26;
        $gap = 10;
    }
    $totalW = $n * $glyphW + ($n - 1) * $gap;
    $w = max(160, $totalW + 16);
    $x = ($w - $totalW) / 2;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h
         . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="captcha">'
         . '<rect width="' . $w . '" height="' . $h . '" fill="#f5f7fc"/>';

    // خطوط نویز
    for ($i = 0; $i < 6; $i++) {
        $c = sprintf('#%02x%02x%02x', random_int(150, 205), random_int(150, 205), random_int(185, 230));
        $svg .= '<line x1="' . random_int(0, $w) . '" y1="' . random_int(0, $h)
              . '" x2="' . random_int(0, $w) . '" y2="' . random_int(0, $h)
              . '" stroke="' . $c . '" stroke-width="1" opacity="0.7"/>';
    }
    // نقاط نویز
    for ($i = 0; $i < 26; $i++) {
        $c = sprintf('#%02x%02x%02x', random_int(140, 200), random_int(140, 200), random_int(180, 230));
        $svg .= '<circle cx="' . random_int(2, $w - 2) . '" cy="' . random_int(2, $h - 2)
              . '" r="1" fill="' . $c . '" opacity="0.6"/>';
    }

    // ارقام هفت‌قسمتی با چرخش و رنگ تصادفی
    foreach ($chars as $ch) {
        $c = sprintf('#%02x%02x%02x', random_int(20, 80), random_int(40, 110), random_int(90, 170));
        $angle = random_int(-12, 12);
        $dy = random_int(-2, 2);
        $cx = $x + $glyphW / 2;
        $cy = $h / 2 + $dy;
        $svg .= '<g transform="rotate(' . $angle . ' ' . $cx . ' ' . $cy
              . ')" stroke="' . $c . '" stroke-width="3.4" stroke-linecap="round" fill="none">';
        foreach (captcha_svg_glyph($ch) as $s) {
            $svg .= '<line x1="' . ($x + $s[0]) . '" y1="' . ($s[1] + $dy)
                  . '" x2="' . ($x + $s[2]) . '" y2="' . ($s[3] + $dy) . '"/>';
        }
        $svg .= '</g>';
        $x += $glyphW + $gap;
    }

    $svg .= '</svg>';

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $svg;
    exit;
}
