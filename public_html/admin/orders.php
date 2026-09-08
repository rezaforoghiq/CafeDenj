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
require_once __DIR__ . '/../../classes/PrintJob.php';
require_once __DIR__ . '/../../classes/Setting.php';

requireLogin();
requirePermission('orders.view');

$activePage = 'orders';
$pageTitle  = 'سفارش‌ها';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? 'status';
    $orderId = (int) ($_POST['id'] ?? 0);

    if (Auth::isBarista()) {
        if (!Auth::can('orders.update_status')) {
            http_response_code(403);
            exit('Access denied');
        }
        if ($action === 'assign_barista') {
            $_SESSION['flash_error'] = 'باریستا اجازهٔ تغییر باریستای سفارش را ندارد.';
            header('Location: orders');
            exit;
        }
        if ($action === 'delete') {
            $_SESSION['flash_error'] = 'باریستا اجازهٔ حذف سفارش را ندارد.';
            header('Location: orders');
            exit;
        }
        if ($action === 'print_invoice' && !Auth::can('orders.print')) {
            $_SESSION['flash_error'] = 'شما اجازهٔ چاپ فاکتور ندارید.';
            header('Location: orders');
            exit;
        }
    }

    if ($action === 'assign_barista') {
        if (Auth::isBarista()) {
            $_SESSION['flash_error'] = 'باریستا نمی‌تواند باریستای دیگری را برای سفارش تعیین کند.';
            header('Location: orders');
            exit;
        }
        $baristaId = (int) ($_POST['barista_id'] ?? 0);
        Order::assignBarista((int) $_POST['id'], $baristaId ?: null);
    } elseif ($action === 'delete') {
        if (Auth::isBarista()) {
            $_SESSION['flash_error'] = 'باریستا اجازهٔ حذف سفارش را ندارد.';
            header('Location: orders');
            exit;
        }
        if ($orderId <= 0 || !Order::delete($orderId, Auth::id(), Auth::username())) {
            $_SESSION['flash_error'] = 'سفارش مورد نظر یافت نشد یا قابل حذف نیست.';
        } else {
            $_SESSION['flash_success'] = 'سفارش با موفقیت حذف شد.';
        }
    } elseif ($action === 'print_invoice') {
        if (!Auth::isAdmin() && !Auth::can('orders.print')) {
            $_SESSION['flash_error'] = 'شما اجازهٔ چاپ فاکتور ندارید.';
            header('Location: orders');
            exit;
        }
        require_once __DIR__ . '/../../classes/PrintJob.php';
        if (PrintJob::isAutomatic()) {
            $jobId = $orderId > 0 ? PrintJob::createCustomerInvoiceForOrder($orderId) : null;
            if ($jobId === null) {
                $_SESSION['flash_error'] = 'ایجاد سفارش چاپ فاکتور انجام نشد.';
            } else {
                $_SESSION['flash_success'] = 'درخواست چاپ فاکتور ثبت شد.';
            }
        } else {
            // Manual mode: trigger browser print for customer invoice without calling Windows Print API
            $_SESSION['manual_print'] = ['id' => $orderId, 'type' => 'customer'];
        }
    } else {
        $status = (string) ($_POST['status'] ?? '');
        $paymentMethod = isset($_POST['payment_method']) ? trim((string) $_POST['payment_method']) : null;
        $allowed = ['card','cash','transfer'];
        if ($status === 'completed' && !in_array($paymentMethod, $allowed, true)) {
            $_SESSION['flash_error'] = 'وقتی سفارش تکمیل می‌شود، روش پرداخت را انتخاب کنید.';
            header('Location: orders?' . http_build_query($_GET));
            exit;
        }

        $prevOrder = Order::findById($orderId);
        $prevStatus = $prevOrder['status'] ?? null;

        if (Auth::isBarista()) {
            $ownedOrder = Order::findById($orderId);
            if (!$ownedOrder) {
                $_SESSION['flash_error'] = 'سفارش مورد نظر یافت نشد.';
                header('Location: orders');
                exit;
            }
            // Allow baristas to act on unassigned pending orders (they will claim them on status change).
            $isPendingUnassigned = ($ownedOrder['status'] === 'pending' && empty($ownedOrder['barista_id']));
            if (! $isPendingUnassigned && (int) ($ownedOrder['barista_id'] ?? 0) !== (int) Auth::currentBaristaId()) {
                $_SESSION['flash_error'] = 'شما فقط می‌توانید سفارش‌های خودتان را تغییر وضعیت دهید.';
                header('Location: orders');
                exit;
            }
            Order::status($orderId, $status, Auth::currentBaristaId(), Auth::username(), 'barista', $paymentMethod);
        } else {
            Order::status($orderId, $status, null, null, 'admin', $paymentMethod);
        }

        // Barista Auto Print Trigger: when transitioning to 'approved' in manual mode
        if ($status === 'approved') {
            require_once __DIR__ . '/../../classes/PrintJob.php';
            if (PrintJob::isManual()) {
                $_SESSION['manual_print'] = ['id' => $orderId, 'type' => 'barista'];
            }
        }
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

if (Auth::isBarista()) {
    $filters['barista_id'] = (string) Auth::currentBaristaId();
    // Show pending orders to all baristas, while other statuses remain scoped to the assigned barista
    $filters['barista_pending_all'] = true;
}

function renderAdminOrderTableRow(array $order, array $statusLabels, array $baristas): void {
?>
      <tr data-order-id="<?= (int) $order['id'] ?>" data-order-status="<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
        <td data-label="شماره سفارش"><b>سفارش شماره <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></b><small class="d-block mt-1" style="color:var(--muted);direction:ltr;text-align:right"><?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></small></td>
        <td data-label="مشتری"><?= htmlspecialchars($order['customer_name'] ?: $order['phone'], ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="باریستا">
          <?php if (Auth::isBarista()): ?>
            <span class="text-light"><?= htmlspecialchars($order['barista_name'] ?? Auth::username(), ENT_QUOTES, 'UTF-8') ?></span>
          <?php else: ?>
            <form method="post" class="d-flex gap-1 order-barista-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="assign_barista">
              <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
              <select name="barista_id" class="form-select form-select-sm">
                <option value="">— بدون باریستا —</option>
                <?php foreach ($baristas as $b): ?><option value="<?= $b['id'] ?>" <?= (int) $order['barista_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-outline-light btn-sm">ثبت</button>
            </form>
          <?php endif; ?>
        </td>
        <td data-label="مبلغ"><?= number_format((float) $order['total_price']) ?></td>
        <td data-label="وضعیت"><?= htmlspecialchars($statusLabels[$order['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="تاریخ ثبت" style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($order['created_at']) ?></td>
        <td data-label="تاریخ تأیید" style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($order['approved_at'] ?? null) ?></td>
        <td data-label="عملیات">
          <div class="d-flex flex-wrap gap-1 align-items-center order-quick-actions" style="margin:0;">
            <a href="orders?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['view' => $order['id']])), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">مشاهده</a>
            <?php if (Auth::isAdmin() || Auth::can('orders.print')): ?>
              <?php if (PrintJob::isManual()): ?>
                <a href="../receipt.php?id=<?= (int) $order['id'] ?>&type=customer&autoprint=1" target="_blank" class="btn btn-sm btn-outline-light btn-manual-print" data-order-id="<?= (int) $order['id'] ?>" style="border-color:var(--line); color:var(--ivory);" title="چاپ فاکتور مشتری">چاپ فاکتور</a>
                <?php if (in_array($order['status'], ['approved', 'completed'], true)): ?>
                  <a href="../receipt.php?id=<?= (int) $order['id'] ?>&type=barista&autoprint=1" target="_blank" class="btn btn-sm btn-outline-light btn-manual-print" data-order-id="<?= (int) $order['id'] ?>" style="border-color:var(--line); color:var(--ivory);" title="چاپ برگه آماده‌سازی باریستا">برگه باریستا</a>
                <?php endif; ?>
              <?php else: ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="print_invoice">
                  <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">چاپ فاکتور</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (Auth::isAdmin()): ?>
              <form method="post" class="d-inline" data-use-custom-confirm data-confirm-text="آیا از حذف این سفارش مطمئن هستید؟ این عملیات قابل بازگشت نیست.">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">حذف</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if (Auth::isAdmin() || Auth::can('orders.update_status')): ?>
          <form method="post" class="d-flex gap-1 flex-wrap align-items-center mt-2 order-status-controls" data-order-status-form style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <select name="status" class="form-select form-select-sm" style="max-width:110px;flex:0 0 auto">
              <?php foreach ($statusLabels as $status => $label): ?><option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
            <select name="payment_method" class="form-select form-select-sm" style="max-width:110px;flex:0 0 auto">
              <option value="">روش پرداخت</option>
              <option value="card">کارتخوان</option>
              <option value="cash">نقدی</option>
              <option value="transfer">کارت به کارت</option>
            </select>
            <div class="w-100 d-flex justify-content-center mt-2 order-status-submit">
              <button class="btn btn-gold btn-sm">ذخیره</button>
            </div>
          </form>
          <?php endif; ?>
        </td>
      </tr>
<?php
}

if (isset($_GET['poll']) && $_GET['poll'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
    $allCurrentOrders = Order::report($filters);
    $activeIds = array_map(function($o) { return (int)$o['id']; }, $allCurrentOrders);

    $maxId = 0;
    $pendingIds = [];
    $orderStatuses = [];
    foreach ($allCurrentOrders as $o) {
        $oid = (int) $o['id'];
        if ($oid > $maxId) {
            $maxId = $oid;
        }
        $st = (string) ($o['status'] ?? '');
        $orderStatuses[$oid] = $st;
        if ($st === 'pending') {
            $pendingIds[] = $oid;
        }
    }

    $newOrders = [];
    if ($afterId > 0) {
        $pollFilters = $filters;
        $pollFilters['after_id'] = $afterId;
        $newOrders = Order::report($pollFilters);
    } elseif ($afterId === 0 && count($allCurrentOrders) > 0) {
        // If initial table was completely empty, all current orders are new
        $newOrders = $allCurrentOrders;
    }
    $baristas = Barista::activeOnly();

    $origGet = $_GET;
    unset($_GET['poll'], $_GET['after_id']);
    ob_start();
    foreach ($newOrders as $order) {
        renderAdminOrderTableRow($order, $statusLabels, $baristas);
    }
    $html = ob_get_clean();
    $_GET = $origGet;

    $reminderInterval = Setting::getInt('order_reminder_interval', 7);
    if ($reminderInterval < 1 || $reminderInterval > 50) {
        $reminderInterval = 7;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode([
        'success' => true,
        'count' => count($newOrders),
        'after_id' => $afterId,
        'max_id' => $maxId,
        'total_count' => count($allCurrentOrders),
        'total_count_display' => Jalali::digits((string)count($allCurrentOrders)),
        'active_ids' => $activeIds,
        'pending_ids' => $pendingIds,
        'order_statuses' => $orderStatuses,
        'reminder_interval' => $reminderInterval,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$orders   = Order::report($filters);
$baristas = Barista::activeOnly();
$viewOrder = null;
$viewOrderId = (int) ($_GET['view'] ?? 0);
if ($viewOrderId > 0) {
    $viewOrder = Order::findById($viewOrderId);
    if (Auth::isBarista()) {
        if (!$viewOrder || (int) ($viewOrder['barista_id'] ?? 0) !== (int) Auth::currentBaristaId()) {
            $viewOrder = null;
        }
    }
}

require __DIR__ . '/../../includes/admin-header.php';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$manualPrint = $_SESSION['manual_print'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['manual_print']);
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="m-0">مدیریت و گزارش سفارش‌ها</h4>
  <button id="toggleOrderSoundBtn" type="button" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1" style="border-color:var(--line); color:var(--gold-soft);" title="فعال/غیرفعال‌سازی صدای سفارش جدید">
    <span id="orderSoundIcon">🔔</span>
    <span id="orderSoundLabel">صدای اعلان: فعال</span>
  </button>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($manualPrint && !empty($manualPrint['id'])): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 mb-3" style="font-size:13.5px;">
    <div>
      <strong>سفارش شماره <?= (int)$manualPrint['id'] ?> با موفقیت تأیید شد.</strong>
      <span class="d-block" style="font-size:12px;opacity:0.9;">در صورتی که پنجرهٔ چاپ برگه باریستا به صورت خودکار باز نشد، روی دکمه مقابل کلیک کنید:</span>
    </div>
    <a href="../receipt.php?id=<?= (int)$manualPrint['id'] ?>&type=<?= htmlspecialchars((string)$manualPrint['type'], ENT_QUOTES, 'UTF-8') ?>&autoprint=1" target="_blank" class="btn btn-sm btn-gold btn-manual-print">
      🖨️ چاپ برگه باریستا (سفارش #<?= (int)$manualPrint['id'] ?>)
    </a>
  </div>
<?php endif; ?>

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
    <?php if (!Auth::isBarista()): ?>
    <div class="col-6 col-md-2">
      <label class="form-label">باریستا</label>
      <select name="barista_id" class="form-select form-select-sm">
        <option value="">همه</option>
        <?php foreach ($baristas as $b): ?><option value="<?= $b['id'] ?>" <?= $filters['barista_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
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
  <h5 class="mb-3">جزئیات سفارش شماره <?= htmlspecialchars($viewOrder['order_number'], ENT_QUOTES, 'UTF-8') ?></h5>
  <div class="mb-3">شماره سفارش: <strong><?= htmlspecialchars($viewOrder['order_number'], ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="mb-3">مشتری: <strong><?= htmlspecialchars($viewOrder['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: htmlspecialchars($viewOrder['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="mb-3">وضعیت: <strong><?= htmlspecialchars($statusLabels[$viewOrder['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></strong></div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>محصول</th>
          <th>قیمت اصلی</th>
          <th>تخفیف</th>
          <th>قیمت بعد از تخفیف</th>
          <th>تعداد</th>
          <th>جمع</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($viewOrder['items'] as $item): 
          $origPrice = (isset($item['original_price']) && (float) $item['original_price'] > 0)
              ? (float) $item['original_price']
              : (float) $item['price'];
          $finalPrice = (float) $item['price'];
          $qty = (int) $item['quantity'];
          $discountAmount = isset($item['discount_amount']) && (float) $item['discount_amount'] > 0
              ? (float) $item['discount_amount']
              : max(0, $origPrice - $finalPrice);
          $discountPercent = !empty($item['discount_percent']) ? (int) $item['discount_percent'] : 0;
          if ($discountPercent === 0 && $origPrice > 0 && $discountAmount > 0) {
              $discountPercent = (int) round(($discountAmount / $origPrice) * 100);
          }
          $lineTotal = $finalPrice * $qty;
        ?>
        <tr>
          <td><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format($origPrice) ?> تومان</td>
          <td><?= $discountAmount > 0 ? number_format($discountAmount) . ' تومان' . ($discountPercent > 0 ? ' (' . $discountPercent . '٪)' : '') : '۰ تومان' ?></td>
          <td><?= number_format($finalPrice) ?> تومان</td>
          <td><?= $qty ?></td>
          <td><?= number_format($lineTotal) ?> تومان</td>
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
</div>
<?php endif; ?>

<div class="table-responsive orders-table" data-printing-method="<?= htmlspecialchars(PrintJob::getPrintingMethod(), ENT_QUOTES, 'UTF-8') ?>">
  <table class="table align-middle">
    <thead>
      <tr><th>شماره سفارش</th><th>مشتری</th><th>باریستا</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ ثبت</th><th>تاریخ تأیید</th><th>عملیات</th></tr>
    </thead>
<?php
  $initialPendingIds = [];
  $maxInitialOrderId = 0;
  foreach ($orders as $ord) {
      $oid = (int) $ord['id'];
      if ($oid > $maxInitialOrderId) {
          $maxInitialOrderId = $oid;
      }
      if (($ord['status'] ?? '') === 'pending') {
          $initialPendingIds[] = $oid;
      }
  }
  $currentReminderInterval = Setting::getInt('order_reminder_interval', 7);
  if ($currentReminderInterval < 1 || $currentReminderInterval > 50) {
      $currentReminderInterval = 7;
  }
?>
    <tbody id="adminOrdersTableBody" 
           data-max-order-id="<?= (int) $maxInitialOrderId ?>"
           data-pending-ids="<?= htmlspecialchars(json_encode($initialPendingIds), ENT_QUOTES, 'UTF-8') ?>"
           data-reminder-interval="<?= (int) $currentReminderInterval ?>">
      <?php if (empty($orders)): ?>
        <tr id="noOrdersRow" class="no-orders-row"><td colspan="8" class="text-center py-4" style="color:var(--muted);">سفارشی یافت نشد.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $order): ?>
        <?php renderAdminOrderTableRow($order, $statusLabels, $baristas); ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($manualPrint) && !empty($manualPrint['id'])): ?>
  <script>
    (function() {
      var printUrl = '../receipt.php?id=<?= (int)$manualPrint['id'] ?>&type=<?= htmlspecialchars((string)$manualPrint['type'], ENT_QUOTES, 'UTF-8') ?>&autoprint=1';
      var w = window.open(printUrl, 'receipt_barista_<?= (int)$manualPrint['id'] ?>', 'width=460,height=680,scrollbars=yes,resizable=yes');
      if (w) {
        w.focus();
      }
    })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>