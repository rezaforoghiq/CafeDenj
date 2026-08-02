# چک‌لیست انتقال به هاست لینوکسی

## ۱) آپلود فایل‌ها
- محتویات `public_html/` پروژه → داخل `public_html` هاست.
- پوشه‌های `config/`، `classes/`، `includes/`، `database/`، `templates/`، `storage/` و فایل `.env`
  → یک سطح بالاتر از `public_html` (کنارش، نه داخلش).

## ۲) دیتابیس
- یک دیتابیس MySQL از پنل هاست بسازید.
- `database/cafe.sql` را Import کنید (شامل جدول‌های محصولات، دسته‌ها، ادمین، مشتریان و ایونت‌ها).
- اگر قبلاً یک نسخهٔ قدیمی‌تر از `cafe.sql` را Import کرده‌اید، فقط
  `database/migration-2-customers-events.sql` را اجرا کنید.

## ۳) فایل `.env`
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_HOST=localhost
DB_NAME=...
DB_USER=...
DB_PASS=...
```

## ۴) دسترسی فایل‌ها (Permissions)
از طریق File Manager هاست یا SSH:
```
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 public_html/uploads
chmod 775 storage/logs
```
پوشهٔ `uploads` و `storage/logs` باید توسط PHP قابل نوشتن باشند؛ بقیهٔ فایل‌ها نیازی به دسترسی نوشتن ندارند.

## ۵) قبل از باز کردن سایت به‌صورت عمومی
- [ ] فایل `public_html/db-test.php` را **حذف کنید**.
- [ ] یوزرنیم/پسورد پیش‌فرض ادمین (`admin` / `admin123`) را با یک هش تازه عوض کنید.
- [ ] گواهی SSL (HTTPS) دامنه را فعال کنید، سپس در `public_html/.htaccess`
      خطوط ریدایرکت HTTPS را از کامنت خارج کنید.
- [ ] `APP_ENV=production` و `APP_DEBUG=false` باشد.
- [ ] یک ایونت واقعی (یا هیچ ایونتی فعال نباشد) را در `admin/events.php` تنظیم کنید — وگرنه پاپ‌آپ نمونه/تستی برای بازدیدکننده‌ها نمایش داده می‌شود.

## ۶) بررسی نهایی
- باز کردن `https://your-domain.com/` → منو باید نمایش داده شود.
- باز کردن `https://your-domain.com/admin/index.php` → ورود به پنل.
- خطاهای احتمالی PHP در `storage/logs/app.log` ثبت می‌شوند (نه روی صفحه، چون APP_DEBUG=false است).
