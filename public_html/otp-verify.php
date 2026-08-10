<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/OtpService.php';
require_once __DIR__ . '/../classes/PendingRegistration.php';

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

$otp = trim((string) ($_POST['otp'] ?? ''));
if ($otp === '' || !preg_match('/^\d{4}$/', $otp)) {
    $_SESSION['otp_error'] = 'کد یک‌بار مصرف باید ۴ رقم باشد.';
    header('Location: otp');
    exit;
}

$otpService = new OtpService();
$verification = $otpService->verifyOtp((string) ($context['phone'] ?? ''), (string) ($context['purpose'] ?? 'login'), $otp);
if (!$verification['success']) {
    $_SESSION['otp_error'] = $verification['message'];
    header('Location: otp');
    exit;
}

if (($context['purpose'] ?? 'login') === 'registration') {
    $pendingId = (int) ($context['pending_id'] ?? 0);
    $pending = PendingRegistration::find(Database::getConnection(), $pendingId);
    if (!$pending) {
        $_SESSION['otp_error'] = 'جلسه تأیید منقضی شده است. دوباره ثبت‌نام کنید.';
        header('Location: register');
        exit;
    }

    try {
        $account = CustomerAuth::registerWithPasswordHash($pending['phone'], (string) ($pending['first_name'] ?? ''), (string) ($pending['last_name'] ?? ''), (string) $pending['password_hash']);
        PendingRegistration::delete(Database::getConnection(), $pendingId);
        unset($_SESSION['otp_context']);
        ActivityLog::record('registration_complete', 'customer', (int) $account['customer_id'], $pending['first_name'] . ' ' . $pending['last_name'], null, 'ثبت‌نام با OTP تکمیل شد.');
        customerLogin($account);
        header('Location: index');
        exit;
    } catch (Throwable $e) {
        error_log('OTP registration completion error: ' . $e->getMessage());
        $_SESSION['otp_error'] = 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.';
        header('Location: register');
        exit;
    }
}

if (($context['purpose'] ?? 'login') === 'password_reset') {
    $customerId = (int) ($context['customer_id'] ?? 0);
    $phone = (string) ($context['phone'] ?? '');

    if ($customerId <= 0) {
        $_SESSION['otp_error'] = 'جلسه بازیابی رمز منقضی شده است. دوباره تلاش کنید.';
        header('Location: forgot-password');
        exit;
    }

    passwordResetAuthorize($customerId, $phone);
    unset($_SESSION['otp_context']);
    ActivityLog::record('password_reset_verify', 'customer', $customerId, $phone, null, 'OTP بازیابی رمز عبور تایید شد.');
    header('Location: reset-password');
    exit;
}

$accountId = (int) ($context['account_id'] ?? 0);
$customerId = (int) ($context['customer_id'] ?? 0);
if ($accountId <= 0 || $customerId <= 0) {
    $_SESSION['otp_error'] = 'نشست ورود منقضی شده است.';
    header('Location: login');
    exit;
}

unset($_SESSION['otp_context']);
customerLogin(['account_id' => $accountId, 'customer_id' => $customerId]);
header('Location: index');
exit;
