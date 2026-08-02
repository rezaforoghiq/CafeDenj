<?php
/**
 * barista/dashboard.php
 * -----------------------------------------------------------------------
 * نمای باریستا از سفارش‌های اختصاص‌یافته به او و امکان تکمیل سفارش.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/barista-auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireBaristaLogin();

$baristaId = BaristaAuth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $orderId = (int) ($_POST['id'] ?? 0);
    // باریستا فقط اجازهٔ تکمیل‌کردن سفارش خودش را دارد
    $order = Order::report(['barista_id' => $baristaId]);
    $ids = array_column($order, 'id');
    if (in_array($orderId, $ids, true)) {
        Order::status($orderId, 'completed', $baristaId, BaristaAuth::name(), 'barista');
    }
    header('Location: dashboard');
    exit;
}

$statusLabels = ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'completed' => 'تکمیل شده'];
$stats = Barista::performance($baristaId);
$orders = Order::report(['barista_id' => $baristaId]);
$viewOrder = null;
$viewOrderId = (int) ($_GET['view'] ?? 0);
if ($viewOrderId > 0) {
    $order = Order::findById($viewOrderId);
    if ($order && (int) $order['barista_id'] === $baristaId) {
        $viewOrder = $order;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل باریستا | کافه دنج</title>
<link href="../assets/css/fonts.css" rel="stylesheet">
<link href="../assets/vendor/bootstrap/bootstrap.rtl.min.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body" style="min-width:0;">
<div class="admin-main" style="max-width:1100px;margin:0 auto;padding:24px 18px 60px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div><span class="header-eyebrow">پنل باریستا</span><h4 class="mb-0">خوش آمدید، <?= htmlspecialchars(BaristaAuth::name() ?? '', ENT_QUOTES, 'UTF-8') ?></h4></div>
    <a href="logout" class="btn btn-outline-light btn-sm">خروج از پنل</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">اختصاص‌یافته</div><div style="font-size:24px;font-weight:700;"><?= (int) $stats['assigned'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">تکمیل‌شده</div><div style="font-size:24px;font-weight:700;color:var(--green);"><?= (int) $stats['completed'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">مجموع مبلغ</div><div style="font-size:24px;font-weight:700;"><?= number_format($stats['total_amount']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--ink-muted);font-size:13px;">میانگین مبلغ</div><div style="font-size:24px;font-weight:700;"><?= number_format($stats['avg_amount']) ?></div></div></div>
  </div>

  <?php if ($viewOrder !== null): ?>
  <div class="card p-3 mb-4">
    <h5 class="mb-3">جزئیات سفارش شماره <?= (int) $viewOrder['id'] ?></h5>
    <div class="mb-2">شماره سفارش: <strong><?= htmlspecialchars($viewOrder['order_number'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="mb-2">مشتری: <strong><?= htmlspecialchars($viewOrder['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: htmlspecialchars($viewOrder['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
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
    <div class="mt-3">مبلغ کل: <strong><?= number_format((float) $viewOrder['total_price']) ?> تومان</strong></div>
    <div class="mb-2">روش پرداخت: <strong><?= htmlspecialchars(($viewOrder['payment_method'] === 'card' ? 'کارتخوان' : ($viewOrder['payment_method'] === 'cash' ? 'نقدی' : ($viewOrder['payment_method'] === 'transfer' ? 'کارت به کارت' : '—'))), ENT_QUOTES, 'UTF-8') ?></strong></div>
    <a href="dashboard" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">بستن جزئیات</a>
  </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>شماره سفارش</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ ثبت</th><th>عملیات</th></tr></thead>
      <tbody>
        <?php if (empty($orders)): ?><tr><td colspan="5" class="text-center py-4" style="color:var(--ink-muted);">هنوز سفارشی به شما اختصاص داده نشده.</td></tr><?php endif; ?>
        <?php foreach ($orders as $order): ?>
        <tr>
          <td><b>سفارش شماره <?= (int) $order['id'] ?></b></td>
          <td><?= number_format((float) $order['total_price']) ?> تومان</td>
          <td><?= htmlspecialchars($statusLabels[$order['status']] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="font-size:12.5px;color:var(--ink-muted)"><?= Jalali::format($order['created_at']) ?></td>
          <td class="d-flex gap-2">
            <a href="dashboard?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['view' => $order['id']])), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">مشاهده</a>
            <?php if ($order['status'] === 'approved'): ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
              <button class="btn btn-gold btn-sm">تکمیل سفارش</button>
            </form>
            <?php else: ?><span style="color:var(--ink-muted);font-size:12px;">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
