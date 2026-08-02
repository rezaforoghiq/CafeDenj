<?php
/**
 * admin/logout.php
 * -----------------------------------------------------------------------
 * خروج ادمین از حساب و بازگشت به صفحهٔ ورود.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

Auth::logout();

header('Location: ' . APP_URL . '/admin/');
exit;
