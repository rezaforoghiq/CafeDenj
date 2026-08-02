<?php
/**
 * classes/Event.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: مدیریت «ایونت/اطلاعیه»ای که به‌صورت پاپ‌آپ به
 * بازدیدکنندگان منوی عمومی نمایش داده می‌شود.
 *
 * قانون مهم: در هر لحظه حداکثر یک ایونت می‌تواند «فعال» باشد — چون قرار
 * است فقط یک پاپ‌آپ روی صفحه نمایش داده شود. activate() این قانون را
 * خودش تضمین می‌کند (بقیه را قبل از فعال‌کردن این یکی، غیرفعال می‌کند).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Event
{
    /**
     * ایونتی که هم‌اکنون فعال است (همان چیزی که پاپ‌آپ نشانش می‌دهد)، یا null.
     */
    public static function getActive(): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM events WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * لیست همهٔ ایونت‌ها (برای پنل ادمین)، جدیدترین‌ها اول.
     */
    public static function all(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query('SELECT * FROM events ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * یافتن یک ایونت با شناسه.
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * ساخت ایونت جدید. اگر is_active=1 باشد، بقیهٔ ایونت‌ها خودکار غیرفعال می‌شوند.
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();

        if (!empty($data['is_active'])) {
            self::deactivateAll();
        }

        $stmt = $pdo->prepare(
            'INSERT INTO events (title, content, collect_phone, is_active)
             VALUES (:title, :content, :collect_phone, :is_active)'
        );
        $stmt->execute([
            'title'         => $data['title'],
            'content'       => $data['content'],
            'collect_phone' => !empty($data['collect_phone']) ? 1 : 0,
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * ویرایش یک ایونت موجود. همان قانون یکتا بودن فعال بودن اینجا هم رعایت می‌شود.
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();

        if (!empty($data['is_active'])) {
            self::deactivateAll();
        }

        $stmt = $pdo->prepare(
            'UPDATE events SET title = :title, content = :content,
                collect_phone = :collect_phone, is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            'title'         => $data['title'],
            'content'       => $data['content'],
            'collect_phone' => !empty($data['collect_phone']) ? 1 : 0,
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
            'id'            => $id,
        ]);
    }

    /**
     * حذف یک ایونت.
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * غیرفعال کردن همهٔ ایونت‌ها (قبل از فعال‌کردن یک ایونت تازه).
     */
    private static function deactivateAll(): void
    {
        $pdo = Database::getConnection();
        $pdo->exec('UPDATE events SET is_active = 0');
    }

    /**
     * اعتبارسنجی سمت سرور دادهٔ فرم ایونت.
     *
     * @return array<string,string>
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'عنوان ایونت الزامی است.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'عنوان نباید بیشتر از ۱۵۰ کاراکتر باشد.';
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            $errors['content'] = 'متن ایونت الزامی است.';
        } elseif (mb_strlen($content) > 1000) {
            $errors['content'] = 'متن ایونت نباید بیشتر از ۱۰۰۰ کاراکتر باشد.';
        }

        return $errors;
    }
}
