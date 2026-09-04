/**
 * Cafe Denj - Customer Orders Live Status & Interaction Handler
 */
const initOrdersModule = () => {
  // Helper: Resolve proper endpoint path respecting PHP, clean URLs, and subdirectories
  const resolveEndpoint = (name, query = '') => {
    const path = window.location.pathname;
    const isPhp = path.endsWith('.php') || path.includes('.php');
    const lastSlash = path.lastIndexOf('/');
    const baseDir = lastSlash !== -1 ? path.substring(0, lastSlash + 1) : '/';
    const ext = isPhp ? '.php' : '';
    const qs = query ? `?${query}` : '';
    return `${baseDir}${name}${ext}${qs}`;
  };

  // Helper: Resilient fetch that tries primary URL and falls back to alternate extension if 404
  const fetchWithFallback = async (name, options = {}, query = '') => {
    const primaryUrl = resolveEndpoint(name, query);
    let res;
    try {
      res = await fetch(primaryUrl, options);
    } catch (err) {
      throw new Error('خطای برقراری ارتباط با سرور.');
    }

    // If 404 returned, retry with/without .php
    if (res.status === 404) {
      const path = window.location.pathname;
      const lastSlash = path.lastIndexOf('/');
      const baseDir = lastSlash !== -1 ? path.substring(0, lastSlash + 1) : '/';
      const altExt = primaryUrl.includes('.php') ? '' : '.php';
      const qs = query ? `?${query}` : '';
      const altUrl = `${baseDir}${name}${altExt}${qs}`;
      try {
        const altRes = await fetch(altUrl, options);
        if (altRes.ok || altRes.status !== 404) {
          res = altRes;
        }
      } catch (_) {}
    }

    return res;
  };

  // Helper: Safely parse JSON without crashing if server returns an HTML error page (<!DOCTYPE ...)
  const parseResponseJson = async (res) => {
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const text = await res.text().catch(() => '');
      if (!res.ok) {
        throw new Error(`خطای ارتباط با سرور (${res.status})`);
      }
      throw new Error('پاسخ نامعتبر از سرور دریافت شد.');
    }
    return await res.json();
  };

  // --- 1. Order Deletion Modal & Action Handler ---
  const modal = document.getElementById('orderModal');
  let toast = document.getElementById('orderToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'orderToast';
    toast.className = 'order-toast';
    document.body.appendChild(toast);
  }

  let pendingDeleteId = null;

  const closeModal = () => {
    if (modal) {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
  };

  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
  };

  document.querySelectorAll('[data-order-delete]').forEach(button => {
    button.addEventListener('click', () => {
      pendingDeleteId = button.dataset.orderDelete;
      if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
      }
    });
  });

  const cancelBtn = document.getElementById('cancelDelete');
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  const backdrop = document.querySelector('.order-modal-backdrop');
  if (backdrop) backdrop.addEventListener('click', closeModal);

  const confirmBtn = document.getElementById('confirmDelete');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', async () => {
      if (!pendingDeleteId) return;
      confirmBtn.disabled = true;
      try {
        const params = new URLSearchParams();
        if (window.orderCsrf) {
          params.append('csrf_token', window.orderCsrf);
        }
        params.append('order_id', String(pendingDeleteId));
        params.append('id', String(pendingDeleteId));

        const res = await fetchWithFallback('order-delete', {
          method: 'POST',
          body: params,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        });

        const data = await parseResponseJson(res);
        if (!data.success) {
          throw new Error(data.message || 'خطا در حذف سفارش');
        }

        const card = document.querySelector(`[data-order-id="${pendingDeleteId}"]`);
        if (card) {
          card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
          card.style.opacity = '0';
          card.style.transform = 'translateY(10px)';
          setTimeout(() => {
            card.remove();
            checkEmptyState();
          }, 300);
        }
        closeModal();
        showToast(data.message || 'سفارش با موفقیت لغو و حذف شد.');
      } catch (err) {
        showToast(err.message || 'خطا در حذف سفارش');
      } finally {
        confirmBtn.disabled = false;
      }
    });
  }

  const checkEmptyState = () => {
    const remainingCards = document.querySelectorAll('.order-card[data-order-id]');
    if (remainingCards.length === 0) {
      const listContainer = document.querySelector('.orders-list') || document.querySelector('.orders-layout');
      if (listContainer) {
        listContainer.innerHTML = `
          <header class="orders-heading">
            <a href="/">← بازگشت به منو</a>
            <div>
              <span>حساب کاربری</span>
              <h1>سفارش‌های من</h1>
              <p>وضعیت سفارش‌ها و فاکتورهای خود را اینجا پیگیری کنید.</p>
            </div>
          </header>
          <section class="cart-empty">
            <h2>هنوز سفارشی ثبت نکرده‌اید</h2>
            <p>از منوی کافه دنج محصول دلخواهتان را انتخاب کنید.</p>
            <a class="auth-submit" href="/">مشاهده منو</a>
          </section>
        `;
      }
    }
  };

  // --- 2. Live Order Status Polling ---
  const POLL_INTERVAL = 2000;
  let isPolling = false;
  let pollTimer = null;

  const pollOrderStatus = async () => {
    if (isPolling) return;

    const singleOrderDetail = document.querySelector('[data-order-detail-id]');
    const cards = document.querySelectorAll('.order-card[data-order-id]');

    if (!singleOrderDetail && cards.length === 0) return;

    isPolling = true;
    try {
      if (singleOrderDetail) {
        const orderId = singleOrderDetail.dataset.orderDetailId;
        const res = await fetchWithFallback('order', {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          cache: 'no-store'
        }, `id=${encodeURIComponent(orderId)}&poll=1`);

        if (res.status === 401 || res.status === 403) {
          if (pollTimer) clearInterval(pollTimer);
          return;
        }

        if (res.ok) {
          const data = await parseResponseJson(res);
          if (data && data.success && data.order) {
            const statusEl = document.querySelector('[data-order-detail-status]');
            if (statusEl && statusEl.dataset.orderDetailStatus !== data.order.status) {
              statusEl.textContent = data.order.label;
              statusEl.dataset.orderDetailStatus = data.order.status;
              statusEl.style.transition = 'transform 0.25s ease, color 0.25s ease';
              statusEl.style.transform = 'scale(1.1)';
              statusEl.style.color = '#73ddbb';
              setTimeout(() => {
                statusEl.style.transform = 'scale(1)';
              }, 400);
            }
          }
        }
      }

      if (cards.length > 0) {
        const res = await fetchWithFallback('orders', {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          cache: 'no-store'
        }, 'poll=1');

        if (res.status === 401 || res.status === 403) {
          if (pollTimer) clearInterval(pollTimer);
          return;
        }

        if (res.ok) {
          const data = await parseResponseJson(res);
          if (data && data.success && Array.isArray(data.statuses)) {
            const statusMap = new Map();
            data.statuses.forEach(item => {
              statusMap.set(Number(item.id), item);
            });

            cards.forEach(card => {
              const orderId = Number(card.dataset.orderId);
              if (!orderId) return;

              const updated = statusMap.get(orderId);
              if (!updated) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateY(10px)';
                setTimeout(() => {
                  card.remove();
                  checkEmptyState();
                }, 300);
                return;
              }

              const statusSpan = card.querySelector('.order-status');
              if (!statusSpan) return;

              const currentStatus = statusSpan.dataset.status || '';
              if (currentStatus !== updated.status) {
                statusSpan.textContent = updated.label;
                statusSpan.className = `order-status ${updated.class}`;
                statusSpan.dataset.status = updated.status;

                statusSpan.style.transition = 'transform 0.25s ease, box-shadow 0.25s ease';
                statusSpan.style.transform = 'scale(1.08)';
                statusSpan.style.boxShadow = '0 0 10px rgba(115, 221, 187, 0.4)';
                setTimeout(() => {
                  statusSpan.style.transform = 'scale(1)';
                  statusSpan.style.boxShadow = 'none';
                }, 400);

                if (!updated.can_delete) {
                  const deleteElement = card.querySelector('form[action*="order-delete"], .order-delete, [data-order-delete]');
                  if (deleteElement) {
                    deleteElement.style.transition = 'opacity 0.2s ease';
                    deleteElement.style.opacity = '0';
                    setTimeout(() => deleteElement.remove(), 200);
                  }
                }
              }
            });
          }
        }
      }
    } catch (_) {
      // Non-disruptive catch
    } finally {
      isPolling = false;
    }
  };

  pollOrderStatus();
  pollTimer = setInterval(pollOrderStatus, POLL_INTERVAL);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      pollOrderStatus();
    }
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initOrdersModule);
} else {
  initOrdersModule();
}