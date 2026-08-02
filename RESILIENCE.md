# استقلال از اینترنت بین‌المللی (Resilience Report)

## ⚠️ محدودیت این مرحله (مهم، حتماً بخونید)
سندباکسی که الان توش کار می‌کنم دسترسی شبکه به هیچ دامنهٔ خارجی نداشت
(حتی `registry.npmjs.org` و `raw.githubusercontent.com` که طبق تنظیماتم
باید مجاز می‌بودن، `403` برگردوندن). یعنی **نتونستم خودم فایل باینری
Bootstrap و فونت‌ها رو دانلود کنم.** به‌جاش، کل معماری پروژه رو برای
self-hosting آماده کردم و کد به مسیرهای لوکال لینک شده — فقط باید خودتون
(از یک سیستم با اینترنت عادی) ۳ فایل کوچیک رو یک‌بار دانلود و جایگذاری
کنید. دستورالعمل دقیقش پایین همین فایله، و همچنین داخل هر پوشه یک
`HOW-TO-DOWNLOAD.md` گذاشتم.

---

## ۱) لیست کامل وابستگی‌های خارجی که پیدا شد

| وابستگی | کجا استفاده می‌شد | نوع |
|---|---|---|
| Google Fonts (Vazirmatn) | `templates/menu.html`, `includes/admin-header.php`, `public_html/admin/index.php` | فونت |
| Google Fonts (Noto Naskh Arabic) | `templates/menu.html` (فقط عنوان صفحهٔ اصلی) | فونت |
| Bootstrap CSS (jsDelivr CDN) | `includes/admin-header.php`, `public_html/admin/index.php` | CSS Framework |
| Bootstrap JS Bundle (jsDelivr CDN) | `includes/admin-footer.php` | جاوااسکریپت (که مشخص شد اصلاً استفاده نمی‌شد) |

**چیزهایی که در پروژه اصلاً وجود نداشت** (پس نیازی به بررسی/جایگزینی نبود):
- درگاه پرداخت (Payment Gateway) — این پروژه یک منوی دیجیتال کافه‌ست، سیستم پرداخت/سفارش نداره.
- API خارجی (نقشه، پیامک، ایمیل و ...)
- ابزار آنالیتیکس (Google Analytics و مشابه)
- نقشه (Google Maps و مشابه)
- ویجت شخص‌ثالث
- آیکون از CDN خارجی (آیکون جستجو در منو یک SVG داخلی/inline هست)
- تصویر از دامنهٔ خارجی (همه از پوشهٔ لوکال `uploads/` میان)

---

## ۲) چه چیزی تغییر کرد

### حذف کامل (بدون نیاز به جایگزین)
- **`bootstrap.bundle.min.js`** از `includes/admin-footer.php` حذف شد. چون کل پنل ادمین رو گشتم، هیچ‌جا از کامپوننت جاوااسکریپتی Bootstrap (مودال، دراپ‌داون، Collapse) استفاده نمی‌شه — فقط کلاس‌های CSS آن (grid، دکمه، فرم، جدول). پس این یک وابستگی خارجی *و* یک درخواست شبکهٔ غیرضروری کمتر شد، بدون اینکه چیزی بشکنه.

### جایگزین با لوکال (نیاز به یک‌بار دانلود دستی توسط شما)
- **فونت‌ها**: به‌جای لینک `fonts.googleapis.com`، حالا `public_html/assets/css/fonts.css` رو با `@font-face` لوکال ساختم. فایل‌های فونت باید داخل `public_html/assets/fonts/vazirmatn/` و `public_html/assets/fonts/noto-naskh-arabic/` قرار بگیرن.
- **Bootstrap CSS**: به‌جای لینک jsDelivr، حالا از `public_html/assets/vendor/bootstrap/bootstrap.rtl.min.css` خونده می‌شه. باید این یک فایل رو دانلود و اونجا بذارید.

