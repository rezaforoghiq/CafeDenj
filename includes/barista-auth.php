<?php
/**
 * includes/barista-auth.php
 * -----------------------------------------------------------------------
 * «نگهبان» صفحات پنل باریستا. مشابه includes/auth.php برای ادمین.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../classes/ActivityLog.php';
require_once __DIR__ . '/../classes/Barista.php';
require_once __DIR__ . '/../classes/BaristaAuth.php';

function requireBaristaLogin(): void
{
    if (!BaristaAuth::check()) {
        header('Location: ' . APP_URL . '/barista/');
        exit;
    }
}
