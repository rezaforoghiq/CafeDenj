<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/OtpService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: otp');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['otp_error'] = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    header('Location: otp');
    exit;
}

$context = $_SESSION['otp_context'] ?? null;
if (!$context) {
    header('Location: login');
    exit;
}

$otpService = new OtpService();
try {
    if (($context['purpose'] ?? 'login') === 'password_reset' && empty($context['customer_id'])) {
        $_SESSION['otp_notice'] = 'اگر این شماره در سامانه ثبت شده باشد، کد تأیید ارسال خواهد شد.';
    } else {
        $otpService->issueOtp((string) ($context['phone'] ?? ''), (string) ($context['purpose'] ?? 'login'), $_SERVER['REMOTE_ADDR'] ?? null);
        $_SESSION['otp_notice'] = 'کد جدید برای شما ارسال شد.';
    }
    header('Location: otp');
    exit;
} catch (Throwable $e) {
    $_SESSION['otp_error'] = $e->getMessage();
    header('Location: otp');
    exit;
}
