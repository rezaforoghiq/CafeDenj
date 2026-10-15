<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/GuestSession.php';
require_once __DIR__ . '/../classes/Order.php';

$guestToken = GuestSession::getToken();
$orders     = Order::findByGuestTokenAll($guestToken);

$labels = [
    'pending'   => 'در انتظار تأیید',
    'approved'  => 'تأیید شده و در حال آماده‌سازی',
    'rejected'  => 'رد شده',
    'completed' => 'تکمیل و تحویل داده شده',
];

// -----------------------------------------------------------------------
// Poll endpoint (AJAX) — بازگشت وضعیت همهٔ سفارش‌های جلسه
// -----------------------------------------------------------------------
if (isset($_GET['poll']) && $_GET['poll'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $statuses = array_map(fn($o) => [
        'id'           => (int)   $o['id'],
        'order_number' => $o['order_number'],
        'status'       => $o['status'],
        'label'        => $labels[$o['status']] ?? $o['status'],
        'class'        => 'status-' . $o['status'],
        'can_delete'   => $o['status'] === 'pending',
    ], $orders);
    echo json_encode(['success' => true, 'count' => count($statuses), 'statuses' => $statuses], JSON_UNESCAPED_UNICODE);
    exit;
}

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/customer-auth.css">
  <title>سفارش‌های من</title>
</head>
<body class="customer-auth orders-page">
<main class="orders-layout">
  <header class="orders-heading">
    <a href="index">← بازگشت به منو</a>
    <div>
      <h1>سفارش‌های این جلسه</h1>
      <p>وضعیت سفارش‌ها و فاکتورهای خود را اینجا دنبال کنید.</p>
    </div>
  </header>

  <?php if (!$orders): ?>
    <section class="cart-empty">
      <h2>هنوز سفارشی ندارید</h2>
      <p>از منو محصول دلخواهتان را انتخاب کنید.</p>
      <a class="auth-submit" href="index">مشاهده منو</a>
    </section>
  <?php endif; ?>

  <section class="orders-list">
    <?php foreach ($orders as $order): ?>
      <article class="order-card" data-order-id="<?= (int) $order['id'] ?>">
        <div class="order-card-top">
          <div>
            <span class="order-number">سفارش شماره <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!empty($order['table_number'])): ?>
              <span class="order-date" style="margin-right:8px;">میز: <?= htmlspecialchars($order['table_number'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <span class="order-date"><?= htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <span class="order-status status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>"
                data-status="<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
            <?= $labels[$order['status']] ?? 'نامشخص' ?>
          </span>
        </div>
        <div class="order-card-bottom">
          <b><?= number_format((float) $order['total_price']) ?> <small>تومان</small></b>
          <?php if (!empty($order['discount_amount']) && (float) $order['discount_amount'] > 0): ?>
            <div style="font-size:13px;color:var(--muted);">
              کوپن: <?= htmlspecialchars($order['coupon_code'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?>
              — <?= (int) ($order['coupon_percent'] ?? 0) ?>%
            </div>
          <?php endif; ?>
          <div class="order-actions">
            <a href="order?id=<?= (int) $order['id'] ?>">مشاهده جزئیات <span>←</span></a>
            <?php if ($order['status'] === 'pending'): ?>
              <button class="order-delete" data-order-delete="<?= (int) $order['id'] ?>">حذف سفارش</button>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</main>

<div class="order-modal" id="orderModal" aria-hidden="true">
  <div class="order-modal-backdrop"></div>
  <div class="order-modal-card">
    <h2>حذف سفارش</h2>
    <p>از حذف این سفارش مطمئن هستید؟ این عمل قابل بازگشت نیست.</p>
    <div>
      <button id="cancelDelete">انصراف</button>
      <button id="confirmDelete">بله، حذف شود</button>
    </div>
  </div>
</div>
<div class="order-toast" id="orderToast"></div>

<script>window.orderCsrf = <?= json_encode(csrfToken()) ?>;</script>
<script src="assets/js/orders.js?v=7"></script>
</body>
</html>