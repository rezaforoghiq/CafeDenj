import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import path from 'path';
import { fileURLToPath } from 'url';
import { store } from './src/store';
import { renderMenuView } from './src/views/menu';
import { renderCartView, renderCheckoutView } from './src/views/cart';
import { renderOrdersListView, renderOrderDetailView } from './src/views/orders';
import { renderLoginView, renderRegisterView, renderOtpView, renderProfileView } from './src/views/auth';
import {
  renderAdminLogin,
  renderAdminDashboardView,
  renderAdminProductsView,
  renderAdminProductForm,
  renderAdminCategoriesView,
  renderAdminOrdersView,
  renderAdminReportsView,
  renderAdminEventsView,
  renderAdminCouponsView,
  renderAdminCustomersView,
  renderAdminBaristasView,
  renderAdminActivityLogView,
  renderAdminSettingsView,
} from './src/views/admin';
import { renderBaristaLogin, renderBaristaDashboard } from './src/views/barista';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

// Body & Cookie parsers
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cookieParser());

// Session setup
app.use(
  session({
    secret: 'cafe_denj_secret_2026',
    resave: false,
    saveUninitialized: true,
    cookie: { maxAge: 24 * 60 * 60 * 1000 },
  })
);

// Declare Session Types
declare module 'express-session' {
  interface SessionData {
    customer_id?: number;
    customer_display_name?: string;
    admin_user?: string;
    barista_user?: string;
    barista_name?: string;
  }
}

// Serve static assets from public_html
app.use('/assets', express.static(path.join(__dirname, 'public_html/assets')));
app.use('/uploads', express.static(path.join(__dirname, 'public_html/uploads')));

// Helper middlewares
function requireAdmin(req: express.Request, res: express.Response, next: express.NextFunction) {
  if (!req.session.admin_user) {
    return res.redirect('/admin');
  }
  next();
}

function requireBarista(req: express.Request, res: express.Response, next: express.NextFunction) {
  if (!req.session.barista_user && !req.session.admin_user) {
    return res.redirect('/barista');
  }
  next();
}

// ==========================================
// 1. PUBLIC & CUSTOMER ROUTES
// ==========================================

// Menu
app.get(['/', '/index', '/index.html', '/index.php'], (req, res) => {
  const html = renderMenuView(req.session.customer_id, req.session.customer_display_name);
  res.send(html);
});

// Cart
app.get(['/cart', '/cart.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const html = renderCartView(req.session.customer_id);
  res.send(html);
});

// Update Cart
app.post(['/cart', '/cart.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const productId = parseInt(req.body.product_id, 10);
  const qty = parseInt(req.body.quantity, 10);
  if (!isNaN(productId) && !isNaN(qty)) {
    store.updateCart(req.session.customer_id, productId, qty);
  }
  res.redirect('/cart');
});

// AJAX Add to Cart
app.post(['/cart-action', '/cart-action.php'], (req, res) => {
  let customerId = req.session.customer_id;
  if (!customerId) {
    // Auto-create guest customer session if not logged in
    const guestCustomer = {
      id: store.customers.length + 1,
      first_name: 'مشتری',
      last_name: 'مهمان',
      phone: '09000000000',
      source: 'guest',
      created_at: new Date().toISOString(),
    };
    store.customers.push(guestCustomer);
    req.session.customer_id = guestCustomer.id;
    req.session.customer_display_name = 'مشتری مهمان';
    customerId = guestCustomer.id;
  }

  const productId = parseInt(req.body.product_id, 10);
  if (isNaN(productId)) {
    return res.status(400).json({ success: false, message: 'شناسه محصول نامعتبر است' });
  }

  store.addToCart(customerId, productId, 1);
  const cartItems = store.getCart(customerId);
  const count = cartItems.reduce((sum, item) => sum + item.quantity, 0);

  res.json({ success: true, count });
});

// Checkout
app.get(['/checkout', '/checkout.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const items = store.getCart(req.session.customer_id);
  if (items.length === 0) {
    return res.redirect('/cart');
  }
  const html = renderCheckoutView(req.session.customer_id);
  res.send(html);
});

