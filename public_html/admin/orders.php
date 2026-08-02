<?php
/**
 * admin/orders.php
 * -----------------------------------------------------------------------
 * مدیریت سفارش‌ها + گزارش سفارش‌ها: تغییر وضعیت، اختصاص/تغییر باریستا،
 * جستجو، فیلتر و مرتب‌سازی. تاریخ‌ها فقط برای نمایش به شمسی تبدیل می‌شوند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();

$activePage = 'orders';
$pageTitle  = 'سفارش‌ها';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? 'status';
    if ($action === 'assign_barista') {
        $baristaId = (int) ($_POST['barista_id'] ?? 0);
        Order::assignBarista((int) $_POST['id'], $baristaId ?: null);
    } else {
        $status = (string) ($_POST['status'] ?? '');
        $paymentMethod = isset($_POST['payment_method']) ? trim((string) $_POST['payment_method']) : null;
        // when admin marks completed, payment method is required and must be one of allowed
        $allowed = ['card','cash','transfer'];
        if ($status === 'completed' && !in_array($paymentMethod, $allowed, true)) {
            $_SESSION['flash_error'] = 'وقتی سفارش تکمیل می‌شود، روش پرداخت را انتخاب کنید.';
            header('Location: orders?' . http_build_query($_GET));
            exit;
        }
        Order::status((int) $_POST['id'], $status, null, null, 'admin', $paymentMethod);
    }
    header('Location: orders?' . http_build_query($_GET));
    exit;
}

$statusLabels = ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'completed' => 'تکمیل شده'];

$filters = [
    'q'          => trim((string) ($_GET['q'] ?? '')),
    'status'     => (string) ($_GET['status'] ?? ''),
    'barista_id' => (string) ($_GET['barista_id'] ?? ''),
    'from'       => (string) ($_GET['from'] ?? ''),
    'to'         => (string) ($_GET['to'] ?? ''),
    'sort'       => (string) ($_GET['sort'] ?? 'date_desc'),
];

$orders   = Order::report($filters);
$baristas = Barista::activeOnly();
$viewOrder = null;
$viewOrderId = (int) ($_GET['view'] ?? 0);
if ($viewOrderId > 0) {
    $viewOrder = Order::findById($viewOrderId);
}

require __DIR__ . '/../../includes/admin-header.php';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<h4 class="mb-4">مدیریت و گزارش سفارش‌ها</h4>
<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="card p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label">جستجو</label>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="شماره سفارش، موبایل، نام مشتری" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">وضعیت</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">همه</option>
        <?php foreach ($statusLabels as $st => $lbl): ?><option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">باریستا</label>
      <select name="barista_id" class="form-select form-select-sm">
        <option value="">همه</option>
        <?php foreach ($baristas as $b): ?><option value="<?= $b['id'] ?>" <?= $filters['barista_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
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
    <div class="col-6 col-md-1">
      <label class="form-label">مرتب‌سازی</label>
      <select name="sort" class="form-select form-select-sm">
        <option value="date_desc" <?= $filters['sort'] === 'date_desc' ? 'selected' : '' ?>>جدیدترین</option>
        <option value="date_asc" <?= $filters['sort'] === 'date_asc' ? 'selected' : '' ?>>قدیمی‌ترین</option>
        <option value="amount_desc" <?= $filters['sort'] === 'amount_desc' ? 'selected' : '' ?>>مبلغ زیاد</option>
        <option value="amount_asc" <?= $filters['sort'] === 'amount_asc' ? 'selected' : '' ?>>مبلغ کم</option>
      </select>
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-gold btn-sm">اعمال فیلتر</button>
      <a href="orders" class="btn btn-outline-light btn-sm">حذف فیلترها</a>
      <a href="export?type=orders&<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-light">خروجی اکسل</a>
    </div>
  </form>
</div>

<?php if ($viewOrder !== null): ?>
<div class="card p-3 mb-4">
  <h5 class="mb-3">جزئیات سفارش شماره <?= (int) $viewOrder['id'] ?></h5>
  <div class="mb-3">شماره سفارش: <strong><?= htmlspecialchars($viewOrder['order_number'], ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="mb-3">مشتری: <strong><?= htmlspecialchars($viewOrder['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: htmlspecialchars($viewOrder['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="mb-3">وضعیت: <strong><?= htmlspecialchars($statusLabels[$viewOrder['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>محصول</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
      <tbody>
        <?php foreach ($viewOrder['items'] as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int) $item['quantity'] ?></td>
          <td><?= number_format((float) $item['price']) ?> تومان</td>
          <td><?= number_format((float) $item['price'] * (int) $item['quantity']) ?> تومان</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($viewOrder['discount_amount']) && (float)$viewOrder['discount_amount'] > 0): ?>
    <div class="mb-2">تخفیف (کوپن <?= htmlspecialchars($viewOrder['coupon_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>): <strong><?= number_format((float)$viewOrder['discount_amount']) ?> تومان</strong></div>
  <?php endif; ?>
  <div class="mt-3">مبلغ کل: <strong><?= number_format((float) $viewOrder['total_price']) ?> تومان</strong></div>
  <div class="mb-2">روش پرداخت: <strong><?= htmlspecialchars(($viewOrder['payment_method'] === 'card' ? 'کارتخوان' : ($viewOrder['payment_method'] === 'cash' ? 'نقدی' : ($viewOrder['payment_method'] === 'transfer' ? 'کارت به کارت' : '—'))), ENT_QUOTES, 'UTF-8') ?></strong></div>
  <a href="orders" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">بستن جزئیات</a>
  <a href="../print_order.php?id=<?= (int) $viewOrder['id'] ?>" target="_blank" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">چاپ سفارش</a>
</div>
<?php endif; ?>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr><th>شماره سفارش</th><th>مشتری</th><th>باریستا</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ ثبت</th><th>تاریخ تأیید</th><th>عملیات</th></tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="8" class="text-center py-4" style="color:var(--muted);">سفارشی یافت نشد.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $order): ?>
      <tr>
        <td><b>سفارش شماره <?= (int) $order['id'] ?></b><small class="d-block mt-1" style="color:var(--muted);direction:ltr;text-align:right"><?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></small></td>
        <td><?= htmlspecialchars($order['customer_name'] ?: $order['phone'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" class="d-flex gap-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="assign_barista">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <select name="barista_id" class="form-select form-select-sm">
              <option value="">— بدون باریستا —</option>
              <?php foreach ($baristas as $b): ?><option value="<?= $b['id'] ?>" <?= (int) $order['barista_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-outline-light btn-sm">ثبت</button>
          </form>
        </td>
        <td><?= number_format((float) $order['total_price']) ?></td>
        <td><?= htmlspecialchars($statusLabels[$order['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($order['created_at']) ?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($order['approved_at'] ?? null) ?></td>
        <td>
          <form method="post" class="d-flex gap-1 flex-wrap align-items-center" data-order-status-form style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <a href="orders?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['view' => $order['id']])), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">مشاهده</a>
            <a href="../print_order.php?id=<?= (int) $order['id'] ?>" target="_blank" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">چاپ</a>
            <select name="status" class="form-select form-select-sm" style="max-width:110px;flex:0 0 auto">
              <?php foreach ($statusLabels as $status => $label): ?><option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
            <select name="payment_method" class="form-select form-select-sm" style="max-width:110px;flex:0 0 auto">
              <option value="">روش پرداخت</option>
              <option value="card">کارتخوان</option>
              <option value="cash">نقدی</option>
              <option value="transfer">کارت به کارت</option>
            </select>
            <div class="w-100 d-flex justify-content-center mt-2">
              <button class="btn btn-gold btn-sm">ذخیره</button>
            </div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
