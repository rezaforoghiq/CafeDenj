<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Order.php';
requireCustomerLogin();
$order = Order::findMine((int) ($_GET['id'] ?? 0), (int) $_SESSION['customer_id']);
$statusLabels = ['pending'=>'در انتظار تأیید','approved'=>'تأیید شده','rejected'=>'رد شده','completed'=>'تکمیل شده'];
if (!$order) { http_response_code(404); exit('سفارش پیدا نشد.'); }
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/css/customer-auth.css"><title>فاکتور</title></head>
<body class="customer-auth"><main class="auth-card"><div class="auth-brand"><h1>فاکتور سفارش شماره <?= (int) $order['id'] ?></h1><p>وضعیت: <?= $statusLabels[$order['status']] ?? 'نامشخص' ?></p></div>
<?php foreach ($order['items'] as $item): ?><p><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?> × <?= (int) $item['quantity'] ?><span style="float:left"><?= number_format((float) $item['price'] * (int) $item['quantity']) ?></span></p><?php endforeach; ?>
<hr><p>مبلغ کل: <b><?= number_format((float) $order['total_price']) ?> تومان</b></p><a class="back-home" href="orders">سفارش‌های من</a></main></body></html>
