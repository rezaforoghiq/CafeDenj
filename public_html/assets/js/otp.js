document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form[data-otp-form]');
  const resendForm = document.querySelector('form[data-otp-resend]');
  const resendButton = document.querySelector('[data-otp-resend-button]');

  if (form) {
    const otpInput = form.querySelector('input[name="otp"]');
    otpInput?.focus();

    if (otpInput) {
      otpInput.addEventListener('input', () => {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        let val = otpInput.value;
        for (let i = 0; i < 10; i++) {
          val = val.replaceAll(persianDigits[i], String(i)).replaceAll(arabicDigits[i], String(i));
        }
        if (val !== otpInput.value) {
          otpInput.value = val;
        }
      });
    }
  }

  if (!resendButton || !resendForm) return;

  const cooldownSeconds = Number(resendButton.dataset.resendCooldownSeconds || 60);
  let remaining = cooldownSeconds;

  const setButtonState = () => {
    resendButton.disabled = remaining > 0;
    resendButton.textContent = remaining > 0 ? `ارسال مجدد (${remaining})` : 'ارسال مجدد کد';
  };

  setButtonState();
  const timer = window.setInterval(() => {
    remaining -= 1;
    if (remaining <= 0) {
      window.clearInterval(timer);
      setButtonState();
      return;
    }
    setButtonState();
  }, 1000);

  resendForm.addEventListener('submit', event => {
    if (resendButton.disabled) {
      event.preventDefault();
      return;
    }
    resendButton.classList.add('is-loading');
  });
});