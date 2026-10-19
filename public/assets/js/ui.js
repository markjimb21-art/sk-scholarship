/**
 * Shared UI behavior for every page (docs/CHANGE_PLAN_UI.md):
 *  - text-size control (A / A+ / A++), remembered in localStorage;
 *  - show/hide password ("eye") button on every password field — one function, skPasswordToggle();
 *  - live new-password checklist + "Passwords match" (the rule itself is checked again on the server);
 *  - sign-in pop-up when the email and/or password is empty;
 *  - data-confirm forms ask in a styled dialog; data-no-submit forms and data-autosubmit selects;
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

  /* ---------- Charts use the site font (the --font-ui stack in style.css), never below 15px ----------
     Pages draw their charts after this runs. */
  if (window.Chart && window.Chart.defaults) {
    window.Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--font-ui').trim() || 'system-ui, sans-serif';
    window.Chart.defaults.font.size = 15;
    window.Chart.defaults.color = '#454E5F';
  }

  /* ---------- Every visible label is tied to its field ----------
     Many forms write <label class="form-label">Name</label><input …> without for/id. Link each label to the first
     field after it in the same container, so screen readers announce it and clicking the label focuses the field. */
  var autoId = 0;
  document.querySelectorAll('label.form-label:not([for])').forEach(function (label) {
    var field = null, el = label.nextElementSibling;
    while (el && !field) {
      field = el.matches('input:not([type=hidden]), select, textarea') ? el : el.querySelector('input:not([type=hidden]), select, textarea');
      el = el.nextElementSibling;
    }
    if (!field || (field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]'))) return;
    if (!field.id) field.id = 'sk-field-' + (++autoId);
    label.setAttribute('for', field.id);
  });

  /* ---------- Icons are decorative: the words next to them (or the control's aria-label) carry the meaning ---------- */
  document.querySelectorAll('i.bi:not([aria-hidden])').forEach(function (i) { i.setAttribute('aria-hidden', 'true'); });

  /* ---------- Confirmation dialog (replaces the browser's "localhost says" box) ----------
     data-confirm="What happens" on a <form> asks first; Cancel / Esc / clicking outside stops the submit. Optional:
       data-confirm-title   short question, e.g. "Approve this applicant?"   (default "Are you sure?")
       data-confirm-subject who or what it acts on, shown in bold, e.g. the applicant's name
       data-confirm-ok      label of the action button                       (default: the form's submit button text)
       data-confirm-tone    success | danger | warning | primary              (default: from the submit button's colour)
     Required fields are checked by the browser before this opens. For danger the focus starts on Cancel. */
  var TONES = {
    success: { icon: 'bi-check-circle-fill', btn: 'btn-success' },
    danger:  { icon: 'bi-exclamation-octagon-fill', btn: 'btn-danger' },
    warning: { icon: 'bi-exclamation-triangle-fill', btn: 'btn-warning' },
    primary: { icon: 'bi-question-circle-fill', btn: 'btn-primary' }
  };
  var confirmEl = null, confirmModal = null, pending = null;

  function buildConfirm() {
    confirmEl = document.createElement('div');
    confirmEl.className = 'modal fade sk-confirm';
    confirmEl.id = 'skConfirm';
    confirmEl.tabIndex = -1;
    confirmEl.setAttribute('role', 'alertdialog');
    confirmEl.setAttribute('aria-modal', 'true');
    confirmEl.setAttribute('aria-labelledby', 'skConfirmTitle');
    confirmEl.setAttribute('aria-describedby', 'skConfirmText');
    confirmEl.innerHTML =
      '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
        '<div class="modal-body text-center px-4 pt-4 pb-3">' +
          '<div class="sk-confirm-icon" aria-hidden="true"><i class="bi"></i></div>' +
          '<h2 class="h4 fw-bold mb-2" id="skConfirmTitle"></h2>' +
          '<div class="sk-confirm-subject mb-2" id="skConfirmSubject"></div>' +
          '<p class="mb-0" id="skConfirmText"></p>' +
        '</div>' +
        '<div class="modal-footer justify-content-center gap-2 border-0 pt-0 pb-4">' +
          '<button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" data-sk-cancel>Cancel</button>' +
          '<button type="button" class="btn px-4" data-sk-ok></button>' +
        '</div>' +
      '</div></div>';
    document.body.appendChild(confirmEl);
    confirmModal = window.bootstrap.Modal.getOrCreateInstance(confirmEl);
    confirmEl.querySelector('[data-sk-ok]').addEventListener('click', function () {
      if (!pending) return;
      var p = pending; pending = null;
      var ok = this; ok.disabled = true; ok.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Working…';
      p.form.dataset.skConfirmed = '1';
      if (typeof p.form.requestSubmit === 'function') p.form.requestSubmit(p.submitter && p.submitter.form === p.form ? p.submitter : undefined);
      else p.form.submit();
    });
    confirmEl.addEventListener('shown.bs.modal', function () {
      confirmEl.setAttribute('role', 'alertdialog'); // Bootstrap resets it to "dialog" while opening
      var tone = confirmEl.dataset.tone;
      confirmEl.querySelector(tone === 'danger' ? '[data-sk-cancel]' : '[data-sk-ok]').focus();
    });
    // Back button after OK: the browser may restore this page with the dialog still showing "Working…".
    window.addEventListener('pageshow', function (ev) { if (ev.persisted) { pending = null; confirmModal.hide(); } });
    confirmEl.addEventListener('hidden.bs.modal', function () {
      // Cancelled (pending still set): give focus back to the button that asked.
      if (pending && pending.submitter && document.contains(pending.submitter)) pending.submitter.focus();
      pending = null;
    });
  }

  function toneOf(form, submitter) {
    var t = form.getAttribute('data-confirm-tone');
    if (TONES[t]) return t;
    var cls = submitter ? submitter.className : '';
    if (/btn-(outline-)?danger/.test(cls)) return 'danger';
    if (/btn-(outline-)?warning/.test(cls)) return 'warning';
    if (/btn-(outline-)?success/.test(cls)) return 'success';
    return 'primary';
  }

  function askConfirm(form, submitter) {
    if (!confirmEl) buildConfirm();
    var tone = toneOf(form, submitter);
    var label = form.getAttribute('data-confirm-ok') ||
      (submitter && (submitter.textContent.trim() || submitter.getAttribute('aria-label'))) || 'Continue';
    var subject = form.getAttribute('data-confirm-subject') || '';
    confirmEl.dataset.tone = tone;
    confirmEl.querySelector('.sk-confirm-icon').className = 'sk-confirm-icon sk-confirm-' + tone;
    confirmEl.querySelector('.sk-confirm-icon i').className = 'bi ' + TONES[tone].icon;
    confirmEl.querySelector('#skConfirmTitle').textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
    var subj = confirmEl.querySelector('#skConfirmSubject');
    subj.textContent = subject; subj.hidden = subject === '';
    confirmEl.querySelector('#skConfirmText').textContent = form.getAttribute('data-confirm');
    var ok = confirmEl.querySelector('[data-sk-ok]');
    ok.disabled = false;
    ok.className = 'btn px-4 ' + TONES[tone].btn;
    ok.textContent = label;
    pending = { form: form, submitter: submitter };
    confirmModal.show();
  }

  /* ---------- Forms: confirm first, never submit, submit on change ----------
     data-confirm on a <form>: the dialog above first.
     data-no-submit on a <form>: Enter never submits it (the application wizard saves its fields by itself).
     data-autosubmit on a <select>: picking an option submits its form (list filters). */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.hasAttribute('data-no-submit')) { e.preventDefault(); return; }
    if (!form.hasAttribute('data-confirm')) return;
    if (form.dataset.skConfirmed === '1') { delete form.dataset.skConfirmed; return; }   // the dialog's OK sent it
    e.preventDefault();
    var submitter = e.submitter || form.querySelector('button:not([type="button"]), input[type="submit"]');
    if (!window.bootstrap || !window.bootstrap.Modal) {   // Bootstrap didn't load: the browser's own box still asks
      if (window.confirm(form.getAttribute('data-confirm'))) { form.dataset.skConfirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
      return;
    }
    askConfirm(form, submitter);
  });
  document.addEventListener('change', function (e) {
    if (e.target.matches('select[data-autosubmit]')) e.target.form.submit();
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
