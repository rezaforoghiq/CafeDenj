<?php
/**
 * classes/Customer.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: نگهداری شمارهٔ مشتریانی که از طریق فرم پاپ‌آپ ایونت
 * (یا هر منبع دیگری در آینده) ثبت می‌شوند، و نمایش آن‌ها در پنل ادمین.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Customer
{
    /**
     * ثبت یک مشتری جدید. اگر شماره از قبل ثبت شده باشد، فقط نامش
     * به‌روزرسانی می‌شود (در صورت ارسال نام تازه) تا خطای Duplicate ندهد.
     *
     * الزامی یا اختیاری بودن شماره از تنظیمات (Setting::getBool('phone_required'))
     * خوانده می‌شود؛ این تابع خودش هیچ فرضی دربارهٔ آن ندارد.
     *
     * @return int شناسهٔ رکورد (جدید یا موجود)
     * @throws RuntimeException اگر شماره نامعتبر باشد (یا الزامی باشد و خالی ارسال شود)
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
            $existing = $pdo->prepare('SELECT id, phone FROM customers WHERE phone IS NOT NULL');
            $existing->execute();
            while ($row = $existing->fetch()) {
                if (self::normalizePhone((string) ($row['phone'] ?? '')) === $phone) {
                    if ($firstName !== null || $lastName !== null) {
                        $update = $pdo->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name WHERE id = :id');
                        $update->execute(['first_name' => $firstName ?: null, 'last_name' => $lastName ?: null, 'id' => $row['id']]);
                    }
                    return (int) $row['id'];
                }
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO customers (first_name, last_name, phone, source) VALUES (:first_name, :last_name, :phone, :source)'
        );
        $stmt->execute([
            'first_name' => $firstName ?: null,
            'last_name'  => $lastName ?: null,
            'phone'      => $phone !== '' ? $phone : null,
            'source'     => $source,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * اعتبارسنجی سادهٔ شمارهٔ موبایل ایران (۰۹xxxxxxxxx) — در صورت نیاز
     * به پذیرفتن شماره‌های دیگر کشورها، این متد را تغییر دهید.
     */
    public static function isValidPhone(string $phone): bool
    {
        $normalized = self::normalizePhone($phone);
        return (bool) preg_match('/^09[0-9]{9}$/', $normalized);
    }

    private static function normalizePhone(string $phone): string
    {
        $normalized = strtr(trim($phone), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $digits = preg_replace('/\D/u', '', $normalized) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '989') && mb_strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '98') && mb_strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && mb_strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * لیست همهٔ مشتریان، جدیدترین‌ها اول (برای پنل ادمین).
     */
    public static function all(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query(
            'SELECT customers.*, customer_accounts.id AS account_id
             FROM customers
             LEFT JOIN customer_accounts ON customer_accounts.customer_id = customers.id
             ORDER BY customers.created_at DESC'
        )->fetchAll();
    }

    /**
     * تعداد کل مشتریان ثبت‌شده.
     */
    public static function count(): int
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    /**
     * حذف یک مشتری از لیست.
     * اگر این مشتری سفارش ثبت کرده باشد، حذف انجام نمی‌شود و false برگردانده می‌شود.
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        // جلوگیری از خطای کلید خارجی: اگر سفارش مرتبط وجود داشته باشد، حذف نشود
        $check = $pdo->prepare('SELECT COUNT(*) c FROM orders WHERE customer_id = :id');
        $check->execute(['id' => $id]);
        $count = (int) $check->fetchColumn();
        if ($count > 0) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function applyDiscountToAmount(float $amount, array $discount): float
    {
        $value = (float) $discount['discount_value'];

        $final = $discount['discount_type'] === 'percentage'
            ? $amount - ($amount * $value / 100)
            : $amount - $value;

        return max(0, round($final));
    }
}
