<?php
/**
 * classes/ActivityLog.php
 * -----------------------------------------------------------------------
 * ثبت و بازیابی لاگ فعالیت سیستم (ورود/خروج، سفارش‌ها، تغییر باریستا و ...).
 * از معماری فعلی (Database::getConnection()) استفاده می‌کند؛ جدول تازه‌ای
 * غیر از activity_logs اضافه نمی‌شود.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class ActivityLog
{
    public const ACTIONS = [
        'order_create'    => 'ایجاد سفارش',
        'order_delete'    => 'حذف سفارش',
        'order_approve'   => 'تأیید سفارش',
        'order_reject'    => 'رد سفارش',
        'order_status'    => 'تغییر وضعیت سفارش',
        'order_barista'   => 'تغییر باریستا',
        'login'           => 'ورود به سیستم',
        'logout'          => 'خروج از سیستم',
        'barista_create'  => 'ایجاد باریستا',
        'barista_update'  => 'ویرایش باریستا',
        'barista_toggle'  => 'تغییر وضعیت باریستا',
        'barista_delete'  => 'حذف باریستا',
        'admin_create'    => 'ایجاد ادمین',
        'admin_delete'    => 'حذف ادمین',
    ];

    public const ROLES = ['admin' => 'ادمین', 'barista' => 'باریستا', 'customer' => 'مشتری', 'system' => 'سیستم'];

    /** ثبت یک رکورد لاگ. IP و User-Agent و زمان به‌صورت خودکار خوانده می‌شوند. */
    public static function record(string $action, string $role, ?int $userId = null, ?string $userLabel = null, ?int $orderId = null, ?string $description = null): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO activity_logs (user_id, user_label, role, action, description, order_id, ip_address, user_agent)
             VALUES (:user_id, :user_label, :role, :action, :description, :order_id, :ip, :ua)'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'user_label'  => $userLabel,
            'role'        => in_array($role, ['admin', 'barista', 'customer', 'system'], true) ? $role : 'system',
            'action'      => $action,
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'order_id'    => $orderId,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'          => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    }

    /** ساخت شرط WHERE و پارامترها بر اساس فیلترها؛ برای استفادهٔ مشترک در search() و deleteFiltered(). */
    private static function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(user_label LIKE :q1 OR description LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['from'])) {
            $where[] = 'created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        return [$where, $params];
    }

    /**
     * جستجو/فیلتر روی لاگ‌ها.
     * $filters: role, action, q (جستجو در کاربر/توضیحات), from, to (Y-m-d), page, per_page
     */
    public static function search(array $filters = []): array
    {
        [$where, $params] = self::buildWhere($filters);

        $sql = 'SELECT * FROM activity_logs';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 30)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $countStmt = Database::getConnection()->prepare('SELECT COUNT(*) c FROM activity_logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** حذف یک رکورد لاگ بر اساس شناسه. */
    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM activity_logs WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /** حذف چند رکورد لاگ بر اساس آرایه‌ای از شناسه‌ها. تعداد رکوردهای حذف‌شده را برمی‌گرداند. */
    public static function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /** حذف تمام رکوردهایی که با فیلترهای فعلی مطابقت دارند (مثلاً همهٔ نتایج یک جستجو). */
    public static function deleteFiltered(array $filters): int
    {
        [$where, $params] = self::buildWhere($filters);
        $sql = 'DELETE FROM activity_logs';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** حذف کامل تمام لاگ‌ها. تعداد رکوردهای حذف‌شده را برمی‌گرداند. */
    public static function clearAll(): int
    {
        $stmt = Database::getConnection()->query('DELETE FROM activity_logs');
        return $stmt->rowCount();
    }

    /** آخرین N فعالیت سیستم (برای داشبورد) */
    public static function recent(int $limit = 8): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(50, $limit)));
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }
}
