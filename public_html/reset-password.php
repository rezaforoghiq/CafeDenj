<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';

if (customerIsLoggedIn()) {
    header('Location: index');
    exit;
}

$_SESSION['otp_notice'] = 'ورود به حساب کاربری بدون نیاز به رمز عبور انجام می‌شود.';
header('Location: login');
exit;
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تغییر رمز عبور | کافه دنج</title>
<link rel="stylesheet" href="assets/css/customer-auth.css">
</head>
<body class="customer-auth">
<main class="auth-card">
  <div class="auth-brand">
    <div class="auth-mark">د</div>
    <h1>تغییر رمز عبور</h1>
    <p>رمز عبور جدید خود را وارد کنید.</p>
  </div>
  <?php if ($error): ?>
    <div class="auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="auth-alert">
      <?php foreach ($errors as $message): ?>
        <div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="auth-field">
      <label for="password">رمز عبور جدید</label>
      <div class="auth-input-wrap">
        <input class="auth-input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="نمایش رمز عبور">◉</button>
      </div>
    </div>
    <div class="auth-field">
      <label for="confirm_password">تکرار رمز عبور</label>
      <div class="auth-input-wrap">
        <input class="auth-input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
        <button class="password-toggle" type="button" data-password-toggle="confirm_password" aria-label="نمایش رمز عبور">◉</button>
      </div>
    </div>
    <button class="auth-submit" type="submit">تغییر رمز عبور</button>
  </form>
  <p class="auth-footer">رمز عبور شما پس از تأیید کد بازیابی تغییر خواهد کرد.</p>
  <p class="auth-footer"><a href="login">بازگشت به ورود</a></p>
  <a class="back-home" href="index">بازگشت به منو</a>
</main>
<script src="assets/js/customer-auth.js"></script>
</body>
</html>