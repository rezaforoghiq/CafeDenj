<?php
/**
 * public_html/receipt.php
 * -----------------------------------------------------------------------
 * نمایش و چاپ فیش حرارتی ۸۰ میلی‌متری (فاکتور مشتری / برگه آماده‌سازی باریستا)
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../classes/Setting.php';
require_once __DIR__ . '/../classes/PrintJob.php';

$orderId = (int) ($_GET['id'] ?? 0);
$type = (string) ($_GET['type'] ?? 'customer');
$autoprint = !empty($_GET['autoprint']);

if ($orderId <= 0) {
    http_response_code(400);
    exit('شناسه سفارش نامعتبر است.');
}

$order = Order::findById($orderId);
if (!$order) {
    http_response_code(404);
    exit('سفارش مورد نظر یافت نشد.');
}

// Security & Access Control
if ($type === 'barista' || $type === 'preparation') {
    // Barista preparation receipt: only Admin and Barista can access
    if (!Auth::isAdmin() && !Auth::isBarista()) {
        http_response_code(403);
        exit('دسترسی به برگه آماده‌سازی باریستا غیرمجاز است.');
    }
} else {
    // Customer receipt: Admin, Barista with orders.print permission, or the customer owner
    $isAdmin = Auth::isAdmin();
    $isBaristaAllowed = Auth::isBarista() && Auth::can('orders.print');
    $isOwnerCustomer = (function_exists('customerIsLoggedIn') && customerIsLoggedIn() && ((int) $order['customer_id'] === (int) ($_SESSION['customer_id'] ?? 0)))
        || (class_exists('CustomerAuth') && method_exists('CustomerAuth', 'check') && CustomerAuth::check() && ((int) $order['customer_id'] === CustomerAuth::id()));

    if (!$isAdmin && !$isBaristaAllowed && !$isOwnerCustomer) {
        http_response_code(403);
        exit('دسترسی به این فاکتور غیرمجاز است.');
    }
}

// Load Cafe Settings
$cafeTitle = Setting::get('cafe_title', 'کافه دنج');
$cafePhone = Setting::get('cafe_phone', '09053680080');
$cafeAddress = Setting::get('cafe_address', 'کرج، بلوار شهید مطهری، نبش خیابان پیروزی، کافه دنج');

if ($type === 'barista' || $type === 'preparation') {
    $payload = PrintJob::buildPayload($order);
    require __DIR__ . '/../templates/receipt-barista.php';
} else {
    $payload = PrintJob::buildInvoicePayload($order);
    require __DIR__ . '/../templates/receipt-customer.php';
}