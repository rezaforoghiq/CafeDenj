<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/GuestSession.php';
require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../classes/Coupon.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/Setting.php';

$guestToken = GuestSession::getToken();
$items      = Order::cart($guestToken);

// سبد خرید خالی → بازگشت به سبد خرید
if (!$items) {
    header('Location: cart');
    exit;
}

$tableCount = Setting::getInt('table_count', 10);
if ($tableCount < 1) {
    $tableCount = 10;
}

$total = array_sum(array_map(fn($i) => (int) $i['price'] * (int) $i['quantity'], $items));
$err   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $err = 'نشست منقضی شده است.';
    } else {
        // --- خواندن و اعتبارسنجی ورودی‌ها ---
        $tableNumber = trim((string) ($_POST['table_number'] ?? ''));
        $note        = trim((string) ($_POST['note']         ?? ''));
        $couponCode  = trim((string) ($_POST['coupon_code']  ?? '')) ?: null;
        $phone       = trim((string) ($_POST['phone']        ?? '')) ?: null;
        $firstName   = trim((string) ($_POST['first_name']   ?? '')) ?: null;
        $lastName    = trim((string) ($_POST['last_name']    ?? '')) ?: null;

        $tableNumberInt = filter_var($tableNumber, FILTER_VALIDATE_INT);
        if ($tableNumber === '') {
            $err = 'لطفاً شماره میز خود را انتخاب کنید.';
        } elseif ($tableNumberInt === false || $tableNumberInt < 1 || $tableNumberInt > $tableCount) {
            $err = 'شماره میز انتخاب‌شده معتبر نیست.';
        } elseif ($phone !== null && !Customer::isValidPhone($phone)) {
            $err = 'شماره تماس واردشده معتبر نیست.';
        } else {
            try {
                $orderId = Order::place(
                    $guestToken,
                    $tableNumber,
                    $note,
                    $couponCode,
                    $phone,
                    $firstName,
                    $lastName
                );
                header('Location: order?id=' . $orderId);
                exit;
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/customer-auth.css">
  <title>تکمیل سفارش</title>
</head>
<body class="customer-auth">
<main class="auth-card">
  <div class="auth-brand">
    <h1>تکمیل سفارش</h1>
    <p>پرداخت آنلاین ندارد؛ سفارش برای تأیید ارسال می‌شود.</p>
  </div>

  <?php if ($err): ?>
    <div class="auth-alert"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <p>
    <div id="original-block" style="display:none;font-size:13px;color:var(--muted);">
      مبلغ قبل از تخفیف: <span id="original-total" style="text-decoration:line-through;font-size:13px;"></span>
    </div>
    <div style="margin-top:6px;">مبلغ کل: <b id="order-total" style="font-size:18px"><?= number_format($total) ?> تومان</b></div>
  </p>

  <form method="post" data-auth-form>
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <!-- شماره میز — الزامی -->
    <div class="auth-field">
      <label for="table_number">شماره میز <span style="color:var(--danger)">*</span></label>
      <select name="table_number" id="table_number" class="auth-input" required style="cursor:pointer;">
        <option value="" style="background:#1a1d24; color:#a0aec0;">— لطفاً شماره میز خود را انتخاب کنید —</option>
        <?php for ($i = 1; $i <= $tableCount; $i++): ?>
          <option value="<?= $i ?>" <?= (string)($_POST['table_number'] ?? '') === (string)$i ? 'selected' : '' ?> style="background:#1a1d24; color:#fff;">
            میز <?= toPersianDigits((string)$i) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>

    <!-- کوپن تخفیف -->
    <div class="auth-field">
      <label>کد کوپن (اختیاری)</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" name="coupon_code" id="coupon_code" class="auth-input"
               placeholder="کد کوپن را وارد کنید" style="flex:1;">
        <button type="button" id="applyCoupon" class="auth-submit"
                style="width:80px;padding:11px 13px;border-radius:8px;white-space:nowrap;">اعمال</button>
      </div>
      <div id="coupon-msg" style="color:var(--muted);font-size:13px;margin-top:6px;"></div>
    </div>

    <!-- توضیحات سفارش -->
    <div class="auth-field">
      <label>توضیحات سفارش (اختیاری)</label>
      <textarea class="auth-input" name="note" maxlength="500" rows="3"><?= htmlspecialchars($_POST['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <!-- اطلاعات تماس — اختیاری -->
    <div class="auth-field" style="border-top:1px solid var(--line);padding-top:16px;margin-top:4px;">
      <label style="font-size:13px;color:var(--muted);">اطلاعات تماس (اختیاری — برای اطلاع‌رسانی‌های بعدی)</label>
      <input type="text" name="first_name" class="auth-input" placeholder="نام"
             value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             style="margin-bottom:8px;" maxlength="100">
      <input type="text" name="last_name" class="auth-input" placeholder="نام‌خانوادگی"
             value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             style="margin-bottom:8px;" maxlength="100">
      <input type="tel" name="phone" class="auth-input" placeholder="شماره موبایل (مثلاً ۰۹۱۲۱۲۳۴۵۶۷)"
             value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             maxlength="20" dir="ltr">
    </div>

    <!-- تخفیف و جمع نهایی -->
    <div class="auth-field"><label>تخفیف: </label><div id="coupon-discount">—</div></div>
    <div class="auth-field">
      <label>مبلغ قابل پرداخت: </label>
      <div id="final-total" style="font-size:18px;font-weight:700"><?= number_format($total) ?> تومان</div>
    </div>

    <button class="auth-submit">ثبت نهایی سفارش</button>
  </form>
</main>

<script>
(function () {
  const total      = <?= (int) $total ?>;
  const input      = document.getElementById('coupon_code');
  const applyBtn   = document.getElementById('applyCoupon');
  const msg        = document.getElementById('coupon-msg');
  const discountEl = document.getElementById('coupon-discount');
  const finalEl    = document.getElementById('final-total');
  const totalEl    = document.getElementById('order-total');
  const origBlock  = document.getElementById('original-block');
  const origTotal  = document.getElementById('original-total');

  function fmt(n) { return new Intl.NumberFormat('fa-IR').format(n) + ' تومان'; }

  function clearCouponUI() {
    msg.textContent         = '';
    discountEl.textContent  = '—';
    finalEl.textContent     = fmt(total);
    totalEl.textContent     = fmt(total);
    origBlock.style.display = 'none';
    input.dataset.applied   = '';
  }

  function showSuccess(code, percent) {
    const discount = Math.round(total * percent / 100);
    msg.style.color         = 'var(--green)';
    msg.textContent         = 'کوپن ' + code + ' با موفقیت اعمال شد — ' + percent + '%';
    discountEl.textContent  = fmt(discount) + ' (' + percent + '%)';
    finalEl.textContent     = fmt(total - discount);
    totalEl.textContent     = fmt(total - discount);
    origBlock.style.display = 'block';
    origTotal.textContent   = fmt(total);
    input.dataset.applied   = code;
  }

  function showError(text) {
    msg.style.color         = 'var(--danger)';
    msg.textContent         = text;
    discountEl.textContent  = '—';
    finalEl.textContent     = fmt(total);
    totalEl.textContent     = fmt(total);
    origBlock.style.display = 'none';
    input.dataset.applied   = '';
  }

  applyBtn.addEventListener('click', function () {
    const code = input.value.trim();
    if (!code) { clearCouponUI(); return; }
    applyBtn.disabled = true;
    fetch('coupon-validate.php?code=' + encodeURIComponent(code))
      .then(r => r.json())
      .then(data => data.valid ? showSuccess(code, data.percent) : showError(data.message || 'کوپن نامعتبر.'))
      .catch(() => showError('خطا در بررسی کوپن.'))
      .finally(() => { applyBtn.disabled = false; });
  });

  input.addEventListener('input', function () { if (!input.value.trim()) clearCouponUI(); });
})();
</script>
</body>
</html>
