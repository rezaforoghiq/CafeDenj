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

$discountErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        Customer::delete((int) $_POST['id']);
        $flashSuccess = 'مشتری از لیست حذف شد.';
    }
}

// ---------------------------------------------------------------
// تخفیف اختصاصی مشتری — ثبت/ویرایش
// ---------------------------------------------------------------
$discountCustomerId = (int) ($_GET['discount'] ?? 0);
$discountOld = ['discount_type' => 'percentage', 'discount_value' => '', 'expires_at' => '', 'is_active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_discount') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $discountOld = [
            'discount_type'  => $_POST['discount_type'] ?? 'percentage',
            'discount_value' => trim($_POST['discount_value'] ?? ''),
            'expires_at'     => trim($_POST['expires_at'] ?? ''),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        $discountErrors = Customer::validateDiscount($discountOld);

        if (empty($discountErrors) && $customerId > 0) {
            Customer::setDiscount($customerId, $discountOld);
            $_SESSION['flash_success'] = 'تخفیف مشتری با موفقیت ذخیره شد.';
            header('Location: customers');
            exit;
        }

        $discountCustomerId = $customerId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_discount') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        Customer::removeDiscount((int) $_POST['customer_id']);
        $flashSuccess = 'تخفیف مشتری حذف شد.';
    } else {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    }
}

$discountCustomer = null;
if ($discountCustomerId > 0) {
    foreach (Customer::all() as $c) {
        if ((int) $c['id'] === $discountCustomerId) {
            $discountCustomer = $c;
            break;
        }
    }

    // اگر فرم تازه submit نشده (یعنی صرفاً از طریق لینک ?discount=ID باز شده)،
    // مقدار فعلی تخفیف (در صورت وجود) برای پیش‌پر کردن فرم خوانده شود
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $discountCustomer !== null) {
        $existingDiscount = Customer::getActiveDiscount($discountCustomerId);
        if ($existingDiscount) {
            $discountOld = [
                'discount_type'  => $existingDiscount['discount_type'],
                'discount_value' => $existingDiscount['discount_value'],
                'expires_at'     => $existingDiscount['expires_at'] ?? '',
                'is_active'      => (int) $existingDiscount['is_active'],
            ];
        }
    }
}

$customers = Customer::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0" style="color:var(--gold-soft);">مشتریان ثبت‌شده</h4>
  <span style="color:var(--muted); font-size:13px;">مجموع: <?= count($customers) ?> نفر</span>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($discountCustomer !== null): ?>
<div class="card p-3 mb-4" style="max-width:480px;">
  <h6 class="mb-3" style="color:var(--ivory);">
    تخفیف اختصاصی برای «<?= htmlspecialchars(trim(($discountCustomer['first_name'] ?? '') . ' ' . ($discountCustomer['last_name'] ?? '')) ?: $discountCustomer['phone'], ENT_QUOTES, 'UTF-8') ?>»"  </h6>

  <?php if (!empty($discountErrors)): ?>
    <div class="alert alert-danger py-2 px-3" style="font-size:13px;">لطفاً خطاهای فرم را بررسی کنید.</div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="save_discount">
    <input type="hidden" name="customer_id" value="<?= $discountCustomer['id'] ?>">

    <div class="row">
      <div class="col-6 mb-3">
        <label class="form-label" style="font-size:13px;">نوع تخفیف</label>
        <select name="discount_type" class="form-select form-select-sm <?= isset($discountErrors['discount_type']) ? 'is-invalid' : '' ?>">
          <option value="percentage" <?= $discountOld['discount_type'] === 'percentage' ? 'selected' : '' ?>>درصدی (%)</option>
          <option value="fixed" <?= $discountOld['discount_type'] === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت (تومان)</option>
        </select>
      </div>
      <div class="col-6 mb-3">
        <label class="form-label" style="font-size:13px;">مقدار تخفیف</label>
        <input type="number" name="discount_value" min="0" step="0.01" class="form-control form-control-sm <?= isset($discountErrors['discount_value']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars((string) $discountOld['discount_value'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($discountErrors['discount_value'])): ?><div class="invalid-feedback"><?= htmlspecialchars($discountErrors['discount_value'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" style="font-size:13px;">تاریخ انقضا (اختیاری)</label>
      <input type="text" data-jalali-picker data-name="expires_at" data-value="<?= htmlspecialchars((string) $discountOld['expires_at'], ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="is_active" id="discountActive" <?= $discountOld['is_active'] ? 'checked' : '' ?>>
      <label class="form-check-label" style="font-size:13px; color:var(--muted);" for="discountActive">فعال باشد</label>
    </div>

    <button type="submit" class="btn btn-gold btn-sm">ذخیرهٔ تخفیف</button>
    <a href="customers" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">انصراف</a>
  </form>
</div>
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
        <?php $activeDiscount = Customer::getActiveDiscount((int) $customer['id']); ?>
        <tr>
          <td><?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="direction:ltr; text-align:right;"><?= htmlspecialchars($customer['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="color:var(--muted);"><?= htmlspecialchars($customer['source'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php if ($activeDiscount): ?>
              <span class="badge" style="background:var(--gold); color:#17130D;">
                <?= $activeDiscount['discount_type'] === 'percentage'
                      ? htmlspecialchars((string) $activeDiscount['discount_value'], ENT_QUOTES, 'UTF-8') . '%'
                      : number_format((float) $activeDiscount['discount_value']) . ' تومان' ?>
              </span>
            <?php else: ?>
              <span style="color:var(--muted); font-size:12.5px;">—</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted); font-size:13px;"><?= htmlspecialchars($customer['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <a href="customers?discount=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">
              <?= $activeDiscount ? 'ویرایش تخفیف' : 'تنظیم تخفیف' ?>
            </a>
            <?php if ($activeDiscount): ?>
              <form method="POST" class="d-inline" onsubmit="return confirm('تخفیف این مشتری حذف شود؟');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="remove_discount">
                <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">حذف تخفیف</button>
              </form>
            <?php endif; ?>
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
