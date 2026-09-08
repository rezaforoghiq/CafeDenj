<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!customerIsLoggedIn()) {
    echo json_encode(['success' => true, 'count' => 0, 'items' => (object)[]]);
    exit;
  }
  $items = Order::cart((int) $_SESSION['customer_id']);
  $itemsMap = [];
  foreach ($items as $it) {
    $itemsMap[$it['product_id']] = (int) $it['quantity'];
  }
  echo json_encode([
    'success' => true,
    'count' => array_sum(array_column($items, 'quantity')),
    'items' => (object)$itemsMap
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر است']);
  exit;
}

if (empty($_SESSION['customer_id'])) {
  $_SESSION['customer_id'] = 1;
  $_SESSION['customer_display_name'] = 'مشتری';
}
try {
  $action = $_POST['action'] ?? (isset($_POST['quantity']) ? 'update' : 'add');
  $productId = (int) $_POST['product_id'];
  if ($action === 'update' || isset($_POST['quantity'])) {
    $rawQty = (int) $_POST['quantity'];
    $qty = max(0, min(99, $rawQty));
    Order::updateCart((int) $_SESSION['customer_id'], $productId, $qty);
  } else {
    Order::add((int) $_SESSION['customer_id'], $productId);
  }
  $items = Order::cart((int) $_SESSION['customer_id']);
  $itemQty = 0;
  $itemsMap = [];
  foreach ($items as $it) {
    $itemsMap[$it['product_id']] = (int)$it['quantity'];
    if ((int)$it['product_id'] === $productId) {
      $itemQty = (int)$it['quantity'];
    }
  }
  echo json_encode([
    'success' => true,
    'count' => array_sum(array_column($items, 'quantity')),
    'product_id' => $productId,
    'item_quantity' => $itemQty,
    'items' => (object)$itemsMap
  ]);
} catch(Throwable $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }