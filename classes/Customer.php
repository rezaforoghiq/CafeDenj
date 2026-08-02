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
        $phone = trim($phone);

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
                    $update = $pdo->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name WHERE id = :id');
                    $update->execute(['first_name' => $firstName ?: null, 'last_name' => $lastName ?: null, 'id' => $row['id']]);
                }
                return (int) $row['id'];
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
        return (bool) preg_match('/^09[0-9]{9}$/', $phone);
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
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // ---------------------------------------------------------------
    // تخفیف اختصاصی مشتری
    // -----------------------------------------------------------
    // هر مشتری حداکثر یک تخفیف اختصاصی فعال دارد؛ setDiscount() هر بار
    // رکورد قبلی را جایگزین می‌کند تا مدیریت از پنل ادمین ساده بماند.
    // ---------------------------------------------------------------

    /**
     * تخفیف فعال یک مشتری (اگر وجود داشته باشد و منقضی نشده باشد)، یا null.
     */
    public static function getActiveDiscount(int $customerId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM customer_discounts
             WHERE customer_id = :customer_id AND is_active = 1
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $discount = $stmt->fetch();

        if (!$discount) {
            return null;
        }

        if (!empty($discount['expires_at']) && $discount['expires_at'] < date('Y-m-d')) {
            return null; // منقضی شده — خودکار نادیده گرفته می‌شود
        }

        return $discount;
    }

    /**
     * ثبت/جایگزینی تخفیف اختصاصی یک مشتری.
     *
     * @param array{discount_type: string, discount_value: float, expires_at?: ?string, is_active?: bool} $data
     */
    public static function setDiscount(int $customerId, array $data): void
    {
        $pdo = Database::getConnection();

        $pdo->prepare('DELETE FROM customer_discounts WHERE customer_id = :customer_id')
            ->execute(['customer_id' => $customerId]);

        $stmt = $pdo->prepare(
            'INSERT INTO customer_discounts (customer_id, discount_type, discount_value, expires_at, is_active)
             VALUES (:customer_id, :discount_type, :discount_value, :expires_at, :is_active)'
        );
        $stmt->execute([
            'customer_id'    => $customerId,
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'expires_at'     => $data['expires_at'] ?: null,
            'is_active'      => !empty($data['is_active']) ? 1 : 0,
        ]);
    }

    /**
     * حذف تخفیف اختصاصی یک مشتری.
     */
    public static function removeDiscount(int $customerId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM customer_discounts WHERE customer_id = :customer_id');
        return $stmt->execute(['customer_id' => $customerId]);
    }

    /**
     * اعتبارسنجی سمت سرور دادهٔ فرم تخفیف مشتری.
     *
     * @return array<string,string>
     */
    public static function validateDiscount(array $data): array
    {
        $errors = [];

        $type = $data['discount_type'] ?? '';
        if (!in_array($type, ['percentage', 'fixed'], true)) {
            $errors['discount_type'] = 'نوع تخفیف را انتخاب کنید.';
        }

        $value = $data['discount_value'] ?? '';
        if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
            $errors['discount_value'] = 'مقدار تخفیف باید یک عدد مثبت باشد.';
        } elseif ($type === 'percentage' && (float) $value > 100) {
            $errors['discount_value'] = 'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.';
        }

        return $errors;
    }

    /**
     * محاسبهٔ قیمت نهایی یک مبلغ با توجه به تخفیف مشتری (در صورت وجود).
     * طبق اولویت خواسته‌شده در پروژه: تخفیف مشتری > تخفیف محصول — یعنی
     * اگر مشتری تخفیف اختصاصی فعال داشته باشد، به‌جای تخفیف محصول همین
     * اعمال می‌شود (نه هر دو با هم).
     */
    public static function applyDiscountToAmount(float $amount, array $discount): float
    {
        $value = (float) $discount['discount_value'];

        $final = $discount['discount_type'] === 'percentage'
            ? $amount - ($amount * $value / 100)
            : $amount - $value;

        return max(0, round($final));
    }
}
