<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';

if (!customerIsLoggedIn() && empty($_SESSION['otp_context'])) {
    header('Location: login');
    exit;
}

$context = $_SESSION['otp_context'] ?? [];
$purpose = isset($context['purpose']) ? (string) $context['purpose'] : 'login';
$phone = (string) ($context['phone'] ?? '');
$notice = $_SESSION['otp_notice'] ?? null;
$error = $_SESSION['otp_error'] ?? null;
unset($_SESSION['otp_notice'], $_SESSION['otp_error']);
$cooldown = max(1, (int) env('OTP_RESEND_COOLDOWN', 60));
$purposeLabel = match ($purpose) {
    'registration' => 'ثبت‌نام',
    'password_reset' => 'بازیابی رمز عبور',
    default => 'ورود',
};
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تأیید کد | کافه دنج</title>
<link rel="stylesheet" href="assets/css/customer-auth.css">
</head>
<body class="customer-auth">
<main class="auth-card">
  <div class="auth-brand">
    <div class="auth-mark">د</div>
    <h1>تأیید کد یک‌بار مصرف</h1>
    <p>کد چهاررقمی برای <?= htmlspecialchars($purposeLabel, ENT_QUOTES, 'UTF-8') ?> به شماره <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?> ارسال شد.</p>
  </div>
  <?php if ($notice): ?>
    <div class="auth-alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <form method="post" action="otp-verify" data-otp-form>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="auth-field">
      <label for="otp">کد یک‌بار مصرف</label>
      <input class="auth-input" id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="4" required placeholder="۴ رقم">
    </div>
    <button class="auth-submit" type="submit">تأیید کد</button>
  </form>
  <form method="post" action="otp-resend" class="otp-resend-form" data-otp-resend>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <button class="auth-submit otp-resend" type="submit" data-otp-resend-button data-resend-cooldown-seconds="<?= (int) $cooldown ?>">ارسال مجدد کد</button>
  </form>
  <p class="auth-footer">در صورت نیاز، کد را دوباره دریافت کنید.</p>
  <a class="back-home" href="<?= $purpose === 'registration' ? 'register' : 'login' ?>">بازگشت</a>
</main>
<script src="assets/js/otp.js"></script>
</body>
</html>