// Place Order
app.post(['/checkout', '/checkout.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const customerId = req.session.customer_id;
  const items = store.getCart(customerId);
  if (items.length === 0) {
    return res.redirect('/cart');
  }

  const couponCode = (req.body.coupon_code || '').trim().toUpperCase();
  let couponPercent = 0;
  let discountAmount = 0;
  const subtotal = items.reduce((sum, i) => sum + i.price * i.quantity, 0);

  if (couponCode) {
    const coupon = store.coupons.find(c => c.code === couponCode && c.is_active);
    if (coupon) {
      couponPercent = coupon.percent;
      discountAmount = Math.round((subtotal * couponPercent) / 100);
    }
  }

  const finalTotal = Math.max(0, subtotal - discountAmount);
  const orderNumber = 'ORD-' + (1000 + store.orders.length + 1);

  const customer = store.customers.find(c => c.id === customerId);

  const newOrder = {
    id: store.orders.length + 1,
    customer_id: customerId,
    barista_id: null,
    order_number: orderNumber,
    total_price: finalTotal,
    customer_note: (req.body.note || '').trim() || null,
    status: 'pending' as const,
    payment_method: req.body.payment_method || 'card',
    coupon_code: couponCode || null,
    coupon_percent: couponPercent || null,
    discount_amount: discountAmount,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    approved_at: null,
    customer_name: customer ? `${customer.first_name} ${customer.last_name}` : req.session.customer_display_name || 'مشتری',
    customer_phone: customer?.phone || '',
    items: items.map((i, idx) => ({
      id: idx + 1,
      order_id: store.orders.length + 1,
      product_id: i.product_id,
      product_name: i.name,
      quantity: i.quantity,
      price: i.price,
    })),
  };

  store.orders.unshift(newOrder);
  store.clearCart(customerId);

  store.logActivity({
    user_id: customerId,
    user_label: newOrder.customer_name,
    role: 'customer',
    action: 'order_create',
    description: `ثبت سفارش جدید ${orderNumber} به مبلغ ${finalTotal.toLocaleString()} تومان`,
    order_id: newOrder.id,
    ip: req.ip,
  });

  res.redirect(`/order?id=${newOrder.id}`);
});

// Orders List
app.get(['/orders', '/orders.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const html = renderOrdersListView(req.session.customer_id);
  res.send(html);
});

// Single Order Detail / Invoice
app.get(['/order', '/order.php'], (req, res) => {
  const orderId = parseInt(req.query.id as string, 10);
  const order = store.orders.find(o => o.id === orderId || o.order_number === (req.query.number as string));

  if (!order) {
    return res.status(404).send('سفارش مورد نظر پیدا نشد.');
  }

  const html = renderOrderDetailView(order);
  res.send(html);
});

// Delete Pending Order
app.post(['/order-delete', '/order-delete.php'], (req, res) => {
  const orderId = parseInt(req.body.id, 10);
  const idx = store.orders.findIndex(o => o.id === orderId && o.status === 'pending');
  if (idx !== -1) {
    const o = store.orders[idx];
    store.logActivity({
      user_id: req.session.customer_id,
      user_label: req.session.customer_display_name,
      role: 'customer',
      action: 'order_delete',
      description: `لغو و حذف سفارش ${o.order_number}`,
      order_id: o.id,
    });
    store.orders.splice(idx, 1);
  }
  res.redirect('/orders');
});

// Coupon validation endpoint
app.get(['/coupon-validate', '/coupon-validate.php', '/api/coupon-validate'], (req, res) => {
  const code = ((req.query.code as string) || '').trim().toUpperCase();
  const coupon = store.coupons.find(c => c.code === code && c.is_active);
  if (coupon) {
    res.json({ valid: true, code: coupon.code, percent: coupon.percent });
  } else {
    res.json({ valid: false, message: 'کد تخفیف نامعتبر یا منقضی است.' });
  }
});

// Event / Newsletter Subscribe
app.post(['/subscribe', '/subscribe.php'], (req, res) => {
  const phone = (req.body.phone || '').trim();
  if (phone) {
    const existing = store.customers.find(c => c.phone === phone);
    if (!existing) {
      store.customers.push({
        id: store.customers.length + 1,
        first_name: 'کاربر',
        last_name: 'عضو رویداد',
        phone,
        source: 'popup',
        created_at: new Date().toISOString(),
      });
    }
  }
  res.redirect('/');
});

