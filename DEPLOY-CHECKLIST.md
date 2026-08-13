# چک‌لیست استقرار (cPanel روی هاست لینوکسی)

این چک‌لیست براساس آخرین تغییرات کد و اسکیمای دیتابیس (اضافه‌شدن ستون‌های کوپن در orders، جدول print_jobs، جدول permissions/barista_permissions و منطق حذف امن سفارش) تنظیم شده است. هدف، یک استقرار تمیز روی هاست لینوکسی با cPanel است.

## ۱) انتخاب روش قرارگیری فایل‌ها (پیشنهاد شده)
- روش اصولی: از cPanel یک Domain یا Subdomain (مثلاً `cafe.yourdomain.com`) بساز و در فیلد Document Root مسیر پوشه `public_html` پروژه را مشخص کن (مثلاً `/home/youruser/public_html/cafe/`).
  - این روش نیازی به کپی کردن دستی فایل‌های خطا یا تغییر مسیرهای داخل `.htaccess` ندارد.
- روش سریع (غیراصولی، فقط برای تست محلی): تمام محتوای `public_html/` را مستقیم در `/home/youruser/public_html/` قرار بده.

نکته: فایل‌های پیکربندی حساس (مثل `config/`، فایل‌های حاوی رمز) را بیرون از Document Root نگه‌دار (یک سطح بالاتر از public_html) تا از دسترسی مستقیم وب محافظت شوند.

## ۲) آپلود و ساختار فایل‌ها
- با File Manager یا FTP/SFTP تمام فایل‌های پروژه را در مسیر موردنظر آپلود کن.
- اطمینان حاصل کن که فایل `public_html/.htaccess` در Document Root وجود دارد — این فایل حاوی Clean URLs و ErrorDocument است که قبلاً به 403/404/500 اشاره داده شده.

## ۳) دیتابیس (ایجاد و Import)
1. در cPanel → MySQL® Databases یک دیتابیس جدید و یک کاربر بساز و کاربر را به دیتابیس با ALL PRIVILEGES وصل کن.
2. Collation/Charset: اگر ممکن است دیتابیس را با utf8mb4_unicode_ci یا utf8mb4_general_ci ایجاد کن. فایل از utf8mb4_persian_ci استفاده می‌کند؛ در صورت عدم پشتیبانی میزبان می‌توان به utf8mb4_general_ci برگشت.
3. Import:
   - توصیه شده: از خط فرمان mysql (اگر دسترسی داری):
     mysql -u DB_USER -p DB_NAME < database/cafe.sql
   - یا در cPanel → phpMyAdmin فایل `database/cafe.sql` را آپلود و Import کن.
4. نکتهٔ مهم: فایل `database/cafe.sql` اصلاح شده و اکنون ستون‌های `coupon_code`, `coupon_percent`, `discount_amount` مستقیماً در CREATE TABLE `orders` گنجانده شده — بنابراین import کامل روی یک دیتابیس خالی باید بی‌خطا انجام شود.
5. اگر دیتابیسِ هدف قبلاً نسخهٔ قدیمی دارد و نمی‌خواهی آن را پاک کنی، از روش incremental/migration استفاده کن — قبل از import کامل با من هماهنگ کن تا اسکریپت ALTER امن آماده کنم.

