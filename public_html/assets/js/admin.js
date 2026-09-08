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

  document.querySelectorAll('[data-toast]').forEach(btn => {
    if (!btn.classList.contains('notification-button')) {
      btn.addEventListener('click', () => toast(btn.dataset.toast, 'info'));
    }
  });

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

  // Receipt printing helper
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

  // =========================================================================
  // AUDIO & ORDER NOTIFICATION CORE SYSTEM
  // =========================================================================
  let notificationAudio = null;
  let audioContext = null;
  let isAudioUnlocked = false;

  const playSynthesizedChime = () => {
    try {
      const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtxClass) return false;
      if (!audioContext) {
        audioContext = new AudioCtxClass();
      }
      if (audioContext.state === 'suspended') {
        audioContext.resume().catch(() => {});
      }

      const now = audioContext.currentTime;

      // Note 1: E5 (659.25 Hz) bell chime
      const osc1 = audioContext.createOscillator();
      const gain1 = audioContext.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(659.25, now);
      gain1.gain.setValueAtTime(0.35, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
      osc1.connect(gain1);
      gain1.connect(audioContext.destination);
      osc1.start(now);
      osc1.stop(now + 0.45);

      // Note 2: B5 (987.77 Hz) higher harmonic chime
      const osc2 = audioContext.createOscillator();
      const gain2 = audioContext.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(987.77, now + 0.12);
      gain2.gain.setValueAtTime(0.4, now + 0.12);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.65);
      osc2.connect(gain2);
      gain2.connect(audioContext.destination);
      osc2.start(now + 0.12);
      osc2.stop(now + 0.65);

      return true;
    } catch (e) {
      return false;
    }
  };

  const getNotificationAudio = () => {
    if (!notificationAudio) {
      notificationAudio = new Audio();
      const primaryUrl = '/assets/audio/notification.mp3';
      const fallbackUrl = '../assets/audio/notification.mp3';
      notificationAudio.src = primaryUrl;
      notificationAudio.preload = 'auto';

      notificationAudio.addEventListener('error', () => {
        if (!notificationAudio.dataset.retried) {
          notificationAudio.dataset.retried = 'true';
          notificationAudio.src = fallbackUrl;
          try { notificationAudio.load(); } catch (_) {}
        }
      });
    }
    return notificationAudio;
  };

  // Robust browser user-gesture unlock for both AudioContext and HTML5 Audio
  const unlockAudio = () => {
    try {
      const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
      if (AudioCtxClass) {
        if (!audioContext) audioContext = new AudioCtxClass();
        if (audioContext.state === 'suspended') {
          audioContext.resume().catch(() => {});
        }
      }
    } catch (_) {}

    try {
      const audio = getNotificationAudio();
      if (audio && !isAudioUnlocked) {
        audio.muted = true;
        const p = audio.play();
        if (p !== undefined) {
          p.then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            isAudioUnlocked = true;
          }).catch(() => {});
        }
      }
    } catch (_) {}
  };

  ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(evt => {
    document.addEventListener(evt, unlockAudio, { passive: true });
  });

  // Helper to read and clamp configured reminder interval (1-50s, default: 7s)
  const getReminderIntervalSeconds = () => {
    let interval = 7;
    if (window.CAFE_SETTINGS && typeof window.CAFE_SETTINGS.reminderInterval === 'number') {
      interval = window.CAFE_SETTINGS.reminderInterval;
    } else {
      const stored = parseInt(localStorage.getItem('cafe_order_reminder_interval') || '0', 10);
      if (stored >= 1 && stored <= 50) {
        interval = stored;
      } else {
        const meta = document.querySelector('meta[name="order-reminder-interval"]');
        if (meta && meta.content) {
          const parsed = parseInt(meta.content, 10);
          if (!isNaN(parsed) && parsed >= 1 && parsed <= 50) {
            interval = parsed;
          }
        }
      }
    }
    const tableBody = document.getElementById('adminOrdersTableBody');
    if (tableBody && tableBody.dataset.reminderInterval) {
      const parsed = parseInt(tableBody.dataset.reminderInterval, 10);
      if (!isNaN(parsed) && parsed >= 1 && parsed <= 50) {
        interval = parsed;
      }
    }
    return Math.max(1, Math.min(50, interval));
  };

  // Cross-tab synchronization via BroadcastChannel & localStorage
  let orderSoundChannel = null;
  if (typeof BroadcastChannel !== 'undefined') {
    try {
      orderSoundChannel = new BroadcastChannel('cafe_orders_sound_channel');
      orderSoundChannel.onmessage = (event) => {
        if (!event.data) return;
        if (event.data.type === 'ORDER_SOUND_PLAYED') {
          try {
            localStorage.setItem('cafe_last_sound_order_id', String(event.data.orderId));
            localStorage.setItem('cafe_last_sound_time', String(event.data.time || Date.now()));
            if (event.data.orderId) {
              localStorage.setItem(`cafe_reminder_order_${event.data.orderId}_last`, String(event.data.time || Date.now()));
              if (typeof PendingReminderManager !== 'undefined') {
                PendingReminderManager.syncExternalChime(event.data.orderId, event.data.time);
              }
            }
          } catch (_) {}
        } else if (event.data.type === 'ORDER_REMINDER_STOP') {
          if (event.data.orderId && typeof PendingReminderManager !== 'undefined') {
            PendingReminderManager.stopPendingOrder(event.data.orderId);
          }
        }
      };
    } catch (_) {}
  }

  // Cross-tab storage event listener for cross-tab sync fallback
  window.addEventListener('storage', (e) => {
    if (e.key && e.key.startsWith('cafe_reminder_order_') && e.key.endsWith('_last')) {
      const match = e.key.match(/cafe_reminder_order_(\d+)_last/);
      if (match && match[1]) {
        const orderId = parseInt(match[1], 10);
        const time = parseInt(e.newValue || '0', 10);
        if (time > 0 && typeof PendingReminderManager !== 'undefined') {
          PendingReminderManager.syncExternalChime(orderId, time);
        }
      }
    }
  });

  // Acoustic throttle tracker (prevents overlapping/colliding audio waveforms within 600ms)
  let lastGlobalSoundPlayedAt = 0;

  // Master Sound Trigger function with multi-tab deduplication
  const playOrderNotificationSound = (orderId = 0, isReminder = false) => {
    if (localStorage.getItem('cafe_admin_order_sound') === 'off') {
      return;
    }

    const now = Date.now();
    if (now - lastGlobalSoundPlayedAt < 600) {
      return;
    }
    lastGlobalSoundPlayedAt = now;

    try {
      localStorage.setItem('cafe_last_sound_time', String(now));
      if (orderId) {
        localStorage.setItem('cafe_last_sound_order_id', String(orderId));
        localStorage.setItem(`cafe_reminder_order_${orderId}_last`, String(now));
      }
    } catch (_) {}

    if (orderSoundChannel && orderId) {
      try {
        orderSoundChannel.postMessage({
          type: 'ORDER_SOUND_PLAYED',
          orderId: orderId,
          isReminder: isReminder,
          time: now
        });
      } catch (_) {}
    }

    let webAudioPlayed = playSynthesizedChime();
    try {
      const audio = getNotificationAudio();
      if (audio && !webAudioPlayed) {
        audio.currentTime = 0;
        const playPromise = audio.play();
        if (playPromise !== undefined) {
          playPromise.catch(() => {});
        }
      }
    } catch (_) {}
  };

  // Tab Background Title Flashing Alert (when tab is in background / minimized)
  let originalDocumentTitle = document.title;
  let titleNotificationTimer = null;

  const notifyTabTitle = (alertText = 'سفارش جدید دریافت شد!') => {
    if (!document.hidden) return;
    if (!originalDocumentTitle) originalDocumentTitle = document.title;

    if (titleNotificationTimer) clearInterval(titleNotificationTimer);
    let toggle = false;
    titleNotificationTimer = setInterval(() => {
      if (!document.hidden) {
        clearInterval(titleNotificationTimer);
        titleNotificationTimer = null;
        document.title = originalDocumentTitle;
        return;
      }
      document.title = toggle ? `🔔 ${alertText}` : originalDocumentTitle;
      toggle = !toggle;
    }, 1000);
  };

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && titleNotificationTimer) {
      clearInterval(titleNotificationTimer);
      titleNotificationTimer = null;
      if (originalDocumentTitle) document.title = originalDocumentTitle;
    }
  });

  // =========================================================================
  // PENDING REMINDER MANAGER (Must be initialized before form binders!)
  // =========================================================================
  const PendingReminderManager = (() => {
    const pendingOrders = new Map();
    const getIntervalMs = () => getReminderIntervalSeconds() * 1000;

    const registerPendingOrder = (orderId, isNewOrderArrival = false) => {
      const numId = parseInt(orderId, 10);
      if (!numId || isNaN(numId) || numId <= 0) return;

      if (pendingOrders.has(numId)) {
        return;
      }

      const orderKey = `cafe_reminder_order_${numId}_last`;
      const intervalMs = getIntervalMs();
      const now = Date.now();
      let initialDelay = intervalMs;

      if (isNewOrderArrival) {
        playOrderNotificationSound(numId, false);
        try {
          localStorage.setItem(orderKey, String(now));
        } catch (_) {}
        initialDelay = intervalMs;
      } else {
        const storedLast = parseInt(localStorage.getItem(orderKey) || '0', 10);
        if (storedLast > 0 && (now - storedLast) < intervalMs) {
          initialDelay = Math.max(500, intervalMs - (now - storedLast));
        } else {
          initialDelay = intervalMs;
          try {
            localStorage.setItem(orderKey, String(now));
          } catch (_) {}
        }
      }

      const item = {
        orderId: numId,
        timer: null
      };

      const tick = () => {
        if (!pendingOrders.has(numId)) {
          return;
        }

        const currentNow = Date.now();
        const currentIntervalMs = getIntervalMs();
        const lastChimed = parseInt(localStorage.getItem(orderKey) || '0', 10);

        if (!lastChimed || (currentNow - lastChimed) >= (currentIntervalMs - 400)) {
          playOrderNotificationSound(numId, true);
          notifyTabTitle(`سفارش شماره ${numId} در انتظار تأیید است`);
          try {
            localStorage.setItem(orderKey, String(currentNow));
          } catch (_) {}
        }

        if (pendingOrders.has(numId)) {
          item.timer = setTimeout(tick, getIntervalMs());
        }
      };

      item.timer = setTimeout(tick, initialDelay);
      pendingOrders.set(numId, item);
    };

    const stopPendingOrder = (orderId) => {
      const numId = parseInt(orderId, 10);
      if (!numId || isNaN(numId)) return;

      const item = pendingOrders.get(numId);
      if (item) {
        if (item.timer) {
          clearTimeout(item.timer);
          item.timer = null;
        }
        pendingOrders.delete(numId);
      }

      try {
        localStorage.removeItem(`cafe_reminder_order_${numId}_last`);
      } catch (_) {}

      if (orderSoundChannel) {
        try {
          orderSoundChannel.postMessage({
            type: 'ORDER_REMINDER_STOP',
            orderId: numId
          });
        } catch (_) {}
      }

      if (pendingOrders.size === 0 && titleNotificationTimer) {
        clearInterval(titleNotificationTimer);
        titleNotificationTimer = null;
        if (originalDocumentTitle) {
          document.title = originalDocumentTitle;
        }
      }
    };

    const syncPendingOrders = (activePendingIds) => {
      if (!Array.isArray(activePendingIds)) return;
      const validSet = new Set(activePendingIds.map(Number));

      for (const trackedId of pendingOrders.keys()) {
        if (!validSet.has(trackedId)) {
          stopPendingOrder(trackedId);
        }
      }

      for (const pid of validSet) {
        if (pid > 0 && !pendingOrders.has(pid)) {
          registerPendingOrder(pid, false);
        }
      }
    };

    const syncExternalChime = (orderId, chimeTime) => {
      const numId = parseInt(orderId, 10);
      if (!numId || !pendingOrders.has(numId)) return;

      const item = pendingOrders.get(numId);
      if (item) {
        if (item.timer) clearTimeout(item.timer);
        const intervalMs = getIntervalMs();
        const elapsed = Date.now() - (chimeTime || Date.now());
        const remaining = Math.max(500, intervalMs - elapsed);

        const tick = () => {
          if (!pendingOrders.has(numId)) return;
          const currentNow = Date.now();
          const currentIntervalMs = getIntervalMs();
          const lastChimed = parseInt(localStorage.getItem(`cafe_reminder_order_${numId}_last`) || '0', 10);

          if (!lastChimed || (currentNow - lastChimed) >= (currentIntervalMs - 400)) {
            playOrderNotificationSound(numId, true);
            notifyTabTitle(`سفارش شماره ${numId} در انتظار تأیید است`);
            try {
              localStorage.setItem(`cafe_reminder_order_${numId}_last`, String(currentNow));
            } catch (_) {}
          }

          if (pendingOrders.has(numId)) {
            item.timer = setTimeout(tick, getIntervalMs());
          }
        };

        item.timer = setTimeout(tick, remaining);
      }
    };

    const getActiveCount = () => pendingOrders.size;

    return {
      registerPendingOrder,
      stopPendingOrder,
      syncPendingOrders,
      syncExternalChime,
      getActiveCount
    };
  })();

  // =========================================================================
  // FORM BINDERS (Safe now that PendingReminderManager is initialized)
  // =========================================================================
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

    HTMLFormElement.prototype.submit.call(form);
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
          const idInput = form.querySelector('input[name="id"]');
          const orderId = idInput?.value ? parseInt(idInput.value, 10) : 0;
          if (orderId && statusSelect.value !== 'pending') {
            PendingReminderManager.stopPendingOrder(orderId);
          }
        };
        statusSelect.addEventListener('change', updateRequirement);
        updateRequirement();
      }

      form.addEventListener('submit', () => {
        const selectedStatus = statusSelect?.value;
        const idInput = form.querySelector('input[name="id"]');
        const orderId = idInput?.value ? parseInt(idInput.value, 10) : 0;

        if (orderId && selectedStatus && selectedStatus !== 'pending') {
          PendingReminderManager.stopPendingOrder(orderId);
        }

        const ordersTable = document.querySelector('.orders-table');
        const printingMethod = ordersTable?.dataset?.printingMethod || 'manual';

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

  // =========================================================================
  // SOUND TOGGLE & HEADER NOTIFICATION CONTROLS
  // =========================================================================
  const soundToggleBtn = document.getElementById('toggleOrderSoundBtn');
  if (soundToggleBtn) {
    const soundIcon = document.getElementById('orderSoundIcon');
    const soundLabel = document.getElementById('orderSoundLabel');

    const updateSoundToggleUI = () => {
      const isOff = localStorage.getItem('cafe_admin_order_sound') === 'off';
      if (soundIcon) soundIcon.textContent = isOff ? '🔕' : '🔔';
      if (soundLabel) soundLabel.textContent = isOff ? 'صدای اعلان: غیرفعال' : 'صدای اعلان: فعال';
      soundToggleBtn.style.color = isOff ? '#8fa0b5' : '#d4a373';
      soundToggleBtn.title = isOff ? 'فعال‌سازی صدای سفارش جدید' : 'غیرفعال‌سازی صدای سفارش جدید (کلیک جهت تست صدا)';
    };

    updateSoundToggleUI();

    soundToggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      unlockAudio();
      const isOff = localStorage.getItem('cafe_admin_order_sound') === 'off';
      if (isOff) {
        localStorage.removeItem('cafe_admin_order_sound');
        updateSoundToggleUI();
        toast('صدای اعلان سفارش جدید فعال شد.', 'info');
        playSynthesizedChime();
      } else {
        localStorage.setItem('cafe_admin_order_sound', 'off');
        updateSoundToggleUI();
        toast('صدای اعلان سفارش جدید غیرفعال شد.', 'info');
      }
    });

    if (soundIcon) {
      soundIcon.addEventListener('click', (e) => {
        e.stopPropagation();
        unlockAudio();
        playSynthesizedChime();
        toast('تست صدا انجام شد 🔔', 'info');
      });
    }
  }

  // Admin Header Notification Bell (Global on EVERY admin page)
  const headerNotificationBtn = document.querySelector('.notification-button');
  if (headerNotificationBtn) {
    headerNotificationBtn.removeAttribute('data-toast');
    headerNotificationBtn.addEventListener('click', (e) => {
      e.preventDefault();
      unlockAudio();
      const isOff = localStorage.getItem('cafe_admin_order_sound') === 'off';
      if (isOff) {
        localStorage.removeItem('cafe_admin_order_sound');
        playSynthesizedChime();
        toast('اعلان صوتی سفارش جدید فعال شد 🔔', 'success');
        const soundLabel = document.getElementById('orderSoundLabel');
        if (soundLabel) soundLabel.textContent = 'صدای اعلان: فعال';
        const soundIcon = document.getElementById('orderSoundIcon');
        if (soundIcon) soundIcon.textContent = '🔔';
      } else {
        playSynthesizedChime();
        const activeCount = PendingReminderManager.getActiveCount();
        if (activeCount > 0) {
          toast(`اعلان صوتی فعال است 🔔 (${activeCount} سفارش در انتظار تأیید)`, 'info');
        } else {
          toast('اعلان صوتی سفارش جدید فعال است 🔔 (تست صدا با موفقیت پخش شد)', 'info');
        }
      }
    });
  }

  // =========================================================================
  // LIVE POLLING SYSTEM
  // =========================================================================
  const ordersTableBody = document.getElementById('adminOrdersTableBody') || document.querySelector('.orders-table tbody');

  let isPolling = false;
  let pollIntervalTimer = null;
  const POLL_INTERVAL = 2500; // 2.5 seconds

  // Robust Polling URL Resolver across all admin views
  const resolveOrdersPollUrl = (afterId) => {
    const loc = window.location;
    const pathname = loc.pathname || '';
    const href = loc.href;

    // If on orders page, preserve active search & filter query params
    if (pathname.endsWith('/orders') || pathname.endsWith('/orders.php') || pathname.includes('/orders')) {
      const url = new URL(href);
      url.searchParams.set('poll', '1');
      url.searchParams.set('after_id', String(afterId));
      return url.toString();
    }

    // On other admin pages (e.g. /admin, /admin/dashboard, /admin/settings, /admin/products)
    const isPhp = pathname.endsWith('.php') || href.includes('.php');
    const ext = isPhp ? '.php' : '';
    let adminBase = '/admin/';

    const adminIdx = pathname.lastIndexOf('/admin');
    if (adminIdx !== -1) {
      adminBase = pathname.substring(0, adminIdx) + '/admin/';
    }

    const url = new URL(`${adminBase}orders${ext}`, loc.origin);
    url.searchParams.set('poll', '1');
    url.searchParams.set('after_id', String(afterId));
    return url.toString();
  };

  const fetchPollData = async (urlStr) => {
    let res;
    try {
      res = await fetch(urlStr, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        cache: 'no-store'
      });
    } catch (err) {
      return null;
    }

    // If 404, retry alternate format (.php vs clean URL)
    if (res && res.status === 404) {
      const altUrl = urlStr.includes('.php')
        ? urlStr.replace('.php', '')
        : urlStr.replace('/orders?', '/orders.php?');
      if (altUrl !== urlStr) {
        try {
          const altRes = await fetch(altUrl, {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store'
          });
          if (altRes && altRes.ok) res = altRes;
        } catch (_) {}
      }
    }

    if (!res || !res.ok) return null;

    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      return null;
    }

    try {
      return await res.json();
    } catch (_) {
      return null;
    }
  };

  // Order ID Tracking
  const getMaxOrderId = () => {
    let max = 0;
    if (ordersTableBody) {
      const initMax = parseInt(ordersTableBody.dataset.maxOrderId || '0', 10);
      if (!isNaN(initMax) && initMax > max) max = initMax;
      ordersTableBody.querySelectorAll('tr[data-order-id]').forEach(tr => {
        if (tr.dataset.removing === 'true') return;
        const id = parseInt(tr.dataset.orderId || '0', 10);
        if (!isNaN(id) && id > max) max = id;
      });
    } else {
      const stored = parseInt(localStorage.getItem('cafe_admin_latest_order_id') || '0', 10);
      if (!isNaN(stored) && stored > max) max = stored;
    }
    return max;
  };

  let lastKnownOrderId = getMaxOrderId();

  // Live Polling Worker
  const pollNewOrders = async () => {
    if (isPolling) return;
    isPolling = true;

    try {
      const currentMaxId = Math.max(lastKnownOrderId, getMaxOrderId());
      const pollUrl = resolveOrdersPollUrl(currentMaxId);
      const data = await fetchPollData(pollUrl);

      if (!data || !data.success) {
        return;
      }

      // Synchronize reminder interval with server setting dynamically
      if (typeof data.reminder_interval === 'number' && data.reminder_interval >= 1 && data.reminder_interval <= 50) {
        window.CAFE_SETTINGS = window.CAFE_SETTINGS || {};
        window.CAFE_SETTINGS.reminderInterval = data.reminder_interval;
        try {
          localStorage.setItem('cafe_order_reminder_interval', String(data.reminder_interval));
        } catch (_) {}
      }

      // Authoritative sync: ensure all pending reminders match server state
      if (Array.isArray(data.pending_ids)) {
        PendingReminderManager.syncPendingOrders(data.pending_ids);
      }

      // Self-heal stale localStorage max_id if database was reset or has lower max
      if (typeof data.max_id === 'number' && data.max_id > 0) {
        if (lastKnownOrderId > data.max_id && !ordersTableBody) {
          lastKnownOrderId = data.max_id;
          try {
            localStorage.setItem('cafe_admin_latest_order_id', String(data.max_id));
          } catch (_) {}
        }
      }

      // --- A: If on Orders Page with Table Body ---
      if (ordersTableBody) {
        // 1. Remove deleted / cancelled orders instantly
        if (Array.isArray(data.active_ids)) {
          const activeSet = new Set(data.active_ids.map(Number));
          const currentRows = ordersTableBody.querySelectorAll('tr[data-order-id]');
          let removedCount = 0;

          currentRows.forEach(row => {
            if (row.dataset.removing === 'true') return;
            const rowId = parseInt(row.dataset.orderId || '0', 10);
            if (rowId && !activeSet.has(rowId)) {
              row.dataset.removing = 'true';
              PendingReminderManager.stopPendingOrder(rowId);
              let orderNum = '';
              if (Array.isArray(data.deleted_orders)) {
                const found = data.deleted_orders.find(d => Number(d.id) === rowId);
                if (found) orderNum = found.order_number;
              }
              if (!orderNum) {
                const boldEl = row.querySelector('td:first-child b') || row.querySelector('b');
                orderNum = boldEl ? boldEl.textContent.trim() : `شماره ${rowId}`;
              }

              // Smooth animation: fade out and slide
              row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              row.style.opacity = '0';
              row.style.transform = 'translateX(20px)';

              setTimeout(() => {
                row.remove();
                if (ordersTableBody.querySelectorAll('tr[data-order-id]').length === 0) {
                  const noOrdersRow = document.getElementById('noOrdersRow') || document.createElement('tr');
                  noOrdersRow.id = 'noOrdersRow';
                  noOrdersRow.className = 'no-orders-row';
                  noOrdersRow.innerHTML = '<td colspan="8" class="text-center py-4" style="color:var(--muted); text-align:center; padding:24px;">سفارشی یافت نشد.</td>';
                  if (!noOrdersRow.parentNode) {
                    ordersTableBody.appendChild(noOrdersRow);
                  }
                }
              }, 350);

              removedCount++;
              toast(`سفارش ${orderNum} حذف شد`, 'danger');
            }
          });

          if (removedCount > 0 && data.total_count_display) {
            const totalCountEl = document.getElementById('adminOrdersTotalCount');
            if (totalCountEl) {
              totalCountEl.textContent = `مجموع سفارش‌ها: ${data.total_count_display}`;
            }
          }
        }

        // 1.5. Live status sync for existing rows if modified externally
        if (data.order_statuses && typeof data.order_statuses === 'object') {
          const statusLabels = {
            pending: 'در انتظار تأیید',
            approved: 'تأیید شده',
            completed: 'تکمیل شده',
            rejected: 'رد شده'
          };
          const statusBadgeClasses = {
            pending: 'badge-pending',
            approved: 'badge-approved',
            completed: 'badge-completed',
            rejected: 'badge-rejected'
          };

          ordersTableBody.querySelectorAll('tr[data-order-id]').forEach(row => {
            if (row.dataset.removing === 'true') return;
            const rowId = parseInt(row.dataset.orderId || '0', 10);
            const newStatus = data.order_statuses[rowId];
            if (newStatus && row.dataset.orderStatus && row.dataset.orderStatus !== newStatus) {
              row.dataset.orderStatus = newStatus;
              const statusBadge = row.querySelector('.badge');
              if (statusBadge) {
                statusBadge.textContent = statusLabels[newStatus] || newStatus;
                statusBadge.className = `badge ${statusBadgeClasses[newStatus] || ''}`;
              }
              const statusSelect = row.querySelector('select[name="status"]');
              if (statusSelect && statusSelect.value !== newStatus) {
                statusSelect.value = newStatus;
              }
              if (newStatus !== 'pending') {
                PendingReminderManager.stopPendingOrder(rowId);
              }
            }
          });
        }

        // 2. Add newly arrived orders
        if (data.count > 0 && data.html) {
          const template = document.createElement('template');
          template.innerHTML = `<table><tbody>${data.html}</tbody></table>`;
          const newRows = Array.from(template.content.querySelectorAll('tr[data-order-id]'));

          // Sort descending by ID (newest at top)
          newRows.sort((a, b) => {
            const idA = parseInt(a.dataset.orderId || '0', 10);
            const idB = parseInt(b.dataset.orderId || '0', 10);
            return idB - idA;
          });

          let addedCount = 0;
          const theadHeaders = Array.from(ordersTableBody.closest('table')?.querySelectorAll('thead th') || []).map(th => th.textContent.trim());

          newRows.forEach(row => {
            const orderId = row.dataset.orderId;
            if (!orderId || ordersTableBody.querySelector(`tr[data-order-id="${orderId}"]`)) {
              return;
            }

            // Remove empty placeholder row if present
            const noOrdersRow = document.getElementById('noOrdersRow') || ordersTableBody.querySelector('.no-orders-row');
            if (noOrdersRow) {
              noOrdersRow.remove();
            }

            // Responsive labels for mobile view
            if (theadHeaders.length > 0) {
              row.querySelectorAll('td').forEach((cell, idx) => {
                if (!cell.hasAttribute('colspan')) cell.dataset.label = theadHeaders[idx] || '';
              });
            }

            // Initial animation state
            row.style.opacity = '0';
            row.style.transform = 'translateY(-10px)';
            row.style.transition = 'opacity 0.4s ease, transform 0.4s ease, background-color 1.2s ease';
            row.style.backgroundColor = 'rgba(212, 163, 115, 0.18)';

            // Re-bind actions and handlers
            bindOrderStatusForms(row);
            bindCustomConfirm(row);
            bindPrintInvoiceForms(row);

            // Prepend new row
            ordersTableBody.prepend(row);
            addedCount++;

            // Enter animation trigger
            requestAnimationFrame(() => {
              row.style.opacity = '1';
              row.style.transform = 'translateY(0)';
              setTimeout(() => {
                row.style.backgroundColor = '';
              }, 2500);
            });

            const numericId = parseInt(orderId, 10);
            if (numericId > lastKnownOrderId) {
              lastKnownOrderId = numericId;
              try {
                localStorage.setItem('cafe_admin_latest_order_id', String(numericId));
              } catch (_) {}
            }

            const rowStatus = row.dataset.orderStatus || row.querySelector('select[name="status"]')?.value || 'pending';
            if (rowStatus === 'pending') {
              PendingReminderManager.registerPendingOrder(numericId, true /* isNewOrderArrival */);
            }
          });

          if (addedCount > 0) {
            const totalCountEl = document.getElementById('adminOrdersTotalCount');
            if (totalCountEl && data.total_count_display) {
              totalCountEl.textContent = `مجموع سفارش‌ها: ${data.total_count_display}`;
            }

            toast(`سفارش جدید دریافت شد (${addedCount} مورد)`, 'info');
            notifyTabTitle(`سفارش جدید (${addedCount} مورد)`);
            if (PendingReminderManager.getActiveCount() === 0) {
              playOrderNotificationSound(lastKnownOrderId);
            }
          }
        } else if (data.max_id && data.max_id > lastKnownOrderId) {
          lastKnownOrderId = data.max_id;
          try {
            localStorage.setItem('cafe_admin_latest_order_id', String(data.max_id));
          } catch (_) {}
        }
      } else {
        // --- B: On other Admin pages (Dashboard, Settings, Products, etc.) ---
        // First poll initialization: establish baseline without false blast of sound
        if (lastKnownOrderId === 0) {
          lastKnownOrderId = data.max_id || 0;
          try {
            localStorage.setItem('cafe_admin_latest_order_id', String(lastKnownOrderId));
          } catch (_) {}
        } else if (data.count > 0 && data.max_id > lastKnownOrderId) {
          const newMax = data.max_id;
          const addedCount = data.count;
          lastKnownOrderId = newMax;
          try {
            localStorage.setItem('cafe_admin_latest_order_id', String(newMax));
          } catch (_) {}

          toast(`سفارش جدید دریافت شد (${addedCount} مورد)`, 'info');
          notifyTabTitle(`سفارش جدید (${addedCount} مورد)`);

          // Register reminders for newly arrived pending orders
          let chimed = false;
          if (Array.isArray(data.pending_ids)) {
            data.pending_ids.forEach(pid => {
              if (pid > currentMaxId) {
                PendingReminderManager.registerPendingOrder(pid, true /* isNewOrderArrival */);
                chimed = true;
              }
            });
          }
          if (!chimed) {
            playOrderNotificationSound(newMax);
          }
        } else if (data.max_id && data.max_id > lastKnownOrderId) {
          lastKnownOrderId = data.max_id;
          try {
            localStorage.setItem('cafe_admin_latest_order_id', String(data.max_id));
          } catch (_) {}
        }
      }
    } catch (e) {
      // Graceful error handling: keep polling timer alive
    } finally {
      isPolling = false;
    }
  };

  // Initialize existing pending orders from page table on initial load
  if (ordersTableBody) {
    try {
      const rawPending = ordersTableBody.dataset.pendingIds;
      if (rawPending) {
        const initialPending = JSON.parse(rawPending);
        if (Array.isArray(initialPending)) {
          initialPending.forEach(id => {
            PendingReminderManager.registerPendingOrder(id, false /* isNewOrderArrival */);
          });
        }
      }
    } catch (_) {}
  }

  // Start continuous polling across all admin views
  pollIntervalTimer = setInterval(pollNewOrders, POLL_INTERVAL);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      pollNewOrders();
    }
  });

  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented || form.dataset.confirmed || form.querySelector('[data-no-loading]')) return;
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.classList.add('is-loading');
  }));
});