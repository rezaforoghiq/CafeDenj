export interface Category {
  id: number;
  name: string;
  slug: string;
  sort_order: number;
  created_at: string;
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  description: string;
  price: number;
  image: string | null;
  badge: string | null;
  status: 'active' | 'inactive';
  discount_enabled: boolean;
  discount_type: 'percentage' | 'fixed' | null;
  discount_value: number | null;
  discount_starts_at: string | null;
  discount_ends_at: string | null;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface Customer {
  id: number;
  first_name: string;
  last_name: string;
  phone: string;
  source: string;
  created_at: string;
}

export interface CustomerAccount {
  id: number;
  customer_id: number;
  password_hash: string;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface AdminUser {
  id: number;
  username: string;
  password_hash: string;
  created_at: string;
}

export interface Barista {
  id: number;
  full_name: string;
  phone: string;
  username: string;
  password_hash: string;
  status: 'active' | 'inactive';
  permissions: string[];
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Coupon {
  id: number;
  code: string;
  percent: number;
  expires_at: string | null;
  is_active: boolean;
  created_at: string;
}

export interface EventPopup {
  id: number;
  title: string;
  content: string;
  collect_phone: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface CartItem {
  customer_id: number;
  product_id: number;
  quantity: number;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  price: number;
}

export interface Order {
  id: number;
  customer_id: number;
  barista_id: number | null;
  order_number: string;
  total_price: number;
  customer_note: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'completed';
  payment_method: 'card' | 'cash' | 'transfer' | null;
  coupon_code: string | null;
  coupon_percent: number | null;
  discount_amount: number;
  created_at: string;
  updated_at: string;
  approved_at: string | null;
  items?: OrderItem[];
  customer_name?: string;
  customer_phone?: string;
}

export interface ActivityLog {
  id: number;
  user_id: number | null;
  user_label: string | null;
  role: 'admin' | 'barista' | 'customer' | 'system';
  action: string;
  description: string;
  order_id: number | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface PrintJob {
  id: number;
  order_id: number;
  order_number: string;
  job_type: 'preparation' | 'customer_invoice';
  job_reference: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  payload: string | null;
  retry_count: number;
  last_error: string | null;
  created_at: string;
  updated_at: string;
  printed_at: string | null;
}
