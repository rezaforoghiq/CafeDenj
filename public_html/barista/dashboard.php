<?php
/**
 * barista/dashboard.php
 * -----------------------------------------------------------------------
 * داشبورد باریستا با همان لایهٔ پنل ادمین، اما محدود به مجوزهای تنظیم‌شده.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/barista-auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireBaristaLogin();

$baristaId = BaristaAuth::id();
if ($baristaId === null || !Auth::can('orders.view')) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$activePage = 'dashboard';
$pageTitle = 'داشبورد';

$stats = Barista::performance((int) $baristaId);
$orders = Order::report(['barista_id' => (string) $baristaId, 'sort' => 'date_desc']);
$statusLabels = ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'completed' => 'تکمیل شده'];

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <span class="header-eyebrow">پنل باریستا</span>
    <h4 class="mb-0">خوش آمدید، <?= htmlspecialchars(Auth::username() ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">اختصاص‌یافته</div><div style="font-size:24px;font-weight:700;"><?= (int) $stats['assigned'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">تکمیل‌شده</div><div style="font-size:24px;font-weight:700;color:var(--green);"><?= (int) $stats['completed'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">مجموع مبلغ</div><div style="font-size:24px;font-weight:700;"><?= number_format((float) $stats['total_amount']) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">میانگین مبلغ</div><div style="font-size:24px;font-weight:700;"><?= number_format((float) $stats['avg_amount']) ?></div></div></div>
</div>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">سفارش‌های اختصاص‌یافته</h5>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>شماره سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="5" class="text-center py-4" style="color:var(--muted);">سفارشی یافت نشد.</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><b>سفارش شماره <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></b></td>
              <td><?= htmlspecialchars($order['customer_name'] ?: $order['phone'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= number_format((float) $order['total_price']) ?></td>
              <td><?= htmlspecialchars($statusLabels[$order['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></td>
              <td style="font-size:12.5px;color:var(--muted)"><?= Jalali::format($order['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