### Graceful Degradation (حتی بدون دانلود فایل‌ها، سایت خراب نمی‌شه)
- فونت‌ها: `font-family` همه‌جا یک fallback قوی داره (`'Vazirmatn', Tahoma, 'Segoe UI', Arial, sans-serif`) — اگه فایل فونت لوکال نبود یا لود نشد، مرورگر خودکار میره سراغ Tahoma (که روی همهٔ سیستم‌عامل‌ها هست)، نه اینکه متن ناپدید بشه.
- `font-display: swap` روی همهٔ `@font-face`ها هست — یعنی محتوا فوراً با فونت جایگزین نمایش داده می‌شه و اگه فونت اصلی لود شد جایگزینش می‌کنه، نه اینکه صفحه بلاک بشه تا فونت بیاد.
- چون هیچ درگاه پرداخت یا API خارجی‌ای در پروژه نبود، بخش‌های ۳ و ۵ چک‌لیست اصلی (مدیریت خطای پرداخت/API) از قبل هیچ نقطهٔ شکستی نداشتن — چیزی برای محافظت کردن وجود نداشت.

---

## ۳) فایل‌های تغییریافته
- `templates/menu.html` — حذف لینک Google Fonts، اضافه‌شدن لینک `fonts.css`
- `includes/admin-header.php` — لینک فونت و Bootstrap لوکال شد، fallback فونت تقویت شد
- `includes/admin-footer.php` — حذف کامل `bootstrap.bundle.min.js`
- `public_html/admin/index.php` — همون تغییر (لینک لوکال + fallback فونت)
- `public_html/assets/css/menu.css` — تقویت fallback فونت (بدون تغییر ظاهری در حالت عادی)
- `public_html/.htaccess` — اضافه‌شدن کش مرورگر برای فایل‌های فونت (`woff2`/`woff`)

## ۴) فایل‌های جدید
- `public_html/assets/css/fonts.css` — تعریف `@font-face` لوکال
- `public_html/assets/fonts/vazirmatn/HOW-TO-DOWNLOAD.md`
- `public_html/assets/fonts/noto-naskh-arabic/HOW-TO-DOWNLOAD.md`
- `public_html/assets/vendor/bootstrap/HOW-TO-DOWNLOAD.md`
- همین فایل (`RESILIENCE.md`)

## Migration دیتابیس
هیچ — این تسک هیچ ربطی به دیتابیس نداشت.

---

## ۵) کاری که باید خودتون انجام بدید (یک‌بار، از یک سیستم با اینترنت عادی)

۳ فایل باینری کوچیک دانلود کنید:

1. `bootstrap.rtl.min.css` → بذارید تو `public_html/assets/vendor/bootstrap/`
2. فونت‌های Vazirmatn (وزن‌های 400/500/600/700، فرمت woff2) → `public_html/assets/fonts/vazirmatn/`
3. فونت‌های Noto Naskh Arabic (وزن‌های 500/700، فرمت woff2) → `public_html/assets/fonts/noto-naskh-arabic/`

جزئیات دقیق هر کدوم (لینک منبع، اسم دقیق فایل) داخل `HOW-TO-DOWNLOAD.md` همون پوشه‌ست.
بعد از این کار، کل سایت — چه پنل ادمین چه منوی عمومی — دیگه به هیچ دامنهٔ
خارجی (Google Fonts، jsDelivr و...) وابسته نیست و حتی اگه اینترنت
بین‌المللی قطع بشه، کامل و بدون افت ظاهری کار می‌کنه.

## ۶) تأیید عملکرد
- هیچ فایل PHP منطقی (کلاس‌ها، روت‌ها، دیتابیس) تغییر نکرد — فقط لینک‌های `<link>`/`<script>` در لایهٔ نمایش.
- ساختار، معماری و تمام قابلیت‌های قبلی (لاگین، CRUD محصول/دسته/ایونت/مشتری، تخفیف، تنظیمات) دست‌نخورده باقی موندن.
- تا قبل از دانلود فایل‌های باینری، سایت **کاملاً کار می‌کنه**، فقط با فونت سیستم و بدون استایل Bootstrap در پنل ادمین (منوی عمومی چون از Bootstrap استفاده نمی‌کرد، حتی الان هم کامل و درست دیده می‌شه).
