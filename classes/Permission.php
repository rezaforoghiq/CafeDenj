<?php

declare(strict_types=1);

class Permission
{
    public static function ensureSchema(): void
    {
        $pdo = Database::getConnection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `permissions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_permissions_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `barista_permissions` (
                `barista_id` INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`barista_id`, `permission_id`),
                KEY `idx_barista_permissions_permission` (`permission_id`),
                CONSTRAINT `fk_barista_permissions_barista`
                    FOREIGN KEY (`barista_id`) REFERENCES `baristas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_barista_permissions_permission`
                    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci'
        );

        $insert = [];
        foreach (self::all() as $row) {
            $insert[] = sprintf("(%s, %s)", $pdo->quote((string) $row['slug']), $pdo->quote((string) $row['label']));
        }

        if ($insert !== []) {
            $pdo->exec(
                'INSERT IGNORE INTO `permissions` (`slug`, `label`) VALUES ' . implode(', ', $insert)
            );
        }

        $pdo->exec(
            "INSERT IGNORE INTO `barista_permissions` (`barista_id`, `permission_id`)
             SELECT b.id, p.id
             FROM `baristas` b
             JOIN `permissions` p ON p.slug IN ('orders.view', 'orders.view_details', 'orders.update_status', 'orders.print')
             LEFT JOIN `barista_permissions` bp ON bp.barista_id = b.id AND bp.permission_id = p.id
             WHERE bp.barista_id IS NULL"
        );
    }

    public static function all(): array
    {
        return [
            ['slug' => 'orders.view', 'label' => 'مشاهده سفارش‌ها'],
            ['slug' => 'orders.view_details', 'label' => 'مشاهده جزییات سفارش'],
            ['slug' => 'orders.update_status', 'label' => 'تغییر وضعیت سفارش'],
            ['slug' => 'orders.print', 'label' => 'چاپ سفارش'],
            ['slug' => 'reports.view', 'label' => 'مشاهده گزارش‌ها'],
            ['slug' => 'products.view', 'label' => 'مشاهده محصولات'],
            ['slug' => 'products.manage', 'label' => 'مدیریت محصولات'],
            ['slug' => 'users.manage', 'label' => 'مدیریت کاربران'],
            ['slug' => 'settings.view', 'label' => 'مشاهده تنظیمات'],
        ];
    }

    public static function defaultBaristaPermissions(): array
    {
        return ['orders.view', 'orders.view_details', 'orders.update_status', 'orders.print'];
    }

    public static function ensureDefaultsForBarista(int $baristaId): void
    {
        self::ensureSchema();
        $existing = self::getBaristaPermissions($baristaId);
        if ($existing === []) {
            self::saveForBarista($baristaId, self::defaultBaristaPermissions());
        }
    }

    public static function getBaristaPermissions(int $baristaId): array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.slug FROM permissions p INNER JOIN barista_permissions bp ON bp.permission_id = p.id WHERE bp.barista_id = :barista_id ORDER BY p.slug ASC'
        );
        $stmt->execute(['barista_id' => $baristaId]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $out[] = (string) $row['slug'];
        }
        return $out;
    }

    public static function saveForBarista(int $baristaId, array $permissions): void
    {
        self::ensureSchema();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM barista_permissions WHERE barista_id = :barista_id')->execute(['barista_id' => $baristaId]);

            $unique = [];
            foreach ($permissions as $permission) {
                $slug = trim((string) $permission);
                if ($slug !== '') {
                    $unique[$slug] = $slug;
                }
            }

            if ($unique === []) {
                $pdo->commit();
                return;
            }

            $in = implode(',', array_fill(0, count($unique), '?'));
            $stmt = $pdo->prepare('SELECT id, slug FROM permissions WHERE slug IN (' . $in . ')');
            $stmt->execute(array_values($unique));
            $rows = $stmt->fetchAll();

            $ids = [];
            foreach ($rows as $row) {
                $ids[(string) $row['slug']] = (int) $row['id'];
            }

            $insert = $pdo->prepare('INSERT INTO barista_permissions (barista_id, permission_id) VALUES (:barista_id, :permission_id)');
            foreach ($unique as $slug) {
                if (!isset($ids[$slug])) {
                    continue;
                }
                $insert->execute(['barista_id' => $baristaId, 'permission_id' => $ids[$slug]]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function hasForBarista(int $baristaId, string $permission): bool
    {
        self::ensureSchema();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM barista_permissions bp INNER JOIN permissions p ON p.id = bp.permission_id WHERE bp.barista_id = :barista_id AND p.slug = :permission LIMIT 1'
        );
        $stmt->execute(['barista_id' => $baristaId, 'permission' => $permission]);
        return (bool) $stmt->fetch();
    }
}
