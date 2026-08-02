<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../classes/Customer.php';

requireCustomerLogin();

$errors = [];
$success = null;
$pdo = Database::getConnection();
$customerId = (int) $_SESSION['customer_id'];
// Load current
$stmt = $pdo->prepare('SELECT first_name, last_name, phone FROM customers WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $customerId]);
$customer = $stmt->fetch();
$first = $customer['first_name'] ?? '';
$last = $customer['last_name'] ?? '';
$phone = $customer['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست منقضی شده است.';
    } else {
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        if ($first === '' || mb_strlen($first) > 100) $errors['first_name'] = 'نام را وارد کنید (حداکثر ۱۰۰ کاراکتر).';
        if ($last === '' || mb_strlen($last) > 100) $errors['last_name'] = 'نام خانوادگی را وارد کنید (حداکثر ۱۰۰ کاراکتر).';

        if (empty($errors)) {
            $upd = $pdo->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name WHERE id = :id');
            $upd->execute(['first_name' => $first, 'last_name' => $last, 'id' => $customerId]);
            // update session display name
            $_SESSION['customer_display_name'] = trim($first . ' ' . $last);
            ActivityLog::record('admin', 'customer', $customerId, $_SESSION['customer_display_name'] ?? null);
            $success = 'اطلاعات پروفایل ذخیره شد.';
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>پروفایل | کافه دنج</title>
<link rel="stylesheet" href="assets/css/customer-auth.css">
</head>
<body class="customer-auth">
<main class="auth-card">
  <div class="auth-brand">
    <h1>پروفایل</h1>
    <p>ویرایش اطلاعات حساب کاربری</p>
  </div>
  <?php if (isset($errors['general'])): ?><div class="auth-alert"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($success): ?><div class="auth-alert" style="background: #e6ffef; color:#166a3a;"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="auth-field"><label for="first_name">نام</label><input class="auth-input <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" id="first_name" name="first_name" type="text" value="<?= htmlspecialchars($first, ENT_QUOTES, 'UTF-8') ?>" required maxlength="100"><?php if(isset($errors['first_name'])): ?><small class="field-error"><?= htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
    <div class="auth-field"><label for="last_name">نام خانوادگی</label><input class="auth-input <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" id="last_name" name="last_name" type="text" value="<?= htmlspecialchars($last, ENT_QUOTES, 'UTF-8') ?>" required maxlength="100"><?php if(isset($errors['last_name'])): ?><small class="field-error"><?= htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
    <div class="auth-field"><label>شماره موبایل</label><div class="auth-input-wrap"><input class="auth-input" type="tel" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" disabled></div></div>
    <div style="display:flex;gap:8px;align-items:center;"><button class="auth-submit" type="submit">ذخیرهٔ تغییرات</button><a href="index" style="margin-left:8px">بازگشت به منو</a></div>
  </form>
</main>
</body>
</html>
