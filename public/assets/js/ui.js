/**
 * Shared UI behavior for every page (docs/CHANGE_PLAN_UI.md):
 *  - text-size control (A / A+ / A++), remembered in localStorage;
 *  - show/hide password ("eye") button on every password field — one function, skPasswordToggle();
 *  - live new-password checklist + "Passwords match" (the rule itself is checked again on the server);
 *  - sign-in pop-up when the email and/or password is empty;
 *  - wide tables scroll sideways inside their card instead of widening the page.
 */
(function () {
  'use strict';

  /* ---------- Text size ---------- */
  var SIZES = ['100', '115', '130'];
  function applyTextSize(size) {
    if (SIZES.indexOf(size) === -1) size = '100';
    if (size === '100') document.documentElement.removeAttribute('data-text-size');
    else document.documentElement.setAttribute('data-text-size', size);
    document.querySelectorAll('[data-text-size-btn]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-text-size-btn') === size ? 'true' : 'false');
    });
  }
  function currentTextSize() { return document.documentElement.getAttribute('data-text-size') || '100'; }
  document.querySelectorAll('[data-text-size-btn]').forEach(function (b) {
    b.addEventListener('click', function () {
      var size = b.getAttribute('data-text-size-btn');
      applyTextSize(size);
      try { localStorage.setItem('sk_text_size', size); } catch (e) { /* storage blocked: the choice lasts for this page only */ }
    });
  });
  applyTextSize(currentTextSize());

  /* ---------- Show / hide password ---------- */
  /** Adds the eye button to one password input. Toggling keeps the value, the caret and the selection. */
  function skPasswordToggle(input) {
    if (input.dataset.pwToggleReady) return;
    input.dataset.pwToggleReady = '1';
    var wrap = document.createElement('span');
    wrap.className = 'pw-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', 'Show password');
    if (input.id) btn.setAttribute('aria-controls', input.id);
    btn.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    wrap.appendChild(btn);
    btn.addEventListener('click', function () {
      var start = input.selectionStart, end = input.selectionEnd;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.firstChild.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
      try { if (start !== null) input.setSelectionRange(start, end); } catch (e) { /* some input types refuse selection APIs */ }
    });
  }
  window.skPasswordToggle = skPasswordToggle;
  document.querySelectorAll('input[type="password"]').forEach(skPasswordToggle);

  /* ---------- Live password checklist ---------- */
  function setItem(li, ok) {
    if (!li) return;
    li.classList.toggle('ok', ok);
    var state = li.querySelector('[data-state]');
    var text = ok ? ' — done' : ' — not yet';
    if (state && state.textContent !== text) state.textContent = text;
  }
  document.querySelectorAll('input[data-pw-rules]').forEach(function (input) {
    var min = parseInt(input.getAttribute('data-pw-min') || '8', 10);
    var special = input.getAttribute('data-pw-special') || '';
    var rules = document.getElementById(input.id + '-rules');
    var confirm = input.form ? input.form.querySelector('[data-pw-confirm-for="' + input.id + '"]') : null;
    var match = confirm ? document.getElementById(confirm.id + '-match') : null;
    function update() {
      var chars = Array.from(input.value);
      if (rules) {
        setItem(rules.querySelector('[data-rule="length"]'), chars.length >= min);
        setItem(rules.querySelector('[data-rule="special"]'), chars.some(function (c) { return special.indexOf(c) !== -1; }));
      }
      if (match) setItem(match.querySelector('[data-rule="match"]'), confirm.value !== '' && confirm.value === input.value);
    }
    input.addEventListener('input', update);
    if (confirm) confirm.addEventListener('input', update);
    update();
  });

  /* ---------- Sign-in: pop-up when a field is empty ---------- */
  function showFieldError(input, message) {
    var box = document.getElementById(input.id + '-error');
    input.classList.toggle('is-invalid', !!message);
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (box) { box.textContent = message || ''; box.hidden = !message; }
  }
  document.querySelectorAll('form[data-login-form]').forEach(function (form) {
    var email = form.querySelector('[name="email"]');
    var password = form.querySelector('[name="password"]');
    var modalEl = document.getElementById('loginMissingModal');
    var firstEmpty = null;
    if (modalEl) {
      modalEl.addEventListener('shown.bs.modal', function () {
        modalEl.setAttribute('role', 'alertdialog'); // Bootstrap resets it to "dialog" while opening
        var ok = modalEl.querySelector('.modal-footer .btn'); if (ok) ok.focus();
      });
      modalEl.addEventListener('hidden.bs.modal', function () { if (firstEmpty) firstEmpty.focus(); });
    }
    [email, password].forEach(function (input) {
      input.addEventListener('input', function () { if (input.value.trim() !== '') showFieldError(input, ''); });
    });
    form.addEventListener('submit', function (e) {
      var noEmail = email.value.trim() === '', noPassword = password.value === '';
      if (!noEmail && !noPassword) return;
      e.preventDefault();
      showFieldError(email, noEmail ? 'Enter your email address.' : '');
      showFieldError(password, noPassword ? 'Enter your password.' : '');
      var parts = [];
      if (noEmail) parts.push('email address');
      if (noPassword) parts.push('password');
      firstEmpty = noEmail ? email : password;
      var text = 'Please enter your ' + parts.join(' and ') + '.';
      if (modalEl && window.bootstrap) {
        document.getElementById('loginMissingText').textContent = text;
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      } else {
        firstEmpty.focus(); // no Bootstrap JS: the inline messages are still shown
      }
    });
  });

  /* ---------- Wide tables scroll inside their card ---------- */
  document.querySelectorAll('table.table').forEach(function (table) {
    if (table.closest('.table-responsive')) return;
    var wrap = document.createElement('div');
    wrap.className = 'table-responsive';
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
})();
