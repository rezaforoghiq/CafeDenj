<?php
/**
 * classes/Admin.php
 * -----------------------------------------------------------------------
 * مدیریت حساب‌های ادمین (افزودن/حذف) — روی جدول موجود admins کار می‌کند
 * (همان جدولی که Auth.php برای ورود از آن استفاده می‌کند). جدول تازه‌ای
 * اضافه نمی‌شود.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Admin
{
    public static function all(): array
    {
        return Database::getConnection()->query('SELECT id, username, created_at FROM admins ORDER BY id ASC')->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::getConnection()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $s = Database::getConnection()->prepare('SELECT id, username, created_at FROM admins WHERE id = :id');
        $s->execute(['id' => $id]);
        $row = $s->fetch();
        return $row ?: null;
    }

    /** @return array<string,string> خطاهای اعتبارسنجی (خالی یعنی معتبر) */
    public static function validate(array $data): array
    {
        $errors = [];

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', trim($data['username'] ?? ''))) {
            $errors['username'] = 'نام کاربری باید ۳ تا ۵۰ کاراکتر انگلیسی/عدد باشد.';
        } else {
            $s = Database::getConnection()->prepare('SELECT id FROM admins WHERE username = :u');
            $s->execute(['u' => trim($data['username'])]);
            if ($s->fetch()) {
                $errors['username'] = 'این نام کاربری قبلاً ثبت شده است.';
            }
        }

        if (strlen((string) ($data['password'] ?? '')) < 6) {
            $errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        }

        return $errors;
    }

    public static function create(array $data): int
    {
        $s = Database::getConnection()->prepare(
            'INSERT INTO admins (username, password) VALUES (:u, :h)'
        );
        $s->execute([
            'u' => trim($data['username']),
            'h' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM admins WHERE id = :id')->execute(['id' => $id]);
    }
}
