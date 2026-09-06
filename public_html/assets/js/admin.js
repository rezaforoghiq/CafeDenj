document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const toggle = document.getElementById('adminMenuToggle');
  const sidebar = document.getElementById('adminSidebar');
  const profile = document.querySelector('.profile-trigger');
  const menu = document.getElementById('profileMenu');
  const modal = document.getElementById('confirmModal');
  let pendingForm = null;

  const closeDrawer = () => { body.classList.remove('drawer-open'); toggle?.setAttribute('aria-expanded', 'false'); };
  toggle?.addEventListener('click', () => { const open = body.classList.toggle('drawer-open'); toggle.setAttribute('aria-expanded', String(open)); });
  document.querySelectorAll('[data-drawer-close], .sidebar-nav a').forEach(el => el.addEventListener('click', closeDrawer));

  profile?.addEventListener('click', e => { e.stopPropagation(); const isOpen = menu.classList.toggle('is-open'); profile.setAttribute('aria-expanded', String(isOpen)); });
  document.addEventListener('click', e => { if (!e.target.closest('.user-menu')) { menu?.classList.remove('is-open'); profile?.setAttribute('aria-expanded', 'false'); } });

  let toastRegion = document.querySelector('.toast-region');
  const getToastRegion = () => {
    if (!toastRegion || !document.body.contains(toastRegion)) {
      toastRegion = document.querySelector('.toast-region');
      if (!toastRegion) {
        toastRegion = document.createElement('div');
        toastRegion.className = 'toast-region';
        toastRegion.setAttribute('aria-live', 'polite');
        toastRegion.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastRegion);
      }
    }
    return toastRegion;
  };

  const toast = (message, type = 'success') => {
    const region = getToastRegion();
    const item = document.createElement('div');
    item.className = `toast toast-${type}`;
    item.innerHTML = `<span>${type === 'success' ? '✓' : 'i'}</span><p>${message}</p><button aria-label="بستن">×</button>`;
    region.append(item);
    requestAnimationFrame(() => item.classList.add('show'));
    const remove = () => { item.classList.remove('show'); setTimeout(() => item.remove(), 220); };
    item.querySelector('button').onclick = remove;
    setTimeout(remove, 5000);
  };
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

  const bindCustomConfirm = (root = document) => {
    root.querySelectorAll('form[data-use-custom-confirm]').forEach(form => {
      if (form.dataset.customConfirmBound) return;
      form.dataset.customConfirmBound = 'true';
      form.removeAttribute('onsubmit');
      form.addEventListener('submit', e => {
        if (form.dataset.confirmed) return;
        e.preventDefault();
        pendingForm = form;
        const button = form.querySelector('button[type="submit"]');
        const confirmText = form.dataset.confirmText?.trim();
        const confirmTextEl = document.getElementById('confirmText');
        if (confirmTextEl) {
          confirmTextEl.textContent = confirmText || (button?.textContent.includes('حذف') ? 'آیا از حذف این سفارش مطمئن هستید؟ این عملیات قابل بازگشت نیست.' : 'آیا از انجام این عملیات مطمئن هستید؟');
        }
        modal?.classList.add('is-open');
        modal?.setAttribute('aria-hidden', 'false');
      });
    });
  };
  bindCustomConfirm();

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

  // Printing Method Integration: Manual mode browser-direct printing
  const openReceiptWindow = (orderId, type = 'customer') => {
    const url = `../receipt.php?id=${encodeURIComponent(orderId)}&type=${encodeURIComponent(type)}&autoprint=1`;
    const win = window.open(url, `receipt_${type}_${orderId}`, 'width=460,height=680,scrollbars=yes,resizable=yes');
    if (win) {
      win.focus();
    } else {
      window.open(url, '_blank');
    }
  };
  window.triggerBrowserReceipt = openReceiptWindow;

  // Handle manual print button clicks
  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-manual-print');
    if (btn) {
      e.preventDefault();
      const href = btn.getAttribute('href');
      const win = window.open(href, 'receipt_window', 'width=460,height=680,scrollbars=yes,resizable=yes');
      if (win) {
        win.focus();
      } else {
        window.open(href, '_blank');
      }
    }
  });

  const bindOrderStatusForms = (root = document) => {
    root.querySelectorAll('form[data-order-status-form]').forEach(form => {
      if (form.dataset.statusBound) return;
      form.dataset.statusBound = 'true';
      const statusSelect = form.querySelector('select[name="status"]');
      const paymentSelect = form.querySelector('select[name="payment_method"]');
      if (statusSelect && paymentSelect) {
        const updateRequirement = () => {
          paymentSelect.required = statusSelect.value === 'completed';
        };
        statusSelect.addEventListener('change', updateRequirement);
        updateRequirement();
      }

      form.addEventListener('submit', () => {
        const selectedStatus = statusSelect?.value;
        const ordersTable = document.querySelector('.orders-table');
        const printingMethod = ordersTable?.dataset?.printingMethod || 'manual';
        const idInput = form.querySelector('input[name="id"]');
        const orderId = idInput?.value;

        if (selectedStatus === 'approved' && printingMethod === 'manual' && orderId) {
          openReceiptWindow(orderId, 'barista');
        }
      });
    });
  };
  bindOrderStatusForms();

  const bindPrintInvoiceForms = (root = document) => {
    const ordersTable = document.querySelector('.orders-table');
    const method = ordersTable?.dataset?.printingMethod || 'automatic';

    if (method === 'manual') {
      root.querySelectorAll('form').forEach(form => {
        const actionInput = form.querySelector('input[name="action"][value="print_invoice"]');
        if (actionInput && !form.dataset.manualPrintBound) {
          form.dataset.manualPrintBound = 'true';
          form.addEventListener('submit', e => {
            e.preventDefault();
            const idInput = form.querySelector('input[name="id"]');
            const orderId = idInput?.value;
            if (orderId) {
              openReceiptWindow(orderId, 'customer');
            }
          });
        }
      });
    }
  };
  bindPrintInvoiceForms();

  // Auto-refresh new orders via lightweight AJAX Polling (Order management page)
  const ordersTableBody = document.getElementById('adminOrdersTableBody') || document.querySelector('.orders-table tbody');
  if (ordersTableBody) {
    let isPolling = false;
    let pollIntervalTimer = null;
    const POLL_INTERVAL = 2000; // 2 seconds

    const getMaxOrderId = () => {
      let max = 0;
      const initMax = parseInt(ordersTableBody.dataset.maxOrderId || '0', 10);
      if (!isNaN(initMax) && initMax > max) max = initMax;
      ordersTableBody.querySelectorAll('tr[data-order-id]').forEach(tr => {
        const id = parseInt(tr.dataset.orderId || '0', 10);
        if (!isNaN(id) && id > max) max = id;
      });
      return max;
    };

    let lastKnownOrderId = getMaxOrderId();

    const pollNewOrders = async () => {
      if (isPolling) return;
      if (document.hidden) return; // Pause polling when tab is not active

      isPolling = true;
      try {
        const currentMaxId = Math.max(lastKnownOrderId, getMaxOrderId());
        const pollUrl = new URL(window.location.href);
        pollUrl.searchParams.set('poll', '1');
        pollUrl.searchParams.set('after_id', String(currentMaxId));

        const res = await fetch(pollUrl.toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          cache: 'no-store'
        });

        if (res.status === 401 || res.status === 403) {
          // Session expired or unauthorized: halt polling
          clearInterval(pollIntervalTimer);
          return;
        }

        if (!res.ok) return;

        const data = await res.json();
        if (!data || !data.success) return;

        // 1. Remove deleted / cancelled orders instantly
        if (Array.isArray(data.active_ids)) {
          const activeSet = new Set(data.active_ids.map(Number));
          const currentRows = ordersTableBody.querySelectorAll('tr[data-order-id]');
          let removedCount = 0;

          currentRows.forEach(row => {
            const rowId = parseInt(row.dataset.orderId || '0', 10);
            if (rowId && !activeSet.has(rowId)) {
              let orderNum = '';
              if (Array.isArray(data.deleted_orders)) {
                const found = data.deleted_orders.find(d => Number(d.id) === rowId);
                if (found) orderNum = found.order_number;
              }
              if (!orderNum) {
                const boldEl = row.querySelector('td:first-child b') || row.querySelector('b');
                orderNum = boldEl ? boldEl.textContent.trim() : `شماره ${rowId}`;
              }

              // Smooth fade-out and slide
              row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
              row.style.opacity = '0';
              row.style.transform = 'translateX(20px)';

              setTimeout(() => {
                row.remove();
                if (ordersTableBody.querySelectorAll('tr[data-order-id]').length === 0) {
                  const noOrdersRow = document.getElementById('noOrdersRow') || document.createElement('tr');
                  noOrdersRow.id = 'noOrdersRow';
                  noOrdersRow.className = 'no-orders-row';
                  noOrdersRow.innerHTML = '<td colspan="7" style="text-align:center; padding:24px; color:#8fa0b5;">هیچ سفارشی موجود نیست.</td>';
                  if (!noOrdersRow.parentNode) {
                    ordersTableBody.appendChild(noOrdersRow);
                  }
                }
              }, 300);

              removedCount++;
              toast(`سفارش ${orderNum} توسط مشتری لغو و حذف شد`, 'danger');
            }
          });

          if (removedCount > 0 && data.total_count_display) {
            const totalCountEl = document.getElementById('adminOrdersTotalCount');
            if (totalCountEl) {
              totalCountEl.textContent = `مجموع سفارش‌ها: ${data.total_count_display}`;
            }
          }
        }

        // 2. Add new orders if any
        if (data.count > 0 && data.html) {
          const temp = document.createElement('tbody');
          temp.innerHTML = data.html;
          const newRows = Array.from(temp.querySelectorAll('tr[data-order-id]'));

          // Sort new rows descending by ID (newest first)
          newRows.sort((a, b) => {
            const idA = parseInt(a.dataset.orderId || '0', 10);
            const idB = parseInt(b.dataset.orderId || '0', 10);
            return idB - idA;
          });

          let addedCount = 0;
          const labels = Array.from(ordersTableBody.closest('table')?.querySelectorAll('thead th') || []).map(th => th.textContent.trim());

          newRows.forEach(row => {
            const orderId = row.dataset.orderId;
            // Strict duplicate check:
            if (!orderId || ordersTableBody.querySelector(`tr[data-order-id="${orderId}"]`)) {
              return;
            }

            // Remove empty placeholder row if present
            const noOrdersRow = document.getElementById('noOrdersRow') || ordersTableBody.querySelector('.no-orders-row');
            if (noOrdersRow) {
              noOrdersRow.remove();
            }

            // Set responsive data-labels for mobile view
            if (labels.length > 0) {
              row.querySelectorAll('td').forEach((cell, idx) => {
                if (!cell.hasAttribute('colspan')) cell.dataset.label = labels[idx] || '';
              });
            }

            // Bind any dynamic form handlers
            bindOrderStatusForms(row);
            bindCustomConfirm(row);
            bindPrintInvoiceForms(row);

            // Prepend new row at top
            ordersTableBody.prepend(row);
            addedCount++;

            const numericId = parseInt(orderId, 10);
            if (numericId > lastKnownOrderId) {
              lastKnownOrderId = numericId;
            }
          });

          if (addedCount > 0) {
            // Update total orders counter if element exists
            const totalCountEl = document.getElementById('adminOrdersTotalCount');
            if (totalCountEl && data.total_count_display) {
              totalCountEl.textContent = `مجموع سفارش‌ها: ${data.total_count_display}`;
            }

            toast(`سفارش جدید دریافت شد (${addedCount} مورد)`, 'info');
          }
        } else if (data.max_id && data.max_id > lastKnownOrderId) {
          lastKnownOrderId = data.max_id;
        }
      } catch (e) {
        // Network error handled gracefully: no UI disruption
      } finally {
        isPolling = false;
      }
    };

    pollIntervalTimer = setInterval(pollNewOrders, POLL_INTERVAL);

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        pollNewOrders();
      }
    });
  }

  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    // Delete forms are intentionally stopped once to show the confirmation
    // dialog; they must not look like a request has already started.
    if (event.defaultPrevented || form.dataset.confirmed || form.querySelector('[data-no-loading]')) return;
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.classList.add('is-loading');
  }));
});