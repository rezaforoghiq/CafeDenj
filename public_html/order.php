<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Order.php';
requireCustomerLogin();
$order = Order::findMine((int) ($_GET['id'] ?? 0), (int) $_SESSION['customer_id']);
$statusLabels = ['pending'=>'در انتظار تأیید','approved'=>'تأیید شده و در حال آماده‌سازی','rejected'=>'رد شده','completed'=>'تکمیل و تحویل داده شده'];
if (!$order) { http_response_code(404); exit('سفارش پیدا نشد.'); }

if (isset($_GET['poll']) && $_GET['poll'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'label' => $statusLabels[$order['status']] ?? $order['status'],
            'class' => 'status-' . $order['status'],
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/css/customer-auth.css"><title>فاکتور</title></head>
<body class="customer-auth"><main class="auth-card" data-order-detail-id="<?= (int)$order['id'] ?>"><div class="auth-brand"><h1>فاکتور سفارش شماره <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></h1><p>وضعیت: <strong data-order-detail-status="<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>"><?= $statusLabels[$order['status']] ?? 'نامشخص' ?></strong></p></div>
<?php foreach ($order['items'] as $item): ?><p><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?> × <?= (int) $item['quantity'] ?><span style="float:left"><?= number_format((float) $item['price'] * (int) $item['quantity']) ?></span></p><?php endforeach; ?>
<hr>
<?php if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0): ?>
  <?php $original = (float)$order['total_price'] + (float)$order['discount_amount']; ?>
  <p>مبلغ اولیه: <b><?= number_format($original) ?> تومان</b></p>
  <p>کوپن: <b><?= htmlspecialchars($order['coupon_code'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></b></p>
  <p>درصد تخفیف: <b><?= (int)($order['coupon_percent'] ?? 0) ?>%</b></p>
  <p>مقدار تخفیف: <b><?= number_format((float)$order['discount_amount']) ?> تومان</b></p>
<?php endif; ?>
<p>مبلغ کل: <b><?= number_format((float) $order['total_price']) ?> تومان</b></p>
<div style="display:flex; gap:8px; margin-top:12px;">
  <a class="back-home" style="margin-top:0; flex:1; text-align:center; background:#223046;" href="receipt.php?id=<?= (int)$order['id'] ?>&type=customer" target="_blank">چاپ فاکتور 🖨️</a>
  <a class="back-home" style="margin-top:0; flex:1; text-align:center;" href="orders">سفارش‌های من</a>
</div>
</main><script src="assets/js/orders.js?v=7"></script></body></html>