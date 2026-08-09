document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const toggle = document.getElementById('adminMenuToggle');
  const sidebar = document.getElementById('adminSidebar');
  const profile = document.querySelector('.profile-trigger');
  const menu = document.getElementById('profileMenu');
  const modal = document.getElementById('confirmModal');
  const toastRegion = document.querySelector('.toast-region');
  let pendingForm = null;

  const closeDrawer = () => { body.classList.remove('drawer-open'); toggle?.setAttribute('aria-expanded', 'false'); };
  toggle?.addEventListener('click', () => { const open = body.classList.toggle('drawer-open'); toggle.setAttribute('aria-expanded', String(open)); });
  document.querySelectorAll('[data-drawer-close], .sidebar-nav a').forEach(el => el.addEventListener('click', closeDrawer));

  profile?.addEventListener('click', e => { e.stopPropagation(); const isOpen = menu.classList.toggle('is-open'); profile.setAttribute('aria-expanded', String(isOpen)); });
  document.addEventListener('click', e => { if (!e.target.closest('.user-menu')) { menu?.classList.remove('is-open'); profile?.setAttribute('aria-expanded', 'false'); } });

  const toast = (message, type = 'success') => { const item = document.createElement('div'); item.className = `toast toast-${type}`; item.innerHTML = `<span>${type === 'success' ? '✓' : 'i'}</span><p>${message}</p><button aria-label="بستن">×</button>`; toastRegion.append(item); requestAnimationFrame(() => item.classList.add('show')); const remove = () => { item.classList.remove('show'); setTimeout(() => item.remove(), 220); }; item.querySelector('button').onclick = remove; setTimeout(remove, 5000); };
  document.querySelectorAll('[data-toast]').forEach(btn => btn.addEventListener('click', () => toast(btn.dataset.toast, 'info')));
  document.querySelectorAll('table').forEach(table => {
    const labels = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach(row => row.querySelectorAll('td').forEach((cell, index) => {
      if (!cell.hasAttribute('colspan')) cell.dataset.label = labels[index] || '';
    }));
  });
  document.querySelectorAll('.alert-success').forEach(alert => { toast(alert.textContent.trim()); alert.remove(); });
  document.querySelectorAll('.alert-danger').forEach(alert => { toast(alert.textContent.trim(), 'danger'); alert.remove(); });

  const closeModal = () => { modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden', 'true'); pendingForm = null; };
  document.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawer(); closeModal(); } });
  // Native confirmation is deliberately retained for destructive forms.
  // The custom dialog is opt-in only, preventing a backdrop from blocking POST.
  document.querySelectorAll('form[data-use-custom-confirm]').forEach(form => {
    form.removeAttribute('onsubmit');
    form.addEventListener('submit', e => {
      if (form.dataset.confirmed) return;
      e.preventDefault();
      pendingForm = form;
      const button = form.querySelector('button[type="submit"]');
      const confirmText = form.dataset.confirmText?.trim();
      document.getElementById('confirmText').textContent = confirmText || (button?.textContent.includes('حذف') ? 'آیا از حذف این سفارش مطمئن هستید؟ این عملیات قابل بازگشت نیست.' : 'آیا از انجام این عملیات مطمئن هستید؟');
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });
  document.getElementById('confirmAction')?.addEventListener('click', event => {
    if (!pendingForm) return;

    const form = pendingForm;
    const sourceButton = form.querySelector('button[type="submit"]');
    form.dataset.confirmed = 'true';
    event.currentTarget.classList.add('is-loading');
    event.currentTarget.disabled = true;
    if (sourceButton) {
      sourceButton.classList.add('is-loading');
      sourceButton.disabled = true;
    }

    // Calling the native method avoids any possible collision with a form
    // control named "submit" and reliably posts the original CSRF fields.
    HTMLFormElement.prototype.submit.call(form);
  });
  document.querySelectorAll('form[data-order-status-form]').forEach(form => {
    const statusSelect = form.querySelector('select[name="status"]');
    const paymentSelect = form.querySelector('select[name="payment_method"]');
    if (statusSelect && paymentSelect) {
      const updateRequirement = () => {
        paymentSelect.required = statusSelect.value === 'completed';
      };
      statusSelect.addEventListener('change', updateRequirement);
      updateRequirement();
    }
  });

  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    // Delete forms are intentionally stopped once to show the confirmation
    // dialog; they must not look like a request has already started.
    if (event.defaultPrevented || form.dataset.confirmed || form.querySelector('[data-no-loading]')) return;
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.classList.add('is-loading');
  }));
});
