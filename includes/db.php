<?php
/**
 * includes/db.php — اتصال PDO و مقداردهی اولیهٔ دیتابیس
 * ------------------------------------------------------------------
 * تابع db() یک نمونهٔ PDO برمی‌گرداند (سینگلتون).
 * در حالت sqlite، اگر فایل دیتابیس وجود نداشته باشد، به‌صورت خودکار
 * ساخته و با دادهٔ اولیه (admin + محصولات نمونه) مقداردهی می‌شود.
 */

function db()
{
    if (isset($GLOBALS['_db_pdo']) && $GLOBALS['_db_pdo'] instanceof PDO) {
        return $GLOBALS['_db_pdo'];
    }

    $pdo = null;
    if (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        // sqlite (پیش‌فرض)
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fresh = !file_exists(DB_SQLITE_PATH);
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        // سازگاری با کوئری‌های MySQL-سبک (NOW())
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            try {
                $pdo->sqliteCreateFunction('NOW', function () { return date('Y-m-d H:i:s'); }, 0);
            } catch (Throwable $e) {}
        }
        if ($fresh) {
            init_sqlite_schema($pdo);
        }
        migrate_sqlite($pdo);
    }

    $GLOBALS['_db_pdo'] = $pdo;
    return $pdo;
}

/**
 * بستن اتصال دیتابیس (singleton را خالی می‌کند).
 * برای عملیاتی مثل حذف فایل sqlite روی ویندوز لازم است — چون ویندوز
 * حذف فایلِ باز را مجاز نمی‌داند.
 */
function db_close()
{
    $GLOBALS['_db_pdo'] = null;
}

/**
 * مهاجرت سبک sqlite برای پایگاه‌های ساخته‌شدهٔ قبلی (بدون تخریب داده).
 */
