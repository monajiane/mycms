-- =====================================================================
-- sql/schema.sql — اسکیمای MySQL برای استقرار روی هاست واقعی
-- ابزارسازی شرق
-- اجرا: phpMyAdmin → import این فایل، یا از طریق mysql CLI
-- =====================================================================

CREATE DATABASE IF NOT EXISTS abzarsazi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abzarsazi;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(120),
    email VARCHAR(120),
    phone VARCHAR(20),
    points INT NOT NULL DEFAULT 0,
    role VARCHAR(20) NOT NULL DEFAULT 'customer',
    referral_code VARCHAR(16) NULL UNIQUE,
    referred_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_refcode (referral_code),
    INDEX idx_users_referred (referred_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) UNIQUE,
    description TEXT,
    image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220),
    description TEXT,
    price INT NOT NULL DEFAULT 0,
    category_id INT,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    featured TINYINT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    owner_user_id INT NULL,
    sale_price INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(80),
    price_delta INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_variants_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280),
    excerpt TEXT,
    content MEDIUMTEXT,
    image VARCHAR(255),
    video VARCHAR(255),
    published TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20),
    customer_email VARCHAR(120),
    address TEXT,
    postal_code VARCHAR(20),
    subtotal INT NOT NULL DEFAULT 0,
    shipping_amount INT NOT NULL DEFAULT 0,
    discount_amount INT NOT NULL DEFAULT 0,
    coupon_code VARCHAR(60),
    points_redeemed INT NOT NULL DEFAULT 0,
    total_amount INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    authority VARCHAR(64),
    ref_id VARCHAR(64),
    payment_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    price INT NOT NULL,
    quantity INT NOT NULL,
    variant_id INT NULL,
    variant_name VARCHAR(150),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    `key` VARCHAR(60) PRIMARY KEY,
    `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'percent',
    value INT NOT NULL DEFAULT 0,
    max_uses INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    min_order_amount INT NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    per_user_limit INT NULL,
    description TEXT,
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE custom_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 1,
    file VARCHAR(255),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT,
    price INT NULL,
    product_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cr_user (user_id),
    INDEX idx_cr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'approved',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (product_id, user_id),
    INDEX idx_review_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE points_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    balance_after INT NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'manual',
    description VARCHAR(255),
    ref_id INT NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pt_user (user_id),
    UNIQUE KEY ux_points_ref (ref_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sms_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    event VARCHAR(30) NOT NULL DEFAULT 'custom',
    status VARCHAR(20) NOT NULL DEFAULT 'sent',
    provider_note VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_phone (phone),
    INDEX idx_sms_event (event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    invitee_id INT NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'registered',
    points_awarded INT NOT NULL DEFAULT 0,
    ref_order_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    rewarded_at DATETIME NULL,
    INDEX idx_ref_referrer (referrer_id),
    CONSTRAINT fk_ref_invitee UNIQUE (invitee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    parent_id INT NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    from_admin TINYINT NOT NULL DEFAULT 0,
    is_read TINYINT NOT NULL DEFAULT 0,
    is_closed TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_user (user_id, parent_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(190) NOT NULL,
    channel VARCHAR(10) NOT NULL DEFAULT 'sms',
    code VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_identifier (identifier, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'processing',
    label VARCHAR(100),
    note VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tracking_order (order_id, created_at),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    phone VARCHAR(30),
    email VARCHAR(190),
    notified TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stocknotif_product (product_id, notified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    day DATE NOT NULL,
    uri VARCHAR(255),
    country VARCHAR(2),
    source VARCHAR(30),
    device VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_visits_ip_day (ip, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ایندکس‌های کارایی
ALTER TABLE products      ADD INDEX idx_products_active_featured (active, featured, id);
ALTER TABLE products      ADD INDEX idx_products_category (category_id, active);
ALTER TABLE products      ADD INDEX idx_products_owner (owner_user_id);
ALTER TABLE product_images ADD INDEX idx_product_images_product (product_id, sort_order);
ALTER TABLE orders        ADD INDEX idx_orders_user (user_id, id);
ALTER TABLE orders        ADD INDEX idx_orders_status (status);
ALTER TABLE order_items   ADD INDEX idx_order_items_order (order_id);
ALTER TABLE articles      ADD INDEX idx_articles_published (published, id);

-- ---- دادهٔ اولیه ----
-- مدیر پیش‌فرض: admin / admin123
-- (هش bcrypt واقعی؛ پس از اولین ورود رمز را تغییر دهید)
INSERT INTO users (username, password, full_name, email, role)
VALUES ('admin', '$2y$10$KALoNlDjd3a5JUoc3qf.w.4ce3X/zHlNDScTmMKcizdHd1jyOCZwK', 'مدیر فروشگاه', 'admin@example.com', 'admin');

INSERT INTO categories (name, slug, description) VALUES
('ابزار دستی', 'hand-tools', 'آچار، پیچ‌گوشتی، انبردست و ابزار دستی'),
('ابزار برقی', 'power-tools', 'دریل، فرز، اره و ابزار برقی صنعتی'),
('تجهیزات ایمنی', 'safety', 'دستکش، عینک، کلاه و تجهیزات حفاظت فردی'),
('اندازه‌گیری', 'measurement', 'کولیس، میکرومتر، تراز و ابزار دقیق');

INSERT INTO products (name, description, price, category_id, stock, featured) VALUES
('دریل شارژی ۱۸ ولت', 'قدرت بالا با باتری لیتیومی، مناسب کارگاه و مصارف صنعتی.', 4500000, 2, 12, 1),
('ست آچار تخت و رینگی ۲۴ تکه', 'آلیاژ کروم-وانادیوم، مقاوم در برابر زنگ‌زدگی.', 2850000, 1, 30, 1),
('دستگاه جوش اینورتر ۲۰۰ آمپر', 'جوشکاری نرم و پایدار با قابلیت حمل آسان.', 9800000, 2, 6, 1),
('کولیس دیجیتال ۱۵۰ میلی‌متر', 'دقت ۰.۰۱ میلی‌متر با نمایشگر دیجیتال.', 1450000, 4, 25, 0),
('دستکش ایمنی ضد برش', 'پوشش نیتریل، مناسب کار با فلز و شیشه.', 380000, 3, 100, 0),
('عینک ایمنی شفاف', 'مقاوم در برابر ضربه و اشعه UV.', 190000, 3, 150, 0),
('فرز سنگ‌بری ۱۸۰ میلی‌متر', 'موتور پرقدرت برای برش فلز و سنگ.', 3700000, 2, 8, 0),
('میکرومتر خارج‌سنج ۰-۲۵', 'دقت ۰.۰۱ میلی‌متر، بدنهٔ ضدسایش.', 2100000, 4, 15, 0),
('پیچ‌گوشتی ست ۸ تکه', 'دستهٔ ارگونومیک و سری مغناطیسی.', 640000, 1, 60, 0),
('تراز لیزری سه‌بعدی', 'خطوط لیزری دقیق برای ترازکاری سطوح.', 5200000, 4, 5, 1);

INSERT INTO settings (`key`, `value`) VALUES
('store_name', 'ابزارسازی شرق'),
('store_tagline', 'ابزار دقیق، صنعت مطمئن'),
('merchant_id', ''),
('payment_mode', 'sandbox'),
('shipping_note', 'ارسال به سراسر کشور از طریق باربری و پست. هزینهٔ ارسال پس از ثبت سفارش اعلام می‌شود.'),
('referral_reward_points', '100'),
('referral_invitee_bonus', '0'),
('shipping_flat_rate', '0'),
('global_discount', '0');
