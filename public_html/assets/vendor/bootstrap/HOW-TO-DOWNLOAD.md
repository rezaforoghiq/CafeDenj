# public_html/assets/vendor/bootstrap/

این پوشه باید شامل دو فایل باشد (که چون سندباکس من الان به اینترنت خارجی
دسترسی نداشت، خودم نتونستم دانلودشون کنم — باید یک‌بار خودتون از یک
سیستم با اینترنت عادی بگیرید):

1. `bootstrap.rtl.min.css`

دانلود از یکی از این آدرس‌ها (هر کدوم در دسترس بود):
- https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css
- https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css
- یا از GitHub Releases پروژهٔ Bootstrap (`twbs/bootstrap`) نسخهٔ ۵.۳.۳

فایل رو دانلود کنید و دقیقاً با همین اسم (`bootstrap.rtl.min.css`) همین‌جا بذارید.

> نکتهٔ مهم: `bootstrap.bundle.min.js` **لازم نیست** دانلود بشه — چون بعد از
> بررسی کل پروژه معلوم شد هیچ‌جای پنل ادمین از کامپوننت‌های جاوااسکریپتی
> Bootstrap (مودال، دراپ‌داون و...) استفاده نمی‌شه، فقط از کلاس‌های CSS آن.
> پس این وابستگی به‌طور کامل حذف شده، نه self-host.

بعد از قرار دادن فایل، همین‌جا کارتون تمومه — کد پروژه از قبل به این مسیر
لینک شده (`includes/admin-header.php` و `public_html/admin/index.php`).
