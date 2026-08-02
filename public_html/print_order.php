<?php
declare(strict_types=1);
// print_order.php - shared printable order receipt for admin and barista

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/BaristaAuth.php';
require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../classes/Setting.php';
require_once __DIR__ . '/../classes/Jalali.php';

// get order id
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid order id';
    exit;
}

$order = Order::findById($id);
if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

// Authorization: allow admin users, or barista assigned to this order
$isAdmin = class_exists('Auth') && Auth::check();
$isBarista = class_exists('BaristaAuth') && BaristaAuth::check();

if (!$isAdmin && !$isBarista) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($isBarista && !$isAdmin) {
    // barista may only print orders assigned to them
    $baristaId = BaristaAuth::id();
    if ((int) $order['barista_id'] !== (int) $baristaId) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

// cafe name from settings
$cafeName = Setting::get('site_name', 'کافه دنج');

// paper width
$paper = ($_GET['paper'] ?? '80') === '58' ? '58' : '80';

// prepare display values
$orderNumber = htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8');
$orderDateTime = $order['created_at'];
$orderDate = Jalali::formatDate($orderDateTime);
$orderTime = $orderDateTime ? date('H:i', strtotime($orderDateTime)) : '';
$customerName = htmlspecialchars($order['customer_name'] ?: $order['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$baristaName = htmlspecialchars($order['barista_name'] ?? '', ENT_QUOTES, 'UTF-8');
$statusLabel = htmlspecialchars(['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'completed' => 'تکمیل شده'][$order['status']] ?? $order['status'], ENT_QUOTES, 'UTF-8');
$items = $order['items'] ?? [];
$customerNote = htmlspecialchars($order['customer_note'] ?? '', ENT_QUOTES, 'UTF-8');

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>چاپ سفارش #<?= (int)$order['id'] ?></title>
<style>
/* Basic print-friendly receipt styles */
:root{--max-80:80mm;--max-58:58mm}
body{font-family: Tahoma, Arial, sans-serif; background:#fff; color:#000; padding:10px;}
.receipt{margin:0 auto; padding:8px; border:0; background:#fff;}
.receipt.header{ text-align:center }
.receipt .cafe{font-size:16px;font-weight:700;margin-bottom:6px}
.receipt .order-number{font-size:20px;font-weight:800;margin:6px 0}
.receipt .meta{font-size:12px;color:#222;margin-bottom:8px}
.items{margin:8px 0;}
.item{display:flex;justify-content:space-between;align-items:flex-start;font-size:13px;padding:6px 0;border-bottom:1px dashed #ddd}
.item .name{flex:1;text-align:right}
.item .qty{width:40px;text-align:left}
.note{font-size:12px;margin-top:10px;border-top:1px solid #eee;padding-top:8px}
.btns{margin:10px 0;text-align:center}
.print-btn{display:inline-block;padding:6px 10px;border:1px solid #333;background:#fff;color:#000;text-decoration:none;font-size:13px;margin-right:6px}

/* Paper widths */
.wrapper-80{width:var(--max-80);}
.wrapper-58{width:var(--max-58);}

@media print{
  body{padding:0}
  .print-controls{display:none}
  .receipt{box-shadow:none;margin:0}
}

/* Compact adjustments for thermal printers */
@media print and (max-width:400px){
  .item{font-size:12px}
  .order-number{font-size:18px}
}
</style>
</head>
<body>
<div class="receipt <?= $paper === '58' ? 'wrapper-58' : 'wrapper-80' ?>">
  <div class="header">
    <div class="cafe"><?= htmlspecialchars($cafeName, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="order-number">سفارش: <?= $orderNumber ?></div>
    <div class="meta">تاریخ: <?= $orderDate ?> &nbsp; &nbsp; زمان: <?= htmlspecialchars($orderTime, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="meta">مشتری: <?= $customerName ?><?= $baristaName ? ' &nbsp; | &nbsp; باریستا: ' . $baristaName : '' ?></div>
    <div class="meta">وضعیت: <?= $statusLabel ?></div>
  </div>

  <div class="items">
    <?php foreach ($items as $it): ?>
      <div class="item">
        <div class="name"><?= htmlspecialchars($it['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($it['notes'])): ?><div class="note" style="border-top:none;padding-top:4px;font-size:12px;"><?= htmlspecialchars($it['notes'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>
        <div class="qty">x<?= (int) $it['quantity'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($customerNote): ?><div class="note">یادداشت سفارش: <?= $customerNote ?></div><?php endif; ?>

  <div class="print-controls btns">
    <a href="#" onclick="window.print();return false;" class="print-btn">نمایش چاپگر (Print)</a>
    <a href="javascript:window.close()" class="print-btn">بستن</a>
  </div>

  <div style="font-size:11px;color:#666;margin-top:8px;text-align:center">این برگه فقط برای آماده‌سازی سفارش است — اطلاعات مالی نشان داده نمی‌شود.</div>
</div>
</body>
</html>