<?php
/**
 * admin/activity-log.php
 * -----------------------------------------------------------------------
 * نمایش، جستجو و فیلتر لاگ فعالیت سیستم.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/ActivityLog.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();
requireAdmin();

$activePage = 'activity-log';
$pageTitle  = 'لاگ فعالیت';

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$flashError = null;

// حفظ فیلترهای فعلی برای ریدایرکت بعد از حذف (تا کاربر در همان صفحه/فیلتر بماند)
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$redirectUrl = 'activity-log' . ($queryString ? ('?' . $queryString) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'delete') {
            // حذف یک رکورد
            $id = (int) ($_POST['id'] ?? 0);
            if ($id && ActivityLog::delete($id)) {
                $_SESSION['flash_success'] = 'رکورد لاگ حذف شد.';
            } else {
                $flashError = 'حذف رکورد ممکن نشد.';
            }
        } elseif ($postAction === 'delete_selected') {
            // حذف چند رکورد انتخاب‌شده
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            $count = ActivityLog::deleteMany($ids);
            $_SESSION['flash_success'] = $count > 0 ? ($count . ' رکورد لاگ حذف شد.') : 'رکوردی برای حذف انتخاب نشده بود.';
        } elseif ($postAction === 'delete_filtered') {
            // حذف تمام نتایجی که با فیلتر فعلی مطابقت دارند
            $filterPayload = [
                'role'   => (string) ($_POST['role'] ?? ''),
                'action' => (string) ($_POST['action_filter'] ?? ''),
                'q'      => trim((string) ($_POST['q'] ?? '')),
                'from'   => (string) ($_POST['from'] ?? ''),
                'to'     => (string) ($_POST['to'] ?? ''),
            ];
            $count = ActivityLog::deleteFiltered($filterPayload);
            $_SESSION['flash_success'] = $count . ' رکورد لاگ (بر اساس فیلتر) حذف شد.';
        } elseif ($postAction === 'clear_all') {
            // حذف کامل تمام لاگ‌ها
            $count = ActivityLog::clearAll();
            $_SESSION['flash_success'] = 'تمام لاگ‌ها حذف شد (' . $count . ' رکورد).';
        }

        if ($flashError === null) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

$filters = [
    'role'   => (string) ($_GET['role'] ?? ''),
    'action' => (string) ($_GET['action'] ?? ''),
    'q'      => trim((string) ($_GET['q'] ?? '')),
    'from'   => (string) ($_GET['from'] ?? ''),
    'to'     => (string) ($_GET['to'] ?? ''),
    'page'   => (int) ($_GET['page'] ?? 1),
];

$result = ActivityLog::search($filters);

require __DIR__ . '/../../includes/admin-header.php';
$hasActiveFilter = (bool) ($filters['role'] || $filters['action'] || $filters['q'] || $filters['from'] || $filters['to']);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="mb-0">لاگ فعالیت سیستم</h4>
  <form method="POST" class="d-inline" onsubmit="return confirm('تمام رکوردهای لاگ برای همیشه حذف شوند؟ این کار غیرقابل بازگشت است.');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="clear_all">
    <button type="submit" class="btn btn-sm btn-outline-danger">پاک کردن همهٔ لاگ‌ها</button>
  </form>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="card p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label">جستجو</label>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="نام کاربر یا توضیحات" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">نقش</label>
      <select name="role" class="form-select form-select-sm">
        <option value="">همه</option>
        <?php foreach (ActivityLog::ROLES as $key => $label): ?><option value="<?= $key ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">نوع عملیات</label>
      <select name="action" class="form-select form-select-sm">
        <option value="">همه</option>
        <?php foreach (ActivityLog::ACTIONS as $key => $label): ?><option value="<?= $key ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">از تاریخ</label>
      <input type="text" data-jalali-picker data-name="from" data-value="<?= htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">تا تاریخ</label>
      <input type="text" data-jalali-picker data-name="to" data-value="<?= htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-12"><button class="btn btn-gold btn-sm">اعمال فیلتر</button> <a href="activity-log" class="btn btn-outline-light btn-sm">حذف فیلترها</a>
      <span style="color:var(--muted);font-size:12.5px;">مجموع: <?= (int) $result['total'] ?> رکورد</span>
    </div>
  </form>
  <?php if ($hasActiveFilter && (int) $result['total'] > 0): ?>
  <form method="POST" class="mt-2" onsubmit="return confirm('همهٔ <?= (int) $result['total'] ?> رکورد مطابق با فیلتر فعلی حذف شوند؟');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="delete_filtered">
    <input type="hidden" name="role" value="<?= htmlspecialchars($filters['role'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action_filter" value="<?= htmlspecialchars($filters['action'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="from" value="<?= htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="to" value="<?= htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger">حذف همهٔ نتایج فیلترشده (<?= (int) $result['total'] ?> رکورد)</button>
  </form>
  <?php endif; ?>
</div>

<form method="POST" id="bulkDeleteForm" onsubmit="return confirm('رکوردهای انتخاب‌شده حذف شوند؟');">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="delete_selected">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" disabled>حذف انتخاب‌شده‌ها</button>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr>
        <th style="width:36px;"><input type="checkbox" id="selectAllLogs" title="انتخاب همه"></th>
        <th>زمان</th><th>کاربر</th><th>نقش</th><th>عملیات</th><th>سفارش مرتبط</th><th>IP</th><th>جزئیات</th><th>حذف</th>
      </tr></thead>
      <tbody>
        <?php if (empty($result['rows'])): ?>
          <tr><td colspan="9" class="text-center py-4" style="color:var(--muted);">رکوردی یافت نشد.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['rows'] as $log): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int) $log['id'] ?>" class="log-row-checkbox"></td>
          <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($log['created_at']) ?></td>
          <td><?= htmlspecialchars($log['user_label'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge" style="background:var(--primary-soft);color:var(--primary)"><?= htmlspecialchars(ActivityLog::roleLabel($log['role']), ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= htmlspecialchars(ActivityLog::actionLabel($log['action']), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $log['order_id'] ? '#' . (int) $log['order_id'] : '—' ?></td>
          <td style="direction:ltr;text-align:right;font-size:12.5px;color:var(--muted)"><?= htmlspecialchars($log['ip_address'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <details>
              <summary style="cursor:pointer;color:var(--primary);font-size:12.5px;">مشاهده</summary>
              <div style="font-size:12px;color:var(--muted);margin-top:6px;max-width:260px;">
                <div><b>توضیحات:</b> <?= htmlspecialchars($log['description'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                <div style="word-break:break-all;margin-top:4px;"><b>User Agent:</b> <?= htmlspecialchars($log['user_agent'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </details>
          </td>
          <td>
            <button type="submit" form="deleteLogForm<?= (int) $log['id'] ?>" class="btn btn-sm btn-outline-danger" title="حذف این رکورد">حذف</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</form>

<?php foreach ($result['rows'] as $log): ?>
<form method="POST" id="deleteLogForm<?= (int) $log['id'] ?>" class="d-none" onsubmit="return confirm('این رکورد لاگ حذف شود؟');">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
</form>
<?php endforeach; ?>

<script>
(function () {
  var selectAll = document.getElementById('selectAllLogs');
  var checkboxes = document.querySelectorAll('.log-row-checkbox');
  var bulkBtn = document.getElementById('bulkDeleteBtn');

  function refreshBulkBtn() {
    var anyChecked = Array.prototype.some.call(checkboxes, function (c) { return c.checked; });
    bulkBtn.disabled = !anyChecked;
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      checkboxes.forEach(function (c) { c.checked = selectAll.checked; });
      refreshBulkBtn();
    });
  }
  checkboxes.forEach(function (c) { c.addEventListener('change', refreshBulkBtn); });
  refreshBulkBtn();
})();
</script>

<?php if ($result['pages'] > 1): ?>
<nav class="d-flex justify-content-center gap-2 mt-3">
  <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
    <a class="btn btn-sm <?= $p === $result['page'] ? 'btn-gold' : 'btn-outline-light' ?>" href="activity-log?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= Jalali::digits((string) $p) ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
