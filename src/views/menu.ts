import { store } from '../store';
import { Jalali } from '../jalali';

export function renderMenuView(customerId?: number, customerDisplayName?: string): string {
  const categories = store.categories.slice().sort((a, b) => a.sort_order - b.sort_order);
  const products = store.products.filter(p => p.status === 'active').sort((a, b) => a.sort_order - b.sort_order);
  const activeEvent = store.events.find(e => e.is_active);

  const isLoggedIn = Boolean(customerId && customerDisplayName);
  let desktopAccountActions = '';
  let mobileAccountActions = '';

  if (isLoggedIn && customerId) {
    const cart = store.getCart(customerId);
    const cartCount = cart.reduce((acc, i) => acc + i.quantity, 0);
    const orders = store.orders.filter(o => o.customer_id === customerId);
    const ordersCount = orders.length;

    const cartBadge = cartCount > 0
      ? `<span class="action-count" data-cart-count>${Jalali.digits(cartCount)}</span>`
      : `<span class="action-count is-empty" data-cart-count></span>`;
    const ordersBadge = ordersCount > 0
      ? `<span class="action-count">${Jalali.digits(ordersCount)}</span>`
      : '';
    const initial = customerDisplayName ? customerDisplayName.charAt(0) : 'م';

    const actionLinks = `
      <a class="user-menu-link" href="/orders"><span>سفارش‌ها</span>${ordersBadge}</a>
      <a class="user-menu-link" href="/cart"><span>سبد خرید</span>${cartBadge}</a>
      <a class="user-menu-link" href="/profile"><span>پروفایل</span></a>
      <a class="user-menu-link user-menu-logout" href="/customer-logout"><span>خروج</span></a>
    `;

    desktopAccountActions = `
      <div class="user-menu">
        <button class="profile-trigger" id="profileTrigger" type="button" aria-label="منوی حساب کاربری" aria-haspopup="true" aria-controls="profileMenu" aria-expanded="false">
          <span class="profile-avatar" aria-hidden="true">${initial}</span>
          <span class="profile-label">${customerDisplayName}</span>
          <svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16"><path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </button>
        <div class="profile-menu" id="profileMenu">
          <div class="profile-menu-head">
            <span class="profile-avatar" aria-hidden="true">${initial}</span>
            <div><b>${customerDisplayName}</b><small>حساب کاربری</small></div>
          </div>
          ${actionLinks}
        </div>
      </div>
    `;

    mobileAccountActions = `<span class="account-welcome">سلام، ${customerDisplayName}</span>${actionLinks}`;
  } else {
    desktopAccountActions = `
      <a class="account-link" href="/login">ورود</a>
      <a class="account-link account-link-primary" href="/register">ثبت‌نام</a>
    `;
    mobileAccountActions = desktopAccountActions;
  }

  // Categories HTML
  const categoryHtml = categories.map(cat => `
    <button class="cat-btn" data-cat="${cat.slug}">${cat.name}</button>
  `).join('');

  const catMap = new Map(categories.map(c => [c.id, c.slug]));

  // Products HTML
  const productHtml = products.map(p => {
    const catSlug = catMap.get(p.category_id) || 'other';
    const discount = store.calculateProductDiscount(p);

    const thumbHtml = p.image
      ? `<img src="/uploads/${p.image}" alt="${p.name}">`
      : `<div style="font-size:36px; display:flex; align-items:center; justify-content:center; height:100%; color:var(--gold-soft);">☕</div>`;

    const descHtml = p.description ? `<p class="item-desc">${p.description}</p>` : '';
    const badgeHtml = p.badge ? `<span class="badge">${p.badge}</span>` : '';

    let priceHtml = '';
    let discountBadgeHtml = '';
    if (discount.has_discount) {
      priceHtml = `<span class="price-original">${Jalali.formatNumber(discount.original)}</span> <span class="price-final">${Jalali.formatNumber(discount.final)}</span>`;
      discountBadgeHtml = `<span class="discount-badge">${Jalali.digits(discount.percent)}٪ تخفیف</span>`;
    } else {
      priceHtml = `${Jalali.formatNumber(discount.final)}`;
    }

    const cartAction = isLoggedIn
      ? `
      <form class="cart-form" data-cart-add action="/cart-action" method="POST">
        <input type="hidden" name="product_id" value="${p.id}">
        <button type="submit" class="add-to-cart-btn">افزودن به سبد</button>
      </form>`
      : `<a href="/login" class="add-to-cart-btn" style="text-align:center; text-decoration:none; display:block;">افزودن به سبد</a>`;

    return `
      <article class="item-card" data-cat="${catSlug}" data-name="${p.name}">
        <div class="thumb">
          ${thumbHtml}
          <span class="availability"><i></i> آماده سفارش</span>
        </div>
        <div class="item-body">
          <div class="item-card-meta">
            <span class="item-kicker">انتخاب دنج</span>
            ${badgeHtml}
          </div>
          <h2 class="item-title">${p.name}</h2>
          ${descHtml}
          <div class="item-pricing">
            <div>
              <small>قیمت</small><span class="price">${priceHtml} <small style="font-size:12px; font-weight:normal;">تومان</small></span>
            </div>
            ${discountBadgeHtml}
          </div>
          ${cartAction}
        </div>
      </article>
    `;
  }).join('');

  // Event Popup HTML
  let eventModalHtml = '';
  if (activeEvent) {
    eventModalHtml = `
      <div class="event-overlay" id="eventOverlay">
        <div class="event-modal">
          <button class="event-close" id="eventCloseBtn" type="button" aria-label="بستن">×</button>
          <div class="event-badge">🎉 رویداد و اطلاعیه</div>
          <h3 class="event-title">${activeEvent.title}</h3>
          <p class="event-desc">${activeEvent.content}</p>
          ${activeEvent.collect_phone ? `
            <form class="event-form" action="/subscribe" method="POST">
              <input type="tel" name="phone" placeholder="شماره موبایل (مثلاً ۰۹۱۲۳۴۵۶۷۸۹)" required dir="ltr" style="text-align:right;">
              <button type="submit">عضویت و دریافت تخفیف</button>
            </form>
          ` : ''}
        </div>
      </div>
    `;
  }

  return `<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>کافه دنج | منوی آنلاین و سفارش</title>
    <link rel="stylesheet" href="/assets/css/fonts.css" />
    <link rel="stylesheet" href="/assets/css/menu.css" />
    <script>
      (function () {
        try {
          var savedTheme = localStorage.getItem("denj-theme");
          document.documentElement.dataset.theme =
            savedTheme === "light" ? "light" : "dark";
        } catch (e) {
          document.documentElement.dataset.theme = "dark";
        }
      })();
    </script>
  </head>
  <body>
    <div id="beans"></div>
    <header>
      <button
        class="mobile-menu-toggle"
        id="mobileMenuToggle"
        type="button"
        aria-label="باز کردن منو"
        aria-expanded="false"
      >
        <i></i><i></i><i></i>
      </button>
      <div class="mobile-menu-backdrop" id="mobileMenuBackdrop"></div>
      <aside class="mobile-menu-drawer" id="mobileMenuDrawer">
        <div class="mobile-menu-head">
          <b>کافه دنج</b>
          <button id="mobileMenuClose" type="button" aria-label="بستن">×</button>
        </div>
        <div class="mobile-menu-actions">${mobileAccountActions}</div>
      </aside>
      <nav class="account-actions" aria-label="حساب کاربری">
        ${desktopAccountActions}
      </nav>
      <div class="header-tools">
        <a class="header-link" href="/admin" title="پنل مدیریت">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          مدیریت
        </a>
        <a class="header-link" href="/barista" title="پنل باریستا">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/></svg>
          باریستا
        </a>
      </div>
      <button
        class="theme-switch"
        id="themeSwitch"
        type="button"
        aria-label="تغییر به حالت روشن"
        aria-pressed="false"
      >
        <span class="theme-switch-icon theme-sun" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
          </svg>
        </span>
        <span class="theme-switch-thumb" aria-hidden="true"></span>
        <span class="theme-switch-icon theme-moon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M20.7 15.1A9 9 0 0 1 8.9 3.3 9 9 0 1 0 20.7 15.1Z" />
          </svg>
        </span>
      </button>
      <div class="logo-mark"><img src="/assets/logo.png" alt="لوگوی کافه دنج" /></div>
      <h1>کافه دنج</h1>
      <p>D E N J &nbsp; C A F É</p>
    </header>
    <div class="controls">
      <div class="search-box">
        <input id="searchInput" type="text" placeholder="جستجو در منو..." />
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
      </div>
      <div class="categories" id="categoryBar">
        <button class="cat-btn active" data-cat="all">همه</button>
        ${categoryHtml}
      </div>
    </div>
    <div class="menu-grid" id="menuGrid">
      ${productHtml}
    </div>
    <div class="empty-state" id="emptyState" style="display:none;">
      چیزی با این عنوان در منو پیدا نشد
    </div>
    ${eventModalHtml}
    <footer>
      <div class="site-footer">
        <div class="footer-info">
          <div class="footer-item">
            <strong>آدرس:</strong>
            <div>${store.settings.cafe_address || 'کرج، بلوار شهید مطهری، نبش خیابان پیروزی، کافه دنج'}</div>
          </div>
          <div class="footer-item">
            <strong>شماره تماس:</strong>
            <div>
              <div class="phone-line">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 01.9-.27c1 .25 2 .38 3 .38a1 1 0 011 1V20a1 1 0 01-1 1C10.07 21 3 13.93 3 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1 .13 2 .38 3a1 1 0 01-.27.9l-2.2 2.2z" fill="currentColor"/></svg>
                <a href="tel:09053680080">09053680080</a> | <a href="tel:09927779232">09927779232</a>
              </div>
            </div>
          </div>
          <div class="footer-item">
            <strong>ساعات کاری:</strong>
            <div>${store.settings.cafe_hours || 'از ۶ صبح الی ۱۲ شب'}</div>
          </div>
          <div class="footer-item">
            <strong>اینستاگرام:</strong>
            <div>
              <a class="footer-instagram" href="https://www.instagram.com/cafe_denj_karaj" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" style="vertical-align:middle; margin-left:4px"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                cafe_denj_karaj
              </a>
            </div>
          </div>
        </div>
      </div>
    </footer>
    <script src="/assets/js/menu.js"></script>
  </body>
</html>`;
}
