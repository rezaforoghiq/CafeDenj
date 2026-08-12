<?php
/**
 * admin/baristas.php
 * -----------------------------------------------------------------------
 * مدیریت باریستا (CRUD) + گزارش عملکرد هر باریستا با فیلتر بازهٔ زمانی.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/CustomerAuth.php';
require_once __DIR__ . '/../../classes/ActivityLog.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();
requireAdmin();

$activePage = 'baristas';
$pageTitle  = 'مدیریت باریستا';

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$flashError = null;
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$old = ['full_name' => '', 'phone' => '', 'username' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $old = [
                'full_name' => trim((string) ($_POST['full_name'] ?? '')),
                'phone'     => trim((string) ($_POST['phone'] ?? '')),
                'username'  => trim((string) ($_POST['username'] ?? '')),
                'status'    => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            ];
            $payload = $old + ['password' => (string) ($_POST['password'] ?? '')];
            $errors = Barista::validate($payload, $id ?: null);

            if (empty($errors)) {
                if ($id) {
                    Barista::update($id, $payload);
                    ActivityLog::record('barista_update', 'admin', Auth::id(), Auth::username(), null, 'ویرایش باریستا: ' . $old['full_name']);
                    $_SESSION['flash_success'] = 'اطلاعات باریستا با موفقیت به‌روزرسانی شد.';
                } else {
                    Barista::create($payload);
                    ActivityLog::record('barista_create', 'admin', Auth::id(), Auth::username(), null, 'ایجاد باریستای جدید: ' . $old['full_name']);
                    $_SESSION['flash_success'] = 'باریستای جدید با موفقیت ایجاد شد.';
                }
                header('Location: baristas');
                exit;
            }
            $editId = $id;
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $b = Barista::find($id);
            Barista::toggleStatus($id);
            ActivityLog::record('barista_toggle', 'admin', Auth::id(), Auth::username(), null, 'تغییر وضعیت باریستا: ' . ($b['full_name'] ?? ''));
            $_SESSION['flash_success'] = 'وضعیت باریستا تغییر کرد.';
            header('Location: baristas');
            exit;
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $b = Barista::find($id);
            Barista::delete($id);
            ActivityLog::record('barista_delete', 'admin', Auth::id(), Auth::username(), null, 'حذف باریستا: ' . ($b['full_name'] ?? ''));
            $_SESSION['flash_success'] = 'باریستا حذف شد.';
            header('Location: baristas');
            exit;
        }
    }
}

if ($editId && empty($errors)) {
    $b = Barista::find($editId);
    if ($b) {
        $old = ['full_name' => $b['full_name'], 'phone' => $b['phone'], 'username' => $b['username'], 'status' => $b['status']];
    } else {
        $editId = 0;
    }
}

// ---- فیلتر بازهٔ گزارش عملکرد ----
$range = $_GET['range'] ?? 'month';
$customFrom = $_GET['from'] ?? '';
$customTo   = $_GET['to'] ?? '';
[$from, $to] = Barista::resolveRange($range, $customFrom, $customTo);
$report = Barista::performanceReport($from, $to);

$rangeLabels = ['today' => 'امروز', 'week' => 'این هفته', 'month' => 'این ماه', 'custom' => 'بازهٔ دلخواه', 'all' => 'کل بازه'];

require __DIR__ . '/../../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
  <h4 class="mb-0">مدیریت باریستا</h4>
  <a href="baristas?edit=0#baristaForm" class="btn btn-gold btn-sm">+ باریستای جدید</a>
</div>
<p style="color:var(--muted); font-size:12.5px;" class="mb-4">باریستاها با نام کاربری و رمز عبوری که اینجا تنظیم می‌کنید، از آدرس <code>/barista/</code> وارد پنل مخصوص خودشان می‌شوند.</p>

<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if ($editId !== 0 || !empty($errors) || isset($_GET['edit'])): ?>
<div class="card p-3 mb-4" id="baristaForm" style="max-width:560px;">
  <h6 class="mb-3"><?= $editId ? 'ویرایش باریستا' : 'ایجاد باریستای جدید' ?></h6>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId ?>">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">نام و نام خانوادگی</label>
        <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['full_name'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">شماره موبایل</label>
        <input type="text" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" style="direction:ltr;text-align:right" value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">نام کاربری (برای ورود باریستا)</label>
        <input type="text" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" style="direction:ltr;text-align:right" value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">رمز عبور <?= $editId ? '(خالی = بدون تغییر)' : '' ?></label>
        <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">وضعیت</label>
        <select name="status" class="form-select">
          <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
          <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-gold btn-sm">ذخیره</button>
    <a href="baristas" class="btn btn-sm btn-outline-light">انصراف</a>
  </form>
</div>
<?php endif; ?>

<div class="card p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label">بازهٔ گزارش</label>
      <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach (['today' => 'امروز', 'week' => 'این هفته', 'month' => 'این ماه', 'custom' => 'بازهٔ دلخواه', 'all' => 'کل بازه'] as $key => $label): ?>
          <option value="<?= $key ?>" <?= $range === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($range === 'custom'): ?>
    <div class="col-6 col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" data-jalali-picker data-name="from" data-value="<?= htmlspecialchars($customFrom, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" data-jalali-picker data-name="to" data-value="<?= htmlspecialchars($customTo, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-6 col-md-2"><button class="btn btn-gold btn-sm w-100">اعمال فیلتر</button></div>
    <?php endif; ?>
  </form>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>باریستا</th><th>موبایل</th><th>وضعیت</th><th>اختصاص‌یافته</th><th>تکمیل‌شده</th>
        <th>مجموع مبلغ</th><th>میانگین مبلغ</th><th>آخرین سفارش</th><th>آخرین فعالیت</th><th>عملیات</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($report)): ?>
        <tr><td colspan="10" class="text-center py-4" style="color:var(--muted);">هنوز باریستایی ثبت نشده.</td></tr>
      <?php endif; ?>
      <?php foreach ($report as $entry): $b = $entry['barista']; $s = $entry['stats']; ?>
      <tr>
        <td><b><?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?></b><small class="d-block" style="color:var(--muted)">@<?= htmlspecialchars($b['username'], ENT_QUOTES, 'UTF-8') ?></small></td>
        <td style="direction:ltr;text-align:right"><?= htmlspecialchars($b['phone'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge" style="background:<?= $b['status'] === 'active' ? 'var(--green-soft)' : 'var(--red-soft)' ?>;color:<?= $b['status'] === 'active' ? '#147454' : '#bd3e49' ?>"><?= $b['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span></td>
        <td><?= (int) $s['assigned'] ?></td>
        <td><?= (int) $s['completed'] ?></td>
        <td><?= number_format($s['total_amount']) ?></td>
        <td><?= number_format($s['avg_amount']) ?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($s['last_order_at']) ?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($s['last_activity']) ?></td>
        <td>
          <a href="baristas?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-light">ویرایش</a>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-outline-light"><?= $b['status'] === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button>
          </form>
          <form method="POST" class="d-inline" onsubmit="return confirm('این باریستا برای همیشه حذف شود؟');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
