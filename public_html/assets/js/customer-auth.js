function normalizePhoneNumber(raw) {
  if (!raw) return '';
  const digitsMap = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '۷': '7', '٨': '8', '٩': '9'
  };
  let str = String(raw).trim().replace(/[۰-۹٠-٩]/g, d => digitsMap[d] || d);
  let digits = str.replace(/\D/g, '');
  if (!digits) return '';
  if (digits.startsWith('989') && digits.length === 12) {
    digits = '0' + digits.substring(2);
  } else if (digits.startsWith('98') && digits.length === 11) {
    digits = '0' + digits.substring(2);
  } else if (digits.startsWith('9') && digits.length === 10) {
    digits = '0' + digits;
  }
  return digits;
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      button.setAttribute('aria-label', input.type === 'password' ? 'نمایش رمز عبور' : 'پنهان کردن رمز عبور');
    });
  });

  document.querySelectorAll('form[data-auth-form]').forEach(form => {
    form.addEventListener('submit', event => {
      const phone = form.querySelector('[name="phone"]');
      const firstName = form.querySelector('[name="first_name"]');
      const lastName = form.querySelector('[name="last_name"]');
      const password = form.querySelector('[name="password"]');
      const confirm = form.querySelector('[name="confirm_password"]');

      if (phone) {
        const norm = normalizePhoneNumber(phone.value);
        if (!/^09[0-9]{9}$/.test(norm)) {
          event.preventDefault();
          phone.setCustomValidity('شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.');
          phone.reportValidity();
          return;
        }
        phone.value = norm;
      }
      if (firstName && firstName.value.trim().length === 0) {
        event.preventDefault();
        firstName.setCustomValidity('نام را وارد کنید.');
        firstName.reportValidity();
        return;
      }
      if (lastName && lastName.value.trim().length === 0) {
        event.preventDefault();
        lastName.setCustomValidity('نام خانوادگی را وارد کنید.');
        lastName.reportValidity();
        return;
      }
      if (password && confirm && password.value.length < 8) {
        event.preventDefault();
        password.setCustomValidity('رمز عبور باید حداقل ۸ کاراکتر باشد.');
        password.reportValidity();
        return;
      }
      if (password && !confirm && password.value.trim().length === 0) {
        event.preventDefault();
        password.setCustomValidity('رمز عبور را وارد کنید.');
        password.reportValidity();
        return;
      }
      if (confirm && password && password.value !== confirm.value) {
        event.preventDefault();
        confirm.setCustomValidity('تکرار رمز عبور با رمز عبور یکسان نیست.');
        confirm.reportValidity();
        return;
      }
      const submit = form.querySelector('[type="submit"]');
      if (submit && form.checkValidity()) submit.classList.add('is-loading');
    });
    const confirm = form.querySelector('[name="confirm_password"]');
    confirm?.addEventListener('input', () => confirm.setCustomValidity(''));
    form.querySelectorAll('.auth-input').forEach(input => input.addEventListener('input', () => input.setCustomValidity('')));
  });
});