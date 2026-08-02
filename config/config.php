<?php
/**
 * config/config.php
 * -----------------------------------------------------------------------
 * فایل مرکزی تنظیمات پروژه — تنها جایی که باید برای جابه‌جایی بین
 * «لوکال» و «هاست» تغییر ایجاد کنید همان فایل .env است، نه این فایل.
 *
 * این فایل باید در همان ابتدای هر فایل ورودی require شود:
 * public_html/index.php، public_html/subscribe.php،
 * public_html/admin/*.php (از طریق includes/auth.php).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

// خواندن فایل .env (کنار همین پوشهٔ config، بیرون از public_html)
loadEnv(__DIR__ . '/../.env');

// ---------------------------------------------------------------------
// انکودینگ — تضمین رفتار یکسان روی هر توزیع لینوکس/PHP، مستقل از تنظیمات
// پیش‌فرض php.ini هاست
// ---------------------------------------------------------------------
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------
// تنظیم نمایش/ثبت خطاها بر اساس محیط اجرا
// ---------------------------------------------------------------------
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));

// خطاها همیشه در یک فایل لاگ ثبت می‌شوند (چه در لوکال، چه روی هاست)
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/app.log');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // در Production خطاها هرگز مستقیم به کاربر نمایش داده نمی‌شوند، فقط لاگ می‌شوند
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------------
// آدرس پایه و مسیرها
// ---------------------------------------------------------------------
define('APP_URL', rtrim(env('APP_URL', ''), '/'));
define('UPLOAD_DIR_PATH', __DIR__ . '/../public_html/uploads');   // مسیر فیزیکی
define('UPLOAD_DIR_URL', APP_URL . '/uploads');                   // مسیر قابل نمایش در HTML
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', 2097152)); // بایت

// ---------------------------------------------------------------------
// اطلاعات دیتابیس (این ثابت‌ها را config/database.php مصرف می‌کند)
// ---------------------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ---------------------------------------------------------------------
// تنظیمات عمومی دیگر
// ---------------------------------------------------------------------
date_default_timezone_set('Asia/Tehran');

// ---------------------------------------------------------------------
// تنظیمات امن Session — قبل از session_start() اعمال می‌شود
// روی هاست (APP_ENV=production) فرض بر HTTPS است؛ اگر دامنه هنوز SSL
// ندارد، این را موقتاً در همین فایل به false تغییر دهید.
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ---------------------------------------------------------------------
// CSRF — این توابع اینجا (نه در includes/auth.php) قرار دارند چون هم
// فرم‌های پنل مدیریت و هم فرم‌های عمومی سایت (مثل فرم ثبت‌نام در پاپ‌آپ
// ایونت) به آن‌ها نیاز دارند؛ includes/auth.php فقط مخصوص محافظت از
// صفحات ادمین است.
// ---------------------------------------------------------------------

/**
 * ساخت (یا بازیابی) توکن CSRF برای سشن جاری.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * بررسی صحت توکن CSRF ارسالی از فرم.
 */
function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * تبدیل ارقام انگلیسی به فارسی — برای نمایش قیمت در منوی عمومی
 * (پنل مدیریت عمداً همیشه ارقام انگلیسی نشان می‌دهد).
 */
function toPersianDigits(string $input): string
{
    static $en = ['0','1','2','3','4','5','6','7','8','9'];
    static $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, $input);
}
