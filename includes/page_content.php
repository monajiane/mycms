<?php
/**
 * includes/page_content.php — محتوای قابل ویرایش صفحات ثابت سایت
 * ------------------------------------------------------------------
 * صفحاتی مثل «درباره ما»، «قوانین»، «حریم خصوصی»، «دانشنامه» و صفحات خطا
 * متن‌شان به‌صورت hardcode داخل فایل بود و از پنل قابل ویرایش نبود.
 * این ماژول یک جدول key/value سبک می‌سازد و توابع خواندن/ذخیره ارائه می‌دهد
 * تا همان متن‌ها از پنل مدیریت («صفحات سایت») ویرایش شوند، در حالی که
 * مقدار پیش‌فرض (= متن فعلی) حفظ می‌شود تا ظاهر سایت تغییر نکند.
 *
 * سازگار با SQLite و MySQL (بدون وابستگی به NOW()).
 * استفاده در صفحات:
 *     <?= e(pc_get('about', 'hero_title', 'ابزارسازی شرق')) ?>
 */

/** ساخت جدول در اولین فراخوانی (idempotent) */
function page_content_ensure(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        if (DB_DRIVER === 'mysql') {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS page_contents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    page_key VARCHAR(100) NOT NULL,
                    field_key VARCHAR(100) NOT NULL,
                    field_value LONGTEXT,
                    updated_at DATETIME,
                    UNIQUE KEY uk_page_field (page_key, field_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS page_contents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    page_key TEXT NOT NULL,
                    field_key TEXT NOT NULL,
                    field_value TEXT,
                    updated_at TEXT,
                    UNIQUE (page_key, field_key)
                )"
            );
        }
    } catch (Throwable $e) {
        // در صورت نبود دسترسی، سایت باید با مقادیر پیش‌فرض کار کند و خطا ندهد
    }
}

/** خواندن یک مقدار؛ در صورت نبود، مقدار پیش‌فرض برمی‌گردد */
function pc_get(string $pageKey, string $fieldKey, string $default = ''): string
{
    page_content_ensure();
    try {
        $st = db()->prepare("SELECT field_value FROM page_contents WHERE page_key = ? AND field_key = ? LIMIT 1");
        $st->execute([$pageKey, $fieldKey]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null) return $default;
        return (string)$val;
    } catch (Throwable $e) {
        return $default;
    }
}

/** همهٔ مقادیر یک صفحه به‌صورت [field_key => value] */
function pc_all(string $pageKey): array
{
    page_content_ensure();
    $out = [];
    try {
        $st = db()->prepare("SELECT field_key, field_value FROM page_contents WHERE page_key = ?");
        $st->execute([$pageKey]);
        foreach ($st->fetchAll() as $r) {
            $out[$r['field_key']] = (string)$r['field_value'];
        }
    } catch (Throwable $e) {
    }
    return $out;
}

/** ذخیرهٔ گروهی فیلدهای یک صفحه (upsert سازگار با هر دو درایور) */
function pc_save(string $pageKey, array $fields): int
{
    page_content_ensure();
    $now = date('Y-m-d H:i:s');
    $n = 0;
    foreach ($fields as $fieldKey => $value) {
        $fieldKey = (string)$fieldKey;
        if ($fieldKey === '') continue;
        $value = (string)$value;
        try {
            $st = db()->prepare("SELECT id FROM page_contents WHERE page_key = ? AND field_key = ? LIMIT 1");
            $st->execute([$pageKey, $fieldKey]);
            $id = $st->fetchColumn();
            if ($id) {
                db()->prepare("UPDATE page_contents SET field_value = ?, updated_at = ? WHERE id = ?")
                    ->execute([$value, $now, $id]);
            } else {
                db()->prepare("INSERT INTO page_contents (page_key, field_key, field_value, updated_at) VALUES (?,?,?,?)")
                    ->execute([$pageKey, $fieldKey, $value, $now]);
            }
            $n++;
        } catch (Throwable $e) {
        }
    }
    return $n;
}