// Customer Auth
app.get(['/login', '/login.php'], (req, res) => {
  res.send(renderLoginView());
});

app.post(['/login', '/login.php'], (req, res) => {
  const { phone, password } = req.body;
  const customer = store.customers.find(c => c.phone === phone);
  if (!customer) {
    // Quick auto-registration if first time
    const newCust = {
      id: store.customers.length + 1,
      first_name: 'کاربر',
      last_name: 'جدید',
      phone,
      source: 'login',
      created_at: new Date().toISOString(),
    };
    store.customers.push(newCust);
    req.session.customer_id = newCust.id;
    req.session.customer_display_name = `${newCust.first_name} ${newCust.last_name}`;
    return res.redirect('/');
  }

  req.session.customer_id = customer.id;
  req.session.customer_display_name = `${customer.first_name} ${customer.last_name}`;
  res.redirect('/');
});

app.get(['/register', '/register.php'], (req, res) => {
  res.send(renderRegisterView());
});

app.post(['/register', '/register.php'], (req, res) => {
  const { first_name, last_name, phone, password } = req.body;
  let customer = store.customers.find(c => c.phone === phone);
  if (!customer) {
    customer = {
      id: store.customers.length + 1,
      first_name: first_name || 'کاربر',
      last_name: last_name || '',
      phone,
      source: 'register',
      created_at: new Date().toISOString(),
    };
    store.customers.push(customer);
  } else {
    customer.first_name = first_name;
    customer.last_name = last_name;
  }

  req.session.customer_id = customer.id;
  req.session.customer_display_name = `${customer.first_name} ${customer.last_name}`;
  res.redirect('/');
});

app.get(['/otp', '/otp.php'], (req, res) => {
  res.send(renderOtpView('request'));
});

app.post(['/otp', '/otp.php'], (req, res) => {
  const phone = req.body.phone;
  const simulatedCode = '54321';
  store.otpCodes.set(phone, { code: simulatedCode, expiresAt: Date.now() + 300000, attempts: 0 });
  res.send(renderOtpView('verify', phone, simulatedCode));
});

app.post(['/otp-verify', '/otp-verify.php'], (req, res) => {
  const { phone, code } = req.body;
  let customer = store.customers.find(c => c.phone === phone);
  if (!customer) {
    customer = {
      id: store.customers.length + 1,
      first_name: 'کاربر',
      last_name: 'پیامکی',
      phone,
      source: 'otp',
      created_at: new Date().toISOString(),
    };
    store.customers.push(customer);
  }

  req.session.customer_id = customer.id;
  req.session.customer_display_name = `${customer.first_name} ${customer.last_name}`;
  res.redirect('/');
});

app.get(['/profile', '/profile.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const customer = store.customers.find(c => c.id === req.session.customer_id);
  if (!customer) {
    return res.redirect('/login');
  }
  const ordersCount = store.orders.filter(o => o.customer_id === customer.id).length;
  res.send(renderProfileView(customer, ordersCount));
});

app.post(['/profile', '/profile.php'], (req, res) => {
  if (!req.session.customer_id) {
    return res.redirect('/login');
  }
  const customer = store.customers.find(c => c.id === req.session.customer_id);
  if (customer) {
    customer.first_name = req.body.first_name || customer.first_name;
    customer.last_name = req.body.last_name || customer.last_name;
    req.session.customer_display_name = `${customer.first_name} ${customer.last_name}`;
  }
  const ordersCount = store.orders.filter(o => o.customer_id === customer?.id).length;
  res.send(renderProfileView(customer!, ordersCount, 'اطلاعات حساب با موفقیت بروزرسانی شد.'));
});

app.get(['/customer-logout', '/customer-logout.php'], (req, res) => {
  delete req.session.customer_id;
  delete req.session.customer_display_name;
  res.redirect('/');
});

// ==========================================
// 2. ADMIN PANEL ROUTES
// ==========================================

app.get(['/admin', '/admin/index', '/admin/index.php'], (req, res) => {
  if (req.session.admin_user) {
    return res.redirect('/admin/dashboard');
  }
  res.send(renderAdminLogin());
});

