import { store } from '../store';
import { Jalali } from '../jalali';
import { Product } from '../types';

export function renderAdminLogin(error?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ورود به پنل مدیریت | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b121e; font-family: 'Vazirmatn', sans-serif; color: #fff; margin:0; }
    .login-box { background: #131c2b; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 36px; width: 100%; max-width: 400px; box-shadow: 0 12px 36px rgba(0,0,0,0.5); }
    .btn-login { width: 100%; background: #d4a373; color: #0b121e; font-weight: 700; border: none; padding: 12px; border-radius: 10px; cursor: pointer; margin-top: 16px; font-size: 15px; }
    .btn-login:hover { background: #e0b080; }
    .form-input { width: 100%; background: #0b121e; border: 1px solid rgba(255,255,255,0.12); color: #fff; padding: 12px; border-radius: 8px; margin-top: 6px; box-sizing: border-box; }
    .form-group { margin-bottom: 16px; text-align: right; }
    .alert { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 10px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .quick-hint { font-size: 12px; color: #8fa0b5; margin-top: 16px; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="login-box">
    <div style="text-align:center; margin-bottom: 24px;">
      <div style="width:54px; height:54px; background:#d4a373; color:#0b121e; font-size:28px; font-weight:bold; display:inline-flex; align-items:center; justify-content:center; border-radius:12px; margin-bottom:12px;">ک</div>
      <h2 style="margin:0 0 4px 0; font-size:20px;">پنل مدیریت کافه دنج</h2>
      <p style="margin:0; font-size:13px; color:#8fa0b5;">لطفاً اطلاعات ورود خود را وارد کنید</p>
    </div>
    ${error ? `<div class="alert">${error}</div>` : ''}
    <form method="post" action="/admin">
      <div class="form-group">
        <label style="font-size:13px; color:#8fa0b5;">نام کاربری</label>
        <input type="text" name="username" class="form-input" value="admin" required autofocus>
      </div>
      <div class="form-group">
        <label style="font-size:13px; color:#8fa0b5;">رمز عبور</label>
        <input type="password" name="password" class="form-input" value="admin123" required>
      </div>
      <button type="submit" class="btn-login">ورود به مدیریت</button>
    </form>
    <div class="quick-hint">
      💡 <b>اطلاعات ورود پیش‌فرض:</b><br>
      نام کاربری: <code style="color:#d4a373;">admin</code> | رمز عبور: <code style="color:#d4a373;">admin123</code>
    </div>
    <div style="text-align:center; margin-top:16px;">
      <a href="/" style="color:#8fa0b5; text-decoration:none; font-size:13px;">← بازگشت به منوی اصلی</a>
    </div>
  </div>
</body>
</html>`;
}

function renderAdminLayout(activePage: string, pageTitle: string, content: string, username = 'مدیر'): string {
  const navItems = [
    { path: '/admin/dashboard', key: 'dashboard', label: 'داشبورد', icon: 'grid' },
    { path: '/admin/orders', key: 'orders', label: 'سفارش‌ها', icon: 'calendar' },
    { path: '/admin/products', key: 'products', label: 'محصولات', icon: 'cup' },
    { path: '/admin/categories', key: 'categories', label: 'دسته‌بندی‌ها', icon: 'layers' },
    { path: '/admin/reports', key: 'reports', label: 'گزارش‌ها', icon: 'chart' },
    { path: '/admin/events', key: 'events', label: 'رویدادها و پاپ‌آپ', icon: 'calendar' },
    { path: '/admin/coupons', key: 'coupons', label: 'کوپن‌های تخفیف', icon: 'cup' },
    { path: '/admin/customers', key: 'customers', label: 'مشتریان', icon: 'users' },
    { path: '/admin/baristas', key: 'baristas', label: 'باریستاها', icon: 'barista' },
    { path: '/admin/activity-log', key: 'activity-log', label: 'لاگ فعالیت', icon: 'log' },
    { path: '/admin/settings', key: 'settings', label: 'تنظیمات', icon: 'settings' },
  ];

  const icons: Record<string, string> = {
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    cup: '<path d="M5 9h11v5a5.5 5.5 0 0 1-11 0V9Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8 4v2M12 4v2M4 21h14"/>',
    layers: '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20.3h-3v-.08A1.7 1.7 0 0 0 10.68 18.66a1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7.02 15 1.7 1.7 0 0 0 5.46 14H5.4v-3h.06A1.7 1.7 0 0 0 7.02 10a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 11.7 4.78V4.7h3v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0 0 19.4 10c.24.58.8.96 1.43 1h.06v3h-.06c-.63.04-1.19.42-1.43 1Z"/>',
    chart: '<path d="M5 19h14"/><path d="M8 19V11"/><path d="M12 19V7"/><path d="M16 19V15"/>',
    barista: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>',
    log: '<path d="M9 4h11v16H9zM4 4h2v16H4zM4 8h1M4 12h1M4 16h1"/>',
  };

  const navHtml = navItems.map(item => `
    <a href="${item.path}" class="nav-item ${activePage === item.key ? 'is-active' : ''}">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        ${icons[item.icon] || ''}
      </svg>
      <span>${item.label}</span>
    </a>
  `).join('');

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${pageTitle} | پنل مدیریت کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <style>
    /* Ensure clean responsive admin styles */
    .stat-card { background: #131c2b; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
    .table-dark-custom { width:100%; color:#fff; border-collapse:collapse; }
    .table-dark-custom th { background: #0f1724; padding: 12px; color: #8fa0b5; font-weight: 600; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .table-dark-custom td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .table-dark-custom tr:hover { background: rgba(255,255,255,0.02); }
    .btn-gold { background: #d4a373; color: #0b121e; font-weight: 600; border: none; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-block; cursor: pointer; }
    .btn-gold:hover { background: #e0b080; color: #0b121e; }
    .btn-sm-action { padding: 4px 10px; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
  </style>
</head>
<body class="admin-body">
<div class="admin-shell">
  <div class="admin-scrim" data-drawer-close></div>
  <aside class="admin-sidebar" id="adminSidebar" aria-label="ناوبری اصلی">
    <div class="sidebar-brand">
      <span class="brand-mark">ک</span>
      <span>کافه دنج</span>
      <small>مدیریت</small>
    </div>
    <nav class="sidebar-nav">
      <p class="nav-caption">منوی اصلی</p>
      ${navHtml}
    </nav>
    <div class="sidebar-footer">
      <span class="status-dot"></span>
      <span>سیستم برخط است</span>
    </div>
  </aside>

  <div class="admin-workspace">
    <header class="admin-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <h5 style="margin:0; color:#fff; font-size:16px;">${pageTitle}</h5>
      </div>
      <div class="header-user" style="display:flex; align-items:center; gap:16px;">
        <a href="/" target="_blank" style="color:var(--gold-soft); text-decoration:none; font-size:13px;">👁️ مشاهده سایت منو</a>
        <a href="/barista/dashboard" style="color:#8fa0b5; text-decoration:none; font-size:13px;">☕ پنل باریستا</a>
        <span style="font-size:13px; color:#fff;">کاربر: <b>${username}</b></span>
        <a href="/admin/logout" class="btn-sm-action" style="background:rgba(239,68,68,0.2); color:#ef4444;">خروج</a>
      </div>
    </header>

    <main class="admin-content p-4">
      ${content}
    </main>
  </div>
</div>
<script src="/assets/js/admin.js"></script>
</body>
</html>`;
}

export function renderAdminDashboardView(username = 'مدیر'): string {
  const totalProducts = store.products.length;
  const activeProducts = store.products.filter(p => p.status === 'active').length;
  const totalOrders = store.orders.length;
  const pendingOrders = store.orders.filter(o => o.status === 'pending').length;
  const totalRevenue = store.orders.filter(o => o.status === 'completed').reduce((sum, o) => sum + o.total_price, 0);
  const totalCustomers = store.customers.length;

  const recentOrders = store.orders.slice().sort((a, b) => b.id - a.id).slice(0, 5);
  const recentLogs = store.activityLogs.slice(0, 6);

  const statusLabels: Record<string, string> = {
    pending: '<span style="color:#faad14; background:rgba(250,173,20,0.1); padding:3px 8px; border-radius:6px;">در انتظار</span>',
    approved: '<span style="color:#1890ff; background:rgba(24,144,255,0.1); padding:3px 8px; border-radius:6px;">تأیید شده</span>',
    completed: '<span style="color:#52c41a; background:rgba(82,196,26,0.1); padding:3px 8px; border-radius:6px;">تکمیل شده</span>',
    rejected: '<span style="color:#ff4d4f; background:rgba(255,77,79,0.1); padding:3px 8px; border-radius:6px;">رد شده</span>',
  };

  const ordersRows = recentOrders.map(o => `
    <tr>
      <td><b>${o.order_number}</b></td>
      <td>${o.customer_name || 'مشتری'}</td>
      <td>${Jalali.formatNumber(o.total_price)} تومان</td>
      <td>${statusLabels[o.status] || o.status}</td>
      <td>${Jalali.format(o.created_at, true)}</td>
      <td><a href="/admin/orders" class="btn-sm-action" style="background:#223046; color:#fff;">مدیریت</a></td>
    </tr>
  `).join('');

  const logsRows = recentLogs.map(l => `
    <div style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:13px; display:flex; justify-content:space-between;">
      <div>
        <b style="color:var(--gold-soft);">${l.user_label || 'سیستم'}:</b> ${l.description}
      </div>
      <span style="color:#8fa0b5; font-size:12px;">${Jalali.format(l.created_at, true)}</span>
    </div>
  `).join('');

  const content = `
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">فروش کل (تکمیل شده)</div>
          <div style="font-size:22px; font-weight:700; color:var(--gold-soft); margin-top:4px;">${Jalali.formatNumber(totalRevenue)} <small style="font-size:12px;">تومان</small></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">سفارش‌های در انتظار</div>
          <div style="font-size:24px; font-weight:700; color:#faad14; margin-top:4px;">${Jalali.digits(pendingOrders)}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">محصولات فعال منو</div>
          <div style="font-size:24px; font-weight:700; color:#52c41a; margin-top:4px;">${Jalali.digits(activeProducts)} <small style="font-size:12px; color:#8fa0b5;">از ${Jalali.digits(totalProducts)}</small></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">کل مشتریان ثبت‌شده</div>
          <div style="font-size:24px; font-weight:700; color:var(--ivory); margin-top:4px;">${Jalali.digits(totalCustomers)}</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="stat-card p-3">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h6 style="margin:0; color:#fff;">آخرین سفارش‌های دریافتی</h6>
            <a href="/admin/orders" class="btn-gold" style="padding:4px 12px; font-size:13px;">مشاهده همه</a>
          </div>
          <div style="overflow-x:auto;">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th>شماره</th>
                  <th>مشتری</th>
                  <th>مبلغ</th>
                  <th>وضعیت</th>
                  <th>زمان ثبت</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                ${ordersRows || '<tr><td colspan="6" style="text-align:center;">هیچ سفارشی ثبت نشده است.</td></tr>'}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">آخرین رویدادهای سیستم</h6>
          <div>
            ${logsRows || '<p style="color:#8fa0b5;">هنوز لاگی ثبت نشده است.</p>'}
          </div>
        </div>
      </div>
    </div>
  `;

  return renderAdminLayout('dashboard', 'داشبورد', content, username);
}

export function renderAdminProductsView(username = 'مدیر'): string {
  const products = store.products.slice().sort((a, b) => a.sort_order - b.sort_order);
  const catMap = new Map(store.categories.map(c => [c.id, c.name]));

  const rows = products.map(p => {
    const disc = store.calculateProductDiscount(p);
    return `
    <tr>
      <td>
        ${p.image ? `<img src="/uploads/${p.image}" style="width:40px; height:40px; border-radius:6px; object-fit:cover;">` : '☕'}
      </td>
      <td><b>${p.name}</b> ${p.badge ? `<span style="background:var(--gold-soft); color:#000; font-size:10px; padding:2px 6px; border-radius:4px; margin-right:4px;">${p.badge}</span>` : ''}</td>
      <td>${catMap.get(p.category_id) || '—'}</td>
      <td>
        ${disc.has_discount ? `
          <div style="font-size:12px; text-decoration:line-through; color:var(--muted);">${Jalali.formatNumber(p.price)} تومان</div>
          <div style="font-weight:700; color:var(--gold-soft);">${Jalali.formatNumber(disc.final)} تومان</div>
        ` : `${Jalali.formatNumber(p.price)} تومان`}
      </td>
      <td>
        ${p.status === 'active' ? '<span style="color:#52c41a;">فعال</span>' : '<span style="color:#ff4d4f;">غیرفعال</span>'}
      </td>
      <td>
        ${disc.has_discount ? `<span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px;">${Jalali.digits(disc.percent)}٪ تخفیف</span>` : '—'}
      </td>
      <td>
        <a href="/admin/edit-product?id=${p.id}" class="btn-sm-action" style="background:#223046; color:#fff;">ویرایش</a>
        <form method="post" action="/admin/products/delete" style="display:inline;" onsubmit="return confirm('آیا از حذف این محصول مطمئنید؟');">
          <input type="hidden" name="id" value="${p.id}">
          <button type="submit" class="btn-sm-action" style="background:rgba(239,68,68,0.2); color:#ef4444;">حذف</button>
        </form>
      </td>
    </tr>
  `;
  }).join('');

  const content = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h5 style="margin:0; color:#fff;">لیست محصولات منو</h5>
        <small style="color:#8fa0b5;">تعداد کل: ${Jalali.digits(products.length)} محصول</small>
      </div>
      <a href="/admin/add-product" class="btn-gold">+ افزودن محصول جدید</a>
    </div>

    <div class="stat-card p-3">
      <div style="overflow-x:auto;">
        <table class="table-dark-custom">
          <thead>
            <tr>
              <th>تصویر</th>
              <th>نام محصول</th>
              <th>دسته‌بندی</th>
              <th>قیمت</th>
              <th>وضعیت</th>
              <th>تخفیف</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  return renderAdminLayout('products', 'مدیریت محصولات', content, username);
}

export function renderAdminProductForm(product?: Product | null, username = 'مدیر'): string {
  const isEdit = Boolean(product);
  const categories = store.categories;

  const content = `
    <div style="max-width:640px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h5 style="margin:0; color:#fff;">${isEdit ? 'ویرایش محصول' : 'افزودن محصول جدید'}</h5>
        <a href="/admin/products" style="color:#8fa0b5; text-decoration:none;">← بازگشت به لیست</a>
      </div>

      <div class="stat-card p-4">
        <form method="post" action="${isEdit ? '/admin/edit-product' : '/admin/add-product'}">
          ${isEdit ? `<input type="hidden" name="id" value="${product?.id}">` : ''}
          <div class="mb-3">
            <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">نام محصول *</label>
            <input type="text" name="name" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${product?.name || ''}" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">دسته‌بندی *</label>
              <select name="category_id" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" required>
                ${categories.map(c => `<option value="${c.id}" ${product?.category_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">قیمت (تومان) *</label>
              <input type="number" name="price" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${product?.price || ''}" required min="0">
            </div>
          </div>

          <div class="mb-3">
            <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">توضیحات</label>
            <textarea name="description" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" rows="3">${product?.description || ''}</textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">نشان / برچسب (مثلاً: پرفروش، خانگی، ویژه)</label>
              <input type="text" name="badge" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${product?.badge || ''}">
            </div>
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:6px;">وضعیت</label>
              <select name="status" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;">
                <option value="active" ${product?.status === 'active' ? 'selected' : ''}>فعال در منو</option>
                <option value="inactive" ${product?.status === 'inactive' ? 'selected' : ''}>غیرفعال</option>
              </select>
            </div>
          </div>

          <div class="mb-3 p-3" style="background:rgba(255,255,255,0.02); border-radius:8px; border:1px dashed rgba(255,255,255,0.1);">
            <label style="color:#d4a373; font-size:13px; font-weight:bold; display:block; margin-bottom:10px;">تنظیم تخفیف محصول</label>
            <div class="row g-3">
              <div class="col-md-6">
                <label style="color:#8fa0b5; font-size:12px; display:block; margin-bottom:4px;">فعال‌سازی تخفیف</label>
                <select name="discount_enabled" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;">
                  <option value="0" ${!product?.discount_enabled ? 'selected' : ''}>خیر (بدون تخفیف)</option>
                  <option value="1" ${product?.discount_enabled ? 'selected' : ''}>بله (دارای تخفیف)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label style="color:#8fa0b5; font-size:12px; display:block; margin-bottom:4px;">درصد تخفیف</label>
                <input type="number" name="discount_value" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${product?.discount_value || ''}" placeholder="مثلاً ۱۵" min="0" max="100">
              </div>
            </div>
          </div>

          <button type="submit" class="btn-gold" style="width:100%; padding:12px; font-size:15px; margin-top:10px;">${isEdit ? 'ذخیره تغییرات' : 'ثبت محصول جدید'}</button>
        </form>
      </div>
    </div>
  `;

  return renderAdminLayout('products', isEdit ? 'ویرایش محصول' : 'افزودن محصول', content, username);
}

export function renderAdminOrdersView(username = 'مدیر'): string {
  const orders = store.orders.slice().sort((a, b) => b.id - a.id);

  const statusOptions = [
    { key: 'pending', label: 'در انتظار تأیید' },
    { key: 'approved', label: 'تأیید شده' },
    { key: 'completed', label: 'تکمیل شده' },
    { key: 'rejected', label: 'رد شده' },
  ];

  const rows = orders.map(o => `
    <tr>
      <td><b>${o.order_number}</b></td>
      <td>
        <b>${o.customer_name || 'مشتری'}</b><br>
        <small style="color:#8fa0b5;">${o.customer_phone || ''}</small>
      </td>
      <td>
        <ul style="margin:0; padding-right:16px; font-size:13px;">
          ${(o.items || []).map(i => `<li>${i.product_name} × ${Jalali.digits(i.quantity)}</li>`).join('')}
        </ul>
        ${o.customer_note ? `<small style="color:#faad14;">یادداشت: ${o.customer_note}</small>` : ''}
      </td>
      <td><b>${Jalali.formatNumber(o.total_price)}</b> تومان</td>
      <td>
        <form method="post" action="/admin/orders/status" style="display:inline-flex; gap:6px;">
          <input type="hidden" name="id" value="${o.id}">
          <select name="status" class="form-control form-control-sm" style="background:#0b121e; color:#fff; border:1px solid rgba(255,255,255,0.1); width:130px;" onchange="this.form.submit()">
            ${statusOptions.map(s => `<option value="${s.key}" ${o.status === s.key ? 'selected' : ''}>${s.label}</option>`).join('')}
          </select>
        </form>
      </td>
      <td>${Jalali.format(o.created_at, true)}</td>
      <td>
        <a href="/order?id=${o.id}" target="_blank" class="btn-sm-action" style="background:#223046; color:#fff;">فاکتور 🖨️</a>
      </td>
    </tr>
  `).join('');

  const content = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h5 style="margin:0; color:#fff;">مدیریت سفارش‌ها</h5>
        <small style="color:#8fa0b5;">مجموع سفارش‌ها: ${Jalali.digits(orders.length)}</small>
      </div>
    </div>

    <div class="stat-card p-3">
      <div style="overflow-x:auto;">
        <table class="table-dark-custom">
          <thead>
            <tr>
              <th>شماره سفارش</th>
              <th>مشتری</th>
              <th>اقلام</th>
              <th>مبلغ</th>
              <th>تغییر وضعیت</th>
              <th>زمان ثبت</th>
              <th>فاکتور</th>
            </tr>
          </thead>
          <tbody>
            ${rows || '<tr><td colspan="7" style="text-align:center;">هیچ سفارشی موجود نیست.</td></tr>'}
          </tbody>
        </table>
      </div>
    </div>
  `;

  return renderAdminLayout('orders', 'مدیریت سفارش‌ها', content, username);
}

export function renderAdminCategoriesView(username = 'مدیر'): string {
  const categories = store.categories.slice().sort((a, b) => a.sort_order - b.sort_order);

  const rows = categories.map(c => `
    <tr>
      <td>${Jalali.digits(c.sort_order)}</td>
      <td><b>${c.name}</b></td>
      <td><code>${c.slug}</code></td>
      <td>${Jalali.digits(store.products.filter(p => p.category_id === c.id).length)} محصول</td>
      <td>
        <form method="post" action="/admin/categories/delete" style="display:inline;" onsubmit="return confirm('آیا از حذف این دسته مطمئنید؟');">
          <input type="hidden" name="id" value="${c.id}">
          <button type="submit" class="btn-sm-action" style="background:rgba(239,68,68,0.2); color:#ef4444;">حذف</button>
        </form>
      </td>
    </tr>
  `).join('');

  const content = `
    <div class="row g-4">
      <div class="col-md-4">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">افزودن دسته‌بندی جدید</h6>
          <form method="post" action="/admin/categories/add">
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">نام دسته‌بندی *</label>
              <input type="text" name="name" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" placeholder="مثلاً: کیک و دسر" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">شناسه انگلیسی (slug) *</label>
              <input type="text" name="slug" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" placeholder="مثلاً: cake" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">ترتیب نمایش</label>
              <input type="number" name="sort_order" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${categories.length + 1}">
            </div>
            <button type="submit" class="btn-gold" style="width:100%;">ثبت دسته‌بندی</button>
          </form>
        </div>
      </div>

      <div class="col-md-8">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">دسته‌بندی‌های منو</h6>
          <div style="overflow-x:auto;">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th>ترتیب</th>
                  <th>نام دسته</th>
                  <th>شناسه (Slug)</th>
                  <th>محصولات</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  `;

  return renderAdminLayout('categories', 'دسته‌بندی‌ها', content, username);
}

export function renderAdminEventsView(username = 'مدیر'): string {
  const events = store.events;

  const rows = events.map(e => `
    <tr>
      <td><b>${e.title}</b></td>
      <td style="max-width:300px;">${e.content}</td>
      <td>${e.collect_phone ? 'بله' : 'خیر'}</td>
      <td>
        <form method="post" action="/admin/events/toggle">
          <input type="hidden" name="id" value="${e.id}">
          <button type="submit" class="btn-sm-action" style="background:${e.is_active ? '#52c41a' : '#595959'}; color:#fff;">
            ${e.is_active ? 'فعال (نمایش پاپ‌آپ)' : 'غیرفعال'}
          </button>
        </form>
      </td>
    </tr>
  `).join('');

  const content = `
    <div class="row g-4">
      <div class="col-md-5">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">افزودن رویداد / پاپ‌آپ اطلاع‌رسانی</h6>
          <form method="post" action="/admin/events/add">
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">عنوان رویداد *</label>
              <input type="text" name="title" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">متن اطلاعیه *</label>
              <textarea name="content" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" rows="4" required></textarea>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">دریافت شماره موبایل جهت عضویت</label>
              <select name="collect_phone" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;">
                <option value="1">بله</option>
                <option value="0">خیر</option>
              </select>
            </div>
            <button type="submit" class="btn-gold" style="width:100%;">ایجاد رویداد</button>
          </form>
        </div>
      </div>

      <div class="col-md-7">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">لیست اطلاعیه‌ها</h6>
          <div style="overflow-x:auto;">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th>عنوان</th>
                  <th>متن</th>
                  <th>فرم تماس</th>
                  <th>وضعیت</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  `;

  return renderAdminLayout('events', 'رویدادها و پاپ‌آپ', content, username);
}

export function renderAdminCouponsView(username = 'مدیر'): string {
  const coupons = store.coupons;

  const rows = coupons.map(c => `
    <tr>
      <td><code>${c.code}</code></td>
      <td><b>${Jalali.digits(c.percent)}٪</b></td>
      <td>${c.is_active ? '<span style="color:#52c41a;">فعال</span>' : '<span style="color:#ff4d4f;">غیرفعال</span>'}</td>
      <td>
        <form method="post" action="/admin/coupons/toggle" style="display:inline;">
          <input type="hidden" name="id" value="${c.id}">
          <button type="submit" class="btn-sm-action" style="background:#223046; color:#fff;">${c.is_active ? 'غیرفعال کردن' : 'فعال‌سازی'}</button>
        </form>
      </td>
    </tr>
  `).join('');

  const content = `
    <div class="row g-4">
      <div class="col-md-4">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">افزودن کوپن تخفیف جدید</h6>
          <form method="post" action="/admin/coupons/add">
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">کد تخفیف *</label>
              <input type="text" name="code" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff; text-transform:uppercase;" placeholder="مثلاً: SPRING25" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">درصد تخفیف *</label>
              <input type="number" name="percent" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" placeholder="مثلاً: 25" min="1" max="100" required>
            </div>
            <button type="submit" class="btn-gold" style="width:100%;">ایجاد کوپن</button>
          </form>
        </div>
      </div>

      <div class="col-md-8">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">کدهای تخفیف موجود</h6>
          <div style="overflow-x:auto;">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th>کد کوپن</th>
                  <th>درصد تخفیف</th>
                  <th>وضعیت</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  `;

  return renderAdminLayout('coupons', 'کوپن‌های تخفیف', content, username);
}

export function renderAdminCustomersView(username = 'مدیر'): string {
  const customers = store.customers;

  const rows = customers.map(c => `
    <tr>
      <td>${c.first_name || ''} ${c.last_name || ''}</td>
      <td dir="ltr" style="text-align:right;">${c.phone}</td>
      <td>${c.source === 'popup' ? 'پاپ‌آپ رویداد' : 'ثبت‌نام مستقیم'}</td>
      <td>${Jalali.digits(store.orders.filter(o => o.customer_id === c.id).length)} سفارش</td>
      <td>${Jalali.format(c.created_at)}</td>
    </tr>
  `).join('');

  const content = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h5 style="margin:0; color:#fff;">باشگاه مشتریان</h5>
        <small style="color:#8fa0b5;">تعداد کل: ${Jalali.digits(customers.length)} نفر</small>
      </div>
    </div>

    <div class="stat-card p-3">
      <div style="overflow-x:auto;">
        <table class="table-dark-custom">
          <thead>
            <tr>
              <th>نام و نام خانوادگی</th>
              <th>شماره تماس</th>
              <th>نحوه عضویت</th>
              <th>تعداد سفارش‌ها</th>
              <th>تاریخ عضویت</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  return renderAdminLayout('customers', 'مشتریان', content, username);
}

export function renderAdminBaristasView(username = 'مدیر'): string {
  const baristas = store.baristas;

  const rows = baristas.map(b => `
    <tr>
      <td><b>${b.full_name}</b></td>
      <td><code>${b.username}</code></td>
      <td dir="ltr" style="text-align:right;">${b.phone}</td>
      <td><span style="color:#52c41a;">فعال</span></td>
      <td>${Jalali.format(b.last_login_at || b.created_at, true)}</td>
    </tr>
  `).join('');

  const content = `
    <div class="row g-4">
      <div class="col-md-4">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">افزودن باریستا جدید</h6>
          <form method="post" action="/admin/baristas/add">
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">نام کامل *</label>
              <input type="text" name="full_name" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">نام کاربری ورود *</label>
              <input type="text" name="username" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">رمز عبور *</label>
              <input type="password" name="password" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" required>
            </div>
            <div class="mb-3">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">شماره تماس</label>
              <input type="tel" name="phone" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" placeholder="0912...">
            </div>
            <button type="submit" class="btn-gold" style="width:100%;">ثبت باریستا</button>
          </form>
        </div>
      </div>

      <div class="col-md-8">
        <div class="stat-card p-3">
          <h6 style="margin:0 0 16px 0; color:#fff;">لیست پرسنل و باریستاها</h6>
          <div style="overflow-x:auto;">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th>نام و سمت</th>
                  <th>نام کاربری</th>
                  <th>شماره تماس</th>
                  <th>وضعیت</th>
                  <th>آخرین ورود</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  `;

  return renderAdminLayout('baristas', 'مدیریت باریستاها', content, username);
}

export function renderAdminActivityLogView(username = 'مدیر'): string {
  const logs = store.activityLogs;

  const rows = logs.map(l => `
    <tr>
      <td>${Jalali.digits(l.id)}</td>
      <td><b>${l.user_label || 'سیستم'}</b> (${l.role})</td>
      <td><code>${l.action}</code></td>
      <td>${l.description}</td>
      <td><code>${l.ip_address || '127.0.0.1'}</code></td>
      <td>${Jalali.format(l.created_at, true)}</td>
    </tr>
  `).join('');

  const content = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <div>
        <h5 style="margin:0; color:#fff;">لاگ فعالیت‌های سیستم</h5>
        <small style="color:#8fa0b5;">ثبت تمام رخدادهای ورود، ثبت سفارش و تغییرات</small>
      </div>
    </div>

    <div class="stat-card p-3">
      <div style="overflow-x:auto;">
        <table class="table-dark-custom">
          <thead>
            <tr>
              <th>#</th>
              <th>کاربر</th>
              <th>عملیات</th>
              <th>شرح</th>
              <th>IP</th>
              <th>زمان</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  return renderAdminLayout('activity-log', 'لاگ فعالیت', content, username);
}

export function renderAdminReportsView(username = 'مدیر'): string {
  const orders = store.orders;
  const completedOrders = orders.filter(o => o.status === 'completed');
  const totalRevenue = completedOrders.reduce((sum, o) => sum + o.total_price, 0);

  // Calculate top products
  const productSalesMap = new Map<string, { count: number; total: number }>();
  for (const o of orders) {
    for (const item of o.items || []) {
      const cur = productSalesMap.get(item.product_name) || { count: 0, total: 0 };
      cur.count += item.quantity;
      cur.total += item.price * item.quantity;
      productSalesMap.set(item.product_name, cur);
    }
  }

  const topProducts = Array.from(productSalesMap.entries()).sort((a, b) => b[1].count - a[1].count);

  const topRows = topProducts.map(([name, data]) => `
    <tr>
      <td><b>${name}</b></td>
      <td>${Jalali.digits(data.count)} عدد</td>
      <td>${Jalali.formatNumber(data.total)} تومان</td>
    </tr>
  `).join('');

  const content = `
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">کل درآمد سفارش‌های تحویل شده</div>
          <div style="font-size:24px; font-weight:bold; color:var(--gold-soft); margin-top:6px;">${Jalali.formatNumber(totalRevenue)} <small style="font-size:13px;">تومان</small></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">میانگین مبلغ هر فاکتور</div>
          <div style="font-size:24px; font-weight:bold; color:var(--ivory); margin-top:6px;">${Jalali.formatNumber(completedOrders.length > 0 ? Math.round(totalRevenue / completedOrders.length) : 0)} <small style="font-size:13px;">تومان</small></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card p-3">
          <div style="color:var(--muted); font-size:13px;">تعداد کل اقلام فروخته شده</div>
          <div style="font-size:24px; font-weight:bold; color:#52c41a; margin-top:6px;">${Jalali.digits(Array.from(productSalesMap.values()).reduce((sum, d) => sum + d.count, 0))} عدد</div>
        </div>
      </div>
    </div>

    <div class="stat-card p-3">
      <h6 style="margin:0 0 16px 0; color:#fff;">پرفروش‌ترین آیتم‌های منو</h6>
      <div style="overflow-x:auto;">
        <table class="table-dark-custom">
          <thead>
            <tr>
              <th>نام آیتم</th>
              <th>تعداد سفارش</th>
              <th>مجموع فروش</th>
            </tr>
          </thead>
          <tbody>
            ${topRows || '<tr><td colspan="3" style="text-align:center;">داده‌ای موجود نیست.</td></tr>'}
          </tbody>
        </table>
      </div>
    </div>
  `;

  return renderAdminLayout('reports', 'گزارش‌های فروش', content, username);
}

export function renderAdminSettingsView(message?: string | null, username = 'مدیر'): string {
  const content = `
    <div style="max-width:650px;">
      ${message ? `<div style="background:rgba(82,196,26,0.1); border:1px solid #52c41a; color:#52c41a; padding:12px; border-radius:8px; margin-bottom:16px;">${message}</div>` : ''}
      <div class="stat-card p-4">
        <h6 style="margin:0 0 20px 0; color:#fff; font-size:16px;">تنظیمات عمومی کافه دنج</h6>
        <form method="post" action="/admin/settings">
          <div class="mb-3">
            <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">نام کافه</label>
            <input type="text" name="cafe_title" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${store.settings.cafe_title || 'کافه دنج'}">
          </div>

          <div class="mb-3">
            <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">آدرس کافه</label>
            <input type="text" name="cafe_address" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${store.settings.cafe_address || 'کرج، بلوار شهید مطهری، نبش خیابان پیروزی، کافه دنج'}">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">شماره تماس</label>
              <input type="text" name="cafe_phone" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${store.settings.cafe_phone || '09053680080'}">
            </div>
            <div class="col-md-6">
              <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">ساعات کاری</label>
              <input type="text" name="cafe_hours" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${store.settings.cafe_hours || 'از ۶ صبح الی ۱۲ شب'}">
            </div>
          </div>

          <div class="mb-3">
            <label style="color:#8fa0b5; font-size:13px; display:block; margin-bottom:4px;">شناسه اینستاگرام</label>
            <input type="text" name="cafe_instagram" class="form-control" style="background:#0b121e; border:1px solid rgba(255,255,255,0.12); color:#fff;" value="${store.settings.cafe_instagram || 'cafe_denj_karaj'}">
          </div>

          <button type="submit" class="btn-gold" style="width:100%; padding:12px; margin-top:10px;">ذخیره تنظیمات</button>
        </form>
      </div>
    </div>
  `;

  return renderAdminLayout('settings', 'تنظیمات سامانه', content, username);
}