/** تعریف مرکزی صفحات ثابت و فیلدهای قابل ویرایش آن‌ها */
function pc_definitions(): array
{
    $storeAbout = function_exists('setting')
        ? (string)setting('company_about', 'ابزارسازی شرق با تکیه بر دانش فنی و ماشین‌آلات پیشرفته، ابزارهای استاندارد و سفارشی صنعتی را طراحی و عرضه می‌کند.')
        : 'ابزارسازی شرق با تکیه بر دانش فنی و ماشین‌آلات پیشرفته، ابزارهای استاندارد و سفارشی صنعتی را طراحی و عرضه می‌کند.';

    return [
        'about' => [
            'label' => 'درباره ما',
            'file'  => 'about.php',
            'fields' => [
                'hero_eyebrow'  => ['label' => 'برچسب بالای عنوان', 'default' => 'دربارهٔ ما', 'type' => 'text'],
                'hero_title'    => ['label' => 'عنوان اصلی', 'default' => 'ابزارسازی شرق', 'type' => 'text'],
                'intro_title'   => ['label' => 'عنوان بخش معرفی', 'default' => 'معرفی شرکت', 'type' => 'text'],
                'intro_body'    => ['label' => 'متن معرفی شرکت', 'default' => $storeAbout, 'type' => 'textarea'],
                'intro_body2'   => ['label' => 'پاراگراف دوم معرفی', 'default' => 'حوزه‌های فعالیت ما شامل تولید ابزارهای برشی، بازسازی و تیزکردن ابزار (ریکاندیشن)، تأمین ابزار دقیق اندازه‌گیری و تجهیزات ایمنی صنعتی است. ما با هدف کاهش هزینه‌ها و افزایش عمر ابزار، خدمات بازسازی و سنگ‌زنی را نیز به‌صورت تخصصی ارائه می‌دهیم.', 'type' => 'textarea'],
                'values_title'  => ['label' => 'عنوان بخش مزیت‌ها', 'default' => 'چرا ابزارسازی شرق؟', 'type' => 'text'],
            ],
        ],
        'terms' => [
            'label' => 'قوانین و مقررات',
            'file'  => 'terms.php',
            'fields' => [
                'title' => ['label' => 'عنوان صفحه', 'default' => 'قوانین و مقررات', 'type' => 'text'],
                'intro' => ['label' => 'متن مقدمه', 'default' => 'کاربر گرامی، استفاده از فروشگاه ما به معنای پذیرش قوانین زیر است.', 'type' => 'textarea'],
            ],
        ],
        'privacy' => [
            'label' => 'حریم خصوصی',
            'file'  => 'privacy.php',
            'fields' => [
                'title' => ['label' => 'عنوان صفحه', 'default' => 'سیاست حفظ حریم خصوصی', 'type' => 'text'],
                'intro' => ['label' => 'متن مقدمه', 'default' => 'ما به حریم خصوصی کاربران فروشگاه خود متعهد هستیم. این سند توضیح می‌دهد چه اطلاعاتی جمع‌آوری می‌شود و چگونه از آن استفاده می‌کنیم.', 'type' => 'textarea'],
            ],
        ],
        'knowledge' => [
            'label' => 'دانشنامه',
            'file'  => 'knowledge.php',
            'fields' => [
                'hero_eyebrow'  => ['label' => 'برچسب بالای عنوان', 'default' => 'دانشنامه', 'type' => 'text'],
                'hero_title'    => ['label' => 'عنوان اصلی', 'default' => 'مطالب فنی و آموزشی', 'type' => 'text'],
                'hero_tagline'  => ['label' => 'زیرعنوان', 'default' => 'راهنما، نکات تخصصی و آموزش ابزار صنعتی', 'type' => 'text'],
            ],
        ],
        'error404' => [
            'label' => 'صفحه ۴۰۴ (پیدا نشد)',
            'file'  => '404.php',
            'fields' => [
                'title' => ['label' => 'عنوان', 'default' => 'صفحه پیدا نشد', 'type' => 'text'],
                'message' => ['label' => 'پیام', 'default' => 'متأسفانه صفحه‌ای که دنبال آن بودید وجود ندارد یا حذف شده است.', 'type' => 'textarea'],
            ],
        ],
        'error500' => [
            'label' => 'صفحه ۵۰۰ (خطای سرور)',
            'file'  => '500.php',
            'fields' => [
                'title' => ['label' => 'عنوان', 'default' => 'خطای سرور', 'type' => 'text'],
                'message' => ['label' => 'پیام', 'default' => 'مشکلی در سرور رخ داده است. لطفاً چند لحظه بعد دوباره تلاش کنید.', 'type' => 'textarea'],
            ],
        ],
    ];
}