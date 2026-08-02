<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Order.php';
header('Content-Type: application/json; charset=utf-8');
if (!customerIsLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'درخواست نامعتبر است.']); exit; }
if (!Order::deletePending((int) ($_POST['order_id'] ?? 0), (int) $_SESSION['customer_id'])) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'فقط سفارش‌های در انتظار تأیید قابل حذف هستند.']); exit; }
echo json_encode(['success'=>true,'message'=>'سفارش با موفقیت حذف شد.'], JSON_UNESCAPED_UNICODE);
