<?php
/**
 * classes/Auth.php
 * -----------------------------------------------------------------------
 * Unified auth layer for admin and barista users.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Permission.php';

class Auth
{
    private const SESSION_ROLE = 'auth_role';
    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USERNAME = 'auth_username';

    public static function attempt(string $username, string $password): bool
    {
        return self::attemptAdmin($username, $password);
    }

    public static function attemptAdmin(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        session_regenerate_id(true);
        self::setCurrentUser('admin', (int) $admin['id'], (string) $admin['username']);
        ActivityLog::record('login', 'admin', (int) $admin['id'], $admin['username']);
        return true;
    }

    public static function attemptBarista(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, username, password_hash, status FROM baristas WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $barista = $stmt->fetch();

        if (!$barista || $barista['status'] !== 'active' || !password_verify($password, $barista['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        self::setCurrentUser('barista', (int) $barista['id'], (string) $barista['full_name']);
        $_SESSION['barista_name'] = $barista['full_name'];
        $_SESSION['barista_id'] = (int) $barista['id'];
        Permission::ensureDefaultsForBarista((int) $barista['id']);
        Database::getConnection()->prepare('UPDATE baristas SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int) $barista['id']]);
        ActivityLog::record('login', 'barista', (int) $barista['id'], $barista['full_name']);
        return true;
    }

    public static function check(): bool
    {
        if (isset($_SESSION[self::SESSION_ROLE])) {
            return true;
        }
        if (isset($_SESSION['admin_id'])) {
            $_SESSION[self::SESSION_ROLE] = 'admin';
            $_SESSION[self::SESSION_USER_ID] = $_SESSION['admin_id'];
            $_SESSION[self::SESSION_USERNAME] = $_SESSION['admin_username'] ?? 'admin';
            return true;
        }
        if (isset($_SESSION['barista_id'])) {
            $_SESSION[self::SESSION_ROLE] = 'barista';
            $_SESSION[self::SESSION_USER_ID] = $_SESSION['barista_id'];
            $_SESSION[self::SESSION_USERNAME] = $_SESSION['barista_name'] ?? 'barista';
            return true;
        }
        return false;
    }

    public static function role(): ?string
    {
        $role = $_SESSION[self::SESSION_ROLE] ?? null;
        if (is_string($role)) {
            return $role;
        }
        if (isset($_SESSION['admin_id'])) {
            return 'admin';
        }
        if (isset($_SESSION['barista_id'])) {
            return 'barista';
        }
        return null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isBarista(): bool
    {
        return self::role() === 'barista';
    }

    public static function id(): ?int
    {
        $value = $_SESSION[self::SESSION_USER_ID] ?? null;
        if ($value === null) {
            if (self::isAdmin()) {
                $value = $_SESSION['admin_id'] ?? null;
            } elseif (self::isBarista()) {
                $value = $_SESSION['barista_id'] ?? null;
            }
        }
        return $value !== null ? (int) $value : null;
    }

    public static function username(): ?string
    {
        $value = $_SESSION[self::SESSION_USERNAME] ?? null;
        if ($value !== null) {
            return (string) $value;
        }
        if (self::isAdmin()) {
            return $_SESSION['admin_username'] ?? null;
        }
        if (self::isBarista()) {
            return $_SESSION['barista_name'] ?? null;
        }
        return null;
    }

    public static function can(string $permission): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        if (!self::isBarista()) {
            return false;
        }
        $baristaId = self::id();
        if ($baristaId === null || $baristaId <= 0) {
            return false;
        }

        $permissions = Permission::getBaristaPermissions((int) $baristaId);
        if ($permissions === []) {
            Permission::ensureDefaultsForBarista((int) $baristaId);
            $permissions = Permission::getBaristaPermissions((int) $baristaId);
        }

        return in_array($permission, $permissions, true) || Permission::hasForBarista((int) $baristaId, $permission);
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/admin/');
            exit;
        }
        if (!self::can($permission)) {
            http_response_code(403);
            echo 'Access denied';
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::check() || !self::isAdmin()) {
            http_response_code(403);
            echo 'Access denied';
            exit;
        }
    }

    public static function currentBaristaId(): ?int
    {
        if (!self::isBarista()) {
            return null;
        }
        $id = self::id();
        return $id !== null && $id > 0 ? (int) $id : null;
    }

    public static function setCurrentUser(string $role, int $id, string $username): void
    {
        $_SESSION[self::SESSION_ROLE] = $role;
        $_SESSION[self::SESSION_USER_ID] = (int) $id;
        $_SESSION[self::SESSION_USERNAME] = $username;

        if ($role === 'admin') {
            $_SESSION['admin_id'] = (int) $id;
            $_SESSION['admin_username'] = $username;
            unset($_SESSION['barista_id'], $_SESSION['barista_name']);
        }
        if ($role === 'barista') {
            $_SESSION['barista_id'] = (int) $id;
            $_SESSION['barista_name'] = $username;
            unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        }
    }

    public static function logout(): void
    {
        $role = self::role();
        $userId = self::id();
        $username = self::username();
        if ($role && $userId !== null) {
            ActivityLog::record('logout', $role, $userId, $username ?? null);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
