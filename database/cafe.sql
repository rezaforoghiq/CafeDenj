-- =========================================================================
-- database/cafe.sql
-- ساختار کامل پایگاه‌دادهٔ «کافه دنج» — تنها فایلی که باید Import کنید.
-- شامل تمام جدول‌ها (محصولات، سفارش‌ها، باریستا، لاگ فعالیت و ...) است؛
-- دیگر نیازی به اجرای migration جداگانه نیست.
-- Engine: InnoDB | Charset: utf8mb4 (پشتیبانی کامل از فارسی)
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- جدول دسته‌بندی‌ها
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,           -- معادل data-cat در HTML (coffee, dessert, ...)
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- ترتیب نمایش دسته‌ها در نوار دسته‌بندی
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول محصولات (شامل ستون‌های تخفیف)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `price`       DECIMAL(10,0) UNSIGNED NOT NULL,   -- تومان، بدون اعشار
  `image`       VARCHAR(255) DEFAULT NULL,          -- نام فایل داخل /public_html/uploads
  `badge`       VARCHAR(50)  DEFAULT NULL,           -- مثل «پرفروش» / «خانگی» (اختیاری)
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `discount_enabled`   TINYINT(1) NOT NULL DEFAULT 0,
  `discount_type`      ENUM('percentage','fixed') NULL DEFAULT NULL,
  `discount_value`     DECIMAL(10,2) NULL DEFAULT NULL,
  `discount_starts_at` DATE NULL DEFAULT NULL,
  `discount_ends_at`   DATE NULL DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول مدیران (ادمین‌های پنل)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(50) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,   -- هش شده با password_hash() الگوریتم BCRYPT/ARGON2ID
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول مشتریان — شماره‌هایی که از طریق فرم پاپ‌آپ ایونت یا ثبت‌نام ثبت می‌شوند
-- (phone می‌تواند خالی بماند، بسته به تنظیم phone_required)
-- اکنون نام و نام خانوادگی به‌صورت جداگانه ذخیره می‌شوند.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name`  VARCHAR(100) DEFAULT NULL,
  `phone`      VARCHAR(20) NULL DEFAULT NULL,
  `source`     VARCHAR(50) NOT NULL DEFAULT 'popup',  -- از کجا ثبت شده (مثلاً popup, manual)
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- اطلاعات ورود مشتری (جدا از customers نگه داشته می‌شود)
-- اکنون حساب مشتری شامل نام کاربری نیست؛ ورود بر اساس شمارهٔ موبایل انجام می‌شود.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_accounts` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`   INT UNSIGNED NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customer_accounts_customer` (`customer_id`),
  CONSTRAINT `fk_customer_accounts_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- کوپن‌های تخفیف (کدهای قابل استفاده در صفحهٔ پرداخت)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`           VARCHAR(64) NOT NULL,
  `percent`        INT NOT NULL,
  `expires_at`     DATE NULL DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- افزودن اطلاعات کوپن به سفارش‌ها (در صورتی که مشتری از کوپن استفاده کند)
-- -------------------------------------------------------------------------
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `coupon_code` VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `coupon_percent` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0;

