<?php
/**
 * classes/Setting.php
 * -----------------------------------------------------------------------
 * یک سیستم تنظیمات عمومی و ساده به‌صورت Key/Value، روی جدول `settings`.
 * هدف: به‌جای ساختن یک سیستم تنظیمات جدید برای هر قابلیت (مثل الزامی/
 * اختیاری بودن شماره تماس)، همه از همین یک کلاس استفاده کنند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Setting
{
    /**
     * خواندن مقدار یک تنظیم؛ اگر وجود نداشت، مقدار پیش‌فرض برگردانده می‌شود.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : $default;
    }

    /**
     * خواندن یک تنظیم بولی («1»/«0» در دیتابیس) به‌صورت true/false واقعی.
     */
    public static function getBool(string $key, bool $default = true): bool
    {
        $value = self::get($key);
        return $value === null ? $default : $value === '1';
    }

    /**
     * ذخیرهٔ (یا به‌روزرسانی) مقدار یک تنظیم.
     */
    public static function set(string $key, string $value): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value'
        );
        return $stmt->execute(['key' => $key, 'value' => $value]);
    }
}
