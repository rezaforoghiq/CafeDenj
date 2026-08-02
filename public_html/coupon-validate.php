<?php declare(strict_types=1);
// Ensure app config and database are loaded so Coupon can use Database::getConnection()
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Coupon.php';
header('Content-Type: application/json; charset=utf-8');
$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    echo json_encode(['valid' => false, 'message' => 'کد وارد نشده است.']);
    exit;
}
try {
    $c = Coupon::findByCode($code);
} catch (Throwable $e) {
    echo json_encode(['valid' => false, 'message' => 'خطا در بررسی کوپن.']);
    exit;
}
if (!$c) {
    echo json_encode(['valid' => false, 'message' => 'کوپن یافت نشد.']);
    exit;
}
if (!Coupon::isValidCoupon($c)) {
    echo json_encode(['valid' => false, 'message' => 'کوپن منقضی یا غیرفعال است.']);
    exit;
}
echo json_encode(['valid' => true, 'percent' => (int)$c['percent']]);
