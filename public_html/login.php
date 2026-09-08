<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/OtpService.php';

if (customerIsLoggedIn()) {
    header('Location: index');
    exit;
}
$error = null;
$notice = $_SESSION['password_reset_success'] ?? null;
unset($_SESSION['password_reset_success']);
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string) ($_POST['phone'] ?? ''));
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    } elseif ($identity === '') {
        $error = 'شماره موبایل را وارد کنید.';
    } else {
        $normalizedPhone = CustomerAuth::normalizePhone($identity);
        if (!Customer::isValidPhone($normalizedPhone)) {
            $error = 'شماره موبایل نامعتبر است. لطفاً شماره را به‌صورت ۰۹xxxxxxxxx وارد کنید.';
        } else {
            try {
                $account = CustomerAuth::findAccountByPhone($normalizedPhone);
                if ($account === null) {
                    $pdo = Database::getConnection();
                    $s = $pdo->prepare('SELECT id, first_name, last_name FROM customers WHERE phone = :p LIMIT 1');
                    $s->execute(['p' => $normalizedPhone]);
                    $existingCustomer = $s->fetch();
                    if (!$existingCustomer) {
                        $error = 'حساب کاربری با این شماره یافت نشد. لطفاً ابتدا ثبت‌نام کنید.';
                    } else {
                        $ins = $pdo->prepare('INSERT INTO customer_accounts (customer_id, password_hash) VALUES (:cid, :ph)');
                        $ins->execute(['cid' => $existingCustomer['id'], 'ph' => bin2hex(random_bytes(32))]);
                        $account = [
                            'account_id' => (int) $pdo->lastInsertId(),
                            'customer_id' => (int) $existingCustomer['id'],
                            'phone' => $normalizedPhone
                        ];
                    }
                }

                if ($account !== null) {
                    $otpService = new OtpService();
                    $otpService->issueOtp($normalizedPhone, 'login', $_SERVER['REMOTE_ADDR'] ?? null);
                    $_SESSION['otp_context'] = [
                        'purpose' => 'login',
                        'phone' => $normalizedPhone,
                        'account_id' => (int) $account['account_id'],
                        'customer_id' => (int) $account['customer_id'],
                    ];
                    $_SESSION['otp_notice'] = 'کد تأیید ورود برای شما ارسال شد.';
                    header('Location: otp');
                    exit;
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Customer login error: ' . $e->getMessage());
                $error = 'ورود انجام نشد. لطفاً دوباره تلاش کنید.';
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
    <title>ورود | کافه دنج</title>
    <link rel="stylesheet" href="assets/css/customer-auth.css">
</head>

<body class="customer-auth">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="auth-mark">د</div>
            <h1>ورود به کافه دنج</h1>
            <p>ورود به حساب کاربری کافه دنج</p>
        </div><?php if ($error): ?><div class="auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="auth-field"><label for="phone">شماره موبایل</label><input class="auth-input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" value="<?= htmlspecialchars($identity, ENT_QUOTES, 'UTF-8') ?>" required autofocus placeholder="۰۹۱۲۳۴۵۶۷۸۹"></div><button class="auth-submit" type="submit">دریافت کد تأیید ورود</button>
        </form><?php if ($notice): ?><div class="auth-alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><p class="auth-footer">حساب کاربری ندارید؟ <a href="register">ثبت‌نام کنید</a></p><a class="back-home" href="index">بازگشت به منو</a>
    </main>
    <script src="assets/js/customer-auth.js"></script>
</body>

</html>