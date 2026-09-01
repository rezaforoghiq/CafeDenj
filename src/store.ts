import {
  Category,
  Product,
  Customer,
  CustomerAccount,
  AdminUser,
  Barista,
  Coupon,
  EventPopup,
  CartItem,
  Order,
  OrderItem,
  ActivityLog,
  PrintJob,
} from './types';

class Store {
  categories: Category[] = [
    { id: 1, name: 'قهوه', slug: 'coffee', sort_order: 1, created_at: new Date().toISOString() },
    { id: 2, name: 'دسر', slug: 'dessert', sort_order: 2, created_at: new Date().toISOString() },
    { id: 3, name: 'نوشیدنی سرد', slug: 'cold', sort_order: 3, created_at: new Date().toISOString() },
    { id: 4, name: 'صبحانه', slug: 'breakfast', sort_order: 4, created_at: new Date().toISOString() },
    { id: 5, name: 'چای و دمنوش', slug: 'tea', sort_order: 5, created_at: new Date().toISOString() },
  ];

  products: Product[] = [
    {
      id: 1,
      category_id: 1,
      name: 'اسپرسو',
      description: 'دان تازه‌آسیاب، عصاره غلیظ و پرکافئین',
      price: 45000,
      image: 'product_6a649a4de181a3.44906207.png',
      badge: null,
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 2,
      category_id: 1,
      name: 'کاپوچینو',
      description: 'اسپرسو، شیر بخارداده و فوم مخملی',
      price: 65000,
      image: 'product_6a7b61686f8cb5.89906919.jpg',
      badge: 'پرفروش',
      status: 'active',
      discount_enabled: true,
      discount_type: 'percentage',
      discount_value: 15,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 2,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 3,
      category_id: 1,
      name: 'لاته',
      description: 'طعمی ملایم با نسبت بالای شیر',
      price: 70000,
      image: null,
      badge: null,
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 3,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 4,
      category_id: 3,
      name: 'آیس‌لاته',
      description: 'قهوه سرد با یخ و شیر سرد',
      price: 75000,
      image: 'product_6a7dc64a791a08.07058210.webp',
      badge: null,
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 5,
      category_id: 2,
      name: 'تیرامیسو',
      description: 'لایه‌های ماسکارپونه و قهوه',
      price: 120000,
      image: 'product_6a9484077eb642.28154739.webp',
      badge: 'خانگی',
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 6,
      category_id: 2,
      name: 'چیزکیک',
      description: 'پنیر خامه‌ای روی بیسکوییت کره‌ای',
      price: 110000,
      image: null,
      badge: null,
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 2,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 7,
      category_id: 4,
      name: 'صبحانه کامل',
      description: 'تخم‌مرغ، پنیر، مربا، کره و نان تازه',
      price: 185000,
      image: null,
      badge: 'ویژه',
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 8,
      category_id: 5,
      name: 'دمنوش گل‌گاوزبان',
      description: 'آرام‌بخش و معطر، سرو با عسل',
      price: 40000,
      image: null,
      badge: null,
      status: 'active',
      discount_enabled: false,
      discount_type: null,
      discount_value: null,
      discount_starts_at: null,
      discount_ends_at: null,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
  ];

  admins: AdminUser[] = [
    { id: 1, username: 'admin', password_hash: 'admin123', created_at: new Date().toISOString() },
    { id: 2, username: 'admin2', password_hash: 'admin123', created_at: new Date().toISOString() },
  ];

  baristas: Barista[] = [
    {
      id: 1,
      full_name: 'علی کریمی (باریستا ارشد)',
      phone: '09123456789',
      username: 'barista',
      password_hash: 'barista123',
      status: 'active',
      permissions: ['orders.view', 'orders.view_details', 'orders.update_status', 'orders.print', 'products.view'],
      last_login_at: new Date().toISOString(),
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
  ];

  customers: Customer[] = [
    { id: 1, first_name: 'محمد', last_name: 'رضایی', phone: '09121112233', source: 'register', created_at: new Date().toISOString() },
    { id: 2, first_name: 'سارا', last_name: 'احمدی', phone: '09355556677', source: 'popup', created_at: new Date().toISOString() },
  ];

  customerAccounts: CustomerAccount[] = [
    { id: 1, customer_id: 1, password_hash: '123456', last_login_at: new Date().toISOString(), created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
  ];

  coupons: Coupon[] = [
    { id: 1, code: 'DENJ10', percent: 10, expires_at: null, is_active: true, created_at: new Date().toISOString() },
    { id: 2, code: 'WELCOME20', percent: 20, expires_at: null, is_active: true, created_at: new Date().toISOString() },
  ];

  events: EventPopup[] = [
    {
      id: 1,
      title: 'تخفیف ویژه آغاز فصل در کافه دنج',
      content: 'به مناسبت افتتاحیه سفارش آنلاین، با عضویت در باشگاه مشتریان از ۱۰٪ تخفیف روی تمام سفارش‌های حضوری و بیرون‌بر بهره‌مند شوید.',
      collect_phone: true,
      is_active: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
  ];

  settings: Record<string, string> = {
    phone_required: '1',
    cafe_title: 'کافه دنج',
    cafe_phone: '09053680080',
    cafe_address: 'کرج، بلوار شهید مطهری، نبش خیابان پیروزی، کافه دنج',
    cafe_hours: 'از ۶ صبح الی ۱۲ شب',
    cafe_instagram: 'cafe_denj_karaj',
  };

  carts: CartItem[] = [];

  orders: Order[] = [
    {
      id: 1,
      customer_id: 1,
      barista_id: 1,
      order_number: 'ORD-1001',
      total_price: 110000,
      customer_note: 'کم شکر لطفاً',
      status: 'completed',
      payment_method: 'card',
      coupon_code: null,
      coupon_percent: null,
      discount_amount: 0,
      created_at: new Date(Date.now() - 3600000 * 5).toISOString(),
      updated_at: new Date(Date.now() - 3600000 * 4).toISOString(),
      approved_at: new Date(Date.now() - 3600000 * 4.8).toISOString(),
      customer_name: 'محمد رضایی',
      customer_phone: '09121112233',
      items: [
        { id: 1, order_id: 1, product_id: 1, product_name: 'اسپرسو', quantity: 1, price: 45000 },
        { id: 2, order_id: 1, product_id: 2, product_name: 'کاپوچینو', quantity: 1, price: 65000 },
      ],
    },
    {
      id: 2,
      customer_id: 2,
      barista_id: null,
      order_number: 'ORD-1002',
      total_price: 185000,
      customer_note: 'نان اضافه',
      status: 'pending',
      payment_method: 'card',
      coupon_code: 'DENJ10',
      coupon_percent: 10,
      discount_amount: 18500,
      created_at: new Date(Date.now() - 600000).toISOString(),
      updated_at: new Date(Date.now() - 600000).toISOString(),
      approved_at: null,
      customer_name: 'سارا احمدی',
      customer_phone: '09355556677',
      items: [
        { id: 3, order_id: 2, product_id: 7, product_name: 'صبحانه کامل', quantity: 1, price: 185000 },
      ],
    },
  ];

  activityLogs: ActivityLog[] = [
    {
      id: 1,
      user_id: 1,
      user_label: 'admin',
      role: 'admin',
      action: 'system_start',
      description: 'راه‌اندازی اولیه سامانه کافه دنج',
      order_id: null,
      ip_address: '127.0.0.1',
      user_agent: 'System',
      created_at: new Date().toISOString(),
    },
  ];

  printJobs: PrintJob[] = [];
  otpCodes: Map<string, { code: string; expiresAt: number; attempts: number }> = new Map();

  // Helper calculation for product discounts
  calculateProductDiscount(product: Product) {
    if (!product.discount_enabled || !product.discount_value) {
      return {
        has_discount: false,
        original: product.price,
        final: product.price,
        percent: 0,
      };
    }
    let finalPrice = product.price;
    let percent = 0;

    if (product.discount_type === 'percentage') {
      percent = Math.round(product.discount_value);
      finalPrice = Math.round(product.price * (1 - percent / 100));
    } else if (product.discount_type === 'fixed') {
      finalPrice = Math.max(0, product.price - product.discount_value);
      percent = Math.round(((product.price - finalPrice) / product.price) * 100);
    }

    return {
      has_discount: finalPrice < product.price,
      original: product.price,
      final: finalPrice,
      percent,
    };
  }

  logActivity(data: {
    user_id?: number | null;
    user_label?: string | null;
    role?: 'admin' | 'barista' | 'customer' | 'system';
    action: string;
    description: string;
    order_id?: number | null;
    ip?: string | null;
    ua?: string | null;
  }) {
    const newLog: ActivityLog = {
      id: this.activityLogs.length + 1,
      user_id: data.user_id ?? null,
      user_label: data.user_label ?? 'سیستم',
      role: data.role ?? 'system',
      action: data.action,
      description: data.description,
      order_id: data.order_id ?? null,
      ip_address: data.ip ?? '127.0.0.1',
      user_agent: data.ua ?? '',
      created_at: new Date().toISOString(),
    };
    this.activityLogs.unshift(newLog);
    if (this.activityLogs.length > 500) this.activityLogs.pop();
  }

  // Cart operations
  getCart(customerId: number) {
    const items = this.carts.filter(c => c.customer_id === customerId);
    return items.map(item => {
      const product = this.products.find(p => p.id === item.product_id);
      const discount = product ? this.calculateProductDiscount(product) : { final: 0, original: 0 };
      return {
        customer_id: item.customer_id,
        product_id: item.product_id,
        quantity: item.quantity,
        name: product?.name ?? 'محصول نامشخص',
        price: discount.final,
        original_price: discount.original,
        image: product?.image ?? null,
      };
    });
  }

  addToCart(customerId: number, productId: number, qty = 1) {
    const existing = this.carts.find(c => c.customer_id === customerId && c.product_id === productId);
    if (existing) {
      existing.quantity += qty;
    } else {
      this.carts.push({ customer_id: customerId, product_id: productId, quantity: qty });
    }
  }

  updateCart(customerId: number, productId: number, quantity: number) {
    const idx = this.carts.findIndex(c => c.customer_id === customerId && c.product_id === productId);
    if (idx !== -1) {
      if (quantity <= 0) {
        this.carts.splice(idx, 1);
      } else {
        this.carts[idx].quantity = quantity;
      }
    }
  }

  clearCart(customerId: number) {
    this.carts = this.carts.filter(c => c.customer_id !== customerId);
  }
}

export const store = new Store();
