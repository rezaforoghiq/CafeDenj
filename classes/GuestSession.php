<?php

declare(strict_types=1);

/**
 * classes/GuestSession.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: تولید، ذخیره‌سازی، و بازیابی توکن جلسهٔ مهمان.
 *
 * توکن به‌طور هم‌زمان در $_SESSION و یک کوکی httponly/Lax ذخیره می‌شود.
 * هر بار که توکن معتبر باشد، عمر کوکی به ۴ ساعت آینده تمدید می‌شود
 * (Sliding Expiration) تا مشتری در حین حضور در کافه دسترسی خود را
 * از دست ندهد.
 * -----------------------------------------------------------------------
 */
class GuestSession
{
    private const SESSION_KEY = 'guest_token';
    private const COOKIE_NAME = 'guest_token';
    private const COOKIE_TTL  = 14400; // 4 ساعت

    /**
     * بازیابی (یا تولید) توکن جلسهٔ مهمان جاری.
     *
     * اولویت: Session → Cookie → تولید جدید
     * پس از هر بازیابی/تولید، کوکی sliding refresh می‌شود.
     *
     * @return string توکن hex با طول ۳۲ کاراکتر
     */
    public static function getToken(): string
    {
        // 1. بررسی Session
        if (!empty($_SESSION[self::SESSION_KEY])) {
            $token = (string) $_SESSION[self::SESSION_KEY];
            self::refreshCookie($token);
            return $token;
        }

        // 2. بررسی Cookie
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            $token = (string) $_COOKIE[self::COOKIE_NAME];
            // اعتبارسنجی فرمت: دقیقاً ۳۲ کاراکتر hex
            if (self::isValidTokenFormat($token)) {
                $_SESSION[self::SESSION_KEY] = $token;
                self::refreshCookie($token);
                return $token;
            }
        }

        // 3. تولید توکن جدید
        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY] = $token;
        self::refreshCookie($token);

        return $token;
    }

    /**
     * بررسی اینکه آیا توکن داده‌شده با توکن جلسهٔ جاری یکسان است.
     *
     * @param string $token توکنی که باید مقایسه شود
     * @return bool
     */
    public static function matches(string $token): bool
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * بررسی اینکه آیا رشتهٔ داده‌شده فرمت معتبر توکن دارد.
     *
     * @param string $token رشتهٔ ورودی
     * @return bool
     */
    private static function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $token);
    }

    /**
     * تمدید عمر کوکی به ۴ ساعت آینده (Sliding Expiration).
     *
     * @param string $token توکنی که در کوکی ذخیره می‌شود
     */
    private static function refreshCookie(string $token): void
    {
        // از setcookie() با options array استفاده می‌شود (PHP 7.3+)
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (defined('APP_ENV') && APP_ENV === 'production'),
        ]);
    }
}
