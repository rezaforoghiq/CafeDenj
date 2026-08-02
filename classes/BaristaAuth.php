<?php
/**
 * classes/BaristaAuth.php
 * -----------------------------------------------------------------------
 * احراز هویت باریستا (جدول baristas) — مشابه Auth.php برای ادمین.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class BaristaAuth
{
    private const SESSION_KEY = 'barista_id';

    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        $barista = Barista::findByUsername($username);
        if (!$barista || $barista['status'] !== 'active' || !password_verify($password, $barista['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $barista['id'];
        $_SESSION['barista_name']    = $barista['full_name'];

        Barista::touchLogin((int) $barista['id']);
        ActivityLog::record('login', 'barista', (int) $barista['id'], $barista['full_name']);

        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['barista_name'] ?? null;
    }

    public static function logout(): void
    {
        if (self::check()) {
            ActivityLog::record('logout', 'barista', self::id(), self::name());
        }
        unset($_SESSION[self::SESSION_KEY], $_SESSION['barista_name']);
        session_regenerate_id(true);
    }
}
