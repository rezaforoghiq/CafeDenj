import { store } from '../store';
import { Jalali } from '../jalali';

export function renderBaristaLogin(error?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ورود باریستا | کافه دنج</title>
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
  </style>
</head>
<body>
  <div class="login-box">
    <div style="text-align:center; margin-bottom: 24px;">
      <div style="font-size:36px; margin-bottom:8px;">☕</div>
      <h2 style="margin:0 0 4px 0; font-size:20px;">پنل باریستا و بار گرم/سرد</h2>
      <p style="margin:0; font-size:13px; color:#8fa0b5;">مدیریت لحظه‌ای و آماده‌سازی سفارش‌ها</p>
    </div>
    ${error ? `<div class="alert">${error}</div>` : ''}
    <form method="post" action="/barista">
      <div class="form-group">
        <label style="font-size:13px; color:#8fa0b5;">نام کاربری</label>
        <input type="text" name="username" class="form-input" value="barista" required autofocus>
      </div>
      <div class="form-group">
        <label style="font-size:13px; color:#8fa0b5;">رمز عبور</label>
        <input type="password" name="password" class="form-input" value="barista123" required>
      </div>
      <button type="submit" class="btn-login">ورود به پنل باریستا</button>
    </form>
    <div style="font-size: 12px; color: #8fa0b5; margin-top: 16px; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; line-height: 1.6;">
      💡 <b>ورود پیش‌فرض باریستا:</b><br>
      نام کاربری: <code style="color:#d4a373;">barista</code> | رمز: <code style="color:#d4a373;">barista123</code>
    </div>
    <div style="text-align:center; margin-top:16px;">
      <a href="/" style="color:#8fa0b5; text-decoration:none; font-size:13px;">← بازگشت به منو</a>
    </div>
  </div>
</body>
</html>`;
}

export function renderBaristaDashboard(baristaName = 'باریستا'): string {
  const pendingOrders = store.orders.filter(o => o.status === 'pending');
  const approvedOrders = store.orders.filter(o => o.status === 'approved');
  const completedOrders = store.orders.filter(o => o.status === 'completed').slice(-5);

  const renderOrderCard = (order: typeof store.orders[0], type: 'pending' | 'approved' | 'completed') => {
    return `
      <div class="order-kitchen-card" style="background:#131c2b; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:16px; box-shadow:0 4px 12px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:8px;">
          <div>
            <b style="font-size:17px; color:var(--gold-soft);">${order.order_number}</b>
            <span style="font-size:13px; color:#8fa0b5; margin-right:8px;">${order.customer_name || 'مشتری'}</span>
          </div>
          <span style="font-size:12px; color:#8fa0b5;">${Jalali.format(order.created_at, true)}</span>
        </div>

        <div style="margin-bottom:12px;">
          <ul style="margin:0; padding-right:16px; font-size:14px; line-height:1.8;">
            ${(order.items || []).map(i => `<li><b>${i.product_name}</b> × <span style="color:var(--gold-soft); font-weight:bold;">${Jalali.digits(i.quantity)}</span></li>`).join('')}
          </ul>
          ${order.customer_note ? `<div style="background:rgba(250,173,20,0.1); border-right:3px solid #faad14; padding:6px 10px; margin-top:8px; font-size:13px; color:#faad14; border-radius:4px;">یادداشت: ${order.customer_note}</div>` : ''}
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid rgba(255,255,255,0.06);">
          <span style="font-weight:bold; font-size:15px;">${Jalali.formatNumber(order.total_price)} تومان</span>
          <div style="display:flex; gap:6px;">
            ${type === 'pending' ? `
              <form method="post" action="/barista/status">
                <input type="hidden" name="id" value="${order.id}">
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="btn-gold" style="padding:6px 14px; font-size:13px;">✓ شروع آماده‌سازی</button>
              </form>
              <form method="post" action="/barista/status">
                <input type="hidden" name="id" value="${order.id}">
                <input type="hidden" name="status" value="rejected">
                <button type="submit" style="background:rgba(239,68,68,0.2); color:#ef4444; border:none; padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">رد</button>
              </form>
            ` : type === 'approved' ? `
              <form method="post" action="/barista/status">
                <input type="hidden" name="id" value="${order.id}">
                <input type="hidden" name="status" value="completed">
                <button type="submit" style="background:#52c41a; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-weight:bold; font-size:13px; cursor:pointer;">✓ تحویل شد (تکمیل)</button>
              </form>
            ` : `
              <span style="color:#52c41a; font-size:13px;">تکمیل و تحویل شد</span>
            `}
            <a href="/order?id=${order.id}" target="_blank" style="background:#223046; color:#fff; padding:6px 10px; border-radius:6px; font-size:13px; text-decoration:none;">فاکتور 🖨️</a>
          </div>
        </div>
      </div>
    `;
  };

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>میز کار باریستا | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <style>
    body { background: #0b121e; color: #fff; font-family: 'Vazirmatn', sans-serif; }
    .kitchen-header { background: #131c2b; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
    .col-board { background: #0e1622; border-radius: 12px; padding: 16px; min-height: 80vh; border: 1px solid rgba(255,255,255,0.05); }
    .board-title { font-size: 16px; font-weight: 700; padding-bottom: 12px; margin-bottom: 16px; border-bottom: 2px solid; display: flex; justify-content: space-between; }
    .btn-gold { background: #d4a373; color: #0b121e; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; }
  </style>
</head>
<body>
  <header class="kitchen-header">
    <div style="display:flex; align-items:center; gap:16px;">
      <div style="font-size:24px;">☕</div>
      <div>
        <h4 style="margin:0; font-size:18px;">صف سفارش‌های باریستا</h4>
        <small style="color:#8fa0b5;">کافه دنج · کاربر: ${baristaName}</small>
      </div>
    </div>
    <div style="display:flex; gap:12px; align-items:center;">
      <a href="/" target="_blank" style="color:var(--gold-soft); text-decoration:none; font-size:14px;">منوی عمومی</a>
      <a href="/admin" style="color:#8fa0b5; text-decoration:none; font-size:14px;">پنل ادمین</a>
      <a href="/barista/logout" style="background:rgba(239,68,68,0.2); color:#ef4444; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:13px;">خروج</a>
    </div>
  </header>

  <main class="container-fluid p-4">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="col-board">
          <div class="board-title" style="border-color:#faad14; color:#faad14;">
            <span>سفارش‌های جدید (در انتظار)</span>
            <span>${Jalali.digits(pendingOrders.length)}</span>
          </div>
          ${pendingOrders.length === 0 ? '<p style="color:#8fa0b5; text-align:center; margin-top:40px;">سفارش جدیدی وجود ندارد.</p>' : pendingOrders.map(o => renderOrderCard(o, 'pending')).join('')}
        </div>
      </div>

      <div class="col-lg-4">
        <div class="col-board">
          <div class="board-title" style="border-color:#1890ff; color:#1890ff;">
            <span>در حال آماده‌سازی</span>
            <span>${Jalali.digits(approvedOrders.length)}</span>
          </div>
          ${approvedOrders.length === 0 ? '<p style="color:#8fa0b5; text-align:center; margin-top:40px;">سفارشی در حال آماده‌سازی نیست.</p>' : approvedOrders.map(o => renderOrderCard(o, 'approved')).join('')}
        </div>
      </div>

      <div class="col-lg-4">
        <div class="col-board">
          <div class="board-title" style="border-color:#52c41a; color:#52c41a;">
            <span>آخرین تحویل‌شده‌ها</span>
            <span>${Jalali.digits(completedOrders.length)}</span>
          </div>
          ${completedOrders.length === 0 ? '<p style="color:#8fa0b5; text-align:center; margin-top:40px;">هنوز سفارشی تحویل نشده است.</p>' : completedOrders.map(o => renderOrderCard(o, 'completed')).join('')}
        </div>
      </div>
    </div>
  </main>
</body>
</html>`;
}
