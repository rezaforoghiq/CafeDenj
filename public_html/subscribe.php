<?php
/**
 * public_html/subscribe.php
 * -----------------------------------------------------------------------
 * Endpoint عمومی (بدون نیاز به لاگین) که فرم پاپ‌آپ ایونت با fetch()
 * به آن POST می‌زند. همیشه یک پاسخ JSON برمی‌گرداند.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/Setting.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'درخواست نامعتبر است.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    respond(false, 'نشست شما منقضی شده. لطفاً صفحه را رفرش کنید.');
}

$phone = trim((string) ($_POST['phone'] ?? ''));
$name  = trim((string) ($_POST['name'] ?? ''));

if ($phone === '') {
    if (Setting::getBool('phone_required', true)) {
        respond(false, 'وارد کردن شمارهٔ موبایل الزامی است.');
    }
} elseif (!Customer::isValidPhone($phone)) {
    respond(false, 'لطفاً شمارهٔ موبایل را به‌درستی وارد کنید (مثل ۰۹۱۲۳۴۵۶۷۸۹).');
}

try {
    Customer::register($phone, $name ?: null, 'popup');
    respond(true, 'ثبت شد! به‌زودی از تخفیف‌ها باخبر می‌شوید.');
} catch (Throwable $e) {
    error_log('Subscribe error: ' . $e->getMessage());
    respond(false, 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
}