function migrate_sqlite(PDO $pdo)
{
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('phone', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT");
    }
    // ستون مالکیت خصوصی محصول (درخواست ابزار سفارشی)
    $pcols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('owner_user_id', $pcols, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN owner_user_id INTEGER");
    }
    // ستون قیمت ویژهٔ محصول (تخفیف تکی)
    if (!in_array('sale_price', $pcols, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sale_price INTEGER DEFAULT NULL");
    }
    // جدول گالری تصاویر محصول (برای پایگاه‌های ساخته‌شدهٔ قبلی)
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('product_images', $tables, true)) {
        $pdo->exec("CREATE TABLE product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            image TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }
    // جدول وارینت‌های محصول (برای پایگاه‌های ساخته‌شدهٔ قبلی)
    if (!in_array('product_variants', $tables, true)) {
        $pdo->exec("CREATE TABLE product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            sku TEXT,
            price_delta INTEGER NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_variants_product ON product_variants(product_id, sort_order)");
    }
    // جدول درخواست‌های ابزار سفارشی (برای پایگاه‌های ساخته‌شدهٔ قبلی)
    if (!in_array('custom_requests', $tables, true)) {
        $pdo->exec("CREATE TABLE custom_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            quantity INTEGER NOT NULL DEFAULT 1,
            file TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            admin_note TEXT,
            price INTEGER,
            product_id INTEGER,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }
    // جدول مقالات دانشنامه (برای پایگاه‌های ساخته‌شدهٔ قبلی)
    if (!in_array('articles', $tables, true)) {
        $pdo->exec("CREATE TABLE articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT,
            excerpt TEXT,
            content TEXT,
            image TEXT,
            video TEXT,
            published INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    } else {
        // ستون ویدیو برای پایگاه‌هایی که جدول مقالات دارند ولی ستون video ندارند
        $acols = $pdo->query("PRAGMA table_info(articles)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('video', $acols, true)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN video TEXT");
        }
    }

    // ---- تخفیف و هزینهٔ ارسال ----
    // ستون‌های مالی سفارش (جمع اقلام / هزینه ارسال / تخفیف / کد کوپن)
    $ocols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('subtotal', $ocols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN subtotal INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('shipping_amount', $ocols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_amount INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('discount_amount', $ocols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN discount_amount INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('coupon_code', $ocols, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code TEXT");
    }

    // ستون‌های وارینت در اقلام سفارش (برای سفارش‌های دارای وارینت)
    $oicols = $pdo->query("PRAGMA table_info(order_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('variant_id', $oicols, true)) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN variant_id INTEGER");
    }
    if (!in_array('variant_name', $oicols, true)) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN variant_name TEXT");
    }

    // جدول کوپن‌های تخفیف
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('coupons', $tables, true)) {
        $pdo->exec("CREATE TABLE coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL DEFAULT 'percent',
            value INTEGER NOT NULL DEFAULT 0,
            max_uses INTEGER,
            used_count INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            expires_at TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }

    // جدول نظرات و امتیاز محصولات (برای پایگاه‌های ساخته‌شدهٔ قبلی)
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('reviews', $tables, true)) {
        $pdo->exec("CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            comment TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime')),
            UNIQUE (product_id, user_id)
        )");
    }

    // ---- ستون‌های پیشرفتهٔ کوپن (حداقل خرید، تاریخ شروع، سقف هر کاربر، توضیح) ----
    $ccols = $pdo->query("PRAGMA table_info(coupons)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('min_order_amount', $ccols, true)) {
        $pdo->exec("ALTER TABLE coupons ADD COLUMN min_order_amount INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('starts_at', $ccols, true)) {
        $pdo->exec("ALTER TABLE coupons ADD COLUMN starts_at TEXT");
    }
    if (!in_array('per_user_limit', $ccols, true)) {
        $pdo->exec("ALTER TABLE coupons ADD COLUMN per_user_limit INTEGER");
    }
    if (!in_array('description', $ccols, true)) {
        $pdo->exec("ALTER TABLE coupons ADD COLUMN description TEXT");
    }

    // ---- باشگاه مشتریان (امتیاز) ----
    $ucols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('points', $ucols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN points INTEGER NOT NULL DEFAULT 0");
    }
    $ocols2 = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('points_redeemed', $ocols2, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN points_redeemed INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('ship_method', $ocols2, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN ship_method TEXT");
    }
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('points_transactions', $tables, true)) {
        $pdo->exec("CREATE TABLE points_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'manual',
            description TEXT,
            ref_id INTEGER,
            created_by INTEGER,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        // ایندکس یکتا برای idempotency: جلوگیری از دوبار ثبت earn/redeem برای یک سفارش
        try {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_points_ref ON points_transactions (ref_id, type)");
        } catch (Throwable $t2) {
            // اگر دادهٔ تکراری موجود باشد، ایندکس ساخته نمی‌شود؛ منطق نرم‌افزاری چک count همچنان فعال است
        }
    }

    // ---- لاگ پیامک‌های سامانه (پنل پیامک مدیریت) ----
    if (!in_array('sms_log', $tables, true)) {
        $pdo->exec("CREATE TABLE sms_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL,
            message TEXT NOT NULL,
            event TEXT NOT NULL DEFAULT 'custom',
            status TEXT NOT NULL DEFAULT 'sent',
            provider_note TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }

    // ---- ایندکس یکتای idempotency امتیازها (مستقل از ساخت جدول) ----
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_points_ref ON points_transactions (ref_id, type)");
    } catch (Throwable $ptIdx) {
        // اگر دادهٔ تکراری موجود باشد، ایندکس ساخته نمی‌شود؛ منطق نرم‌افزاری چک count همچنان فعال است
    }

    // ---- جدول معرف‌ها (Referral) ----
    if (!in_array('referrals', $tables, true)) {
        $pdo->exec("CREATE TABLE referrals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            referrer_id INTEGER NOT NULL,
            invitee_id INTEGER NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'registered',
            points_awarded INTEGER NOT NULL DEFAULT 0,
            ref_order_id INTEGER,
            created_at TEXT DEFAULT (datetime('now','localtime')),
            rewarded_at TEXT
        )");
    }

    // ---- ستون‌های کد معرف در users (SQLite: ALTER ساده) ----
    try {
        $ucols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('referral_code', $ucols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN referral_code TEXT NULL");
        }
        if (!in_array('referred_by', $ucols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN referred_by INTEGER NULL");
        }
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_users_refcode ON users (referral_code)");
    } catch (Throwable $rc) {
        // اگر ایندکس به‌خاطر دادهٔ تکراری موجود نساخت، lazy-create با try/catch امن است
    }

    // ---- پیام‌رسان داخلی ----
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('messages', $tables, true)) {
        $pdo->exec("CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            subject TEXT,
            body TEXT NOT NULL,
            from_admin INTEGER NOT NULL DEFAULT 0,
            is_read INTEGER NOT NULL DEFAULT 0,
            is_closed INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }

    // ---- دفترچهٔ آدرس‌های مشتری ----
    if (!in_array('addresses', $tables, true)) {
        $pdo->exec("CREATE TABLE addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            full_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            postal_code TEXT,
            address TEXT NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_addresses_user ON addresses(user_id, id)");

    // ---- ایندکس‌های کارایی (idempotent) ----
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_active_featured ON products(active, featured, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id, active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_owner ON products(owner_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images(product_id, sort_order)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_variants_product ON product_variants(product_id, sort_order)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_published ON articles(published, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_user ON messages(user_id, parent_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_points_tx_user ON points_transactions(user_id, id)");

    // تنظیمات پیش‌فرض تخفیف/ارسال در صورت نبودن
    ensure_setting($pdo, 'shipping_flat_rate', '0');
    // ایندکس یکتا روی phone (اگر قبلاً نبود) + ادغام ردیف‌های تکراری
    $hasPhoneIdx = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='ux_users_phone'")->fetchColumn();
    if (!$hasPhoneIdx) {
        // پیش از ساخت ایندکس، ردیف‌های تکراری را فقط برای آخرین مورد نگه می‌داریم
        $dups = $pdo->query("SELECT phone, COUNT(*) c FROM users WHERE phone IS NOT NULL AND phone != '' GROUP BY phone HAVING c > 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dups as $d) {
            // قدیمی‌ترین‌ها حذف می‌شوند؛ فقط جدیدترین نگه داشته می‌شود
            $keepId = (int)$pdo->query("SELECT id FROM users WHERE phone = " . $pdo->quote($d['phone']) . " ORDER BY id DESC LIMIT 1")->fetchColumn();
            $pdo->prepare("DELETE FROM users WHERE phone = ? AND id != ?")->execute([$d['phone'], $keepId]);
        }
        $pdo->exec("CREATE UNIQUE INDEX ux_users_phone ON users (phone) WHERE phone IS NOT NULL AND phone != ''");
    }
    ensure_setting($pdo, 'pickup_enabled', '1');
    ensure_setting($pdo, 'pickup_address', 'کارخانه — تهران، شهرک صنعتی شمس‌آباد، خیابان ابزار، پلاک ۱۰');
    ensure_setting($pdo, 'pickup_hours', 'شنبه تا چهارشنبه ۹ تا ۱۷');
    ensure_setting($pdo, 'global_discount', '0');

    // ستون تصویر دسته‌بندی (برای کارت‌های تصویردار)
    $ccols = $pdo->query("PRAGMA table_info(categories)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('image', $ccols, true)) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN image TEXT");
    }

    // ستون بسته‌شدن گفتگوها
    $mcols = $pdo->query("PRAGMA table_info(messages)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_closed', $mcols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_closed INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_closed ON messages(parent_id, is_closed)");
    }

    // ستون تأیید نظرات (approved/pending)
    $rcols = $pdo->query("PRAGMA table_info(reviews)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('status', $rcols, true)) {
        $pdo->exec("ALTER TABLE reviews ADD COLUMN status TEXT NOT NULL DEFAULT 'approved'");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(product_id, status)");
    }

    // ---- OTP (ورود با کد یک‌بارمصرف) ----
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('otp_codes', $tables, true)) {
        $pdo->exec("CREATE TABLE otp_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            channel TEXT NOT NULL DEFAULT 'sms',
            code TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            attempts INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_otp_identifier ON otp_codes(identifier, used, expires_at)");
    }

    // ---- رهگیری سفارش (وضعیت مرسوله) ----
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('order_tracking', $tables, true)) {
        $pdo->exec("CREATE TABLE order_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'processing',
            label TEXT,
            note TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tracking_order ON order_tracking(order_id, created_at)");
    }

    // ---- اطلاع‌رسانی موجودشدن کالا (stock_notifications) ----
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('stock_notifications', $tables, true)) {
        $pdo->exec("CREATE TABLE stock_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            variant_id INTEGER DEFAULT NULL,
            phone TEXT,
            email TEXT,
            notified INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stocknotif_product ON stock_notifications(product_id, notified)");
    }

    // ---- بازدیدهای سایت (یکتای روزانه بر اساس IP) ----
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('visits', $tables, true)) {
        $pdo->exec("CREATE TABLE visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            day TEXT NOT NULL,
            uri TEXT,
            country TEXT,
            source TEXT,
            device TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visits_ip_day ON visits(ip, day)");
    } else {
        // ارتقای جدول موجود: ستون‌های country/source/device
        $vcols = $pdo->query("PRAGMA table_info(visits)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('country', $vcols, true)) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN country TEXT");
        }
        if (!in_array('source', $vcols, true)) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN source TEXT");
        }
        if (!in_array('device', $vcols, true)) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN device TEXT");
        }
    }

    // ---- جدول طرح‌های ویرایشگر بصری (GrapesJS page designer) ----
    // توجه: جدول‌های pages/page_blocks پایین‌تر در همین تابع (بلوک سئو/صفحه‌ساز)
    // ساخته می‌شوند؛ اینجا فقط page_designs با IF NOT EXISTS ساخته می‌شود تا در نصب
    // تازه، ترتیب اجرا باعث خطای «table pages already exists» نشود.
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_designs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL UNIQUE,
        project_json TEXT,
        html TEXT,
        css TEXT,
        updated_at TEXT
    )");
    // --- مهاجرت سئو / صفحه‌ساز (seo_manager, page_builder) ---
    // ۱) ستون‌های سئو برای جدول‌های موجود
    $seoTables = ['products', 'articles', 'categories'];
    $seoCols = [
        'seo_title'       => "ALTER TABLE %s ADD COLUMN seo_title TEXT DEFAULT ''",
        'seo_description' => "ALTER TABLE %s ADD COLUMN seo_description TEXT DEFAULT ''",
        'seo_keywords'    => "ALTER TABLE %s ADD COLUMN seo_keywords TEXT DEFAULT ''",
        'canonical'       => "ALTER TABLE %s ADD COLUMN canonical TEXT DEFAULT ''",
        'og_image'        => "ALTER TABLE %s ADD COLUMN og_image TEXT DEFAULT ''",
        'noindex'         => "ALTER TABLE %s ADD COLUMN noindex INTEGER NOT NULL DEFAULT 0",
    ];
    foreach ($seoTables as $st) {
        try {
            $cols = $pdo->query("PRAGMA table_info(" . $st . ")")->fetchAll(PDO::FETCH_COLUMN, 1);
        } catch (Throwable $e) { continue; }
        foreach ($seoCols as $colName => $sql) {
            if (!in_array($colName, $cols, true)) {
                try { $pdo->exec(sprintf($sql, $st)); } catch (Throwable $e) {}
            }
        }
    }
    // ۲) جدول صفحات (صفحه‌ساز)
    if (!in_array('pages', $tables, true)) {
        $pdo->exec("CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL DEFAULT '',
            slug TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'draft',
            seo_title TEXT DEFAULT '',
            seo_description TEXT DEFAULT '',
            seo_keywords TEXT DEFAULT '',
            og_image TEXT DEFAULT '',
            canonical TEXT DEFAULT '',
            noindex INTEGER NOT NULL DEFAULT 0,
            blocks_json TEXT DEFAULT '[]',
            created_at TEXT DEFAULT (datetime('now','localtime')),
            updated_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }
    // ۳) جدول بلوک‌های صفحه
    if (!in_array('page_blocks', $tables, true)) {
        $pdo->exec("CREATE TABLE page_blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'unknown',
            content_json TEXT,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_page_blocks_page ON page_blocks(page_id, position)");
    }
    // ۴) جدول ریدایرکت‌های سئو
    if (!in_array('seo_redirects', $tables, true)) {
        $pdo->exec("CREATE TABLE seo_redirects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_path TEXT NOT NULL,
            to_url TEXT NOT NULL,
            code INTEGER NOT NULL DEFAULT 301,
            created_at TEXT DEFAULT (datetime('now','localtime')),
            updated_at TEXT DEFAULT (datetime('now','localtime'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_seo_redirects_from ON seo_redirects(from_path)");
    }
    // --- مهاجرت بازاریابی (newsletter / ab_tests / referrals.commission) ---
    if (!in_array('newsletter', $tables, true)) {
        $pdo->exec("CREATE TABLE newsletter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        )");
    }
    if (!in_array('ab_tests', $tables, true)) {
        $pdo->exec("CREATE TABLE ab_tests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT '',
            variant_a TEXT NOT NULL DEFAULT '',
            variant_b TEXT NOT NULL DEFAULT '',
            started_at TEXT DEFAULT (datetime('now','localtime')),
            ended_at TEXT
        )");
    }
    try {
        $rcols = $pdo->query("PRAGMA table_info(referrals)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('commission', $rcols, true)) {
            $pdo->exec("ALTER TABLE referrals ADD COLUMN commission INTEGER NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {}
}

/**
 * ساخت اسکیمای sqlite و درج دادهٔ اولیه.
 */
function init_sqlite_schema(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            full_name TEXT,
            email TEXT,
            phone TEXT,
            points INTEGER NOT NULL DEFAULT 0,
            role TEXT NOT NULL DEFAULT 'customer',
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE,
            description TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT,
            description TEXT,
            price INTEGER NOT NULL DEFAULT 0,
            category_id INTEGER,
            stock INTEGER NOT NULL DEFAULT 0,
            image TEXT,
            featured INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            owner_user_id INTEGER,
            sale_price INTEGER DEFAULT NULL,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            image TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            sku TEXT,
            price_delta INTEGER NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT,
            excerpt TEXT,
            content TEXT,
            image TEXT,
            video TEXT,
            published INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            customer_name TEXT NOT NULL,
            customer_phone TEXT,
            customer_email TEXT,
            address TEXT,
            postal_code TEXT,
            subtotal INTEGER NOT NULL DEFAULT 0,
            shipping_amount INTEGER NOT NULL DEFAULT 0,
            discount_amount INTEGER NOT NULL DEFAULT 0,
            coupon_code TEXT,
            points_redeemed INTEGER NOT NULL DEFAULT 0,
            total_amount INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'pending',
            authority TEXT,
            ref_id TEXT,
            payment_note TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER,
            product_name TEXT NOT NULL,
            price INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            variant_id INTEGER,
            variant_name TEXT
        );
        CREATE TABLE settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );
        CREATE TABLE coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL DEFAULT 'percent',
            value INTEGER NOT NULL DEFAULT 0,
            max_uses INTEGER,
            used_count INTEGER NOT NULL DEFAULT 0,
            min_order_amount INTEGER NOT NULL DEFAULT 0,
            starts_at TEXT,
            expires_at TEXT,
            per_user_limit INTEGER,
            description TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE custom_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            quantity INTEGER NOT NULL DEFAULT 1,
            file TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            admin_note TEXT,
            price INTEGER,
            product_id INTEGER,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            comment TEXT,
            created_at TEXT DEFAULT (datetime('now','localtime')),
            UNIQUE (product_id, user_id)
        );
        CREATE TABLE points_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'manual',
            description TEXT,
            ref_id INTEGER,
            created_by INTEGER,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            subject TEXT,
            body TEXT NOT NULL,
            from_admin INTEGER NOT NULL DEFAULT 0,
            is_read INTEGER NOT NULL DEFAULT 0,
            is_closed INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );
    ");

    // کاربر مدیر پیش‌فرض: admin / admin123
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')");
    $st->execute(['admin', $hash, 'مدیر فروشگاه', 'admin@example.com']);

    // دسته‌بندی‌ها
    $cats = [
        ['ابزار دستی', 'hand-tools', 'آچار، پیچ‌گوشتی، انبردست و ابزار دستی'],
        ['ابزار برقی', 'power-tools', 'دریل، فرز، اره و ابزار برقی صنعتی'],
        ['تجهیزات ایمنی', 'safety', 'دستکش، عینک، کلاه و تجهیزات حفاظت فردی'],
        ['اندازه‌گیری', 'measurement', 'کولیس، میکرومتر، تراز و ابزار دقیق'],
    ];
    $st = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
    foreach ($cats as $c) {
        $st->execute($c);
    }

    // محصولات نمونه (قیمت به تومان)
    $products = [
        ['دریل شارژی ۱۸ ولت', 'قدرت بالا با باتری لیتیومی، مناسب کارگاه و مصارف صنعتی.', 4500000, 2, 12, 1],
        ['ست آچار تخت و رینگی ۲۴ تکه', 'آلیاژ کروم-وانادیوم، مقاوم در برابر زنگ‌زدگی.', 2850000, 1, 30, 1],
        ['دستگاه جوش اینورتر ۲۰۰ آمپر', 'جوشکاری نرم و پایدار با قابلیت حمل آسان.', 9800000, 2, 6, 1],
        ['کولیس دیجیتال ۱۵۰ میلی‌متر', 'دقت ۰.۰۱ میلی‌متر با نمایشگر دیجیتال.', 1450000, 4, 25, 0],
        ['دستکش ایمنی ضد برش', 'پوشش نیتریل، مناسب کار با فلز و شیشه.', 380000, 3, 100, 0],
        ['عینک ایمنی شفاف', 'مقاوم در برابر ضربه و اشعه UV.', 190000, 3, 150, 0],
        ['فرز سنگ‌بری ۱۸۰ میلی‌متر', 'موتور پرقدرت برای برش فلز و سنگ.', 3700000, 2, 8, 0],
        ['میکرومتر خارج‌سنج ۰-۲۵', 'دقت ۰.۰۱ میلی‌متر، بدنهٔ ضدسایش.', 2100000, 4, 15, 0],
        ['پیچ‌گوشتی ست ۸ تکه', 'دستهٔ ارگونومیک و سری مغناطیسی.', 640000, 1, 60, 0],
        ['تراز لیزری سه‌بعدی', 'خطوط لیزری دقیق برای ترازکاری سطوح.', 5200000, 4, 5, 1],
    ];
    $st = $pdo->prepare("INSERT INTO products (name, description, price, category_id, stock, featured) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $st->execute($p);
    }

    // تنظیمات پیش‌فرض
    $settings = [
        'store_name'    => STORE_NAME,
        'store_tagline' => STORE_TAGLINE,
        'merchant_id'   => ZARINPAL_MERCHANT,
        'payment_mode'  => PAYMENT_MODE,
        'shipping_note' => 'ارسال به سراسر کشور از طریق باربری و پست. هزینهٔ ارسال پس از ثبت سفارش اعلام می‌شود.',
        'shipping_flat_rate' => '0',
        'global_discount'    => '0',
    ];
    $st = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $st->execute([$k, $v]);
    }
}

/**
 * درج یک تنظیم فقط اگر وجود نداشته باشد (بدون بازنویسی مقدار موجود).
 */
function ensure_setting(PDO $pdo, $key, $value)
{
    $st = $pdo->prepare("SELECT COUNT(*) AS c FROM settings WHERE key = ?");
    $st->execute([$key]);
    if ((int)$st->fetch(PDO::FETCH_COLUMN) === 0) {
        $ins = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        $ins->execute([$key, $value]);
    }
}
