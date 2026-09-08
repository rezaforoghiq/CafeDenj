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

$_SESSION['otp_notice'] = 'ورود به حساب کاربری بدون نیاز به رمز عبور و با کد تأیید پیامکی انجام می‌شود.';
header('Location: login');
exit;
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