<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Setting.php';

$activePage = $activePage ?? '';
$navItems = [
    'dashboard'  => ['label' => 'داشبورد', 'icon' => 'grid', 'permission' => null],
    'products'   => ['label' => 'محصولات', 'icon' => 'cup', 'permission' => 'products.view'],
    'categories' => ['label' => 'دسته‌بندی‌ها', 'icon' => 'layers', 'permission' => 'products.manage'],
    'menu-display' => ['label' => 'مدیریت نمایش محصولات', 'icon' => 'sort', 'permission' => 'products.manage'],
    'events'     => ['label' => 'رویدادها', 'icon' => 'calendar', 'permission' => null],
    'customers'  => ['label' => 'مشتریان', 'icon' => 'users', 'permission' => null],
    'orders'     => ['label' => 'سفارش‌ها', 'icon' => 'calendar', 'permission' => 'orders.view'],
    'reports'    => ['label' => 'گزارش‌ها', 'icon' => 'chart', 'permission' => 'reports.view'],
    'baristas'   => ['label' => 'باریستاها', 'icon' => 'barista', 'permission' => null],
    'activity-log' => ['label' => 'لاگ فعالیت', 'icon' => 'log', 'permission' => null],
    'admins'     => ['label' => 'ادمین‌ها', 'icon' => 'users', 'permission' => null],
    'settings'   => ['label' => 'تنظیمات', 'icon' => 'settings', 'permission' => 'settings.view'],
];

foreach ($navItems as $path => $item) {
    if ($path === 'dashboard') {
        continue;
    }
    if (Auth::isAdmin()) {
        continue;
    }
    if ($item['permission'] === null) {
        $navItems[$path] = null;
        continue;
    }
    if (!Auth::can((string) $item['permission'])) {
        $navItems[$path] = null;
    }
}
$navItems = array_filter($navItems, fn ($item) => $item !== null);

function adminIcon(string $name): string {
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'cup' => '<path d="M5 9h11v5a5.5 5.5 0 0 1-11 0V9Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8 4v2M12 4v2M4 21h14"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20.3h-3v-.08A1.7 1.7 0 0 0 10.68 18.66a1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7.02 15 1.7 1.7 0 0 0 5.46 14H5.4v-3h.06A1.7 1.7 0 0 0 7.02 10a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 11.7 4.78V4.7h3v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0 0 19.4 10c.24.58.8.96 1.43 1h.06v3h-.06c-.63.04-1.19.42-1.43 1Z"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'barista' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>',
        'chart' => '<path d="M5 19h14"/><path d="M8 19V11"/><path d="M12 19V7"/><path d="M16 19V15"/>',
        'sort' => '<path d="m3 16 4 4 4-4M7 20V4M21 8l-4-4-4 4M17 4v16"/>',
        'log' => '<path d="M9 4h11v16H9zM4 4h2v16H4zM4 8h1M4 12h1M4 16h1"/>',
    ];
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#101927">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' | ' : '' ?>کافه دنج</title>
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <meta name="order-reminder-interval" content="<?= max(1, min(50, Setting::getInt('order_reminder_interval', 7) ?: 7)) ?>">
  <script>
    window.CAFE_SETTINGS = window.CAFE_SETTINGS || {};
    window.CAFE_SETTINGS.reminderInterval = <?= max(1, min(50, Setting::getInt('order_reminder_interval', 7) ?: 7)) ?>;
  </script>
</head>
<body class="admin-body">
<div class="admin-shell">
  <div class="admin-scrim" data-drawer-close></div>
  <aside class="admin-sidebar" id="adminSidebar" aria-label="ناوبری اصلی">
    <div class="sidebar-brand"><span class="brand-mark">ک</span><span>کافه دنج</span><small>مدیریت</small></div>
    <nav class="sidebar-nav">
      <p class="nav-caption">منوی اصلی</p>
      <?php foreach ($navItems as $path => $item): ?>
        <a href="<?= $path ?>" class="nav-item <?= $activePage === $path ? 'is-active' : '' ?>"><?= adminIcon($item['icon']) ?><span><?= $item['label'] ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><span class="status-dot"></span><span>سیستم در دسترس است</span></div>
  </aside>
  <div class="admin-workspace">
    <header class="admin-header">
      <button class="icon-button mobile-menu" type="button" id="adminMenuToggle" aria-label="باز کردن منو" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
      <div class="header-title"><span class="header-eyebrow">پنل مدیریت</span><strong><?= htmlspecialchars($pageTitle ?? 'داشبورد', ENT_QUOTES, 'UTF-8') ?></strong></div>
      <div class="header-actions">
        <button class="icon-button notification-button" type="button" data-toast="همه‌چیز مرتب است؛ اعلان جدیدی ندارید." aria-label="اعلان‌ها"><?= adminIcon('bell') ?><i></i></button>
        <div class="user-menu">
          <button class="profile-trigger" type="button" aria-expanded="false" aria-controls="profileMenu">
            <span class="avatar"><?= htmlspecialchars(function_exists('mb_substr') ? mb_substr(Auth::username() ?? 'م', 0, 1) : 'م', ENT_QUOTES, 'UTF-8') ?></span>
          <span class="profile-copy"><b><?= htmlspecialchars(Auth::username() ?? 'مدیر', ENT_QUOTES, 'UTF-8') ?></b><small><?= Auth::isAdmin() ? 'مدیر سیستم' : 'باریستا' ?></small></span><?= adminIcon('chevron') ?>
          </button>
        <div class="profile-menu" id="profileMenu"><div><b><?= htmlspecialchars(Auth::username() ?? 'مدیر', ENT_QUOTES, 'UTF-8') ?></b><small><?= Auth::isAdmin() ? 'حساب کاربری مدیر' : 'حساب کاربری باریستا' ?></small></div><?php if (Auth::isAdmin() || Auth::can('settings.view')): ?><a href="settings"><?= adminIcon('settings') ?>تنظیمات حساب</a><?php endif; ?><a href="logout" class="danger-link"><?= adminIcon('logout') ?>خروج از پنل</a></div>
        </div>
      </div>
    </header>
    <main class="admin-main">