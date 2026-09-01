<?php
/**
 * admin/dashboard.php
 * -----------------------------------------------------------------------
 * صفحهٔ اصلی پنل بعد از ورود — نمای کلی از وضعیت منو.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Category.php';
require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/ActivityLog.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();

$activePage = 'dashboard';
$pageTitle  = 'داشبورد';

$totalProducts    = Product::count();
$activeProducts   = Product::countActive();
$inactiveProducts = $totalProducts - $activeProducts;
$totalCategories  = Category::count();
$totalCustomers   = Customer::count();

$orderCounts   = Order::dashboardCounts();
$mostActive    = Barista::mostActive();
$baristaReport = Barista::performanceReport();
$dailyCounts   = Order::dailyCounts(14);
$recentLogs    = ActivityLog::recent(8);
$maxDaily      = max(1, ...array_values($dailyCounts));

require __DIR__ . '/../../includes/admin-header.php';
?>

<h4 class="mb-1" style="color:var(--gold-soft);">خوش آمدید، <?= htmlspecialchars(Auth::username() ?? '', ENT_QUOTES, 'UTF-8') ?> 👋</h4>
<p class="mb-4" style="color:var(--muted); font-size:14px;">نمای کلی وضعیت منوی کافه دنج</p>

<div class="row g-3">
  <div class="col-6 col-md-3">
    <div class="stat-card p-3">
      <div style="color:var(--muted); font-size:13px;">کل محصولات</div>
      <div style="font-size:26px; font-weight:700; color:var(--ivory);"><?= $totalProducts ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card p-3">
      <div style="color:var(--muted); font-size:13px;">فعال در منو</div>
      <div style="font-size:26px; font-weight:700; color:var(--gold-soft);"><?= $activeProducts ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card p-3">
      <div style="color:var(--muted); font-size:13px;">غیرفعال</div>
      <div style="font-size:26px; font-weight:700; color:#E27878;"><?= $inactiveProducts ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card p-3">
      <div style="color:var(--muted); font-size:13px;">دسته‌بندی‌ها</div>
      <div style="font-size:26px; font-weight:700; color:var(--ivory);"><?= $totalCategories ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card p-3">
      <div style="color:var(--muted); font-size:13px;">مشتریان ثبت‌شده</div>
      <div style="font-size:26px; font-weight:700; color:var(--ivory);"><?= $totalCustomers ?></div>
    </div>
  </div>
</div>

<div class="mt-4 mb-2">
  <a href="products" class="btn btn-gold btn-sm">مدیریت محصولات</a>
  <a href="events" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">مدیریت ایونت‌ها</a>
</div>

<h5 class="mt-5 mb-3" style="color:var(--gold-soft);">وضعیت سفارش‌ها</h5>
<div class="row g-3">
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--muted); font-size:13px;">سفارش‌های امروز</div><div style="font-size:26px; font-weight:700; color:var(--ivory);"><?= $orderCounts['today'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--muted); font-size:13px;">در انتظار</div><div style="font-size:26px; font-weight:700; color:#E2B878;"><?= $orderCounts['pending'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--muted); font-size:13px;">تأییدشده</div><div style="font-size:26px; font-weight:700; color:var(--gold-soft);"><?= $orderCounts['approved'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card p-3"><div style="color:var(--muted); font-size:13px;">تکمیل‌شده</div><div style="font-size:26px; font-weight:700; color:#3fd49a;"><?= $orderCounts['completed'] ?></div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-7">
    <div class="card p-3">
      <h6 class="mb-3">سفارش‌های ۱۴ روز اخیر</h6>
      <div style="display:flex;align-items:flex-end;gap:4px;height:140px;">
        <?php foreach ($dailyCounts as $day => $count): ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;" title="<?= Jalali::formatDate($day) ?>: <?= $count ?> سفارش">
            <div style="width:100%;max-width:22px;background:var(--primary,#315efb);border-radius:4px 4px 0 0;height:<?= max(3, (int) round($count / $maxDaily * 110)) ?>px;transition:height .3s;"></div>
            <small style="font-size:9px;color:var(--muted);white-space:nowrap;"><?= Jalali::digits(date('j', strtotime($day))) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card p-3">
      <h6 class="mb-3">فعال‌ترین باریستا</h6>
      <?php if ($mostActive): ?>
        <div style="font-size:18px;font-weight:700;color:var(--gold-soft);"><?= htmlspecialchars($mostActive['barista']['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div style="color:var(--muted);font-size:12.5px;margin-top:4px;"><?= (int) $mostActive['stats']['completed'] ?> سفارش تکمیل‌شده از مجموع <?= (int) $mostActive['stats']['assigned'] ?> سفارش</div>
      <?php else: ?>
        <div style="color:var(--muted);font-size:13px;">هنوز سفارش تکمیل‌شده‌ای ثبت نشده.</div>
      <?php endif; ?>
      <hr style="border-color:var(--line);">
      <h6 class="mb-2" style="font-size:13px;color:var(--muted);">تعداد سفارش هر باریستا</h6>
      <?php foreach (array_slice($baristaReport, 0, 5) as $entry): ?>
        <div class="d-flex justify-content-between" style="font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line);">
          <span><?= htmlspecialchars($entry['barista']['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
          <b><?= (int) $entry['stats']['assigned'] ?></b>
        </div>
      <?php endforeach; ?>
      <?php if (empty($baristaReport)): ?><div style="color:var(--muted);font-size:12.5px;">باریستایی ثبت نشده.</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="card p-3 mt-3">
  <h6 class="mb-3">آخرین فعالیت‌های سیستم</h6>
  <?php if (empty($recentLogs)): ?>
    <div style="color:var(--muted);font-size:13px;">فعالیتی ثبت نشده.</div>
  <?php endif; ?>
  <?php foreach ($recentLogs as $log): ?>
    <div class="d-flex justify-content-between align-items-center" style="font-size:12.5px;padding:7px 0;border-bottom:1px solid var(--line);">
      <span><b><?= htmlspecialchars($log['user_label'] ?: ActivityLog::roleLabel($log['role']), ENT_QUOTES, 'UTF-8') ?></b> — <?= htmlspecialchars(ActivityLog::actionLabel($log['action']), ENT_QUOTES, 'UTF-8') ?></span>
      <span style="color:var(--muted);"><?= Jalali::format($log['created_at']) ?></span>
    </div>
  <?php endforeach; ?>
  <a href="activity-log" class="btn btn-sm btn-outline-light mt-3">مشاهدهٔ لاگ کامل</a>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
