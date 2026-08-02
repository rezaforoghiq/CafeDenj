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
      if (phone && !/^09[0-9]{9}$/.test(phone.value.replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))) {
        event.preventDefault();
        phone.setCustomValidity('شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.');
        phone.reportValidity();
        return;
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
      if (password && password.value.length < 8) {
        event.preventDefault();
        password.setCustomValidity('رمز عبور باید حداقل ۸ کاراکتر باشد.');
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
