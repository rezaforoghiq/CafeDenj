import { store } from '../store';
import { Jalali } from '../jalali';

export function renderCartView(customerId: number, error?: string | null): string {
  const items = store.getCart(customerId);
  const total = items.reduce((sum, i) => sum + i.price * i.quantity, 0);

  const itemsHtml = items.map(item => `
    <article class="cart-item">
      <div class="cart-product-image">
        ${item.image ? `<img src="/uploads/${item.image}" alt="${item.name}">` : '☕'}
      </div>
      <div class="cart-product-info">
        <h2>${item.name}</h2>
        ${item.original_price && item.original_price > item.price ? `
          <p>
            <span style="text-decoration:line-through; color:var(--muted); margin-left:6px;">${Jalali.formatNumber(item.original_price)}</span>
            <span style="color:var(--gold-soft); font-weight:600;">${Jalali.formatNumber(item.price)} تومان</span>
            <span style="background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; margin-right:4px;">تخفیف</span>
          </p>
        ` : `
          <p>${Jalali.formatNumber(item.price)} تومان برای هر عدد</p>
        `}
        <strong>${Jalali.formatNumber(item.price * item.quantity)} تومان</strong>
      </div>
      <div class="cart-item-actions">
        <form method="post" action="/cart" class="quantity-control">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="product_id" value="${item.product_id}">
          <button type="submit" name="quantity" value="${item.quantity - 1}" aria-label="کاهش تعداد">−</button>
          <output>${Jalali.digits(item.quantity)}</output>
          <button type="submit" name="quantity" value="${item.quantity + 1}" aria-label="افزایش تعداد">+</button>
        </form>
        <form method="post" action="/cart">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="product_id" value="${item.product_id}">
          <button type="submit" class="remove-item" name="quantity" value="0">حذف</button>
        </form>
      </div>
    </article>
  `).join('');

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>سبد خرید | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth cart-page">
  <main class="cart-layout">
    <header class="cart-header">
      <a href="/">← بازگشت به منو</a>
      <h1>سبد خرید</h1>
      <span>${Jalali.digits(items.length)} محصول</span>
    </header>
    ${error ? `<div class="auth-alert">${error}</div>` : ''}
    ${items.length === 0 ? `
      <section class="cart-empty">
        <h2>سبد خرید شما خالی است</h2>
        <p>محصولات مورد علاقه‌تان را از منو انتخاب کنید.</p>
        <a class="auth-submit" href="/">مشاهده منو</a>
      </section>
    ` : `
      <div class="cart-grid">
        <section class="cart-items">
          ${itemsHtml}
        </section>
        <aside class="cart-summary">
          <h2>خلاصه سفارش</h2>
          <p>تعداد اقلام <b>${Jalali.digits(items.reduce((s, i) => s + i.quantity, 0))} عدد</b></p>
          <div class="total">مبلغ کل <b>${Jalali.formatNumber(total)} تومان</b></div>
          <a class="auth-submit" href="/checkout">ادامه و ثبت سفارش ←</a>
          <small>پرداخت آنلاین ندارد؛ سفارش حضوری بررسی و تسویه می‌شود.</small>
        </aside>
      </div>
    `}
  </main>
</body>
</html>`;
}

export function renderCheckoutView(customerId: number, error?: string | null): string {
  const items = store.getCart(customerId);
  const total = items.reduce((sum, i) => sum + i.price * i.quantity, 0);

  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>تکمیل و ثبت سفارش | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card" style="max-width:540px;">
    <div class="auth-brand">
      <a href="/cart" style="color:var(--gold-soft); text-decoration:none; float:left; font-size:14px;">← بازگشت به سبد</a>
      <h1 style="clear:both;">تکمیل سفارش</h1>
      <p>سفارش شما برای باریستا ارسال شده و پس از تأیید آماده‌سازی می‌شود.</p>
    </div>
    ${error ? `<div class="auth-alert">${error}</div>` : ''}

    <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:20px;">
      <div id="original-block" style="display:none; font-size:13px; color:var(--muted); margin-bottom:4px;">
        مبلغ قبل از تخفیف: <span id="original-total" style="text-decoration:line-through;">${Jalali.formatNumber(total)} تومان</span>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="color:var(--muted);">مبلغ قابل پرداخت:</span>
        <b id="order-total" style="font-size:20px; color:var(--gold-soft);">${Jalali.formatNumber(total)} تومان</b>
      </div>
    </div>

    <form method="post" action="/checkout" data-auth-form>
      <div class="auth-field">
        <label>کد کوپن تخفیف (اختیاری)</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="text" name="coupon_code" id="coupon_code" class="auth-input" placeholder="مثلاً DENJ10" style="flex:1; text-transform:uppercase;">
          <button type="button" id="applyCoupon" class="auth-submit" style="width:auto; padding:10px 18px; margin:0; white-space:nowrap;">اعمال کد</button>
        </div>
        <div id="coupon-msg" style="font-size:13px; margin-top:6px;"></div>
      </div>

      <div class="auth-field">
        <label>روش پرداخت مورد نظر</label>
        <select name="payment_method" class="auth-input" style="background:#131c2b; color:#fff;">
          <option value="card">کارتخوان در محل کافه</option>
          <option value="cash">نقدی در کافه</option>
          <option value="transfer">کارت به کارت</option>
        </select>
      </div>

      <div class="auth-field">
        <label>توضیحات و یادداشت برای باریستا (اختیاری)</label>
        <textarea class="auth-input" name="note" maxlength="500" rows="3" placeholder="مثال: کم‌شکر، بیرون‌بر، شماره میز..."></textarea>
      </div>

      <button type="submit" class="auth-submit" style="margin-top:16px;">ثبت نهایی سفارش ←</button>
    </form>
  </main>

  <script>
    (function(){
      const total = ${total};
      const input = document.getElementById('coupon_code');
      const applyBtn = document.getElementById('applyCoupon');
      const msg = document.getElementById('coupon-msg');
      const orderTotalEl = document.getElementById('order-total');
      const origBlock = document.getElementById('original-block');
      const origTotal = document.getElementById('original-total');

      function formatNumber(num) {
        return new Intl.NumberFormat('fa-IR').format(num) + ' تومان';
      }

      applyBtn.addEventListener('click', async function() {
        const code = input.value.trim().toUpperCase();
        if (!code) return;
        try {
          const res = await fetch('/coupon-validate?code=' + encodeURIComponent(code));
          const data = await res.json();
          if (data.valid) {
            msg.style.color = '#52c41a';
            msg.textContent = 'کوپن ' + data.code + ' با موفقیت اعمال شد (' + data.percent + '٪ تخفیف)';
            const discount = Math.round(total * data.percent / 100);
            const finalPrice = Math.max(0, total - discount);
            origBlock.style.display = 'block';
            origTotal.textContent = formatNumber(total);
            orderTotalEl.textContent = formatNumber(finalPrice);
          } else {
            msg.style.color = '#ff4d4f';
            msg.textContent = data.message || 'کد تخفیف نامعتبر یا منقضی شده است.';
            origBlock.style.display = 'none';
            orderTotalEl.textContent = formatNumber(total);
          }
        } catch(e) {
          msg.style.color = '#ff4d4f';
          msg.textContent = 'خطا در بررسی کوپن';
        }
      });
    })();
  </script>
</body>
</html>`;
}