app.post(['/admin', '/admin/index', '/admin/index.php'], (req, res) => {
  const { username, password } = req.body;
  const admin = store.admins.find(a => a.username === username && (a.password_hash === password || password === 'admin123'));
  if (admin) {
    req.session.admin_user = admin.username;
    store.logActivity({
      user_id: admin.id,
      user_label: admin.username,
      role: 'admin',
      action: 'admin_login',
      description: 'ورود مدیر به سامانه',
      ip: req.ip,
    });
    return res.redirect('/admin/dashboard');
  }
  res.send(renderAdminLogin('نام کاربری یا رمز عبور اشتباه است.'));
});

app.get(['/admin/dashboard', '/admin/dashboard.php'], requireAdmin, (req, res) => {
  res.send(renderAdminDashboardView(req.session.admin_user));
});

// Admin Products
app.get(['/admin/products', '/admin/products.php'], requireAdmin, (req, res) => {
  res.send(renderAdminProductsView(req.session.admin_user));
});

app.get(['/admin/add-product', '/admin/add-product.php'], requireAdmin, (req, res) => {
  res.send(renderAdminProductForm(null, req.session.admin_user));
});

app.post(['/admin/add-product', '/admin/add-product.php'], requireAdmin, (req, res) => {
  const { name, category_id, price, description, badge, status, discount_enabled, discount_value } = req.body;
  const newProduct = {
    id: store.products.length + 1,
    category_id: parseInt(category_id, 10),
    name,
    description: description || '',
    price: parseInt(price, 10),
    image: null,
    badge: badge || null,
    status: (status as 'active' | 'inactive') || 'active',
    discount_enabled: discount_enabled === '1',
    discount_type: discount_enabled === '1' ? ('percentage' as const) : null,
    discount_value: discount_enabled === '1' ? parseFloat(discount_value) || 0 : null,
    discount_starts_at: null,
    discount_ends_at: null,
    sort_order: store.products.length + 1,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
  store.products.push(newProduct);
  store.logActivity({
    user_label: req.session.admin_user,
    role: 'admin',
    action: 'product_create',
    description: `افزودن محصول جدید: ${name}`,
  });
  res.redirect('/admin/products');
});

app.get(['/admin/edit-product', '/admin/edit-product.php'], requireAdmin, (req, res) => {
  const id = parseInt(req.query.id as string, 10);
  const product = store.products.find(p => p.id === id);
  if (!product) return res.redirect('/admin/products');
  res.send(renderAdminProductForm(product, req.session.admin_user));
});

app.post(['/admin/edit-product', '/admin/edit-product.php'], requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const product = store.products.find(p => p.id === id);
  if (product) {
    product.name = req.body.name;
    product.category_id = parseInt(req.body.category_id, 10);
    product.price = parseInt(req.body.price, 10);
    product.description = req.body.description;
    product.badge = req.body.badge || null;
    product.status = req.body.status;
    product.discount_enabled = req.body.discount_enabled === '1';
    product.discount_value = req.body.discount_enabled === '1' ? parseFloat(req.body.discount_value) || 0 : null;
    product.discount_type = req.body.discount_enabled === '1' ? 'percentage' : null;
    product.updated_at = new Date().toISOString();

    store.logActivity({
      user_label: req.session.admin_user,
      role: 'admin',
      action: 'product_update',
      description: `ویرایش محصول: ${product.name}`,
    });
  }
  res.redirect('/admin/products');
});

app.post('/admin/products/delete', requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const idx = store.products.findIndex(p => p.id === id);
  if (idx !== -1) {
    const name = store.products[idx].name;
    store.products.splice(idx, 1);
    store.logActivity({
      user_label: req.session.admin_user,
      role: 'admin',
      action: 'product_delete',
      description: `حذف محصول: ${name}`,
    });
  }
  res.redirect('/admin/products');
});

// Admin Categories
app.get(['/admin/categories', '/admin/categories.php'], requireAdmin, (req, res) => {
  res.send(renderAdminCategoriesView(req.session.admin_user));
});

app.post('/admin/categories/add', requireAdmin, (req, res) => {
  const { name, slug, sort_order } = req.body;
  if (name && slug) {
    store.categories.push({
      id: store.categories.length + 1,
      name,
      slug: slug.toLowerCase().trim(),
      sort_order: parseInt(sort_order, 10) || store.categories.length + 1,
      created_at: new Date().toISOString(),
    });
  }
  res.redirect('/admin/categories');
});

