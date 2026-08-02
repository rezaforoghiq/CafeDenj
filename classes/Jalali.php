<?php
/**
 * classes/Jalali.php
 * -----------------------------------------------------------------------
 * تبدیل تاریخ میلادی (که در دیتابیس ذخیره می‌شود) به شمسی، فقط برای نمایش.
 * هیچ مقداری در دیتابیس تغییر نمی‌کند؛ این کلاس فقط رشتهٔ نمایشی می‌سازد.
 * پیاده‌سازی مستقل و بدون هیچ وابستگی/کتابخانهٔ خارجی.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Jalali
{
    private const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    private const MONTHS   = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** تبدیل عدد سال/ماه/روز میلادی به [سال، ماه، روز] شمسی */
    private static function toJalaliParts(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd;
        for ($i = 0; $i < $gm - 1; $i++) {
            $days += $gDaysInMonth[$i];
        }
        if ($gm > 2 && ($gy % 4 === 0 && ($gy % 100 !== 0 || $gy % 400 === 0))) {
            $days++;
        }

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $jm = 0;
        for ($i = 0; $i < 11 && $days >= $jDaysInMonth[$i]; $i++) {
            $days -= $jDaysInMonth[$i];
            $jm++;
        }
        $jd = $days + 1;

        return [$jy, $jm + 1, $jd];
    }

    /** تبدیل ارقام لاتین به فارسی */
    public static function digits(string $value): string
    {
        static $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        static $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($en, $fa, $value);
    }

    /**
     * تاریخ+ساعت شمسی کامل، مثل «۱۴۰۴/۰۵/۰۳ - ۱۴:۲۲»
     * ورودی: هر رشتهٔ قابل‌فهم strtotime (خروجی دیتابیس) یا null (یعنی اکنون)
     */
    public static function format(?string $datetime, bool $withTime = true): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '—';
        }
        [$jy, $jm, $jd] = self::toJalaliParts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        $out = self::digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
        if ($withTime) {
            $out .= ' - ' . self::digits(date('H:i', $ts));
        }
        return $out;
    }

    /** نمایش کامل با نام ماه و روز هفته، مثل «شنبه ۳ مرداد ۱۴۰۴» */
    public static function formatLong(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '—';
        }
        [$jy, $jm, $jd] = self::toJalaliParts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        $weekday = self::WEEKDAYS[(int) date('w', $ts)];
        return $weekday . ' ' . self::digits((string) $jd) . ' ' . self::MONTHS[$jm - 1] . ' ' . self::digits((string) $jy);
    }

    /** فقط تاریخ شمسی بدون ساعت، برای input یا فیلترها */
    public static function formatDate(?string $datetime): string
    {
        return self::format($datetime, false);
    }
}
