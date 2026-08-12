<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------------
 * Shared guard for the admin panel and permission-aware routes.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

function requireLogin(): void
{
    if (!Auth::check()) {
        header('Location: ' . APP_URL . '/admin/');
        exit;
    }
}

function requirePermission(string $permission): void
{
    requireLogin();
    Auth::requirePermission($permission);
}

function requireAdmin(): void
{
    requireLogin();
    Auth::requireAdmin();
}
