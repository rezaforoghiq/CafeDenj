<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------------
 * «نگهبان» صفحات ادمین. کافی است در ابتدای هر صفحهٔ محافظت‌شدهٔ پنل
 * (dashboard.php, products.php, add-product.php, edit-product.php)
 * این خط را بنویسید:
 *
 *     require_once __DIR__ . '/../../includes/auth.php';
 *
 * این فایل خودش config.php و database.php و Auth.php را هم بار می‌کند،
 * پس نیازی به require جداگانهٔ آن‌ها در صفحات ادمین نیست.
 *
 * توابع csrfToken() / verifyCsrfToken() اینجا نیستند — در config/config.php
 * تعریف شده‌اند چون فرم‌های عمومی سایت (نه فقط پنل ادمین) هم به آن‌ها
 * نیاز دارند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

/**
 * اگر ادمین لاگین نباشد، به صفحهٔ ورود ریدایرکت می‌شود.
 * این تابع را در ابتدای تمام صفحات محافظت‌شدهٔ پنل صدا بزنید.
 *
 * @return void
 */
function requireLogin(): void
{
    if (!Auth::check()) {
        header('Location: ' . APP_URL . '/admin/');
        exit;
    }
}
