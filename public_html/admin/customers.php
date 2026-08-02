<?php
/**
 * admin/customers.php
 * -----------------------------------------------------------------------
 * لیست مشتریانی که شماره‌شان از طریق فرم پاپ‌آپ ایونت (یا هر منبع دیگر)
 * ثبت شده است.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Customer.php';

requireLogin();

$activePage = 'customers';
$pageTitle  = 'مشتریان';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $deleted = Customer::delete((int) $_POST['id']);
        if ($deleted) {
            $flashSuccess = 'مشتری از لیست حذف شد.';
        } else {
            $flashError = 'مشتری دارای سفارش یا وابستگی‌هایی است و قابل حذف نیست.';
        }
    }
}

// Note: customer-specific discounts have been removed. Use the Coupons system in the admin panel to manage discount codes.

$customers = Customer::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0" style="color:var(--gold-soft);">مشتریان ثبت‌شده</h4>
  <div>
    <a href="coupons" class="btn btn-sm btn-outline-light me-2" style="border-color:var(--line); color:var(--ivory);">مدیریت کوپن‌ها</a>
    <span style="color:var(--muted); font-size:13px;">مجموع: <?= count($customers) ?> نفر</span>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>


<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>نام</th>
        <th>شماره تماس</th>
        <th>منبع ثبت</th>
        <th>تخفیف اختصاصی</th>
        <th>تاریخ ثبت</th>
        <th>عملیات</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($customers)): ?>
        <tr><td colspan="6" class="text-center py-4" style="color:var(--muted);">هنوز هیچ مشتری‌ای ثبت نشده.</td></tr>
      <?php endif; ?>

      <?php foreach ($customers as $customer): ?>
        <tr>
          <td><?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="direction:ltr; text-align:right;"><?= htmlspecialchars($customer['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="color:var(--muted);"><?= htmlspecialchars($customer['source'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <span style="color:var(--muted); font-size:12.5px;">—</span>
          </td>
          <td style="color:var(--muted); font-size:13px;"><?= htmlspecialchars($customer['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <a href="coupons" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">مدیریت کوپن‌ها</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('این مشتری از لیست حذف شود؟');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $customer['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">حذف مشتری</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
