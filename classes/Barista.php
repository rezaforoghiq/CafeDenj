<?php
/**
 * classes/Barista.php
 * -----------------------------------------------------------------------
 * مدیریت باریستاها (CRUD) + گزارش عملکرد هر باریستا.
 * روی جدول‌های موجود baristas و orders کار می‌کند؛ جدول تازه‌ای اضافه نمی‌کند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/CustomerAuth.php';

class Barista
{
    public static function all(): array
    {
        return Database::getConnection()->query('SELECT * FROM baristas ORDER BY status = "active" DESC, full_name ASC')->fetchAll();
    }

    public static function activeOnly(): array
    {
        return Database::getConnection()->query("SELECT id, full_name FROM baristas WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $s = Database::getConnection()->prepare('SELECT * FROM baristas WHERE id = :id');
        $s->execute(['id' => $id]);
        $row = $s->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $s = Database::getConnection()->prepare('SELECT * FROM baristas WHERE username = :u');
        $s->execute(['u' => $username]);
        $row = $s->fetch();
        return $row ?: null;
    }

    /** @return array<string,string> خطاهای اعتبارسنجی (خالی یعنی معتبر) */
    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        if (mb_strlen(trim($data['full_name'] ?? '')) < 3) {
            $errors['full_name'] = 'نام و نام خانوادگی را کامل وارد کنید.';
        }
        if (!preg_match('/^09[0-9]{9}$/', CustomerAuth::normalizePhone(trim($data['phone'] ?? '')))) {
            $errors['phone'] = 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', trim($data['username'] ?? ''))) {
            $errors['username'] = 'نام کاربری باید ۳ تا ۵۰ کاراکتر انگلیسی/عدد باشد.';
        } else {
            $s = Database::getConnection()->prepare('SELECT id FROM baristas WHERE username = :u' . ($ignoreId ? ' AND id != :id' : ''));
            $params = ['u' => trim($data['username'])];
            if ($ignoreId) {
                $params['id'] = $ignoreId;
            }
            $s->execute($params);
            if ($s->fetch()) {
                $errors['username'] = 'این نام کاربری قبلاً ثبت شده است.';
            }
        }
        // رمز عبور فقط هنگام ایجاد اجباری است؛ هنگام ویرایش اختیاری (خالی = بدون تغییر)
        if (!$ignoreId && strlen((string) ($data['password'] ?? '')) < 6) {
            $errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        } elseif ($ignoreId && !empty($data['password']) && strlen((string) $data['password']) < 6) {
            $errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        }

        return $errors;
    }

    public static function create(array $data): int
    {
        $s = Database::getConnection()->prepare(
            'INSERT INTO baristas (full_name, phone, username, password_hash, status) VALUES (:n, :p, :u, :h, :st)'
        );
        $s->execute([
            'n'  => trim($data['full_name']),
            'p'  => CustomerAuth::normalizePhone(trim($data['phone'])),
            'u'  => trim($data['username']),
            'h'  => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'st' => ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE baristas SET full_name = :n, phone = :p, username = :u, status = :st';
        $params = [
            'n'  => trim($data['full_name']),
            'p'  => CustomerAuth::normalizePhone(trim($data['phone'])),
            'u'  => trim($data['username']),
            'st' => ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'id' => $id,
        ];
        if (!empty($data['password'])) {
            $sql .= ', password_hash = :h';
            $params['h'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';
        Database::getConnection()->prepare($sql)->execute($params);
    }

    public static function toggleStatus(int $id): void
    {
        Database::getConnection()->prepare(
            "UPDATE baristas SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM baristas WHERE id = :id')->execute(['id' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::getConnection()->prepare('UPDATE baristas SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * بازهٔ تاریخ بر اساس کلید فیلتر: today / week / month / custom
     * @return array{0:?string,1:?string} [from, to] به فرمت Y-m-d یا null برای «همه»
     */
    public static function resolveRange(string $range, ?string $from = null, ?string $to = null): array
    {
        $today = date('Y-m-d');
        switch ($range) {
            case 'today':
                return [$today, $today];
            case 'week':
                return [date('Y-m-d', strtotime('-6 days')), $today];
            case 'month':
                return [date('Y-m-d', strtotime('-29 days')), $today];
            case 'custom':
                return [$from ?: null, $to ?: null];
            default:
                return [null, null];
        }
    }

    /**
     * گزارش عملکرد یک باریستا در بازهٔ داده‌شده.
     * تعداد اختصاص‌یافته، تکمیل‌شده، مجموع مبلغ، میانگین، آخرین سفارش، آخرین فعالیت
     */
    public static function performance(int $baristaId, ?string $from = null, ?string $to = null): array
    {
        $where = ['barista_id = :id'];
        $params = ['id' => $baristaId];
        if ($from) {
            $where[] = 'created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = 'created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) assigned,
                    SUM(status = 'completed') completed,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END), 0) AS total_amount,
                    COALESCE(AVG(CASE WHEN status = 'completed' THEN total_price END), 0) AS avg_amount,
                    MAX(CASE WHEN status = 'completed' THEN created_at END) AS last_order_at
             FROM orders WHERE $whereSql"
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $lastActivity = Database::getConnection()->prepare(
            'SELECT created_at FROM activity_logs WHERE role = "barista" AND user_id = :id ORDER BY created_at DESC LIMIT 1'
        );
        $lastActivity->execute(['id' => $baristaId]);
        $lastActivityRow = $lastActivity->fetch();

        return [
            'assigned'      => (int) ($row['assigned'] ?? 0),
            'completed'     => (int) ($row['completed'] ?? 0),
            'total_amount'  => (float) ($row['total_amount'] ?? 0),
            'avg_amount'    => (float) ($row['avg_amount'] ?? 0),
            'last_order_at' => $row['last_order_at'] ?? null,
            'last_activity' => $lastActivityRow['created_at'] ?? null,
        ];
    }

    /** گزارش عملکرد همهٔ باریستاها در یک بازه (برای جدول گزارش) */
    public static function performanceReport(?string $from = null, ?string $to = null): array
    {
        $out = [];
        foreach (self::all() as $barista) {
            $out[] = ['barista' => $barista, 'stats' => self::performance((int) $barista['id'], $from, $to)];
        }
        return $out;
    }

    /** فعال‌ترین باریستا در بازه (بر اساس تعداد سفارش تکمیل‌شده) — برای داشبورد */
    public static function mostActive(?string $from = null, ?string $to = null): ?array
    {
        $best = null;
        foreach (self::performanceReport($from, $to) as $entry) {
            if ($best === null || $entry['stats']['completed'] > $best['stats']['completed']) {
                $best = $entry;
            }
        }
        return $best && $best['stats']['completed'] > 0 ? $best : null;
    }
}
