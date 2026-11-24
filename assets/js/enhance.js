// assets/js/enhance.js
// Nâng cấp giao diện: đổi sáng/tối, toast thay alert, hộp xác nhận đẹp, kiểm tra form từng ô.
// Nạp SAU app.js, không cần sửa app.js.
(function () {
  'use strict';

  // ---------- Lưu trữ an toàn ----------
  function store(key, val) {
    try {
      if (val === undefined) return window.localStorage.getItem(key);
      if (val === null) window.localStorage.removeItem(key); else window.localStorage.setItem(key, val);
    } catch (e) { return null; }
    return null;
  }

  // =========================================================
  // 1) DARK MODE
  // =========================================================
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function currentTheme() {
    var saved = store('theme');
    if (saved === 'dark' || saved === 'light') return saved;
    return media && media.matches ? 'dark' : 'light';
  }
  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    var btn = document.querySelector('[data-theme-toggle]');
    if (btn) {
      btn.textContent = theme === 'dark' ? '☀' : '☾';
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      btn.setAttribute('aria-label', theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
      btn.title = btn.getAttribute('aria-label');
    }
  }
  function mountThemeToggle() {
    var meta = document.querySelector('.topbar-meta') || document.querySelector('.topbar');
    if (!meta || document.querySelector('[data-theme-toggle]')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.setAttribute('data-theme-toggle', '');
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      store('theme', next);
      applyTheme(next);
    });
    meta.insertBefore(btn, meta.firstChild);
  }
  mountThemeToggle();
  applyTheme(currentTheme());
  if (media && media.addEventListener) {
    media.addEventListener('change', function () {
      if (!store('theme')) applyTheme(currentTheme());
    });
  }

  // =========================================================
  // 2) TOAST
  // =========================================================
  var region = null;
  var ICONS = { success: '✓', error: '✕', warn: '!', info: 'i' };

  function getRegion() {
    if (region && document.body.contains(region)) return region;
    region = document.createElement('div');
    region.id = 'toast-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);
    return region;
  }

  function toast(message, type, ms) {
    type = ICONS[type] ? type : 'info';
    ms = ms || (type === 'error' ? 6500 : 4500);
    var host = getRegion();

    // Giữ tối đa 4 toast cùng lúc.
    while (host.children.length >= 4) host.removeChild(host.firstChild);

    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.setAttribute('role', type === 'error' || type === 'warn' ? 'alert' : 'status');
    el.style.setProperty('--toast-ms', ms + 'ms');

    var icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = ICONS[type];

    var msg = document.createElement('div');
    msg.className = 'toast-msg';
    msg.textContent = String(message);          // textContent: không chèn HTML, tránh XSS

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Đóng thông báo');
    close.textContent = '×';

    var bar = document.createElement('span');
    bar.className = 'toast-bar';

    el.appendChild(icon); el.appendChild(msg); el.appendChild(close); el.appendChild(bar);
    host.appendChild(el);

    var timer = null;
    function dismiss() {
      if (!el.parentNode) return;
      window.clearTimeout(timer);
      el.classList.add('is-leaving');
      window.setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 200);
    }
    function arm(delay) { window.clearTimeout(timer); timer = window.setTimeout(dismiss, delay); }

    close.addEventListener('click', dismiss);
    el.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
    el.addEventListener('mouseleave', function () { arm(1500); });
    arm(ms);
    return el;
  }

  // alert() cũ (app.js, inline script) tự chuyển thành toast, không còn chặn màn hình.
  window.__nativeAlert = window.alert;
  window.alert = function (message) {
    var text = String(message == null ? '' : message);
    toast(text, /không|phải|vui lòng|lỗi|tối đa|hợp lệ/i.test(text) ? 'warn' : 'info');
  };

  // Thông báo flash của PHP (header.php gắn data-flash) -> toast.
  document.querySelectorAll('.alert[data-flash]').forEach(function (a) {
    var type = ['success', 'error', 'warn', 'info'].filter(function (t) { return a.classList.contains(t); })[0] || 'info';
    toast(a.textContent.trim(), type);
    a.hidden = true;
  });

  // =========================================================
  // 3) HỘP XÁC NHẬN
  // =========================================================
  var nativeConfirm = window.confirm;

  function confirmBox(message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var lastFocus = document.activeElement;
      var danger = opts.danger != null ? opts.danger : /xóa|xoá|hủy|huỷ|trả phòng|khóa|khoá/i.test(message);

      var overlay = document.createElement('div');
      overlay.className = 'ui-modal-overlay';

      var box = document.createElement('div');
      box.className = 'ui-modal';
      box.setAttribute('role', 'alertdialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-labelledby', 'ui-modal-title');
      box.setAttribute('aria-describedby', 'ui-modal-msg');

      var h = document.createElement('h3');
      h.id = 'ui-modal-title';
      h.textContent = opts.title || 'Xác nhận thao tác';
      var p = document.createElement('p');
      p.id = 'ui-modal-msg';
      p.textContent = message;

      var actions = document.createElement('div');
      actions.className = 'ui-modal-actions';
      var no = document.createElement('button');
      no.type = 'button'; no.className = 'btn gray'; no.textContent = opts.cancelText || 'Hủy';
      var yes = document.createElement('button');
      yes.type = 'button'; yes.className = 'btn ' + (danger ? 'red' : ''); yes.textContent = opts.okText || (danger ? 'Đồng ý xóa' : 'Đồng ý');
      actions.appendChild(no); actions.appendChild(yes);

      box.appendChild(h); box.appendChild(p); box.appendChild(actions);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      var prevOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      (danger ? no : yes).focus();

      function close(result) {
        document.removeEventListener('keydown', onKey, true);
        document.body.style.overflow = prevOverflow;
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(false); }
        else if (e.key === 'Tab') {            // giữ focus trong hộp thoại
          e.preventDefault();
          (document.activeElement === yes ? no : yes).focus();
        }
      }
      document.addEventListener('keydown', onKey, true);
      no.addEventListener('click', function () { close(false); });
      yes.addEventListener('click', function () { close(true); });
      overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(false); });
    });
  }

  // Chạy fn trong lúc window.confirm() luôn trả true (để app.js không hỏi lần 2).
  function withConfirmBypass(fn) {
    window.confirm = function () { return true; };
    try { fn(); } finally { window.confirm = nativeConfirm; }
  }

  // =========================================================
  // 4) KIỂM TRA FORM TỪNG Ô (form[data-validate])
  // =========================================================
  var LABEL_FALLBACK = { identity_card: 'CCCD/CMND', phone: 'Số điện thoại', email: 'Email' };

  function labelOf(el) {
    if (el.getAttribute('data-label')) return el.getAttribute('data-label');
    var field = el.closest('.field');
    var lb = field && field.querySelector('label');
    if (lb) return lb.textContent.replace('*', '').trim();
    return LABEL_FALLBACK[el.name] || el.name || 'trường này';
  }

  // Quy tắc giữ nguyên như app.js cũ (CCCD 9-12 số, SĐT 9-11 số, số >= min), thêm max và email.
  function messageFor(el) {
    if (el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return '';
    var v = (el.value || '').trim();

    if (el.hasAttribute('required')) {
      if (el.type === 'checkbox' && !el.checked) return 'Vui lòng đánh dấu: ' + labelOf(el);
      if (el.type === 'file' && !(el.files && el.files.length)) return 'Vui lòng chọn tệp: ' + labelOf(el);
      if (el.type !== 'checkbox' && el.type !== 'file' && v === '')
        return (el.tagName === 'SELECT' ? 'Vui lòng chọn: ' : 'Vui lòng nhập: ') + labelOf(el);
    }
    if (el.name === 'identity_card' && !/^[0-9]{9,12}$/.test(v)) return 'CCCD/CMND phải gồm 9-12 chữ số';
    if (el.name === 'phone' && !/^[0-9]{9,11}$/.test(v)) return 'Số điện thoại phải gồm 9-11 chữ số';
    if (el.type === 'email' && v !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Email chưa đúng định dạng';
    if (el.type === 'number' && v !== '') {
      var n = parseFloat(v);
      if (el.min !== '' && n < parseFloat(el.min)) return labelOf(el) + ' không được nhỏ hơn ' + el.min;
      if (el.max !== '' && n > parseFloat(el.max)) return labelOf(el) + ' không được lớn hơn ' + el.max;
    }
    return '';
  }

  function showError(el, msg) {
    var field = el.closest('.field') || el.parentNode;
    var box = field.querySelector('.field-error');
    if (!msg) {
      field.classList.remove('has-error');
      el.removeAttribute('aria-invalid');
      if (box) box.parentNode.removeChild(box);
      return;
    }
    field.classList.add('has-error');
    el.setAttribute('aria-invalid', 'true');
    if (!box) {
      box = document.createElement('div');
      box.className = 'field-error';
      box.setAttribute('role', 'alert');
      field.appendChild(box);
    }
    box.textContent = msg;
  }

  function controls(form) {
    return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.setAttribute('novalidate', '');   // dùng giao diện lỗi của mình thay cho bong bóng trình duyệt
    form.addEventListener('focusout', function (e) {
      if (controls(form).indexOf(e.target) < 0 || e.target.type === 'file') return;
      showError(e.target, messageFor(e.target));
    });
    form.addEventListener('input', function (e) {
      if (e.target.closest && e.target.closest('.field.has-error')) showError(e.target, messageFor(e.target));
    });
    form.addEventListener('change', function (e) {
      if (e.target.closest && e.target.closest('.field.has-error')) showError(e.target, messageFor(e.target));
    });
  });

  // Chạy ở pha capture để chặn app.js khi form chưa hợp lệ.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-validate]')) return;

    var firstBad = null, count = 0;
    controls(form).forEach(function (el) {
      var msg = messageFor(el);
      showError(el, msg);
      if (msg) { count++; if (!firstBad) firstBad = el; }
    });
    if (!firstBad) return;

    e.preventDefault();
    e.stopImmediatePropagation();
    toast(count === 1 ? 'Còn 1 ô chưa hợp lệ, vui lòng kiểm tra lại.' : 'Còn ' + count + ' ô chưa hợp lệ, vui lòng kiểm tra lại.', 'error');
    firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try { firstBad.focus({ preventScroll: true }); } catch (err) { firstBad.focus(); }
  }, true);

  // =========================================================
  // 5) XÁC NHẬN BẰNG HỘP THOẠI ĐẸP (form[data-confirm] + nút xóa ảnh)
  // =========================================================
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-confirm]') || form.__uiConfirmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    var submitter = e.submitter || null;
    confirmBox(form.getAttribute('data-confirm')).then(function (ok) {
      if (!ok) return;
      form.__uiConfirmed = true;
      withConfirmBypass(function () {
        if (form.requestSubmit) form.requestSubmit(submitter); else form.submit();
      });
      form.__uiConfirmed = false;
    });
  }, true);

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-delete-room-image]');
    if (!btn || btn.__uiConfirmed || btn.disabled) return;
    e.preventDefault();
    e.stopPropagation();
    confirmBox('Bạn chắc chắn muốn xóa ảnh này ngay khỏi thư viện phòng?', { title: 'Xóa ảnh phòng', danger: true })
      .then(function (ok) {
        if (!ok) return;
        btn.__uiConfirmed = true;
        withConfirmBypass(function () { btn.click(); });
        btn.__uiConfirmed = false;
      });
  }, true);

  // =========================================================
  // 6) CHỐNG BẤM GỬI NHIỀU LẦN
  // =========================================================
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || !form.matches || !form.matches('form[method="post" i], form[method="POST"]')) return;
    if (form.target || form.hasAttribute('data-no-loading')) return;
    var btn = e.submitter || form.querySelector('button:not([type]), button[type="submit"]');
    if (!btn) return;
    window.setTimeout(function () {         // trễ 1 nhịp để trình duyệt kịp lấy name/value của nút
      btn.classList.add('is-loading');
      btn.disabled = true;
    }, 0);
  });
  window.addEventListener('pageshow', function (e) {   // quay lại bằng nút Back: trả nút về bình thường
    if (!e.persisted) return;
    document.querySelectorAll('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.disabled = false; });
  });

  // Cho phép dùng ở nơi khác: AppUI.toast('Đã lưu', 'success'); AppUI.confirm('Chắc chắn?').then(...)
  window.AppUI = { toast: toast, confirm: confirmBox };
})();
