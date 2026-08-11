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
  themeSwitch?.addEventListener('click', function () { const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light'; root.dataset.theme = nextTheme; try { localStorage.setItem('denj-theme', nextTheme); } catch (e) {} updateThemeControl(nextTheme); });
  const searchInput = document.getElementById('searchInput');
  const catButtons = document.querySelectorAll('.cat-btn');
  const cards = document.querySelectorAll('.item-card');
  const emptyState = document.getElementById('emptyState');
  let activeCat = 'all';
  function applyFilters() { const term = searchInput?.value.trim().toLowerCase() || ''; let visibleCount = 0; cards.forEach(card => { const visible = (activeCat === 'all' || card.dataset.cat === activeCat) && card.dataset.name.toLowerCase().includes(term); card.classList.toggle('hidden', !visible); if (visible) visibleCount++; }); if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none'; }
  catButtons.forEach(button => button.addEventListener('click', function () { catButtons.forEach(item => item.classList.remove('active')); button.classList.add('active'); activeCat = button.dataset.cat || 'all'; applyFilters(); }));
  searchInput?.addEventListener('input', applyFilters);
  document.querySelectorAll('[data-cart-add]').forEach(form => form.addEventListener('submit', async event => { event.preventDefault(); const button = form.querySelector('button'); button.disabled = true; try { const response = await fetch(form.action, { method: 'POST', body: new FormData(form) }); const data = await response.json(); if (!data.success) throw new Error(data.message || 'خطا در افزودن محصول'); document.querySelectorAll('[data-cart-count]').forEach(badge => { badge.textContent = data.count ? Number(data.count).toLocaleString('fa-IR') : ''; badge.classList.toggle('is-empty', !data.count); }); button.textContent = '✓ به سبد اضافه شد'; button.classList.add('added'); let toast = document.querySelector('.cart-toast'); if (!toast) { toast = document.createElement('div'); toast.className = 'cart-toast'; document.body.append(toast); } toast.textContent = 'محصول به سبد خرید اضافه شد · ' + data.count + ' محصول در سبد'; requestAnimationFrame(() => toast.classList.add('show')); setTimeout(() => { toast.classList.remove('show'); button.textContent = 'افزودن به سبد'; button.classList.remove('added'); }, 1800); } catch (error) { alert(error.message); } finally { button.disabled = false; } }));
  const overlay = document.getElementById('eventOverlay'); if (!overlay) return;
  const eventKey = 'eventPopupShown'; if (!sessionStorage.getItem(eventKey)) { overlay.classList.add('open'); sessionStorage.setItem(eventKey, '1'); }
  const closePopup = () => overlay.classList.remove('open'); document.getElementById('eventCloseBtn')?.addEventListener('click', closePopup); overlay.addEventListener('click', event => { if (event.target === overlay) closePopup(); });
});
