<?php
/**
 * public_html/admin/reports.php
 * -----------------------------------------------------------------------
 * گزارش‌های مدیریتی پنل، بر اساس سفارش‌های تکمیل‌شده.
 * جدول محصولات، پرفروش‌ترین‌ها، فروش ساعتی و عملکرد باریستا.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();

$activePage = 'reports';
$pageTitle = 'گزارش‌ها';

$range = (string) ($_GET['range'] ?? 'today');
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
[$fromDate, $toDate] = Barista::resolveRange($range, $from, $to);

$rangeLabels = [
    'today' => 'امروز',
    'week' => 'این هفته',
    'month' => 'این ماه',
    'custom' => 'بازه دلخواه',
];

$pdo = Database::getConnection();
$where = ['o.status = "completed"'];
$params = [];
if ($fromDate) {
    $where[] = 'o.created_at >= :from';
    $params['from'] = $fromDate . ' 00:00:00';
}
if ($toDate) {
    $where[] = 'o.created_at <= :to';
    $params['to'] = $toDate . ' 23:59:59';
}
$whereSql = implode(' AND ', $where);

$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS orders_count, COALESCE(SUM(o.total_price), 0) AS sales_total, COALESCE(AVG(o.total_price), 0) AS average_order FROM orders o WHERE $whereSql");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['orders_count' => 0, 'sales_total' => 0, 'average_order' => 0];

// Best product (uses the same completed + date range filter)
$bestProductStmt = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.quantity * oi.price) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $whereSql GROUP BY oi.product_id, oi.product_name ORDER BY quantity DESC, revenue DESC LIMIT 1");
$bestProductStmt->execute($params);
$bestProduct = $bestProductStmt->fetch();

// Product sales: aggregate only order_items that belong to completed orders inside the selected date range
// Use a subquery that joins order_items to orders (INNER JOIN) with the completed + date filters, then left-join the aggregated results to products.
$productItemsSub = "SELECT oi.product_id, SUM(oi.quantity) AS quantity, SUM(oi.quantity * oi.price) AS revenue, MAX(o.created_at) AS last_order_at
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'completed'";
if ($fromDate) {
    $productItemsSub .= ' AND o.created_at >= :from';
}
if ($toDate) {
    $productItemsSub .= ' AND o.created_at <= :to';
}
$productItemsSub .= ' GROUP BY oi.product_id';

$productSalesSql = "SELECT p.id AS product_id, p.name AS product_name, COALESCE(s.quantity, 0) AS quantity, COALESCE(s.revenue, 0) AS revenue, s.last_order_at
    FROM products p
    LEFT JOIN (" . $productItemsSub . ") s ON s.product_id = p.id
    ORDER BY quantity DESC, revenue DESC, p.name ASC";
$productSalesStmt = $pdo->prepare($productSalesSql);
$productSalesStmt->execute($params);
$productSales = $productSalesStmt->fetchAll();

$hourlyStmt = $pdo->prepare("SELECT HOUR(o.created_at) AS hour, COUNT(*) AS order_count FROM orders o WHERE $whereSql GROUP BY hour ORDER BY hour ASC");
$hourlyStmt->execute($params);
$hourlyData = $hourlyStmt->fetchAll();

$bestHour = null;
foreach ($hourlyData as $row) {
    if ($bestHour === null || (int) $row['order_count'] > (int) $bestHour['order_count']) {
        $bestHour = $row;
    }
}

$baristaStmt = $pdo->prepare("SELECT b.id, b.full_name, COUNT(o.id) AS completed_orders, COALESCE(SUM(o.total_price), 0) AS total_revenue, COALESCE(AVG(o.total_price), 0) AS average_amount FROM baristas b LEFT JOIN orders o ON o.barista_id = b.id AND o.status = 'completed'" . ($fromDate ? ' AND o.created_at >= :from' : '') . ($toDate ? ' AND o.created_at <= :to' : '') . " GROUP BY b.id, b.full_name ORDER BY completed_orders DESC, total_revenue DESC");
$baristaStmt->execute($params);
$baristaPerformance = $baristaStmt->fetchAll();

// Low sellers: aggregate only order_items linked to completed orders in the selected date range, then include products with zero sales
$lowItemsSub = "SELECT oi.product_id, SUM(oi.quantity) AS quantity FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status = 'completed'";
if ($fromDate) {
    $lowItemsSub .= ' AND o.created_at >= :from';
}
if ($toDate) {
    $lowItemsSub .= ' AND o.created_at <= :to';
}
$lowItemsSub .= ' GROUP BY oi.product_id';

$lowSellersSql = "SELECT p.id, p.name, COALESCE(s.quantity, 0) AS quantity FROM products p LEFT JOIN (" . $lowItemsSub . ") s ON s.product_id = p.id WHERE COALESCE(s.quantity, 0) < 5 ORDER BY quantity ASC, p.name ASC LIMIT 20";
$lowSellersStmt = $pdo->prepare($lowSellersSql);
$lowSellersStmt->execute($params);
$lowSellers = $lowSellersStmt->fetchAll();

// Payment method report (only completed orders, respecting date filters)
$paymentStmt = $pdo->prepare("SELECT o.payment_method, COUNT(*) AS orders_count, COALESCE(SUM(o.total_price), 0) AS total_amount FROM orders o WHERE $whereSql GROUP BY o.payment_method ORDER BY total_amount DESC");
$paymentStmt->execute($params);
$paymentData = $paymentStmt->fetchAll();

function formatMoney(float $value): string
{
    return number_format($value) . ' تومان';
}

require __DIR__ . '/../../includes/admin-header.php';
$selectedRange = $rangeLabels[$range] ?? 'امروز';
?>

<h4 class="mb-4">گزارش‌های مدیریتی</h4>
<div class="card p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end reports-filters">
    <div class="col-6 col-md-3">
      <label class="form-label">بازه گزارش</label>
      <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach ($rangeLabels as $key => $label): ?>
          <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $range === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" data-jalali-picker data-name="from" data-value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" data-jalali-picker data-name="to" data-value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="انتخاب تاریخ">
    </div>
    <div class="col-12 col-md-3 d-flex gap-2 flex-wrap reports-filter-actions">
      <button class="btn btn-gold btn-sm">اعمال فیلتر</button>
      <a href="reports" class="btn btn-outline-light btn-sm" style="border-color:var(--line); color:var(--ivory);">پاک کردن</a>
      <a href="export?type=reports&<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">خروجی اکسل</a>
    </div>
  </form>
</div>

<div class="row g-3 mb-4 reports-kpis">
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div>فروش کل</div><div><?= formatMoney((float) $summary['sales_total']) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div>سفارش‌های تکمیل‌شده</div><div><?= (int) $summary['orders_count'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div>میانگین ارزش سفارش</div><div><?= formatMoney((float) $summary['average_order']) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div>بهترین محصول</div><div><?= htmlspecialchars($bestProduct['product_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div></div>
</div>

<!-- Payment Method Summary Cards -->
<div class="row g-3 mb-4 reports-payment-summary">
  <?php
    $methods = ['card' => 'کارتخوان', 'cash' => 'نقدی', 'transfer' => 'کارت به کارت'];
    // prepare lookup
    $paymentLookup = [];
    foreach ($paymentData as $row) {
      $paymentLookup[$row['payment_method']] = $row;
    }
    foreach ($methods as $key => $label):
      $row = $paymentLookup[$key] ?? null;
      $count = $row ? (int) $row['orders_count'] : 0;
      $total = $row ? (float) $row['total_amount'] : 0.0;
  ?>
  <div class="col-6 col-md-4"><div class="stat-card p-3"><div><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div><div><?= (int) $count ?> سفارش — <?= formatMoney($total) ?></div></div></div>
  <?php endforeach; ?>
</div>

<!-- Payment Methods Table -->
<div class="card p-3 mb-4">
  <h5 class="mb-3">گزارش روش‌های پرداخت</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>روش پرداخت</th><th>تعداد سفارش‌های تکمیل‌شده</th><th>مبلغ کل</th></tr></thead>
      <tbody>
        <?php if (empty($paymentData)): ?>
          <tr><td colspan="3" class="text-center py-4" style="color:var(--muted);">هیچ سفارش تکمیل‌شده‌ای در بازه انتخاب‌شده وجود ندارد.</td></tr>
        <?php endif; ?>
        <?php foreach ($paymentData as $p): ?>
          <tr>
            <td><?= htmlspecialchars($methods[$p['payment_method']] ?? ($p['payment_method'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $p['orders_count'] ?></td>
            <td><?= formatMoney((float) $p['total_amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5 class="mb-3">گزارش فروش محصولات</h5>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>محصول</th><th>تعداد فروخته</th><th>درآمد</th><th>آخرین سفارش</th></tr></thead>
          <tbody>
            <?php if (empty($productSales)): ?>
              <tr><td colspan="4" class="text-center py-4" style="color:var(--muted);">هیچ سفارش تکمیل‌شده‌ای در بازه انتخاب‌شده وجود ندارد.</td></tr>
            <?php endif; ?>
            <?php foreach ($productSales as $product): ?>
              <tr>
                <td><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $product['quantity'] ?></td>
                <td><?= formatMoney((float) $product['revenue']) ?></td>
                <td><?= htmlspecialchars(Jalali::format($product['last_order_at']), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 mb-3">
      <h5 class="mb-3">پرفروش‌ترین محصولات</h5>
      <?php if (empty($productSales)): ?>
        <div style="color:var(--muted);font-size:13px;">هیچ سفارش تکمیل‌شده‌ای در بازه انتخاب‌شده وجود ندارد.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>ردیف</th><th>محصول</th><th>تعداد فروش</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($productSales, 0, 10) as $index => $product): ?>
                <tr>
                  <td><?= $index + 1 ?></td>
                  <td><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= (int) $product['quantity'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div class="card p-3">
      <h5 class="mb-3">محصولات کم‌فروش</h5>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>محصول</th><th>تعداد فروش</th></tr></thead>
          <tbody>
            <?php if (empty($lowSellers)): ?>
              <tr><td colspan="2" class="text-center py-4" style="color:var(--muted);">محصولی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($lowSellers as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $item['quantity'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5 class="mb-3">تحلیل ساعات فروش</h5>
      <?php if (empty($hourlyData)): ?>
        <div style="color:var(--muted);font-size:13px;">هیچ سفارش تکمیل‌شده‌ای در بازه انتخاب‌شده وجود ندارد.</div>
      <?php else: ?>
        <div style="display:grid;gap:10px;">
          <?php $maxCount = max(array_column($hourlyData, 'order_count')); ?>
          <?php foreach ($hourlyData as $entry): ?>
            <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="min-width:55px;display:inline-block;color:var(--muted);"><?= htmlspecialchars(str_pad((string) $entry['hour'], 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?>:00</span>
                <div style="flex:1;height:10px;background:var(--line);border-radius:999px;overflow:hidden;"><div style="width:<?= $maxCount > 0 ? round((int) $entry['order_count'] / $maxCount * 100) : 0 ?>%;height:100%;background:var(--primary);"></div></div>
              </div>
              <div style="font-weight:700;"><?= (int) $entry['order_count'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-3" style="font-size:13px;color:var(--muted);">
          بهترین ساعت فروش: <strong><?= $bestHour ? htmlspecialchars(str_pad((string) $bestHour['hour'], 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') . ':00' : '—' ?></strong>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3">
      <h5 class="mb-3">گزارش عملکرد باریستاها</h5>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>باریستا</th><th>سفارش‌های تکمیل‌شده</th><th>کل فروش</th><th>میانگین سفارش</th></tr></thead>
          <tbody>
            <?php if (empty($baristaPerformance)): ?>
              <tr><td colspan="4" class="text-center py-4" style="color:var(--muted);">باریستایی ثبت نشده است.</td></tr>
            <?php endif; ?>
            <?php foreach ($baristaPerformance as $barista): ?>
              <tr>
                <td><?= htmlspecialchars($barista['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $barista['completed_orders'] ?></td>
                <td><?= formatMoney((float) $barista['total_revenue']) ?></td>
                <td><?= formatMoney((float) $barista['average_amount']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
