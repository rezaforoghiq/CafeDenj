# راهنمای اجرا و تنظیمات (آپدیت مطابق تغییرات اخیر)

این فایل نکات سریع و ضروری راه‌اندازی محلی و روی هاست را جمع‌بندی می‌کند.
برای چک‌لیست کاملِ استقرار روی cPanel به `DEPLOY-CHECKLIST.md` مراجعه کنید.

## ۱) اجرای محلی (XAMPP / Laragon)
1. پوشهٔ پروژه را داخل `htdocs` (یا `www` در Laragon) کپی یا extract کنید.
2. در phpMyAdmin دیتابیس جدید بسازید (مثلاً `cafe_local`) و فایل `database/cafe.sql` را Import کنید.
   - فایل `cafe.sql` اکنون شامل: جدول‌های محصولات، categories، orders (با ستون‌های کوپن)، order_items, print_jobs, permissions, baristas, activity_logs و دادهٔ اولیه است.
3. فایل `.env` (از روی `.env.example`) را در سطح پروژه ایجاد و تنظیم کن:
```
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/cafe-project/public_html
DB_HOST=127.0.0.1
DB_NAME=cafe_local
DB_USER=root
DB_PASS=
```
4. برای تست اتصال دیتابیس می‌توانی `public_html/db-test.php` را باز کنی (خروجی ساده). بعد از تست، این فایل را حذف یا غیرفعال کن.

## ۲) نکات کلیدی دیتابیس و تغییرات اخیر
- ستون‌های کوپن/totals اکنون در CREATE TABLE `orders` گنجانده شده‌اند: `coupon_code`, `coupon_percent`, `discount_amount`.
- جدول `print_jobs` اضافه شده و از فیلد `job_type` برای تمایز بین چاپ‌های آماده‌سازی و فاکتور مشتری استفاده می‌کند.
- `order_items` دارای FK با `ON DELETE CASCADE` است؛ حذف سفارش، اقلام مرتبط را نیز پاک می‌کند.
- `activity_logs` عمداً `order_id` را بدون FK نگه می‌دارد تا تاریخچهٔ عملیاتی پس از حذف سفارش حفظ شود.

اگر دیتابیس قدیمی دارید و نمی‌خواهید آن را پاک کنید، ترجیحاً از یک اسکریپت incremental برای آپدیت استفاده کنید — در صورت نیاز من می‌توانم اسکریپت ALTER امن تولید کنم.

## ۳) استقرار روی هاست با cPanel (خلاصه)
1. از طریق cPanel یک Domain یا Subdomain بساز و Document Root را به پوشهٔ `public_html` پروژه اشاره بده (مثلاً `/home/youruser/public_html/cafe`).
2. فایل‌های پروژه را آپلود کن. فایل‌های حساس (config، classes، database، .env) را یک سطح بالاتر از Document Root قرار بده.
3. در cPanel → MySQL® Databases یک دیتابیس و کاربر بساز و `database/cafe.sql` را import کن (phpMyAdmin یا خط فرمان).
4. در cPanel → MultiPHP Manager نسخه PHP مناسب (8.x توصیه می‌شود) و اکستنشن‌های لازم را فعال کن: pdo_mysql, mbstring, intl, fileinfo, openssl, gd, xml, zip.
5. در cPanel → MultiPHP INI Editor تنظیمات production را اعمال کن: `display_errors = Off` و `log_errors = On`.
6. SSL: از AutoSSL/Let’s Encrypt استفاده و HTTPS را فعال کن.

## ۴) پیکربندی‌های امنیتی/عملیاتی پس از استقرار
- حتماً رمز پیش‌فرض ادمین‌ها را تغییر بده (حساب‌های تستی در فایل seed وجود دارند — رمز نمونه در README هشدار داده شده).
- فایل `public_html/db-test.php` را حذف کن.
- پوشه‌های قابل نوشتن را بررسی کن: `uploads`, `storage/logs`, `storage/cache` باید قابل نوشتن باشند.
- اطمینان از خواندن `public_html/.htaccess` توسط سرور (ErrorDocumentها و Clean URLs). در صورت نمایش صفحات خطای پیش‌فرضِ آپاچی، مسیر Document Root احتمالا اشتباه است.

## ۵) منطق برنامه مربوط به مجوزها و باریستا
- پروژه از جدول `permissions` و `barista_permissions` برای کنترل دقیق دسترسی‌ها استفاده می‌کند. پس از استقرار، از پنل تنظیمات یا DB مقداردهی اولیه مجوزها را بررسی کن.
- برای باریستاها مسیرهای /barista/* وجود دارد که همان صفحات ادمین را با محدودیت‌های مجوز نمایش می‌دهند.

## ۶) حذف امن سفارش و چاپ
- حذف سفارش توسط ادمین سرور-ساید انجام می‌شود و از تراکنش و پاک‌سازی مرتبط (print_jobs, order_items) استفاده می‌کند تا رکوردهای یتیم باقی نمانند.
- Print Bridge و دستگاه‌های چاپ حرارتی مستقل از هاست هستند: اگر از چاپ فیزیکی استفاده می‌کنی، اطمینان حاصل کن سرویس Print Bridge/ویندوزی در شبکه در دسترس است و توکن/دسترسی آن در تنظیمات قرار داده شده است.

## ۷) تست‌های ضروری پس از استقرار
- ورود به پنل ادمین و تغییر رمز ادمین‌ها.
- ایجاد یک سفارش تست، افزودن آیتم، و بررسی ایجاد `print_jobs` و `order_items`.
- تست حذف سفارش توسط ادمین: بررسی کن `order_items` و `print_jobs` مرتبط پاک شوند و یک رکورد در `activity_logs` ثبت شود.
- تست رفتار باریستاها: مشاهدهٔ سفارش‌های pending توسط چند باریستا و عملکرد claim (اولین باریستا که وضعیت را تغییر دهد، سفارش را متعهد می‌کند).
- بررسی صفحات خطا: مسیر ناموجود → صفحهٔ 404 سفارشی باید نمایش داده شود.

## ۸) نکات اضافی
- اگر می‌خواهی دیتابیس فعلی را بدون پاک‌کردن به‌روز کنی، اعلام کن تا اسکریپت incremental (ALTER) آماده کنم.
- برای بهینه‌سازی موبایل و عملکرد، انیمیشن پس‌زمینه (`.beans`) را در CSS/JS می‌توان محدود کرد یا بسته به User-Agent کاهش داد.

---
فایل‌های مرجع:
- `database/cafe.sql`  — فایل کامل دیتابیس برای import
- `DEPLOY-CHECKLIST.md` — چک‌لیست مفصل استقرار روی cPanel
- `templates/menu.html`, `public_html/assets/css/menu.css`, `public_html/assets/js/menu.js` — فایل‌های رابط کاربری (footer، انیمیشن، تم‌ها)

در صورت تمایل، تغییرات را کامیت کنم یا نسخهٔ خلاصه‌شده‌ای برای تیم پشتیبانی آماده کنم.