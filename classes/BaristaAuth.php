<?php
/**
 * classes/BaristaAuth.php
 * -----------------------------------------------------------------------
 * Backward-compatible wrapper for barista auth.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

class BaristaAuth
{
    public static function attempt(string $username, string $password): bool
    {
        return Auth::attemptBarista($username, $password);
    }

    public static function check(): bool
    {
        return Auth::isBarista();
    }

    public static function id(): ?int
    {
        return Auth::currentBaristaId();
    }

    public static function name(): ?string
    {
        return Auth::username();
    }

    public static function logout(): void
    {
        Auth::logout();
    }
}
