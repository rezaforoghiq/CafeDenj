<?php
/**
 * config/env.php
 * -----------------------------------------------------------------------
 * یک Parser سبک برای خواندن فایل .env — بدون نیاز به Composer یا هیچ
 * کتابخانهٔ بیرونی (چون قرار است روی هاست اشتراکی ساده اجرا شود).
 *
 * خروجی: مقادیر فایل .env در $_ENV و همچنین از طریق getenv() قابل خواندن
 * می‌شوند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

if (!function_exists('loadEnv')) {

    /**
     * خواندن فایل .env و قرار دادن مقادیر آن در $_ENV.
     *
     * @param string $path مسیر کامل فایل .env
     * @return void
     */
    function loadEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            die(
                'فایل .env پیدا نشد. لطفاً فایل .env.example را کپی کرده و ' .
                'به نام .env ذخیره کنید، سپس اطلاعات دیتابیس خود را در آن وارد کنید.'
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // رد شدن از خطوط کامنت و خطوط خالی
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // فقط خطوطی که فرمت KEY=VALUE دارند پردازش شوند
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            // حذف کوتیشن اطراف مقدار در صورت وجود ("value" یا 'value')
            $value = trim($value, "\"'");

            // اگر متغیر از قبل در محیط سرور تنظیم شده باشد (مثلاً در پنل هاست)
            // آن را بازنویسی نمی‌کنیم — اولویت با تنظیمات واقعی سرور است.
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * خواندن یک متغیر محیطی با مقدار پیش‌فرض اختیاری.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // تبدیل رشته‌های بولی‌شکل به bool واقعی
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            default => $value,
        };
    }
}
