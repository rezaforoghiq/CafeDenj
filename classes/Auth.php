<?php
/**
 * classes/Auth.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس فقط «احراز هویت مدیر» است:
 * - تلاش برای ورود (بررسی یوزرنیم/پسورد در جدول admins)
 * - بررسی اینکه کاربر فعلی لاگین است یا نه
 * - گرفتن اطلاعات مدیر لاگین‌شده
 * - خروج از حساب (Logout)
 *
 * این کلاس هیچ HTML یا ریدایرکتی تولید نمی‌کند — آن مسئولیت به عهدهٔ
 * includes/auth.php (نگهبان صفحات ادمین) و admin/index.php است.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/ActivityLog.php';

class Auth
{
    /** کلیدی که شناسهٔ ادمین لاگین‌شده در سشن با آن ذخیره می‌شود */
    private const SESSION_KEY = 'admin_id';

    /**
     * تلاش برای ورود با یوزرنیم و پسورد.
     * در صورت موفقیت، شناسهٔ ادمین در سشن ذخیره و true برگردانده می‌شود.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return false;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT id, username, password FROM admins WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        // جلوگیری از Session Fixation: بعد از لاگین موفق، شناسهٔ سشن عوض شود
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = (int) $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];

        ActivityLog::record('login', 'admin', (int) $admin['id'], $admin['username']);

        return true;
    }

    /**
     * آیا در حال حاضر یک ادمین لاگین است؟
     *
     * @return bool
     */
    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * شناسهٔ ادمین لاگین‌شده (یا null اگر لاگین نباشد).
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * نام کاربری ادمین لاگین‌شده (برای نمایش در پنل).
     *
     * @return string|null
     */
    public static function username(): ?string
    {
        return $_SESSION['admin_username'] ?? null;
    }

    /**
     * خروج از حساب: پاک کردن سشن به‌طور کامل.
     *
     * @return void
     */
    public static function logout(): void
    {
        if (self::check()) {
            ActivityLog::record('logout', 'admin', self::id(), self::username());
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
