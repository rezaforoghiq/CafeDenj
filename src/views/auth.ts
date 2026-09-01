import { Jalali } from '../jalali';
import { Customer } from '../types';

export function renderLoginView(error?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ورود مشتری | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card">
    <div class="auth-brand">
      <div style="font-size:36px; margin-bottom:8px;">☕</div>
      <h1>ورود به کافه دنج</h1>
      <p>برای ثبت سفارش و مشاهده تاریخچه وارد شوید.</p>
    </div>
    ${error ? `<div class="auth-alert">${error}</div>` : ''}
    <form method="post" action="/login" data-auth-form>
      <div class="auth-field">
        <label>شماره موبایل</label>
        <input class="auth-input" type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required autofocus dir="ltr" style="text-align:right;">
      </div>
      <div class="auth-field">
        <label>رمز عبور (یا ۶ رقم دلخواه)</label>
        <input class="auth-input" type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="auth-submit">ورود به حساب</button>
    </form>
    <div class="auth-links">
      <a href="/register">حساب کاربری ندارید؟ ثبت‌نام کنید</a>
      <a href="/otp">ورود سریع با کد پیامکی (OTP)</a>
      <a href="/" class="back-home">← بازگشت به منو</a>
    </div>
  </main>
</body>
</html>`;
}

export function renderRegisterView(error?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ثبت‌نام مشتری | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card">
    <div class="auth-brand">
      <div style="font-size:36px; margin-bottom:8px;">☕</div>
      <h1>عضویت در کافه دنج</h1>
      <p>با عضویت در باشگاه مشتریان از تخفیف‌ها بهره‌مند شوید.</p>
    </div>
    ${error ? `<div class="auth-alert">${error}</div>` : ''}
    <form method="post" action="/register" data-auth-form>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div class="auth-field">
          <label>نام</label>
          <input class="auth-input" type="text" name="first_name" placeholder="محمد" required>
        </div>
        <div class="auth-field">
          <label>نام خانوادگی</label>
          <input class="auth-input" type="text" name="last_name" placeholder="رضایی" required>
        </div>
      </div>
      <div class="auth-field">
        <label>شماره موبایل</label>
        <input class="auth-input" type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required dir="ltr" style="text-align:right;">
      </div>
      <div class="auth-field">
        <label>رمز عبور</label>
        <input class="auth-input" type="password" name="password" placeholder="حداقل ۶ کاراکتر" required minlength="4">
      </div>
      <button type="submit" class="auth-submit">ثبت‌نام و ورود</button>
    </form>
    <div class="auth-links">
      <a href="/login">قبلاً ثبت‌نام کرده‌اید؟ وارد شوید</a>
      <a href="/" class="back-home">← بازگشت به منو</a>
    </div>
  </main>
</body>
</html>`;
}

export function renderOtpView(step: 'request' | 'verify', phone = '', code = '', error?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ورود با کد یک‌بار مصرف | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card">
    <div class="auth-brand">
      <div style="font-size:36px; margin-bottom:8px;">📱</div>
      <h1>کد تأیید پیامکی</h1>
      <p>${step === 'request' ? 'شماره موبایل خود را برای دریافت کد یک‌بار مصرف وارد کنید.' : `کد ارسال شده به شماره ${phone} را وارد کنید.`}</p>
    </div>
    ${error ? `<div class="auth-alert">${error}</div>` : ''}
    ${step === 'verify' && code ? `
      <div style="background:rgba(82, 196, 26, 0.1); border:1px solid #52c41a; color:#52c41a; padding:12px; border-radius:8px; margin-bottom:16px; font-size:14px; text-align:center;">
        کد شبیه‌سازی شده: <strong style="font-size:18px; letter-spacing:4px;">${code}</strong>
      </div>
    ` : ''}

    ${step === 'request' ? `
      <form method="post" action="/otp" data-auth-form>
        <div class="auth-field">
          <label>شماره موبایل</label>
          <input class="auth-input" type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required autofocus dir="ltr" style="text-align:right;">
        </div>
        <button type="submit" class="auth-submit">ارسال کد تأیید</button>
      </form>
    ` : `
      <form method="post" action="/otp-verify" data-auth-form>
        <input type="hidden" name="phone" value="${phone}">
        <div class="auth-field">
          <label>کد ۵ رقمی</label>
          <input class="auth-input" type="text" name="code" placeholder="۱۲۳۴۵" required autofocus maxlength="6" style="text-align:center; font-size:22px; letter-spacing:6px;">
        </div>
        <button type="submit" class="auth-submit">تأیید و ورود</button>
      </form>
    `}
    <div class="auth-links">
      <a href="/login">ورود با رمز عبور</a>
      <a href="/" class="back-home">← بازگشت به منو</a>
    </div>
  </main>
</body>
</html>`;
}

export function renderProfileView(customer: Customer, ordersCount: number, message?: string | null): string {
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>پروفایل کاربری | کافه دنج</title>
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/customer-auth.css">
</head>
<body class="customer-auth">
  <main class="auth-card" style="max-width:520px;">
    <div class="auth-brand">
      <div style="font-size:40px; margin-bottom:8px;">👤</div>
      <h1>پروفایل کاربر</h1>
      <p>اطلاعات حساب کاربری شما در کافه دنج</p>
    </div>
    ${message ? `<div style="background:rgba(82,196,26,0.1); border:1px solid #52c41a; color:#52c41a; padding:12px; border-radius:8px; margin-bottom:16px;">${message}</div>` : ''}

    <form method="post" action="/profile">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div class="auth-field">
          <label>نام</label>
          <input class="auth-input" type="text" name="first_name" value="${customer.first_name || ''}" required>
        </div>
        <div class="auth-field">
          <label>نام خانوادگی</label>
          <input class="auth-input" type="text" name="last_name" value="${customer.last_name || ''}" required>
        </div>
      </div>
      <div class="auth-field">
        <label>شماره موبایل</label>
        <input class="auth-input" type="tel" name="phone" value="${customer.phone}" readonly style="opacity:0.7;">
      </div>
      <div class="auth-field">
        <label>تعداد کل سفارش‌های شما</label>
        <div style="padding:10px; background:rgba(255,255,255,0.03); border-radius:8px; font-weight:bold; color:var(--gold-soft);">
          ${Jalali.digits(ordersCount)} سفارش
        </div>
      </div>
      <button type="submit" class="auth-submit">ذخیره تغییرات</button>
    </form>

    <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.1);">
      <a href="/orders" class="auth-submit" style="background:#223046; text-decoration:none; text-align:center; width:auto; padding:8px 16px;">مشاهده سفارش‌ها</a>
      <a href="/customer-logout" style="color:#ff4d4f; text-decoration:none; font-size:14px; align-self:center;">خروج از حساب</a>
    </div>
    <div style="text-align:center; margin-top:12px;">
      <a href="/" class="back-home">← بازگشت به منو</a>
    </div>
  </main>
</body>
</html>`;
}
