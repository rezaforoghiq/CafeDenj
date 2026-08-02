<?php
/**
 * barista/index.php
 * -----------------------------------------------------------------------
 * صفحهٔ ورود باریستا. اگر از قبل لاگین باشد، مستقیم به داشبورد می‌رود.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/barista-auth.php';

if (BaristaAuth::check()) {
    header('Location: ' . APP_URL . '/barista/dashboard');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        if (BaristaAuth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: ' . APP_URL . '/barista/dashboard');
            exit;
        }
        $error = 'نام کاربری، رمز عبور یا وضعیت حساب نامعتبر است.';
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود باریستا | کافه دنج</title>
<link href="../assets/css/fonts.css" rel="stylesheet">
<link href="../assets/vendor/bootstrap/bootstrap.rtl.min.css" rel="stylesheet">
<style>
  :root{--bg:#14110D;--surface:#1E1912;--line:#3A3226;--gold:#C9A24D;--gold-soft:#E8CE8B;--ivory:#EDE6D6;--muted:#A89B85;}
  body{background:var(--bg);color:var(--ivory);font-family:'Vazirmatn',Tahoma,'Segoe UI',Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .login-card{width:100%;max-width:380px;background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:36px 30px;}
  .logo-mark{width:52px;height:52px;margin:0 auto 16px;border:1.5px solid var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--gold);}
  .login-card h1{font-size:18px;text-align:center;color:var(--gold-soft);margin-bottom:4px;}
  .login-card p.sub{text-align:center;color:var(--muted);font-size:13px;margin-bottom:24px;}
  .form-label{color:var(--muted);font-size:13.5px;}
  .form-control{background:var(--bg);border:1px solid var(--line);color:var(--ivory);}
  .form-control:focus{background:var(--bg);color:var(--ivory);border-color:var(--gold);box-shadow:0 0 0 0.2rem rgba(201,162,77,0.15);}
  .btn-gold{background:var(--gold);border-color:var(--gold);color:#17130D;font-weight:600;}
  .btn-gold:hover{background:var(--gold-soft);border-color:var(--gold-soft);color:#17130D;}
</style>
</head>
<body>
<div class="login-card">
  <div class="logo-mark">ب</div>
  <h1>ورود به پنل باریستا</h1>
  <p class="sub">کافه دنج</p>
  <?php if ($error): ?><div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="POST" action="" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="mb-3"><label class="form-label" for="username">نام کاربری</label><input type="text" class="form-control" id="username" name="username" required autofocus></div>
    <div class="mb-4"><label class="form-label" for="password">رمز عبور</label><input type="password" class="form-control" id="password" name="password" required></div>
    <button type="submit" class="btn btn-gold w-100">ورود</button>
  </form>
</div>
</body>
</html>
