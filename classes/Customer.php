<?php

declare(strict_types=1);

/**
 * classes/Customer.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: مدیریت رکوردهای CRM مشتریان.
 *
 * در فاز ۱ (Guest Checkout)، این کلاس دیگر به سیستم احراز هویت وابسته
 * نیست. اطلاعات مشتری به‌صورت اختیاری از فرم checkout دریافت می‌شود.
 * -----------------------------------------------------------------------
 */

class Customer
{
    /**
     * پیدا کردن یا ایجاد یک رکورد مشتری بر اساس شماره تماس.
     * اگر شماره از قبل وجود داشته باشد، نام به‌روزرسانی می‌شود.
     *
     * @param string      $phone     شماره تماس (الزامی)
     * @param string|null $firstName نام (اختیاری)
     * @param string|null $lastName  نام‌خانوادگی (اختیاری)
     * @param string      $source    منبع ثبت (پیش‌فرض: 'checkout')
     * @return int شناسهٔ رکورد (جدید یا موجود)
     * @throws RuntimeException اگر شماره نامعتبر باشد
     */
    public static function findOrCreate(string $phone, ?string $firstName = null, ?string $lastName = null, string $source = 'checkout'): int
    {
        $phone = self::normalizePhone($phone);

        if ($phone === '' || !self::isValidPhone($phone)) {
            throw new RuntimeException('شمارهٔ موبایل واردشده معتبر نیست.');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
        $row  = $stmt->fetch();

        if ($row) {
            if ($firstName !== null || $lastName !== null) {
                $pdo->prepare('UPDATE customers SET first_name = :fn, last_name = :ln WHERE id = :id')
                    ->execute(['fn' => $firstName ?: null, 'ln' => $lastName ?: null, 'id' => (int) $row['id']]);
            }
            return (int) $row['id'];
        }

        $pdo->prepare('INSERT INTO customers (first_name, last_name, phone, source) VALUES (:fn, :ln, :phone, :source)')
            ->execute(['fn' => $firstName ?: null, 'ln' => $lastName ?: null, 'phone' => $phone, 'source' => $source]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * ثبت یک مشتری جدید (برای پاپ‌آپ ایونت یا منابع دیگر).
     * اگر شماره از قبل ثبت شده باشد، فقط نامش به‌روزرسانی می‌شود.
     *
     * @param string      $phone     شماره تماس
     * @param string|null $firstName نام
     * @param string|null $lastName  نام‌خانوادگی
     * @param string      $source    منبع ثبت (پیش‌فرض: 'popup')
     * @return int شناسهٔ رکورد (جدید یا موجود)
     * @throws RuntimeException اگر شماره نامعتبر باشد یا الزامی بودن رعایت نشود
     */
    public static function register(string $phone, ?string $firstName = null, ?string $lastName = null, string $source = 'popup'): int
    {
        $phone = self::normalizePhone($phone);

        if ($phone === '') {
            if (Setting::getBool('phone_required', true)) {
                throw new RuntimeException('شمارهٔ موبایل الزامی است.');
            }
        } elseif (!self::isValidPhone($phone)) {
            throw new RuntimeException('شمارهٔ موبایل واردشده معتبر نیست.');
        }

        $pdo = Database::getConnection();

        if ($phone !== '') {
            $existing = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
            $existing->execute(['phone' => $phone]);
            $row = $existing->fetch();

            if ($row) {
                if ($firstName !== null || $lastName !== null) {
                    $pdo->prepare('UPDATE customers SET first_name = :fn, last_name = :ln WHERE id = :id')
                        ->execute(['fn' => $firstName ?: null, 'ln' => $lastName ?: null, 'id' => (int) $row['id']]);
                }
                return (int) $row['id'];
            }
        }

        $pdo->prepare('INSERT INTO customers (first_name, last_name, phone, source) VALUES (:fn, :ln, :phone, :source)')
            ->execute(['fn' => $firstName ?: null, 'ln' => $lastName ?: null, 'phone' => $phone !== '' ? $phone : null, 'source' => $source]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * اعتبارسنجی سادهٔ شمارهٔ موبایل ایران (۰۹xxxxxxxxx).
     *
     * @param string $phone شمارهٔ ورودی
     * @return bool
     */
    public static function isValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^09[0-9]{9}$/', self::normalizePhone($phone));
    }

    /**
     * نرمال‌سازی شماره موبایل (تبدیل ارقام فارسی/عربی، حذف کد کشور).
     *
     * @param string $phone ورودی خام
     * @return string شماره نرمال‌شده یا رشتهٔ خالی
     */
    public static function normalizePhone(string $phone): string
    {
        $map    = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
        $digits = preg_replace('/\D/u', '', strtr(trim($phone), $map)) ?? '';

        if ($digits === '') { return ''; }
        if (str_starts_with($digits, '989') && mb_strlen($digits) === 12) { return '0' . substr($digits, 2); }
        if (str_starts_with($digits, '98')  && mb_strlen($digits) === 11) { return '0' . substr($digits, 2); }
        if (str_starts_with($digits, '9')   && mb_strlen($digits) === 10) { return '0' . $digits; }

        return $digits;
    }

    /**
     * لیست همهٔ مشتریان، جدیدترین‌ها اول (برای پنل ادمین).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::getConnection()
            ->query('SELECT * FROM customers ORDER BY created_at DESC')
            ->fetchAll();
    }

    /**
     * پیدا کردن رکورد مشتری بر اساس شناسه.
     *
     * @param int $id شناسهٔ مشتری
     * @return array|null آرایهٔ اطلاعات مشتری یا null اگر یافت نشد
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * تعداد کل مشتریان ثبت‌شده.
     *
     * @return int
     */
    public static function count(): int
    {
        return (int) Database::getConnection()
            ->query('SELECT COUNT(*) FROM customers')
            ->fetchColumn();
    }

    /**
     * حذف یک مشتری از لیست CRM.
     * اگر این مشتری دارای سفارش باشد، حذف انجام نمی‌شود.
     *
     * @param int $id شناسهٔ مشتری
     * @return bool true در صورت موفقیت
     */
    public static function delete(int $id): bool
    {
        $pdo   = Database::getConnection();
        $check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = :id');
        $check->execute(['id' => $id]);

        if ((int) $check->fetchColumn() > 0) {
            return false;
        }

        return (bool) $pdo->prepare('DELETE FROM customers WHERE id = :id')->execute(['id' => $id]);
    }
}
