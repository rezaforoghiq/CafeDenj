document.addEventListener('DOMContentLoaded', function () {
  const themeSwitch = document.getElementById('themeSwitch');
  const root = document.documentElement;
  function updateThemeControl(theme) {
    if (!themeSwitch) return;
    const isLight = theme === 'light';
    themeSwitch.setAttribute('aria-pressed', String(isLight));
    themeSwitch.setAttribute('aria-label', isLight ? 'تغییر به حالت تاریک' : 'تغییر به حالت روشن');
  }
  updateThemeControl(root.dataset.theme || 'dark');
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const mobileClose = document.getElementById('mobileMenuClose');
  const mobileBackdrop = document.getElementById('mobileMenuBackdrop');
  const closeMobile = () => { document.body.classList.remove('mobile-menu-open'); mobileToggle?.setAttribute('aria-expanded', 'false'); };
  mobileToggle?.addEventListener('click', () => { document.body.classList.add('mobile-menu-open'); mobileToggle.setAttribute('aria-expanded', 'true'); });
  mobileClose?.addEventListener('click', closeMobile); mobileBackdrop?.addEventListener('click', closeMobile);
  const profileTrigger = document.getElementById('profileTrigger'); const userMenu = profileTrigger?.closest('.user-menu'); const closeProfileMenu = () => { userMenu?.classList.remove('is-open'); profileTrigger?.setAttribute('aria-expanded', 'false'); }; profileTrigger?.addEventListener('click', () => { const isOpen = userMenu?.classList.toggle('is-open'); profileTrigger.setAttribute('aria-expanded', String(Boolean(isOpen))); }); document.addEventListener('click', event => { if (userMenu && !userMenu.contains(event.target)) closeProfileMenu(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape') closeProfileMenu(); });
  themeSwitch?.addEventListener('click', function () { const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light'; root.dataset.theme = nextTheme; try { localStorage.setItem('denj-theme', nextTheme); } catch (e) {} updateThemeControl(nextTheme);
    // toggle beans animation when theme changes
    if (nextTheme === 'dark' || nextTheme === 'light') initBeans(); else clearBeans();
  });

  // --- Beans animation (dark theme) ---
  function initBeans(){
    const beansEl = document.getElementById('beans');
    if(!beansEl) return;
    if(beansEl.dataset.inited) return; // prevent duplicate
    const total = 14;
    for (let i = 0; i < total; i++) {
      const b = document.createElement('div');
      b.className = 'bean';

      const size = 10 + Math.random() * 10;
      b.style.width = size + 'px';
      b.style.height = (size * 1.4) + 'px';
      b.style.left = Math.random() * 100 + 'vw';
      b.style.top = (100 + Math.random() * 20) + 'vh';
      b.style.animationDuration = (14 + Math.random() * 14) + 's';
      b.style.animationDelay = (Math.random() * 10) + 's';

      beansEl.appendChild(b);
    }
    beansEl.dataset.inited = '1';
  }
  function clearBeans(){
    const beansEl = document.getElementById('beans');
    if(!beansEl) return;
    beansEl.innerHTML = '';
    delete beansEl.dataset.inited;
  }

  // Initialize on load if theme is dark or light
  if(root.dataset.theme === 'dark' || root.dataset.theme === 'light') initBeans();

  const searchInput = document.getElementById('searchInput');
  const catButtons = document.querySelectorAll('.cat-btn');
  const cards = document.querySelectorAll('.item-card');
  const emptyState = document.getElementById('emptyState');
  let activeCat = 'all';
  function applyFilters() { const term = searchInput?.value.trim().toLowerCase() || ''; let visibleCount = 0; cards.forEach(card => { const visible = (activeCat === 'all' || card.dataset.cat === activeCat) && card.dataset.name.toLowerCase().includes(term); card.classList.toggle('hidden', !visible); if (visible) visibleCount++; }); if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none'; }
  catButtons.forEach(button => button.addEventListener('click', function () { catButtons.forEach(item => item.classList.remove('active')); button.classList.add('active'); activeCat = button.dataset.cat || 'all'; applyFilters(); }));
  searchInput?.addEventListener('input', applyFilters);
  function toPersianDigits(n) {
    if (n === null || n === undefined) return '';
    const str = String(n);
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str.replace(/[0-9]/g, function (w) { return persianDigits[Number(w)]; });
  }

  function updateGlobalCartBadges(count) {
    const num = Math.max(0, Number(count) || 0);
    document.querySelectorAll('[data-cart-count]').forEach(function (badge) {
      badge.textContent = num > 0 ? toPersianDigits(num) : '';
      badge.classList.toggle('is-empty', num === 0);
    });
    document.querySelectorAll('.header-cart-btn').forEach(function (btn) {
      btn.classList.toggle('is-hidden', num === 0);
    });
    document.querySelectorAll('.header-cart-badge, [data-cart-count]').forEach(function (el) {
      el.classList.remove('pulse');
      void el.offsetWidth;
      el.classList.add('pulse');
    });
  }

  let toastTimeout = null;
  function showToast(msg) {
    let toast = document.querySelector('.cart-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'cart-toast';
      document.body.append(toast);
    }
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(function () {
      toast.classList.remove('show');
    }, 2200);
  }

  // CartCoordinator: Manages Stepper UI, optimistic updates, request batching & race-condition prevention
  const CartCoordinator = {
    items: new Map(),
    csrfToken: null,

    init: function () {
      const sampleCsrf = document.querySelector('input[name="csrf_token"]');
      if (sampleCsrf) {
        this.csrfToken = sampleCsrf.value;
      }
      const self = this;
      document.querySelectorAll('[data-cart-add]').forEach(function (form) {
        const pId = parseInt(form.dataset.productId, 10);
        if (!pId) return;
        const initialQty = parseInt(form.dataset.quantity, 10) || 0;
        self.items.set(pId, {
          confirmedQty: initialQty,
          targetQty: initialQty,
          inFlight: false,
          lastSeq: 0,
          debounceTimer: null,
        });
        self.syncCardUI(pId, initialQty);
      });
    },

    getState: function (productId) {
      let state = this.items.get(productId);
      if (!state) {
        state = {
          confirmedQty: 0,
          targetQty: 0,
          inFlight: false,
          lastSeq: 0,
          debounceTimer: null,
        };
        this.items.set(productId, state);
      }
      return state;
    },

    syncCardUI: function (productId, qty) {
      const clamped = Math.max(0, Math.min(99, qty));
      const forms = document.querySelectorAll('[data-cart-add][data-product-id="' + productId + '"]');
      forms.forEach(function (form) {
        form.dataset.quantity = String(clamped);
        const qtyInput = form.querySelector('[data-qty-input]');
        if (qtyInput) qtyInput.value = String(clamped);

        const slot = form.querySelector('.cart-control-slot');
        const addBtn = form.querySelector('.add-to-cart-btn');
        const stepper = form.querySelector('.stepper-wrap');
        const qtyLabel = form.querySelector('[data-step-qty]');

        if (clamped > 0) {
          slot?.classList.add('has-items');
          addBtn?.classList.add('is-hidden');
          stepper?.classList.add('is-active');
          if (qtyLabel) {
            qtyLabel.textContent = toPersianDigits(clamped);
            qtyLabel.classList.remove('pulse');
            void qtyLabel.offsetWidth;
            qtyLabel.classList.add('pulse');
          }
        } else {
          slot?.classList.remove('has-items');
          addBtn?.classList.remove('is-hidden');
          stepper?.classList.remove('is-active');
        }
      });
    },

    changeQuantity: function (productId, deltaOrTarget, isAbsolute) {
      const state = this.getState(productId);
      const prevTarget = state.targetQty;
      const nextTarget = isAbsolute
        ? Math.max(0, Math.min(99, deltaOrTarget))
        : Math.max(0, Math.min(99, prevTarget + deltaOrTarget));

      if (nextTarget === prevTarget) return;

      state.targetQty = nextTarget;

      // 1. Optimistic Local UI update (Zero latency)
      this.syncCardUI(productId, nextTarget);

      // 2. Optimistic Header Badges update
      let totalEst = 0;
      for (const s of this.items.values()) {
        totalEst += s.targetQty;
      }
      updateGlobalCartBadges(totalEst);

      // 3. Batch rapid clicks via tight trailing debounce
      if (state.debounceTimer) {
        clearTimeout(state.debounceTimer);
      }

      const self = this;
      state.debounceTimer = setTimeout(function () {
        self.flush(productId);
      }, 140);
    },

    flush: async function (productId) {
      const state = this.getState(productId);
      if (state.inFlight) {
        return;
      }

      if (state.targetQty === state.confirmedQty) {
        return;
      }

      state.inFlight = true;
      const thisSeq = ++state.lastSeq;
      const targetToSend = state.targetQty;

      const form = document.querySelector('[data-cart-add][data-product-id="' + productId + '"]');
      const actionUrl = (form && form.getAttribute('action')) || (form && form.action) || '/cart-action';
      const token = form?.querySelector('input[name="csrf_token"]')?.value || this.csrfToken;

      const formData = new URLSearchParams();
      formData.append('product_id', String(productId));
      formData.append('action', 'update');
      formData.append('quantity', String(targetToSend));
      if (token) {
        formData.append('csrf_token', token);
      }

      const self = this;
      try {
        const res = await fetch(actionUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
          },
          body: formData.toString(),
        });

        let data = null;
        try {
          data = await res.json();
        } catch (parseErr) {
          console.warn('Cart response not JSON:', parseErr);
        }

        // Stale response guard
        if (thisSeq < state.lastSeq) {
          return;
        }

        if (!res.ok || !data || !data.success) {
          const msg = (data && data.message) ? data.message : 'خطا در به‌روزرسانی سبد خرید';
          throw new Error(msg);
        }

        state.confirmedQty = Number(data.item_quantity) ?? targetToSend;
        updateGlobalCartBadges(data.count);

        if (state.targetQty !== state.confirmedQty) {
          setTimeout(function () { self.flush(productId); }, 40);
        } else {
          self.syncCardUI(productId, state.confirmedQty);
        }
      } catch (err) {
        console.error('Cart sync error:', err);
        showToast(err.message || 'خطا در به‌روزرسانی سبد خرید');
        // Roll back to server-confirmed state
        state.targetQty = state.confirmedQty;
        self.syncCardUI(productId, state.confirmedQty);
        self.refreshAllFromServer();
      } finally {
        state.inFlight = false;
        if (state.targetQty !== state.confirmedQty && thisSeq === state.lastSeq) {
          self.flush(productId);
        }
      }
    },

    refreshAllFromServer: async function () {
      try {
        const sampleForm = document.querySelector('[data-cart-add]');
        const refreshUrl = (sampleForm && sampleForm.getAttribute('action')) || (sampleForm && sampleForm.action) || '/cart-action';
        const res = await fetch(refreshUrl, {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.success) return;

        const serverItems = data.items || {};
        updateGlobalCartBadges(data.count);

        const self = this;
        document.querySelectorAll('[data-cart-add]').forEach(function (form) {
          const pId = parseInt(form.dataset.productId, 10);
          if (!pId) return;
          const sQty = serverItems[pId] || 0;
          const st = self.getState(pId);
          st.confirmedQty = sQty;
          st.targetQty = sQty;
          self.syncCardUI(pId, sQty);
        });
      } catch (e) {}
    },
  };

  // Event delegation for clicks on stepper and add buttons
  document.addEventListener('click', function (event) {
    const actionBtn = event.target.closest('[data-action]');
    if (!actionBtn) return;
    const form = actionBtn.closest('[data-cart-add]');
    if (!form) return;

    event.preventDefault();
    const productId = parseInt(form.dataset.productId, 10);
    if (!productId) return;

    const action = actionBtn.dataset.action;
    if (action === 'initial-add') {
      CartCoordinator.changeQuantity(productId, 1, true);
    } else if (action === 'increase') {
      CartCoordinator.changeQuantity(productId, +1, false);
    } else if (action === 'decrease') {
      CartCoordinator.changeQuantity(productId, -1, false);
    }
  });

  // Intercept form submit fallback
  document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-cart-add]');
    if (!form) return;
    event.preventDefault();
    const productId = parseInt(form.dataset.productId, 10);
    if (productId) {
      CartCoordinator.changeQuantity(productId, +1, false);
    }
  });

  CartCoordinator.init();

  // Re-sync on page restore / back-forward navigation
  window.addEventListener('pageshow', function () {
    CartCoordinator.refreshAllFromServer();
  });
  const overlay = document.getElementById('eventOverlay'); if (!overlay) return;
  const eventKey = 'eventPopupShown'; if (!sessionStorage.getItem(eventKey)) { overlay.classList.add('open'); sessionStorage.setItem(eventKey, '1'); }
  const closePopup = () => overlay.classList.remove('open'); document.getElementById('eventCloseBtn')?.addEventListener('click', closePopup); overlay.addEventListener('click', event => { if (event.target === overlay) closePopup(); });
});