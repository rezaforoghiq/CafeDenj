<?php
/**
 * admin/admins.php
 * -----------------------------------------------------------------------
 * مدیریت حساب‌های ادمین: افزودن ادمین جدید و حذف ادمین موجود.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/ActivityLog.php';
require_once __DIR__ . '/../../classes/Admin.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();
requireAdmin();

$activePage = 'admins';
$pageTitle  = 'مدیریت ادمین‌ها';

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$flashError = null;
$errors = [];
$old = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $old = ['username' => trim((string) ($_POST['username'] ?? ''))];
            $payload = $old + ['password' => (string) ($_POST['password'] ?? '')];
            $errors = Admin::validate($payload);

            if (empty($errors)) {
                Admin::create($payload);
                ActivityLog::record('admin_create', 'admin', Auth::id(), Auth::username(), null, 'ایجاد ادمین جدید: ' . $old['username']);
                $_SESSION['flash_success'] = 'ادمین جدید با موفقیت ایجاد شد.';
                header('Location: admins');
                exit;
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === Auth::id()) {
                $flashError = 'شما نمی‌توانید حساب خودتان را حذف کنید.';
            } elseif (Admin::count() <= 1) {
                $flashError = 'حداقل یک حساب ادمین باید باقی بماند.';
            } else {
                $a = Admin::find($id);
                Admin::delete($id);
                ActivityLog::record('admin_delete', 'admin', Auth::id(), Auth::username(), null, 'حذف ادمین: ' . ($a['username'] ?? ''));
                $_SESSION['flash_success'] = 'ادمین حذف شد.';
                header('Location: admins');
                exit;
            }
        }
    }
}

$admins = Admin::all();

require __DIR__ . '/../../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
  <h4 class="mb-0">مدیریت ادمین‌ها</h4>
  <a href="#adminForm" class="btn btn-gold btn-sm">+ ادمین جدید</a>
</div>
<p style="color:var(--muted); font-size:12.5px;" class="mb-4">ادمین‌های ایجادشده با نام کاربری و رمز عبوری که اینجا تنظیم می‌کنید، از آدرس <code>/admin/</code> وارد پنل می‌شوند.</p>

<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="card p-3 mb-4" id="adminForm" style="max-width:560px;">
  <h6 class="mb-3">ایجاد ادمین جدید</h6>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="create">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">نام کاربری</label>
        <input type="text" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" style="direction:ltr;text-align:right" value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">رمز عبور</label>
        <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-gold btn-sm">ذخیره</button>
  </form>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr><th>نام کاربری</th><th>تاریخ ایجاد</th><th>عملیات</th></tr>
    </thead>
    <tbody>
      <?php if (empty($admins)): ?>
        <tr><td colspan="3" class="text-center py-4" style="color:var(--muted);">هنوز ادمینی ثبت نشده.</td></tr>
      <?php endif; ?>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td><b><?= htmlspecialchars($a['username'], ENT_QUOTES, 'UTF-8') ?></b><?= ((int) $a['id'] === Auth::id()) ? ' <span class="badge" style="background:var(--green-soft);color:#147454;">شما</span>' : '' ?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($a['created_at']) ?></td>
        <td>
          <form method="POST" class="d-inline" onsubmit="return confirm('این ادمین برای همیشه حذف شود؟');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" <?= ((int) $a['id'] === Auth::id()) ? 'disabled' : '' ?>>حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
