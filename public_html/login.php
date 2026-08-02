<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';

if (customerIsLoggedIn()) { header('Location: index'); exit; }
$error = null; $identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    } elseif ($identity === '' || $password === '') {
        $error = 'شماره موبایل و رمز عبور را وارد کنید.';
    } else {
        try {
            $account = CustomerAuth::attempt($identity, $password);
            if ($account !== null) { customerLogin($account); header('Location: index'); exit; }
            $error = 'اطلاعات ورود صحیح نیست.';
        } catch (Throwable $e) {
            error_log('Customer login error: ' . $e->getMessage());
            $error = 'ورود انجام نشد. لطفاً دوباره تلاش کنید.';
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود | کافه دنج</title><link rel="stylesheet" href="assets/css/customer-auth.css"></head><body class="customer-auth"><main class="auth-card"><div class="auth-brand"><div class="auth-mark">د</div><h1>ورود به حساب</h1><p>خوش آمدید؛ ادامهٔ تجربهٔ شما از همین‌جا است</p></div><?php if ($error): ?><div class="auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><div class="auth-field"><label for="phone">شماره موبایل</label><input class="auth-input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" value="<?= htmlspecialchars($identity, ENT_QUOTES, 'UTF-8') ?>" required autofocus placeholder="۰۹۱۲۳۴۵۶۷۸۹"></div><div class="auth-field"><label for="password">رمز عبور</label><div class="auth-input-wrap"><input class="auth-input" id="password" name="password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="نمایش رمز عبور">◉</button></div></div><button class="auth-submit" type="submit">ورود به حساب</button></form><p class="auth-footer">حساب کاربری ندارید؟ <a href="register">ثبت‌نام کنید</a></p><a class="back-home" href="index">بازگشت به منو</a></main><script src="assets/js/customer-auth.js"></script></body></html>
