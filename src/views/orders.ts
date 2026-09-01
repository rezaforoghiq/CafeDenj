import { store } from '../store';
import { Jalali } from '../jalali';
import { Order } from '../types';

const statusLabels: Record<string, string> = {
  pending: 'در انتظار تأیید',
  approved: 'تأیید شده و در حال آماده‌سازی',
  completed: 'تکمیل و تحویل شده',
  rejected: 'رد شده',
};

const statusClasses: Record<string, string> = {
  pending: 'status-pending',
  approved: 'status-approved',
  completed: 'status-completed',
  rejected: 'status-rejected',
};

export function renderOrdersListView(customerId: number): string {
  const orders = store.orders.filter(o => o.customer_id === customerId).sort((a, b) => b.id - a.id);

  const ordersHtml = orders.map(order => `
    <article class="order-card" data-order-id="${order.id}">
      <div class="order-card-top">
        <div>
          <span class="order-number">سفارش شماره ${order.order_number}</span>
          <span class="order-date">${Jalali.format(order.created_at, true)}</span>
        </div>
        <span class="order-status ${statusClasses[order.status] || ''}">${statusLabels[order.status] || order.status}</span>
      </div>
      <div class="order-card-bottom">
        <div>
          <b>${Jalali.formatNumber(order.total_price)} <small>تومان</small></b>
          ${order.discount_amount > 0 ? `
            <div style="font-size:13px; color:var(--muted); margin-top:2px;">
              کوپن: ${order.coupon_code || '—'} (${Jalali.digits(order.coupon_percent || 0)}٪ تخفیف)
            </div>
          ` : ''}
        </div>
        <div class="order-actions">
          <a href="/order?id=${order.id}">مشاهده فاکتور <span>←</span></a>
          ${order.status === 'pending' ? `
            <form method="post" action="/order-delete" style="display:inline;" onsubmit="return confirm('آیا از حذف این سفارش مطمئن هستید؟');">
              <input type="hidden" name="id" value="${order.id}">
              <button type="submit" class="order-delete" style="background:none; border:none; color:#ff4d4f; cursor:pointer; font-size:13px;">حذف سفارش</button>
            </form>
          ` : ''}
        </div>
      </div>
    </article>
  `).join('');

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>سفارش‌های من | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth orders-page">
  <main class="orders-layout">
    <header class="orders-heading">
      <a href="/">← بازگشت به منو</a>
      <div>
        <span>حساب کاربری</span>
        <h1>سفارش‌های من</h1>
        <p>وضعیت سفارش‌ها و فاکتورهای خود را اینجا پیگیری کنید.</p>
      </div>
    </header>

    ${orders.length === 0 ? `
      <section class="cart-empty">
        <h2>هنوز سفارشی ثبت نکرده‌اید</h2>
        <p>از منوی کافه دنج محصول دلخواهتان را انتخاب کنید.</p>
        <a class="auth-submit" href="/">مشاهده منو</a>
      </section>
    ` : `
      <section class="orders-list">
        ${ordersHtml}
      </section>
    `}
  </main>
</body>
</html>`;
}

export function renderOrderDetailView(order: Order): string {
  const items = order.items || [];
  const statusLabel = statusLabels[order.status] || order.status;

  const itemsHtml = items.map(item => `
    <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px dashed rgba(255,255,255,0.08);">
      <span>${item.product_name} × ${Jalali.digits(item.quantity)}</span>
      <b>${Jalali.formatNumber(item.price * item.quantity)} تومان</b>
    </div>
  `).join('');

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>فاکتور سفارش ${order.order_number} | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card" style="max-width:500px;">
    <div class="auth-brand">
      <div style="font-size:32px; margin-bottom:8px;">☕</div>
      <h1>فاکتور سفارش ${order.order_number}</h1>
      <p>وضعیت: <strong style="color:var(--gold-soft);">${statusLabel}</strong></p>
      <small style="color:var(--muted);">${Jalali.format(order.created_at, true)}</small>
    </div>

    <div style="margin:20px 0;">
      <h3 style="font-size:15px; margin-bottom:10px; color:var(--muted);">اقلام سفارش:</h3>
      ${itemsHtml}
    </div>

    ${order.discount_amount > 0 ? `
      <div style="font-size:14px; margin-bottom:6px; color:var(--muted); display:flex; justify-content:space-between;">
        <span>تخفیف کوپن (${order.coupon_code}):</span>
        <span style="color:#52c41a;">−${Jalali.formatNumber(order.discount_amount)} تومان</span>
      </div>
    ` : ''}

    ${order.customer_note ? `
      <div style="font-size:13px; background:rgba(255,255,255,0.02); padding:10px; border-radius:8px; margin:10px 0; color:var(--muted);">
        <b>یادداشت:</b> ${order.customer_note}
      </div>
    ` : ''}

    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:12px; border-top:2px solid rgba(255,255,255,0.1);">
      <span style="font-size:16px;">مبلغ کل نهایی:</span>
      <b style="font-size:22px; color:var(--gold-soft);">${Jalali.formatNumber(order.total_price)} تومان</b>
    </div>

    <div style="display:flex; gap:10px; margin-top:24px;">
      <button onclick="window.print()" class="auth-submit" style="background:#223046; border:1px solid rgba(255,255,255,0.1); width:auto; flex:1;">چاپ فاکتور 🖨️</button>
      <a class="auth-submit" href="/orders" style="text-align:center; text-decoration:none; width:auto; flex:1;">سفارش‌های من</a>
    </div>
  </main>
</body>
</html>`;
}
