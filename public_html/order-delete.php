<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Order.php';

header('Content-Type: application/json; charset=utf-8');

if (!customerIsLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'نشست شما منقضی شده است. لطفاً صفحه را تازه‌سازی کنید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? $_POST['id'] ?? 0);
if ($orderId <= 0 || !Order::deletePending($orderId, (int)$_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'فقط سفارش‌های در انتظار تأیید قابل حذف هستند.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'سفارش با موفقیت لغو و حذف شد.'], JSON_UNESCAPED_UNICODE);