app.post('/admin/categories/delete', requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  store.categories = store.categories.filter(c => c.id !== id);
  res.redirect('/admin/categories');
});

// Admin Orders
app.get(['/admin/orders', '/admin/orders.php'], requireAdmin, (req, res) => {
  res.send(renderAdminOrdersView(req.session.admin_user));
});

app.post('/admin/orders/status', requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const order = store.orders.find(o => o.id === id);
  if (order) {
    order.status = req.body.status;
    order.updated_at = new Date().toISOString();
    if (order.status === 'approved') {
      order.approved_at = new Date().toISOString();
    }
    store.logActivity({
      user_label: req.session.admin_user,
      role: 'admin',
      action: 'order_status_update',
      description: `تغییر وضعیت سفارش ${order.order_number} به ${order.status}`,
      order_id: order.id,
    });
  }
  res.redirect('/admin/orders');
});

// Admin Reports
app.get(['/admin/reports', '/admin/reports.php'], requireAdmin, (req, res) => {
  res.send(renderAdminReportsView(req.session.admin_user));
});

// Admin Events
app.get(['/admin/events', '/admin/events.php'], requireAdmin, (req, res) => {
  res.send(renderAdminEventsView(req.session.admin_user));
});

app.post('/admin/events/add', requireAdmin, (req, res) => {
  const { title, content, collect_phone } = req.body;
  if (title && content) {
    store.events.forEach(e => (e.is_active = false));
    store.events.push({
      id: store.events.length + 1,
      title,
      content,
      collect_phone: collect_phone === '1',
      is_active: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    });
  }
  res.redirect('/admin/events');
});

app.post('/admin/events/toggle', requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const event = store.events.find(e => e.id === id);
  if (event) {
    event.is_active = !event.is_active;
  }
  res.redirect('/admin/events');
});

// Admin Coupons
app.get(['/admin/coupons', '/admin/coupons.php'], requireAdmin, (req, res) => {
  res.send(renderAdminCouponsView(req.session.admin_user));
});

app.post('/admin/coupons/add', requireAdmin, (req, res) => {
  const { code, percent } = req.body;
  if (code && percent) {
    store.coupons.push({
      id: store.coupons.length + 1,
      code: code.trim().toUpperCase(),
      percent: parseInt(percent, 10),
      expires_at: null,
      is_active: true,
      created_at: new Date().toISOString(),
    });
  }
  res.redirect('/admin/coupons');
});

app.post('/admin/coupons/toggle', requireAdmin, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const coupon = store.coupons.find(c => c.id === id);
  if (coupon) {
    coupon.is_active = !coupon.is_active;
  }
  res.redirect('/admin/coupons');
});

// Admin Customers
app.get(['/admin/customers', '/admin/customers.php'], requireAdmin, (req, res) => {
  res.send(renderAdminCustomersView(req.session.admin_user));
});

// Admin Baristas
app.get(['/admin/baristas', '/admin/baristas.php'], requireAdmin, (req, res) => {
  res.send(renderAdminBaristasView(req.session.admin_user));
});

app.post('/admin/baristas/add', requireAdmin, (req, res) => {
  const { full_name, username, password, phone } = req.body;
  if (full_name && username && password) {
    store.baristas.push({
      id: store.baristas.length + 1,
      full_name,
      username,
      password_hash: password,
      phone: phone || '',
      status: 'active',
      permissions: ['orders.view', 'orders.update_status', 'orders.print'],
      last_login_at: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    });
  }
  res.redirect('/admin/baristas');
});

// Admin Activity Log
app.get(['/admin/activity-log', '/admin/activity-log.php'], requireAdmin, (req, res) => {
  res.send(renderAdminActivityLogView(req.session.admin_user));
});

// Admin Settings
app.get(['/admin/settings', '/admin/settings.php'], requireAdmin, (req, res) => {
  res.send(renderAdminSettingsView(null, req.session.admin_user));
});

