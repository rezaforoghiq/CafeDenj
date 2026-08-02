# راهنمای اجرای لوکال و انتقال به هاست

## ۱) اجرای لوکال (XAMPP / Laragon)

1. کل پوشهٔ پروژه (`cafe-menu-system`) را داخل `htdocs` (یا `www` در Laragon) کپی کنید.
2. یک دیتابیس با نام دلخواه (مثلاً `cafe_noyan`) در phpMyAdmin بسازید و فایل
   `database/cafe.sql` را داخلش Import کنید.
3. فایل `.env` (که از قبل کنار `.env.example` ساخته شده) را باز کنید و اطلاعات
   لوکال خودتان را بگذارید، مثلاً:
   ```
   DB_HOST=localhost
   DB_NAME=cafe_noyan
   DB_USER=root
   DB_PASS=
   APP_URL=http://localhost/cafe-menu-system/public_html
   ```
4. در مرورگر بروید به:
   `http://localhost/cafe-menu-system/public_html/db-test.php`
   اگر پیام «اتصال به دیتابیس با موفقیت انجام شد» را دیدید، همه‌چیز آماده است.

## ۲) انتقال به هاست اشتراکی (public_html)

1. محتویات پوشهٔ `public_html/` پروژه را داخل `public_html` هاست بریزید.
2. پوشه‌های `config/`، `classes/`، `includes/`، `database/` و فایل `.env` را
   **یک سطح بالاتر از public_html** (کنار آن، نه داخلش) آپلود کنید — دقیقاً
   مثل ساختار فعلی پروژه.
3. یک دیتابیس MySQL از پنل هاست بسازید و `database/cafe.sql` را Import کنید.
4. فقط فایل `.env` را ویرایش کنید:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   DB_HOST=localhost
   DB_NAME=نام_دیتابیس_هاست
   DB_USER=یوزرنیم_دیتابیس_هاست
   DB_PASS=پسورد_دیتابیس_هاست
   ```
5. آدرس `https://your-domain.com/db-test.php` را باز کنید تا اتصال را تست کنید،
   سپس **این فایل را حتماً حذف کنید** (نباید روی سایت زنده بماند).

## نکتهٔ مهم امنیتی

هیچ‌وقت لازم نیست کد PHP را برای تغییر هاست دستکاری کنید — فقط و فقط `.env`
تغییر می‌کند. فایل `.env` خودش هم چون بیرون از `public_html` قرار دارد، از
طریق مرورگر اصلاً قابل دسترسی نیست.

## تغییر ظاهر سایت (بدون دست‌زدن به PHP)

طراحی صفحهٔ اصلی منو کاملاً از منطق PHP جدا شده:

- **`templates/menu.html`** — ساختار HTML صفحه (فقط چند نشانهٔ `{{...}}` که PHP پرشان می‌کند؛ هیچ کد PHP واقعی داخلش نیست)
- **`public_html/assets/css/menu.css`** — تمام استایل‌ها
- **`public_html/assets/js/menu.js`** — رفتار جستجو/فیلتر و پاپ‌آپ ایونت

برای تغییر رنگ، فونت، چیدمان یا هر چیز ظاهری دیگر، فقط همین سه فایل را
ویرایش کنید — نیازی به باز کردن هیچ فایل `.php` نیست.

## برای انتقال نهایی به هاست لینوکسی

راهنمای کامل و چک‌لیست امنیتی/بهینه‌سازی را در `DEPLOY-CHECKLIST.md` ببینید.

