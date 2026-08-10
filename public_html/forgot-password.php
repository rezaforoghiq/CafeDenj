<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../classes/OtpService.php';

if (customerIsLoggedIn()) {
    header('Location: index');
    exit;
}

$error = $_SESSION['password_reset_error'] ?? $_SESSION['otp_error'] ?? null;
unset($_SESSION['password_reset_error'], $_SESSION['otp_error']);
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    } elseif ($phone === '') {
        $error = 'شماره موبایل را وارد کنید.';
    } elseif (!Customer::isValidPhone($phone)) {
        $error = 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.';
    } else {
        $normalizedPhone = CustomerAuth::normalizePhone($phone);
        $account = CustomerAuth::findAccountByPhone($normalizedPhone);
        $_SESSION['otp_context'] = [
            'purpose' => 'password_reset',
            'phone' => $normalizedPhone,
            'customer_id' => $account ? (int) $account['customer_id'] : null,
        ];
        $_SESSION['otp_notice'] = 'اگر این شماره در سامانه ثبت شده باشد، کد تأیید ارسال خواهد شد.';

        if ($account) {
            try {
                $otpService = new OtpService();
                $otpService->issueOtp($normalizedPhone, 'password_reset', $_SERVER['REMOTE_ADDR'] ?? null);
            } catch (Throwable $e) {
                error_log('Password reset OTP send error: ' . $e->getMessage());
                $error = 'ارسال کد انجام نشد. لطفاً دوباره تلاش کنید.';
                unset($_SESSION['otp_context'], $_SESSION['otp_notice']);
            }
        }

        if ($error === null) {
            header('Location: otp');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>بازیابی رمز عبور | کافه دنج</title>
<link rel="stylesheet" href="assets/css/customer-auth.css">
</head>
<body class="customer-auth">
<main class="auth-card">
  <div class="auth-brand">
    <div class="auth-mark">د</div>
    <h1>بازیابی رمز عبور</h1>
    <p>برای تغییر رمز عبور شماره موبایل خود را وارد کنید.</p>
  </div>
  <?php if ($error): ?>
    <div class="auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="auth-field">
      <label for="phone">شماره موبایل</label>
      <input class="auth-input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
    </div>
    <button class="auth-submit" type="submit">ارسال کد بازیابی</button>
  </form>
  <p class="auth-footer">کد بازیابی را دریافت نکردید؟ پس از چند دقیقه دوباره تلاش کنید.</p>
  <p class="auth-footer"><a href="login">بازگشت به ورود</a></p>
  <a class="back-home" href="index">بازگشت به منو</a>
</main>
<script src="assets/js/customer-auth.js"></script>
</body>
</html>