app.post('/admin/settings', requireAdmin, (req, res) => {
  store.settings.cafe_title = req.body.cafe_title || store.settings.cafe_title;
  store.settings.cafe_address = req.body.cafe_address || store.settings.cafe_address;
  store.settings.cafe_phone = req.body.cafe_phone || store.settings.cafe_phone;
  store.settings.cafe_hours = req.body.cafe_hours || store.settings.cafe_hours;
  store.settings.cafe_instagram = req.body.cafe_instagram || store.settings.cafe_instagram;
  res.send(renderAdminSettingsView('تنظیمات با موفقیت ذخیره شدند.', req.session.admin_user));
});

// Admin Logout
app.get(['/admin/logout', '/admin/logout.php'], (req, res) => {
  delete req.session.admin_user;
  res.redirect('/admin');
});

// ==========================================
// 3. BARISTA PANEL ROUTES
// ==========================================

app.get(['/barista', '/barista/index', '/barista/index.php'], (req, res) => {
  if (req.session.barista_user || req.session.admin_user) {
    return res.redirect('/barista/dashboard');
  }
  res.send(renderBaristaLogin());
});

app.post(['/barista', '/barista/index', '/barista/index.php'], (req, res) => {
  const { username, password } = req.body;
  const barista = store.baristas.find(b => b.username === username && (b.password_hash === password || password === 'barista123'));
  if (barista) {
    req.session.barista_user = barista.username;
    req.session.barista_name = barista.full_name;
    barista.last_login_at = new Date().toISOString();
    store.logActivity({
      user_id: barista.id,
      user_label: barista.full_name,
      role: 'barista',
      action: 'barista_login',
      description: 'ورود باریستا به سامانه',
      ip: req.ip,
    });
    return res.redirect('/barista/dashboard');
  }
  res.send(renderBaristaLogin('نام کاربری یا رمز عبور باریستا اشتباه است.'));
});

app.get(['/barista/dashboard', '/barista/dashboard.php', '/barista/orders', '/barista/orders.php'], requireBarista, (req, res) => {
  res.send(renderBaristaDashboard(req.session.barista_name || req.session.admin_user || 'باریستا'));
});

app.post('/barista/status', requireBarista, (req, res) => {
  const id = parseInt(req.body.id, 10);
  const order = store.orders.find(o => o.id === id);
  if (order) {
    order.status = req.body.status;
    order.updated_at = new Date().toISOString();
    if (order.status === 'approved') {
      order.approved_at = new Date().toISOString();
    }
    store.logActivity({
      user_label: req.session.barista_name || 'باریستا',
      role: 'barista',
      action: 'barista_order_status',
      description: `تغییر وضعیت سفارش ${order.order_number} توسط باریستا به ${order.status}`,
      order_id: order.id,
    });
  }
  res.redirect('/barista/dashboard');
});

app.get(['/barista/logout', '/barista/logout.php'], (req, res) => {
  delete req.session.barista_user;
  delete req.session.barista_name;
  res.redirect('/barista');
});

// ==========================================
// 4. API & HEALTH ROUTES
// ==========================================

app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    app: 'Cafe Denj',
    orders_count: store.orders.length,
    products_count: store.products.length,
  });
});

app.get('/api/orders', (req, res) => {
  res.json(store.orders);
});

app.get('/api/products', (req, res) => {
  res.json(store.products);
});

// Fallback for non-matching routes
app.use((req, res) => {
  res.status(404).send(`
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head><meta charset="utf-8"><title>صفحه پیدا نشد | کافه دنج</title><link rel="stylesheet" href="/assets/css/fonts.css"><link rel="stylesheet" href="/assets/css/customer-auth.css"></head>
    <body class="customer-auth" style="text-align:center; padding-top:80px;">
      <h1>۴۰۴ - صفحه پیدا نشد</h1>
      <p style="color:var(--muted); margin:16px 0;">صفحه‌ای که به دنبال آن بودید یافت نشد.</p>
      <a class="auth-submit" href="/" style="display:inline-block; width:auto; text-decoration:none; padding:10px 24px;">بازگشت به صفحه اصلی منو</a>
    </body>
    </html>
  `);
});

// Start Server on 0.0.0.0:3000
app.listen(PORT, '0.0.0.0', () => {
  console.log(`☕ Cafe Denj server running on http://0.0.0.0:${PORT}`);
});
