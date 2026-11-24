// assets/js/datepicker.js
// Lịch chọn ngày / chọn tháng riêng thay cho lịch mặc định của trình duyệt.
// - Input gốc (type="date" / "month") vẫn giữ nguyên giá trị yyyy-mm-dd / yyyy-mm, nên PHP và các script cũ không phải sửa.
// - Hiển thị: ngày dạng 24/11/2026, tháng dạng "Tháng 11 2026" (không còn "Tháng Mười Một").
// - Muốn giữ lịch gốc cho một ô nào đó: thêm thuộc tính data-native-date vào input.
(function () {
  'use strict';

  var WEEK = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
  var valueDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  var ICON_CAL = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg>';
  var ICON_PREV = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
  var ICON_NEXT = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';

  function pad(n) { return n < 10 ? '0' + n : String(n); }
  function iso(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
  function isoMonth(y, m) { return y + '-' + pad(m); }
  function daysIn(y, m) { return new Date(y, m, 0).getDate(); }

  function parse(v, type) {
    var re = type === 'month' ? /^(\d{4})-(\d{2})$/ : /^(\d{4})-(\d{2})-(\d{2})$/;
    var r = re.exec(v || '');
    if (!r) return null;
    var y = +r[1], m = +r[2], d = type === 'month' ? 1 : +r[3];
    if (m < 1 || m > 12 || d < 1 || d > daysIn(y, m)) return null;
    return { y: y, m: m, d: d };
  }
  function today() { var t = new Date(); return { y: t.getFullYear(), m: t.getMonth() + 1, d: t.getDate() }; }

  function label(p, type) {
    return type === 'month' ? 'Tháng ' + p.m + ' ' + p.y : pad(p.d) + '/' + pad(p.m) + '/' + p.y;
  }

  // ------------------------------------------------------------------
  // Một ô nhập đã được "thay áo"
  // ------------------------------------------------------------------
  var all = [];          // danh sách các ô đã nâng cấp
  var active = null;     // ô đang mở lịch
  var pop = null;        // phần tử lịch (dùng chung cho mọi ô)
  var state = null;      // { view, y, m, focus }

  function enhance(input) {
    if (input.__dp || input.hasAttribute('data-native-date')) return;
    var type = input.type;
    if (type !== 'date' && type !== 'month') return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dp-display' + (input.closest('.field') ? '' : ' dp-inline');
    btn.setAttribute('aria-haspopup', 'dialog');
    btn.setAttribute('aria-expanded', 'false');
    input.parentNode.insertBefore(btn, input.nextSibling);

    input.classList.add('dp-native');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');

    var rec = { input: input, btn: btn, type: type };
    input.__dp = rec;
    all.push(rec);

    // Cập nhật phần hiển thị mỗi khi script khác gán input.value = ...
    Object.defineProperty(input, 'value', {
      configurable: true,
      get: function () { return valueDesc.get.call(input); },
      set: function (v) { valueDesc.set.call(input, v); sync(rec); }
    });

    btn.addEventListener('click', function () { toggle(rec); });
    input.addEventListener('focus', function () { btn.focus(); });   // label / báo lỗi trình duyệt
    input.addEventListener('change', function () { sync(rec); });
    if (input.form) input.form.addEventListener('reset', function () { window.setTimeout(function () { sync(rec); }, 0); });

    new MutationObserver(function () { sync(rec); })
      .observe(input, { attributes: true, attributeFilter: ['min', 'max', 'disabled', 'required', 'aria-invalid'] });

    sync(rec);
  }

  function sync(rec) {
    var input = rec.input, btn = rec.btn;
    var p = parse(valueDesc.get.call(input), rec.type);
    var text = p ? label(p, rec.type) : (rec.type === 'month' ? 'Chọn tháng' : 'Chọn ngày');
    btn.innerHTML = '<span class="dp-text">' + text + '</span><span class="dp-icon">' + ICON_CAL + '</span>';
    btn.classList.toggle('is-empty', !p);
    btn.classList.toggle('is-invalid', input.getAttribute('aria-invalid') === 'true');
    btn.disabled = input.disabled;
    btn.setAttribute('aria-required', input.required ? 'true' : 'false');
    var lb = input.closest('.field') && input.closest('.field').querySelector('label');
    btn.setAttribute('aria-label', (lb ? lb.textContent.replace('*', '').trim() + ': ' : '') + text);
  }

  // ------------------------------------------------------------------
  // Giới hạn min / max (đọc lại mỗi lần mở vì có script đổi động)
  // ------------------------------------------------------------------
  function bounds(rec) {
    var min = rec.input.min || '', max = rec.input.max || '';
    return { min: min, max: max };
  }
  function dayOk(rec, y, m, d) {
    var b = bounds(rec), s = iso(y, m, d);
    return !(b.min && s < b.min.slice(0, 10)) && !(b.max && s > b.max.slice(0, 10));
  }
  function monthOk(rec, y, m) {
    var b = bounds(rec), s = isoMonth(y, m);
    return !(b.min && s < b.min.slice(0, 7)) && !(b.max && s > b.max.slice(0, 7));
  }
  function yearOk(rec, y) {
    var b = bounds(rec);
    return !(b.min && y < +b.min.slice(0, 4)) && !(b.max && y > +b.max.slice(0, 4));
  }

  // ------------------------------------------------------------------
  // Mở / đóng
  // ------------------------------------------------------------------
  function ensurePop() {
    if (pop) return;
    pop = document.createElement('div');
    pop.className = 'dp-pop';
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-modal', 'false');
    pop.hidden = true;
    document.body.appendChild(pop);

    pop.addEventListener('click', onPopClick);
    pop.addEventListener('keydown', onPopKey);

    document.addEventListener('mousedown', function (e) {
      if (!active) return;
      if (pop.contains(e.target) || active.btn.contains(e.target)) return;
      close(false);
    });
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
  }

  function toggle(rec) {
    if (active === rec) { close(true); return; }
    open(rec);
  }

  function open(rec) {
    ensurePop();
    if (active) active.btn.setAttribute('aria-expanded', 'false');
    active = rec;
    rec.btn.setAttribute('aria-expanded', 'true');

    var p = parse(valueDesc.get.call(rec.input), rec.type);
    if (!p) {                       // chưa có giá trị: mở ở tháng hiện tại, nhưng nằm trong khoảng cho phép
      p = today();
      var b = bounds(rec);
      if (b.min && iso(p.y, p.m, p.d) < b.min.slice(0, 10)) p = parse(b.min.slice(0, 10), 'date') || p;
      if (b.max && iso(p.y, p.m, p.d) > b.max.slice(0, 10)) p = parse(b.max.slice(0, 10), 'date') || p;
    }
    state = { view: rec.type === 'month' ? 'months' : 'days', y: p.y, m: p.m, focus: p.d };
    pop.hidden = false;
    pop.classList.remove('dp-in'); void pop.offsetWidth; pop.classList.add('dp-in');
    render(true);
  }

  function close(refocus) {
    if (!active) return;
    var rec = active;
    active = null;
    rec.btn.setAttribute('aria-expanded', 'false');
    pop.hidden = true;
    if (refocus) rec.btn.focus();
  }

  function place() {
    if (!active || pop.hidden) return;
    var r = active.btn.getBoundingClientRect();
    var vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
    var w = Math.min(316, vw - 16);
    pop.style.width = w + 'px';
    var h = pop.offsetHeight;
    var left = Math.max(8, Math.min(r.left, vw - w - 8));
    var top = r.bottom + 8;
    if (top + h > vh - 8 && r.top - h - 8 > 8) top = r.top - h - 8;       // không đủ chỗ phía dưới -> mở lên trên
    else if (top + h > vh - 8) top = Math.max(8, vh - h - 8);
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
  }

  // ------------------------------------------------------------------
  // Vẽ lịch
  // ------------------------------------------------------------------
  function render(focusIt) {
    var rec = active;
    if (!rec) return;
    var t = today(), sel = parse(valueDesc.get.call(rec.input), rec.type);
    var html = '', title = '', prevOk = true, nextOk = true;
    var v = state.view;

    if (v === 'days') {
      title = 'Tháng ' + state.m + ' ' + state.y;
      prevOk = monthOk(rec, state.m === 1 ? state.y - 1 : state.y, state.m === 1 ? 12 : state.m - 1);
      nextOk = monthOk(rec, state.m === 12 ? state.y + 1 : state.y, state.m === 12 ? 1 : state.m + 1);
    } else if (v === 'months') {
      title = String(state.y);
      prevOk = yearOk(rec, state.y - 1);
      nextOk = yearOk(rec, state.y + 1);
    } else {
      var start = Math.floor(state.y / 12) * 12;
      title = start + ' – ' + (start + 11);
      prevOk = yearOk(rec, start - 1);
      nextOk = yearOk(rec, start + 12);
    }

    html += '<div class="dp-head">' +
      '<button type="button" class="dp-nav" data-act="prev" aria-label="Trước"' + (prevOk ? '' : ' disabled') + '>' + ICON_PREV + '</button>' +
      '<button type="button" class="dp-title" data-act="title" aria-live="polite">' + title + '</button>' +
      '<button type="button" class="dp-nav" data-act="next" aria-label="Sau"' + (nextOk ? '' : ' disabled') + '>' + ICON_NEXT + '</button>' +
      '</div><div class="dp-body">';

    if (v === 'days') {
      html += '<div class="dp-week">' + WEEK.map(function (w, i) { return '<span' + (i === 6 ? ' class="dp-sun"' : '') + '>' + w + '</span>'; }).join('') + '</div>';
      html += '<div class="dp-grid dp-days" role="grid">';
      var offset = (new Date(state.y, state.m - 1, 1).getDay() + 6) % 7;     // tuần bắt đầu từ thứ Hai
      for (var i = 0; i < 42; i++) {
        var dt = new Date(state.y, state.m - 1, 1 - offset + i);
        var y = dt.getFullYear(), m = dt.getMonth() + 1, d = dt.getDate();
        var out = m !== state.m, ok = dayOk(rec, y, m, d);
        var cls = 'dp-day' + (out ? ' dp-out' : '') + (y === t.y && m === t.m && d === t.d ? ' dp-today' : '') +
          (sel && sel.y === y && sel.m === m && sel.d === d ? ' dp-sel' : '');
        var isFocus = !out && d === state.focus;
        html += '<button type="button" class="' + cls + '" data-iso="' + iso(y, m, d) + '"' + (ok ? '' : ' disabled') +
          ' tabindex="' + (isFocus ? 0 : -1) + '"' + (sel && sel.y === y && sel.m === m && sel.d === d ? ' aria-selected="true"' : '') + '>' + d + '</button>';
      }
      html += '</div>';
    } else if (v === 'months') {
      html += '<div class="dp-grid dp-cells">';
      for (var k = 1; k <= 12; k++) {
        var c = 'dp-cell' + (state.y === t.y && k === t.m ? ' dp-today' : '') + (sel && sel.y === state.y && sel.m === k ? ' dp-sel' : '');
        html += '<button type="button" class="' + c + '" data-month="' + k + '"' + (monthOk(rec, state.y, k) ? '' : ' disabled') + '>Tháng ' + k + '</button>';
      }
      html += '</div>';
    } else {
      var s0 = Math.floor(state.y / 12) * 12;
      html += '<div class="dp-grid dp-cells">';
      for (var j = 0; j < 12; j++) {
        var yy = s0 + j;
        var c2 = 'dp-cell' + (yy === t.y ? ' dp-today' : '') + (sel && sel.y === yy ? ' dp-sel' : '');
        html += '<button type="button" class="' + c2 + '" data-year="' + yy + '"' + (yearOk(rec, yy) ? '' : ' disabled') + '>' + yy + '</button>';
      }
      html += '</div>';
    }

    var nowOk = rec.type === 'month' ? monthOk(rec, t.y, t.m) : dayOk(rec, t.y, t.m, t.d);
    html += '</div><div class="dp-foot">' +
      (rec.input.required ? '<span></span>' : '<button type="button" class="dp-link" data-act="clear">Xóa</button>') +
      '<button type="button" class="dp-link dp-strong" data-act="today"' + (nowOk ? '' : ' disabled') + '>' + (rec.type === 'month' ? 'Tháng này' : 'Hôm nay') + '</button>' +
      '</div>';

    pop.innerHTML = html;
    pop.setAttribute('aria-label', rec.type === 'month' ? 'Chọn tháng' : 'Chọn ngày');
    place();
    if (focusIt) {
      var target = pop.querySelector('.dp-day[tabindex="0"]:not(:disabled)') || pop.querySelector('.dp-sel:not(:disabled)') || pop.querySelector('.dp-cell:not(:disabled)') || pop.querySelector('.dp-title');
      if (target) target.focus({ preventScroll: true });
    }
  }

  function commit(value) {
    var input = active.input, rec = active;
    valueDesc.set.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    sync(rec);
    close(true);
  }

  function shift(dir) {
    if (state.view === 'days') {
      state.m += dir;
      if (state.m < 1) { state.m = 12; state.y--; }
      if (state.m > 12) { state.m = 1; state.y++; }
      state.focus = Math.min(state.focus, daysIn(state.y, state.m));
    } else if (state.view === 'months') {
      state.y += dir;
    } else {
      state.y += dir * 12;
    }
    render(false);
  }

  function onPopClick(e) {
    var b = e.target.closest('button');
    if (!b || b.disabled || !active) return;
    var act = b.getAttribute('data-act');
    if (act === 'prev') return shift(-1);
    if (act === 'next') return shift(1);
    if (act === 'title') {
      state.view = state.view === 'days' ? 'months' : 'years';
      return render(true);
    }
    if (act === 'clear') return commit('');
    if (act === 'today') { var t = today(); return commit(active.type === 'month' ? isoMonth(t.y, t.m) : iso(t.y, t.m, t.d)); }
    if (b.hasAttribute('data-iso')) return commit(b.getAttribute('data-iso'));
    if (b.hasAttribute('data-month')) {
      state.m = +b.getAttribute('data-month');
      if (active.type === 'month') return commit(isoMonth(state.y, state.m));
      state.view = 'days';
      state.focus = Math.min(state.focus, daysIn(state.y, state.m));
      return render(true);
    }
    if (b.hasAttribute('data-year')) {
      state.y = +b.getAttribute('data-year');
      state.view = 'months';
      return render(true);
    }
  }

  // Bàn phím: Esc đóng, mũi tên di chuyển ngày, PageUp/PageDown đổi tháng.
  function onPopKey(e) {
    if (!active) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); return; }
    if (state.view !== 'days' || !e.target.classList.contains('dp-day')) return;
    var cur = parse(e.target.getAttribute('data-iso'), 'date');
    if (!cur) return;
    var delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
    if (delta === undefined && e.key !== 'PageUp' && e.key !== 'PageDown' && e.key !== 'Home' && e.key !== 'End') return;
    e.preventDefault();
    var dt = new Date(cur.y, cur.m - 1, cur.d);
    if (delta !== undefined) dt.setDate(dt.getDate() + delta);
    else if (e.key === 'PageUp') dt.setMonth(dt.getMonth() - 1);
    else if (e.key === 'PageDown') dt.setMonth(dt.getMonth() + 1);
    else if (e.key === 'Home') dt.setDate(1);
    else dt = new Date(cur.y, cur.m, 0);
    state.y = dt.getFullYear(); state.m = dt.getMonth() + 1; state.focus = dt.getDate();
    render(true);
  }

  // ------------------------------------------------------------------
  // Khởi động
  // ------------------------------------------------------------------
  function init() {
    document.querySelectorAll('input[type="date"], input[type="month"]').forEach(enhance);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.AppDatePicker = { enhance: enhance, refresh: init };
})();