## ۴) فایل پیکربندی محیط (.env / config)
- نمونه متغیرها را در `config` یا `.env` (متناسب با پروژه) تنظیم کن:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password
```
- اگر پروژه از توکن/کلید برای Print Bridge استفاده می‌کند، مقدار مربوطه را در تنظیمات یا settings جدول ذخیره کن (مطمئن شو مقادیر محرمانه در فایل‌های خارج از وب ذخیره شوند).

## ۵) مجوزهای فایل و مالکیت (permissions / ownership)
- در cPanel معمولاً مالکیت صحیح برقرار است؛ اما اگر SSH دسترسی داری:
```
cd /home/youruser/public_html/cafe
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 public_html/uploads storage/logs storage/cache
# اگر PHP-FPM را وب‌سرور با کاربر www-data اجرا می‌کند، مالک را تنظیم کن (در cPanel معمولاً کاربر سایت مناسب است):
chown -R youruser:youruser /home/youruser/public_html/cafe
```
- پوشه‌های قابل نوشتن (uploads, storage, cache, logs) باید قابل نوشتن توسط PHP باشند.

## ۶) نسخهٔ PHP و اکستنشن‌ها
- پیشنهادی: PHP 8.0+ (یا همان نسخه‌ای که در توسعه استفاده کردی).
- اکستنشن‌های حداقل لازم: pdo_mysql, mbstring, intl, fileinfo, openssl, gd, xml, zip
- در cPanel → MultiPHP Manager و MultiPHP INI Editor بررسی و فعال کن.

## ۷) Apache / cPanel نکات
- چون از cPanel استفاده می‌کنی، تغییر Document Root از طریق Domains انجام شود — cPanel خودش VirtualHost می‌سازد.
- .htaccess حاوی ErrorDocument برای 403/404/500 است؛ اگر صفحه‌های دلخواه نمایش داده نمی‌شوند، بررسی کن Document Root صحیح است.
- در برخی هاست‌ها AllowOverride محدود می‌شود؛ اگر .htaccess نخوانده شود از پشتیبانی هاست درخواست کن AllowOverride (FileInfo) فعال شود.
- فعال‌سازی SSL: از AutoSSL یا Let’s Encrypt در cPanel استفاده کن و سپس HTTPS را اجباری کن (ریدایرکت در .htaccess یا تنظیمات cPanel).

## ۸) کرون و سرویس‌های جانبی
- اگر پروژه به کار زمان‌بندی‌شده نیاز دارد (پاک‌سازی، بررسی print_jobs، ارسال نوتیفیکیشن و ...)، در cPanel → Cron Jobs دستور PHP مربوطه را اضافه کن، مثلاً:
```
*/5 * * * * /usr/bin/php /home/youruser/public_html/cafe/cli/check_print_jobs.php >> /home/youruser/logs/print_bridge.log 2>&1
```
- Print Bridge: سرویس ویندوزی و دستگاه‌های چاپ حرارتی باید جداگانه پیکربندی و توکن/دسترسی شبکه فراهم شود — این روی هاست انجام نمی‌شود.

## ۹) لاگ‌ها و خطاها
- در production: display_errors = Off، log_errors = On (MultiPHP INI Editor).
- مسیر لاگ‌ها را بررسی کن (storage/logs یا مسیر معین) و مجوز نوشتن را بده.

## ۱۰) تست‌های پس از استقرار
- [ ] ورود به پنل ادمین (admin) و تغییر رمز پیش‌فرض ادمین‌ها.
- [ ] ساخت مشتری و ثبت سفارش آزمایشی: بررسی کن order_items، print_jobs ایجاد شوند و delete action برای ادمین کار کند.
- [ ] کارکرد باریستا: یک باریستا وارد شود، سفارش‌های pending را ببیند و اولین بار status را تغییر دهد (claim behavior).
- [ ] تست صفحات خطا: باز کردن مسیر ناموجود → بررسی نمایش 404 سفارشی؛ ایجاد خطای 500 در محیطی که display_errors خاموش است برای تست صفحه 500.
- [ ] بررسی activity_logs: اقدامات مهم (مثلاً حذف سفارش) در activity_logs ثبت می‌شوند.

## ۱۱) پشتیبان‌گیری و rollback
- قبل از import کامل یا ارتقا دیتابیس، از دیتابیس فعلی و فایل‌های پروژه بکاپ بگیر.
- در صورت بروز مشکل، بازگرداندن دیتابیس و فایل‌ها آسان‌تر خواهد بود.

## ۱۲) نکات مربوط به دیتابیس و منطق حذف سفارش
- جدول `order_items` دارای FK با `ON DELETE CASCADE` است — حذف سفارش همهٔ آیتم‌ها را پاک می‌کند.
- `activity_logs` عمداً دارای FK به `orders` نیست تا تاریخچهٔ عملیاتی حتی بعد از حذف سفارش حفظ شود.
- منطق حذف امن در کد (classes/Order.php) باید قبل از انتشار روی هاست رویه‌ها را اجرا کند؛ مطمئن شو نسخهٔ کد روی هاست با دیتابیس هم‌خوانی دارد.

## ۱۳) موارد اختیاری/درخواست از طرف تو
- اگر می‌خواهی import incremental (آپدیت دیتابیس موجود) انجام شود، اعلام کن تا اسکریپت ALTER امن آماده کنم.
- اگر مایل باشی بررسی کنم که `database/cafe.sql` کاملاً با هاست‌ت سازگار است (collation/size limits)، فایل‌ها را برایت آماده می‌کنم.

---
در صورت تمایل، می‌توانم این فایل را مستقیماً در repo کامیت کنم (با پیام کامیت مناسب). همچنین اگر اطلاعات هاست (نام کاربری cPanel و مسیر دقیق فایل‌ها) را بدهی، می‌توانم یک راهنمای دقیق‌تر با مسیرهای جایگزین تولید کنم.