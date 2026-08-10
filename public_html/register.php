<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/OtpService.php';
require_once __DIR__ . '/../classes/PendingRegistration.php';

if (customerIsLoggedIn()) { header('Location: index'); exit; }
$errors = []; $old = ['phone' => '', 'first_name' => '', 'last_name' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $old['first_name'] = trim((string) ($_POST['first_name'] ?? ''));
    $old['last_name'] = trim((string) ($_POST['last_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست شما منقضی شده است؛ صفحه را تازه‌سازی کنید.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'تکرار رمز عبور یکسان نیست.';
    } else {
        $passwordErrors = CustomerAuth::validatePassword($password, $confirm);
        if ($passwordErrors !== []) {
            $errors = array_merge($errors, $passwordErrors);
        } else {
            try {
            $phone = CustomerAuth::normalizePhone($old['phone']);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $pendingId = PendingRegistration::create(Database::getConnection(), $phone, $old['first_name'], $old['last_name'], $passwordHash);
            try {
                $otpService = new OtpService();
                $otpService->issueOtp($phone, 'registration', $_SERVER['REMOTE_ADDR'] ?? null);
                $_SESSION['otp_context'] = [
                    'purpose' => 'registration',
                    'phone' => $phone,
                    'pending_id' => $pendingId,
                ];
                $_SESSION['otp_notice'] = 'کد تأیید برای ثبت‌نام شما ارسال شد.';
                header('Location: otp');
                exit;
            } catch (Throwable $e) {
                PendingRegistration::delete(Database::getConnection(), $pendingId);
                throw $e;
            }
        } catch (InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true) ?: ['general' => 'اطلاعات واردشده معتبر نیست.'];
        } catch (RuntimeException $e) {
            $errors['general'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Customer registration error: ' . $e->getMessage());
            $errors['general'] = 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.';
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ثبت‌نام | کافه دنج</title><link rel="stylesheet" href="assets/css/customer-auth.css"></head><body class="customer-auth"><main class="auth-card"><div class="auth-brand"><div class="auth-mark">د</div><h1>ساخت حساب کاربری</h1><p>برای دریافت خدمات شخصی‌سازی‌شده کافه دنج</p></div><?php if (isset($errors['general'])): ?><div class="auth-alert"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post" data-auth-form novalidate><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><div class="auth-field"><label for="phone">شماره موبایل</label><input class="auth-input <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>" required><?php if(isset($errors['phone'])): ?><small class="field-error"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
<div class="auth-field"><label for="first_name">نام</label><input class="auth-input <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" id="first_name" name="first_name" type="text" autocomplete="given-name" placeholder="نام شما" value="<?= htmlspecialchars($old['first_name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100"><?php if(isset($errors['first_name'])): ?><small class="field-error"><?= htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
<div class="auth-field"><label for="last_name">نام خانوادگی</label><input class="auth-input <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" id="last_name" name="last_name" type="text" autocomplete="family-name" placeholder="نام خانوادگی شما" value="<?= htmlspecialchars($old['last_name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100"><?php if(isset($errors['last_name'])): ?><small class="field-error"><?= htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
<div class="auth-field"><label for="password">رمز عبور</label><div class="auth-input-wrap"><input class="auth-input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="نمایش رمز عبور">◉</button></div><?php if(isset($errors['password'])): ?><small class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div><div class="auth-field"><label for="confirm_password">تکرار رمز عبور</label><div class="auth-input-wrap"><input class="auth-input <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle="confirm_password" aria-label="نمایش رمز عبور">◉</button></div><?php if(isset($errors['confirm_password'])): ?><small class="field-error"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div><p class="auth-hint">رمز عبور باید حداقل ۸ کاراکتر داشته باشد.</p><button class="auth-submit" type="submit">ساخت حساب</button></form><p class="auth-footer">حساب دارید؟ <a href="login">وارد شوید</a></p><a class="back-home" href="index">بازگشت به منو</a></main><script src="assets/js/customer-auth.js"></script></body></html>
