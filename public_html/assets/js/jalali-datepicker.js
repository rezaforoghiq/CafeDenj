/**
 * assets/js/jalali-datepicker.js
 * -----------------------------------------------------------------------
 * انتخاب‌گر تاریخ شمسی (جلالی) — کاملاً محلی و مستقل، بدون هیچ سرویس،
 * API یا CDN خارجی. تبدیل تاریخ بر پایهٔ فرمول‌های ریاضی استاندارد تقویم
 * جلالی محاسبه می‌شود (بدون فراخوانی شبکه) و آفلاین هم به‌درستی کار می‌کند.
 *
 * نحوهٔ استفاده: به‌جای <input type="date">، یک ورودی متنی با ویژگی‌های
 * data-jalali-picker و data-name="نام‌فیلد" و data-value="مقدار میلادی
 * YYYY-MM-DD" قرار می‌گیرد. مقدار واقعی (میلادی) که به سرور ارسال می‌شود
 * در یک input مخفی با همان name نگه داشته می‌شود — یعنی هیچ تغییری در
 * سمت سرور (اعتبارسنجی/ذخیره‌سازی) لازم نیست.
 * -----------------------------------------------------------------------
 */
(function () {
  'use strict';

  // ---------------- تبدیل میلادی <-> جلالی (بدون وابستگی خارجی) ----------------
  function trunc(x) { return x < 0 ? Math.ceil(x) : Math.floor(x); }
  function div(a, b) { return trunc(a / b); }
  function mod(a, b) { return a - div(a, b) * b; }

  var BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

  function jalCal(jy) {
    var bl = BREAKS.length, gy = jy + 621, leapJ = -14, jp = BREAKS[0], jm, jump = 0, n, i;
    for (i = 1; i < bl; i += 1) {
      jm = BREAKS[i];
      jump = jm - jp;
      if (jy < jm) { break; }
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) { leapJ += 1; }
    var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    var march = 20 + leapJ - leapG;
    if (jump - n < 6) { n = n - jump + div(jump + 4, 33) * 33; }
    var leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) { leap = 4; }
    return { leap: leap, gy: gy, march: march };
  }

  function g2d(gy, gm, gd) {
    var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    return d;
  }

  function d2g(jdn) {
    var j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    var i = div(mod(j, 1461), 4) * 5 + 308;
    var gd = div(mod(i, 153), 5) + 1;
    var gm = mod(div(i, 153), 12) + 1;
    var gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return [gy, gm, gd];
  }

  function j2d(jy, jm, jd) {
    var r = jalCal(jy);
    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
  }

  function d2j(jdn) {
    var gy = d2g(jdn)[0], jy = gy - 621, r = jalCal(jy), jdn1f = g2d(gy, 3, r.march), k = jdn - jdn1f, jm, jd;
    if (k >= 0) {
      if (k <= 185) { return [jy, 1 + div(k, 31), mod(k, 31) + 1]; }
      k -= 186;
    } else {
      jy -= 1;
      k += 179;
      if (r.leap === 1) { k += 1; }
    }
    jm = 7 + div(k, 30);
    jd = mod(k, 30) + 1;
    return [jy, jm, jd];
  }

  function gregorianToJalali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
  function jalaliToGregorian(jy, jm, jd) { return d2g(j2d(jy, jm, jd)); }

  function daysInJalaliMonth(jy, jm) {
    var a = j2d(jy, jm, 1);
    var b = (jm === 12) ? j2d(jy + 1, 1, 1) : j2d(jy, jm + 1, 1);
    return b - a;
  }

  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  var FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  function faDigits(s) {
    return String(s).replace(/[0-9]/g, function (d) { return FA_DIGITS[+d]; });
  }
  var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  // ---------------- ابزارهای تبدیل رشته <-> اجزای تاریخ ----------------
  function parseGregorian(value) {
    if (!value) { return null; }
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    if (!m) { return null; }
    return [parseInt(m[1], 10), parseInt(m[2], 10), parseInt(m[3], 10)];
  }

  function formatGregorian(gy, gm, gd) {
    return gy + '-' + pad2(gm) + '-' + pad2(gd);
  }

  function todayJalali() {
    var now = new Date();
    return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
  }

  // ---------------- پاپ‌آپ تقویم مشترک ----------------
  var popup = null;
  var activeField = null;
  var viewJy, viewJm;

  function ensurePopup() {
    if (popup) { return popup; }
    popup = document.createElement('div');
    popup.className = 'jalali-dp-popup';
    popup.setAttribute('role', 'dialog');
    document.body.appendChild(popup);
    document.addEventListener('mousedown', function (e) {
      if (popup.style.display === 'block' && !popup.contains(e.target) && (!activeField || e.target !== activeField.display)) {
        closePopup();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closePopup(); }
    });
    window.addEventListener('resize', function () { if (popup.style.display === 'block') { positionPopup(); } });
    return popup;
  }

  function closePopup() {
    if (popup) { popup.style.display = 'none'; }
    activeField = null;
  }

  function positionPopup() {
    if (!activeField) { return; }
    var rect = activeField.display.getBoundingClientRect();
    popup.style.top = (window.scrollY + rect.bottom + 6) + 'px';
    popup.style.left = (window.scrollX + rect.left) + 'px';
  }

  function renderPopup() {
    var daysCount = daysInJalaliMonth(viewJy, viewJm);
    var firstDow = mod(j2d(viewJy, viewJm, 1) + 2, 7); // ۰=شنبه ... با مطابقت هفتهٔ ایرانی
    var html = '';
    html += '<div class="jalali-dp-head">';
    html += '<button type="button" class="jalali-dp-nav" data-act="py">&#187;</button>';
    html += '<button type="button" class="jalali-dp-nav" data-act="pm">&#8250;</button>';
    html += '<span class="jalali-dp-title">' + MONTHS[viewJm - 1] + ' ' + faDigits(viewJy) + '</span>';
    html += '<button type="button" class="jalali-dp-nav" data-act="nm">&#8249;</button>';
    html += '<button type="button" class="jalali-dp-nav" data-act="ny">&#171;</button>';
    html += '</div>';
    html += '<div class="jalali-dp-grid jalali-dp-weekdays">';
    for (var w = 0; w < 7; w++) { html += '<span>' + WEEKDAYS[w] + '</span>'; }
    html += '</div>';
    html += '<div class="jalali-dp-grid jalali-dp-days">';
    for (var e = 0; e < firstDow; e++) { html += '<span></span>'; }
    var selected = activeField ? activeField.getSelectedJalali() : null;
    for (var d = 1; d <= daysCount; d++) {
      var isSel = selected && selected[0] === viewJy && selected[1] === viewJm && selected[2] === d;
      html += '<button type="button" class="jalali-dp-day' + (isSel ? ' is-selected' : '') + '" data-day="' + d + '">' + faDigits(d) + '</button>';
    }
    html += '</div>';
    html += '<div class="jalali-dp-footer">';
    html += '<button type="button" class="jalali-dp-link" data-act="today">امروز</button>';
    html += '<button type="button" class="jalali-dp-link" data-act="clear">پاک کردن</button>';
    html += '</div>';
    popup.innerHTML = html;
  }

  function shiftMonth(delta) {
    viewJm += delta;
    while (viewJm > 12) { viewJm -= 12; viewJy += 1; }
    while (viewJm < 1) { viewJm += 12; viewJy -= 1; }
    renderPopup();
  }

  function onPopupClick(e) {
    var dayBtn = e.target.closest('.jalali-dp-day');
    if (dayBtn) {
      var d = parseInt(dayBtn.getAttribute('data-day'), 10);
      var g = jalaliToGregorian(viewJy, viewJm, d);
      activeField.setGregorian(g[0], g[1], g[2]);
      closePopup();
      return;
    }
    var actBtn = e.target.closest('[data-act]');
    if (!actBtn) { return; }
    var act = actBtn.getAttribute('data-act');
    if (act === 'pm') { shiftMonth(-1); }
    else if (act === 'nm') { shiftMonth(1); }
    else if (act === 'py') { viewJy -= 1; renderPopup(); }
    else if (act === 'ny') { viewJy += 1; renderPopup(); }
    else if (act === 'today') {
      var now = new Date();
      var gj = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
      viewJy = gj[0]; viewJm = gj[1];
      activeField.setGregorian(now.getFullYear(), now.getMonth() + 1, now.getDate());
      closePopup();
    } else if (act === 'clear') {
      activeField.setGregorian(null);
      closePopup();
    }
  }

  function openPopup(field) {
    ensurePopup();
    if (activeField === field && popup.style.display === 'block') { closePopup(); return; }
    activeField = field;
    var sel = field.getSelectedJalali() || todayJalali();
    viewJy = sel[0]; viewJm = sel[1];
    renderPopup();
    popup.style.display = 'block';
    positionPopup();
  }

  // ---------------- اتصال به فیلدهای صفحه ----------------
  function makeField(input) {
    var name = input.getAttribute('data-name') || input.name || '';
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = name;
    hidden.value = input.getAttribute('data-value') || '';
    input.removeAttribute('name');
    input.parentNode.insertBefore(hidden, input.nextSibling);

    var field = {
      display: input,
      hidden: hidden,
      getSelectedJalali: function () {
        var g = parseGregorian(hidden.value);
        return g ? gregorianToJalali(g[0], g[1], g[2]) : null;
      },
      setGregorian: function (gy, gm, gd) {
        if (gy === null) {
          hidden.value = '';
          input.value = '';
        } else {
          hidden.value = formatGregorian(gy, gm, gd);
          var j = gregorianToJalali(gy, gm, gd);
          input.value = faDigits(j[0] + '/' + pad2(j[1]) + '/' + pad2(j[2]));
        }
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    var initial = field.getSelectedJalali();
    input.value = initial ? faDigits(initial[0] + '/' + pad2(initial[1]) + '/' + pad2(initial[2])) : '';
    input.readOnly = true;
    input.autocomplete = 'off';
    input.classList.add('jalali-date-input');
    input.addEventListener('click', function () { openPopup(field); });
    input.addEventListener('focus', function () { openPopup(field); });
  }

  function init() {
    ensurePopup().addEventListener('mousedown', function (e) { e.stopPropagation(); });
    ensurePopup().addEventListener('click', onPopupClick);
    var inputs = document.querySelectorAll('[data-jalali-picker]');
    for (var i = 0; i < inputs.length; i++) { makeField(inputs[i]); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
