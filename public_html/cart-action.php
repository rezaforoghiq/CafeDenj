<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/GuestSession.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';

header('Content-Type: application/json; charset=utf-8');

$guestToken = GuestSession::getToken();

// -----------------------------------------------------------------------
// GET — بازگشت وضعیت سبد خرید فعلی
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items    = Order::cart($guestToken);
    $itemsMap = [];
    foreach ($items as $item) {
        $itemsMap[$item['product_id']] = (int) $item['quantity'];
    }
    echo json_encode([
        'success' => true,
        'count'   => array_sum(array_column($items, 'quantity')),
        'items'   => (object) $itemsMap,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------------
// POST — افزودن یا به‌روزرسانی آیتم سبد خرید
// -----------------------------------------------------------------------
try {
    $action    = $_POST['action'] ?? (isset($_POST['quantity']) ? 'update' : 'add');
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($productId <= 0) {
        throw new RuntimeException('شناسهٔ محصول نامعتبر است.');
    }

    if ($action === 'update' || isset($_POST['quantity'])) {
        $qty = max(0, min(99, (int) ($_POST['quantity'] ?? 0)));
        Order::updateCart($guestToken, $productId, $qty);
    } else {
        Order::add($guestToken, $productId);
    }

    $items    = Order::cart($guestToken);
    $itemsMap = [];
    $itemQty  = 0;
    foreach ($items as $item) {
        $itemsMap[$item['product_id']] = (int) $item['quantity'];
        if ((int) $item['product_id'] === $productId) {
            $itemQty = (int) $item['quantity'];
        }
    }

    echo json_encode([
        'success'       => true,
        'count'         => array_sum(array_column($items, 'quantity')),
        'product_id'    => $productId,
        'item_quantity' => $itemQty,
        'items'         => (object) $itemsMap,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}