-- -------------------------------------------------------------------------
-- تنظیمات عمومی سایت (Key/Value)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول ایونت‌ها/اطلاعیه‌ها — همیشه حداکثر یکی «فعال» است و همان یکی
-- به‌صورت پاپ‌آپ به بازدیدکنندگان سایت نمایش داده می‌شود
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(150) NOT NULL,
  `content`        TEXT NOT NULL,
  `collect_phone`  TINYINT(1) NOT NULL DEFAULT 1,   -- آیا فرم شمارهٔ تماس هم نمایش داده شود؟
  `is_active`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول باریستاها
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `baristas` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name`      VARCHAR(150) NOT NULL,
  `phone`          VARCHAR(20)  NOT NULL,
  `username`       VARCHAR(50)  NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at`  DATETIME NULL DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_baristas_username` (`username`),
  KEY `idx_baristas_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- سبد خرید هر مشتری
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cart_customer_product` (`customer_id`, `product_id`),
  CONSTRAINT `fk_carts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carts_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- سفارش‌ها (شامل اتصال به باریستا و تاریخ تأیید)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`   INT UNSIGNED NOT NULL,
  `barista_id`    INT UNSIGNED NULL DEFAULT NULL,
  `order_number`  VARCHAR(32) NOT NULL,
  `total_price`   DECIMAL(12,0) UNSIGNED NOT NULL,
  `customer_note` VARCHAR(500) NULL,
  `status`        ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at`   DATETIME NULL DEFAULT NULL,
  `payment_method` ENUM('card','cash','transfer') NULL DEFAULT NULL,
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_barista` (`barista_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_barista`  FOREIGN KEY (`barista_id`)  REFERENCES `baristas`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- اقلام هر سفارش
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`     INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `quantity`     SMALLINT UNSIGNED NOT NULL,
  `price`        DECIMAL(12,0) UNSIGNED NOT NULL,
  CONSTRAINT `fk_order_items_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- لاگ فعالیت سیستم — order_id عمداً بدون FK است تا حتی بعد از حذف سفارش،
-- سابقهٔ لاگ (برای مثال «حذف سفارش») باقی بماند.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NULL DEFAULT NULL,
  `user_label`  VARCHAR(150) NULL DEFAULT NULL,   -- نام/یوزرنیم کاربر برای نمایش سریع
  `role`        ENUM('admin','barista','customer','system') NOT NULL DEFAULT 'system',
  `action`      VARCHAR(60) NOT NULL,             -- کلید عملیات، مثل order_create
  `description` VARCHAR(500) NULL DEFAULT NULL,
  `order_id`    INT UNSIGNED NULL DEFAULT NULL,
  `ip_address`  VARCHAR(45) NULL DEFAULT NULL,
  `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_logs_role` (`role`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_order` (`order_id`),
  KEY `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- -------------------------------------------------------------------------
-- جدول صف چاپ اتوماتیک (print_jobs)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `print_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `order_number` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `payload` LONGTEXT NULL,
  `retry_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `printed_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `uq_print_jobs_order` UNIQUE (`order_id`),
  KEY `idx_print_jobs_status` (`status`),
  KEY `idx_print_jobs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- دادهٔ اولیه (Seed)
-- =========================================================================

INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES
('قهوه', 'coffee', 1),
('دسر', 'dessert', 2),
('نوشیدنی سرد', 'cold', 3),
('صبحانه', 'breakfast', 4),
('چای و دمنوش', 'tea', 5);

INSERT INTO `products`
(`category_id`, `name`, `description`, `price`, `badge`, `status`, `sort_order`) VALUES
(1, 'اسپرسو',   'دان تازه‌آسیاب، عصاره غلیظ و پرکافئین',           45000,  NULL,        'active', 1),
(1, 'کاپوچینو', 'اسپرسو، شیر بخارداده و فوم مخملی',                 65000,  'پرفروش',    'active', 2),
(1, 'لاته',     'طعمی ملایم با نسبت بالای شیر',                     70000,  NULL,        'active', 3),
(3, 'آیس‌لاته',  'قهوه سرد با یخ و شیر سرد',                          75000,  NULL,        'active', 1),
(2, 'تیرامیسو', 'لایه‌های ماسکارپونه و قهوه',                        120000, 'خانگی',     'active', 1),
(2, 'چیزکیک',   'پنیر خامه‌ای روی بیسکوییت کره‌ای',                  110000, NULL,        'active', 2),
(4, 'صبحانه کامل', 'تخم‌مرغ، پنیر، مربا، کره و نان تازه',            185000, NULL,        'active', 1),
(5, 'دمنوش گل‌گاوزبان', 'آرام‌بخش و معطر، سرو با عسل',               40000,  NULL,        'active', 1);

-- نکته امنیتی: رمز نمونهٔ زیر معادل «admin123» است و فقط برای تست محلی است.
-- در محیط Production حتماً یک هش تازه با password_hash() تولید و جایگزین کنید.
-- سه حساب پیش‌فرض تا پنل بلافاصله در دسترس باشد (رمز هر سه: admin123 — پس از اولین ورود تغییر دهید)
INSERT INTO `admins` (`username`, `password`) VALUES
('admin', '$2b$12$1q4H6Gzx4VLE4MrMT45sgODhH3sgLTTg7D6g3oJcaCb4DMTie1huq'),
('admin2', '$2b$12$1q4H6Gzx4VLE4MrMT45sgODhH3sgLTTg7D6g3oJcaCb4DMTie1huq'),
('admin3', '$2b$12$1q4H6Gzx4VLE4MrMT45sgODhH3sgLTTg7D6g3oJcaCb4DMTie1huq');

-- تنظیم پیش‌فرض: شمارهٔ تماس الزامی است (از پنل «تنظیمات» قابل تغییر است)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('phone_required', '1');
