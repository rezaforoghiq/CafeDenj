<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------
 * اتصال به دیتابیس MySQL از طریق PDO — الگوی Singleton.
 *
 * نکته: این فایل هیچ مقدار ثابتی (host/db/user/pass) را خودش تعریف
 * نمی‌کند؛ همهٔ این مقادیر از ثابت‌های DB_HOST / DB_NAME / DB_USER /
 * DB_PASS / DB_CHARSET که در config/config.php از روی فایل .env ساخته
 * می‌شوند خوانده می‌شود. برای تغییر اطلاعات اتصال، فقط فایل .env را
 * ویرایش کنید — نیازی به دست‌زدن به این فایل نیست.
 *
 * قبل از استفاده از این کلاس، حتماً باید config/config.php یک‌بار
 * require شده باشد (معمولاً از طریق includes/auth.php برای صفحات ادمین،
 * یا مستقیماً در ابتدای public_html/index.php و public_html/subscribe.php).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Database
{
    /** @var PDO|null نمونهٔ تکی (Singleton) اتصال */
    private static ?PDO $instance = null;

    /**
     * جلوگیری از ساخت نمونه از بیرون کلاس (الگوی Singleton).
     */
    private function __construct()
    {
    }

    /**
     * برگرداندن اتصال PDO فعال. اگر اتصالی وجود نداشته باشد، آن را می‌سازد.
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            if (!defined('DB_HOST')) {
                die('تنظیمات دیتابیس بارگذاری نشده است. ابتدا config/config.php را require کنید.');
            }

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                // خطاها به‌صورت Exception پرتاب شوند (مدیریت خطای تمیزتر)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // نتایج به‌صورت آرایهٔ انجمنی برگردانده شوند
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // از Prepared Statement واقعی MySQL استفاده شود (نه شبیه‌سازی)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // در محیط Production جزئیات خطا هرگز مستقیم به کاربر نشان داده نشود
                error_log('Database connection error: ' . $e->getMessage());

                if (defined('APP_DEBUG') && APP_DEBUG) {
                    die('خطا در اتصال به دیتابیس: ' . $e->getMessage());
                }

                die('خطا در اتصال به پایگاه‌داده. لطفاً بعداً دوباره تلاش کنید.');
            }
        }

        return self::$instance;
    }

    /**
     * جلوگیری از کلون شدن نمونه (حفظ الگوی Singleton).
     */
    private function __clone()
    {
    }
}
