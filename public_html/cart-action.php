<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';
header('Content-Type: application/json; charset=utf-8');
if (!customerIsLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
try { Order::add((int) $_SESSION['customer_id'], (int) $_POST['product_id']); $items=Order::cart((int) $_SESSION['customer_id']); echo json_encode(['success'=>true,'count'=>array_sum(array_column($items,'quantity'))]); } catch(Throwable $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
