<?php
/**
 * classes/Template.php
 * -----------------------------------------------------------------------
 * یک موتور قالب بسیار سبک (Mustache-like) — بدون هیچ کتابخانهٔ بیرونی،
 * چون قرار است روی هاست اشتراکی ساده اجرا شود.
 *
 * هدف: کاملاً جدا کردن «طراحی ظاهری» (HTML/CSS/JS داخل پوشهٔ templates/
 * و public_html/assets/) از «منطق PHP». طراح/کاربر می‌تواند فایل‌های
 * templates/*.html و assets/css/*.css و assets/js/*.js را کاملاً آزادانه
 * تغییر بدهد؛ هیچ‌کدام از آن فایل‌ها حاوی کد PHP نیستند.
 *
 * نحوهٔ کار:
 *   ۱) داخل فایل template یک بخش تکرارشونده (مثل کارت هر محصول) بین دو
 *      نشانهٔ کامنتی مشخص می‌شود:
 *        <!-- BEGIN:PRODUCT_ITEM --> ... <!-- END:PRODUCT_ITEM -->
 *   ۲) extractBlock() آن بخش را از HTML جدا می‌کند و جای آن یک placeholder
 *      به‌شکل {{PRODUCT_ITEM}} می‌گذارد.
 *   ۳) PHP به ازای هر ردیف داده (هر محصول) یک نسخه از آن بخش را با fill()
 *      پر می‌کند و همه را به هم می‌چسباند.
 *   ۴) در پایان fill() یک‌بار روی کل صفحه اجرا می‌شود تا هم placeholder
 *      بخش‌های تکرارشونده و هم متغیرهای ساده (مثل {{PAGE_TITLE}}) جایگزین شوند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Template
{
    /**
     * خواندن محتوای یک فایل قالب از دیسک.
     */
    public static function load(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("فایل قالب پیدا نشد: {$path}");
        }

        return file_get_contents($path);
    }

    /**
     * جدا کردن یک بخش تکرارشونده از HTML.
     *
     * @param string $html
     * @param string $blockName نام بخش (بدون BEGIN:/END:)، مثل PRODUCT_ITEM
     * @return array{0: string, 1: string}  [0] => HTML باقی‌مانده با placeholder جایگزین‌شده
     *                                       [1] => قالب داخلی همان بخش (برای fill کردن به ازای هر ردیف)
     */
    public static function extractBlock(string $html, string $blockName): array
    {
        $pattern = '/<!--\s*BEGIN:' . preg_quote($blockName, '/') . '\s*-->(.*?)<!--\s*END:' . preg_quote($blockName, '/') . '\s*-->/s';

        if (!preg_match($pattern, $html, $matches)) {
            // اگر بخش پیدا نشد (مثلاً کاربر موقع طراحی مجدد اشتباهی حذفش کرده)
            // خطای واضح می‌دهیم تا مشکل فوراً مشخص شود، نه یک صفحهٔ خراب و ساکت
            throw new RuntimeException("بخش «{$blockName}» در فایل قالب پیدا نشد. نشانه‌های <!-- BEGIN:{$blockName} --> و <!-- END:{$blockName} --> را حذف نکنید.");
        }

        $blockTemplate = $matches[1];
        $remainingHtml = preg_replace($pattern, '{{' . $blockName . '}}', $html, 1);

        return [$remainingHtml, $blockTemplate];
    }

    /**
     * جایگزینی متغیرهای {{KEY}} در یک رشتهٔ HTML.
     *
     * نکته: مقادیر باید از قبل توسط فراخواننده escape شده باشند
     * (htmlspecialchars) — این کلاس هیچ escape خودکاری انجام نمی‌دهد، دقیقاً
     * مثل بقیهٔ پروژه که escape صریح و آگاهانه انجام می‌شود.
     *
     * @param string $html
     * @param array<string,string> $vars
     */
    public static function fill(string $html, array $vars): string
    {
        $search  = [];
        $replace = [];

        foreach ($vars as $key => $value) {
            $search[]  = '{{' . $key . '}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $html);
    }
}
