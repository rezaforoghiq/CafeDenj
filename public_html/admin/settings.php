<?php
/**
 * admin/settings.php
 * -----------------------------------------------------------------------
 * تنظیمات عمومی سایت. فعلاً فقط یک تنظیم دارد: الزامی/اختیاری بودن
 * شماره تماس در فرم پاپ‌آپ ایونت. اگر بعداً تنظیم دیگری لازم شد، همین
 * صفحه و همین کلاس Setting قابل توسعه است — نیازی به ساختار جدید نیست.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Setting.php';

requireLogin();

$activePage = 'settings';
$pageTitle  = 'تنظیمات';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $phoneRequired = ($_POST['phone_required'] ?? '1') === '1' ? '1' : '0';
        Setting::set('phone_required', $phoneRequired);

        $_SESSION['flash_success'] = 'تنظیمات با موفقیت ذخیره شد.';
        header('Location: settings');
        exit;
    }
}

$phoneRequired = Setting::getBool('phone_required', true);

require __DIR__ . '/../../includes/admin-header.php';
?>

<h4 class="mb-4" style="color:var(--gold-soft);">تنظیمات عمومی سایت</h4>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card p-4" style="max-width:480px;">
  <h6 class="mb-3" style="color:var(--ivory);">شمارهٔ تماس در فرم ثبت‌نام مشتریان</h6>
  <p style="color:var(--muted); font-size:13px; margin-top:-8px; margin-bottom:18px;">
    این تنظیم روی فرم پاپ‌آپ ایونت (صفحهٔ اصلی منو) اثر می‌گذارد.
  </p>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="mb-4">
      <label class="form-label" style="font-size:13px;">وضعیت شمارهٔ موبایل</label>
      <select name="phone_required" class="form-select">
        <option value="1" <?= $phoneRequired ? 'selected' : '' ?>>الزامی (Required)</option>
        <option value="0" <?= !$phoneRequired ? 'selected' : '' ?>>اختیاری (Optional)</option>
      </select>
    </div>

    <button type="submit" class="btn btn-gold btn-sm">ذخیرهٔ تنظیمات</button>
  </form>
</div>

<!-- Print Bridge quick docs -->
<div class="card p-3 mt-4" style="max-width:760px;">
    <h6 class="mb-2">Windows Print Bridge (API) — راه‌اندازی سریع</h6>
    <p style="color:var(--muted);font-size:13px;">این بخش راهنمای کوتاه و عملی برای اتصال برنامهٔ ویندوزی چاپ به سایت است. برای تنظیم کامل‌تر و راهنمای گام‌به‌گام، فایل‌های سند در پوشهٔ <code>/docs</code> را ببینید.</p>
    <ul style="font-size:13px;color:var(--muted);">
      <li><strong>آدرس API:</strong> <code><?= htmlspecialchars((isset($_SERVER['HTTPS'])? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-site') . '/api/print_bridge.php', ENT_QUOTES, 'UTF-8') ?></code></li>
      <li><strong>احراز هویت:</strong> توکن دستگاه در تنظیمات (کلید <code>print_bridge_token</code>) — برنامهٔ ویندوز باید هدر <code>X-Device-Token</code> را ارسال کند.</li>
      <li><strong>Endpoints:</strong>
        <ul>
          <li><code>GET ?action=next</code> — گرفتن شغل بعدی</li>
          <li><code>POST ?action=confirm</code> — تایید چاپ موفق (بدنهٔ JSON {"job_id":123})</li>
          <li><code>POST ?action=failed</code> — گزارش خطا (بدنهٔ JSON {"job_id":123, "error":"..."})</li>
        </ul>
      </li>
    </ul>
    <div style="font-size:13px;color:var(--muted);">برای ایمن‌سازی، مقدار کلید <code>print_bridge_token</code> را در جدول <code>settings</code> اضافه یا ویرایش کنید. همچنین می‌توانید تعداد تلاش مجاز چاپ را با کلید <code>print_bridge_max_retries</code> تنظیم کنید (پیش‌فرض 3).</div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
