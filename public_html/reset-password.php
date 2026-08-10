<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';

if (customerIsLoggedIn()) {
    header('Location: index');
    exit;
}

$resetContext = passwordResetGetContext();
if (!$resetContext) {
    $_SESSION['otp_error'] = 'جلسه بازیابی رمز منقضی شده است. دوباره تلاش کنید.';
    header('Location: forgot-password');
    exit;
}

$error = null;
$errors = [];
$newPassword = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    } else {
        $newPassword = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $errors = CustomerAuth::validatePassword($newPassword, $confirmPassword);

        if ($errors === []) {
            try {
                if (!CustomerAuth::updatePassword((int) $resetContext['customer_id'], $newPassword)) {
                    throw new RuntimeException('Unable to update password.');
                }

                passwordResetClear();
                unset($_SESSION['otp_context']);
                $_SESSION['password_reset_success'] = 'رمز عبور شما با موفقیت تغییر کرد. اکنون می‌توانید وارد شوید.';
                ActivityLog::record('password_reset_complete', 'customer', (int) $resetContext['customer_id'], $resetContext['phone'], null, 'رمز عبور با موفقیت تغییر کرد.');
                header('Location: login');
                exit;
            } catch (Throwable $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $error = 'رمز عبور تغییر نکرد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}
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
