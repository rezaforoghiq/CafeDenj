<?php
/**
 * admin/settings.php
 * -----------------------------------------------------------------------
 * تنظیمات عمومی سایت و مدیریت دسترسی باریستاها.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Setting.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Permission.php';

requireLogin();
requireAdmin();

$activePage = 'settings';
$pageTitle  = 'تنظیمات';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

$baristas = Barista::all();
$selectedBaristaId = (int) ($_GET['barista_id'] ?? 0);
if ($selectedBaristaId <= 0 && !empty($baristas)) {
    $selectedBaristaId = (int) $baristas[0]['id'];
}
$selectedPermissions = $selectedBaristaId > 0 ? Permission::getBaristaPermissions($selectedBaristaId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        if (($_POST['action'] ?? '') === 'barista_permissions') {
            $baristaId = (int) ($_POST['barista_id'] ?? 0);
            $selected = $_POST['permissions'] ?? [];
            if ($baristaId > 0) {
                $permissionList = [];
                foreach ($selected as $permission) {
                    $permissionList[] = (string) $permission;
                }
                Permission::saveForBarista($baristaId, $permissionList);
                $_SESSION['flash_success'] = 'دسترسی‌های باریستا با موفقیت ذخیره شد.';
                header('Location: settings?barista_id=' . rawurlencode((string) $baristaId));
                exit;
            }
            $flashError = 'باریستا انتخاب نشده است.';
        } elseif (($_POST['action'] ?? '') === 'printing_settings') {
            $method = in_array($_POST['printing_method'] ?? '', ['automatic', 'manual'], true) ? (string)$_POST['printing_method'] : 'automatic';
            Setting::set('printing_method', $method);
            $_SESSION['flash_success'] = 'روش چاپ فاکتور با موفقیت ذخیره شد.';
            header('Location: settings');
            exit;
        }
    }
}

require __DIR__ . '/../../includes/admin-header.php';
?>

<h4 class="mb-4" style="color:var(--gold-soft);">تنظیمات عمومی سایت</h4>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card p-4" style="max-width:640px;">
  <h6 class="mb-3" style="color:var(--ivory);">مدیریت دسترسی باریستا</h6>
  <form method="GET" class="mb-3">
    <label class="form-label">باریستا</label>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <select name="barista_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($baristas as $barista): ?>
          <option value="<?= (int) $barista['id'] ?>" <?= (int) $selectedBaristaId === (int) $barista['id'] ? 'selected' : '' ?>><?= htmlspecialchars($barista['full_name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="barista_permissions">
    <input type="hidden" name="barista_id" value="<?= (int) $selectedBaristaId ?>">
    <div class="row g-2">
      <?php foreach (Permission::all() as $permission): ?>
        <div class="col-12 col-md-6">
          <label class="d-flex align-items-center gap-2 p-2 rounded border" style="border-color:var(--line); background:rgba(255,255,255,0.02);">
            <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permission['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array($permission['slug'], $selectedPermissions, true) ? 'checked' : '' ?>>
            <span style="font-size:13px; color:var(--ivory);"><?= htmlspecialchars($permission['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3"><button type="submit" class="btn btn-gold btn-sm">ذخیره دسترسی‌ها</button></div>
  </form>
</div>

<!-- Printing Method Setting Card -->
<div class="card p-4 mt-4" style="max-width:640px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="mb-0" style="color:var(--ivory);">روش چاپ فاکتور (Printing Method)</h6>
    <?php $currentPrintingMethod = Setting::get('printing_method', 'automatic'); ?>
    <span class="badge" style="background:<?= $currentPrintingMethod === 'automatic' ? 'var(--gold-soft)' : 'var(--blue)' ?>; color:#000; font-size:12px;">
      <?= $currentPrintingMethod === 'automatic' ? 'اتوماتیک (Windows Bridge)' : 'دستی (Browser Print)' ?>
    </span>
  </div>
  
  <p style="color:var(--muted); font-size:13px; margin-bottom:16px;">
    مشخص کنید هنگام کلیک روی دکمه‌های چاپ یا فعال‌شدن تریگر خودکار باریستا، فاکتورها از چه طریقی چاپ شوند.
  </p>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="printing_settings">

    <div class="d-flex flex-column gap-3 mb-3">
      <label class="d-flex align-items-start gap-3 p-3 rounded border" style="border-color:<?= $currentPrintingMethod === 'automatic' ? 'var(--gold-soft)' : 'var(--line)' ?>; background:rgba(255,255,255,0.02); cursor:pointer;">
        <input type="radio" name="printing_method" value="automatic" class="mt-1" <?= $currentPrintingMethod === 'automatic' ? 'checked' : '' ?>>
        <div>
          <strong style="color:var(--ivory); font-size:14px; display:block;">چاپ اتوماتیک (Automatic)</strong>
          <div style="font-size:12.5px; color:var(--muted); margin-top:2px;">
            فاکتورها و برگه‌های آماده‌سازی مستقیماً به صف دیتابیس ارسال شده و توسط نرم‌افزار واسط ویندوز (Windows Print Bridge) بر روی چاپگر حرارتی چاپ می‌شوند.
          </div>
        </div>
      </label>

      <label class="d-flex align-items-start gap-3 p-3 rounded border" style="border-color:<?= $currentPrintingMethod === 'manual' ? 'var(--gold-soft)' : 'var(--line)' ?>; background:rgba(255,255,255,0.02); cursor:pointer;">
        <input type="radio" name="printing_method" value="manual" class="mt-1" <?= $currentPrintingMethod === 'manual' ? 'checked' : '' ?>>
        <div>
          <strong style="color:var(--ivory); font-size:14px; display:block;">چاپ دستی (Manual / Browser Print)</strong>
          <div style="font-size:12.5px; color:var(--muted); margin-top:2px;">
            دیالوگ استاندارد چاپ مرورگر (با ابعاد استاندارد ۸۰ میلی‌متری حرارتی) باز می‌شود. در این حالت هیچ درخواستی به API نرم‌افزار ویندوز ارسال نشده و هیچ صف چاپی تشکیل نمی‌شود.
          </div>
        </div>
      </label>
    </div>

    <button type="submit" class="btn btn-gold btn-sm">ذخیره روش چاپ</button>
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