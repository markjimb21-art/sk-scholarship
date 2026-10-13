/**
 * Application wizard, steps I–III: every field is saved on this device at once and on the server shortly after
 * (debounced), with retry/backoff, offline handling, a last flush when the tab closes, and the "restore from this
 * device?" prompt after an accidental reload. Needs application/shared.js.
 */
(function () {
  'use strict';

  const A = window.SKApplication;
  if (!A || !A.editable) return; // no wizard, or a read-only application: nothing to wire up
  const { wizard, autosaveUrl, csrfToken, serverUpdatedAt, appId, storageKey, stepStorageKey, readStore, writeStore, setIndicator } = A;

  /* ---------------- Field serialization ---------------- */
  function serializeForm(form) {
    const data = {};
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
      if (el.type === 'checkbox') data[el.name] = el.checked;
      else if (el.type === 'file') return;
      else data[el.name] = el.value;
    });
    return data;
  }

  function applyToForm(form, data) {
    Object.keys(data).forEach((name) => {
      const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
      if (!el) return;
      if (el.type === 'checkbox') el.checked = !!data[name];
      else el.value = data[name];
    });
    form.dispatchEvent(new Event('sk:restored'));
  }

  /* ---------------- Server sync (with small retry/backoff) ---------------- */
  const pendingRetries = {};

  function syncStep(step, data, attempt) {
    attempt = attempt || 0;
    setIndicator('saving', 'Saving…');

    const body = new FormData();
    body.append('step', step);
    body.append('_csrf', csrfToken);
    Object.keys(data).forEach((k) => body.append(k, data[k] === true ? '1' : (data[k] === false ? '' : data[k])));

    fetch(autosaveUrl, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken },
    })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) {
          setIndicator('error', res.message || 'Sync failed — your local copy is still preserved.');
          return;
        }
        if (res.application_id && String(res.application_id) !== appId()) {
          migrateApplicationId(String(res.application_id));
        }
        const store = readStore();
        if (store[step]) store[step].synced = true;
        writeStore(store);
        delete pendingRetries[step];
        setIndicator('saved', 'Saved');
      })
      .catch(() => {
        setIndicator('local', 'Saved locally — waiting for connection');
        scheduleRetry(step, data, attempt);
      });
  }

  function scheduleRetry(step, data, attempt) {
    if (!navigator.onLine || attempt >= 5) return;
    clearTimeout(pendingRetries[step]);
    const delay = Math.min(30000, 1000 * Math.pow(2, attempt));
    pendingRetries[step] = setTimeout(() => syncStep(step, data, attempt + 1), delay);
  }

  function migrateApplicationId(newId) {
    const oldKey = storageKey();
    const oldStepKey = stepStorageKey();
    const oldData = localStorage.getItem(oldKey);
    const oldStep = localStorage.getItem(oldStepKey);
    wizard.dataset.applicationId = newId;
    if (oldData) { try { localStorage.setItem(storageKey(), oldData); localStorage.removeItem(oldKey); } catch (e) {} }
    if (oldStep) { try { localStorage.setItem(stepStorageKey(), oldStep); localStorage.removeItem(oldStepKey); } catch (e) {} }
  }

  /* ---------------- Debounced local + server save per step ---------------- */
  const debounceTimers = {};
  function queueSave(step, form) {
    clearTimeout(debounceTimers[step]);
    debounceTimers[step] = setTimeout(() => {
      const data = serializeForm(form);
      const store = readStore();
      store[step] = { data, ts: Date.now(), synced: false };
      writeStore(store);
      if (navigator.onLine) syncStep(step, data);
      else setIndicator('local', 'You are offline. Your recent changes are being saved on this device.');
    }, 700);
  }

  /* ---------------- Input normalization: uppercase + phone digits (mirrors server-side rules) ---------------- */
  wizard.querySelectorAll('[data-uppercase]').forEach((el) => {
    const transform = () => {
      const start = el.selectionStart, end = el.selectionEnd;
      const upper = el.value.toUpperCase();
      if (upper !== el.value) {
        el.value = upper;
        if (start !== null && end !== null && typeof el.setSelectionRange === 'function') {
          try { el.setSelectionRange(start, end); } catch (e) {}
        }
      }
    };
    el.addEventListener('input', transform);
    el.addEventListener('paste', () => setTimeout(transform, 0));
  });
  wizard.querySelectorAll('[data-phone]').forEach((el) => {
    const sanitize = () => {
      const digits = el.value.replace(/\D+/g, '').slice(0, 11);
      if (digits !== el.value) el.value = digits;
    };
    el.addEventListener('input', sanitize);
    el.addEventListener('paste', () => setTimeout(sanitize, 0));
  });

  const stepForms = wizard.querySelectorAll('form[data-step]');
  stepForms.forEach((form) => {
    const step = form.dataset.step;
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (el.type === 'file') return;
      const evt = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'date') ? 'change' : 'input';
      el.addEventListener(evt, () => queueSave(step, form));
    });
  });

  // Best-effort flush right before the tab closes/hides (data is already in localStorage synchronously).
  window.addEventListener('pagehide', () => {
    stepForms.forEach((form) => {
      const step = form.dataset.step;
      const store = readStore();
      if (store[step] && store[step].synced) return;
      const data = serializeForm(form);
      const body = new FormData();
      body.append('step', step);
      body.append('_csrf', csrfToken);
      Object.keys(data).forEach((k) => body.append(k, data[k] === true ? '1' : (data[k] === false ? '' : data[k])));
      if (navigator.sendBeacon) navigator.sendBeacon(autosaveUrl, body);
    });
  });

  /* ---------------- Offline / online handling ---------------- */
  window.addEventListener('offline', () => {
    setIndicator('local', 'You are offline. Your recent changes are being saved on this device.');
  });
  window.addEventListener('online', () => {
    setIndicator('saving', 'Syncing…');
    const store = readStore();
    stepForms.forEach((form) => {
      const step = form.dataset.step;
      if (store[step] && !store[step].synced) syncStep(step, store[step].data);
    });
  });

  /* ---------------- Restore-after-reload prompt ---------------- */
  const restoreBanner = document.getElementById('restoreBanner');
  const restoreLocalBtn = document.getElementById('restoreLocalBtn');
  const useServerBtn = document.getElementById('useServerBtn');

  (function checkForNewerLocalCopy() {
    const store = readStore();
    const newest = Object.values(store).reduce((max, s) => Math.max(max, s.ts || 0), 0);
    if (newest > 0 && newest > serverUpdatedAt && restoreBanner) {
      restoreBanner.classList.remove('d-none');
    }
  })();

  if (restoreLocalBtn) {
    restoreLocalBtn.addEventListener('click', () => {
      const store = readStore();
      stepForms.forEach((form) => {
        const step = form.dataset.step;
        if (store[step]) applyToForm(form, store[step].data);
      });
      restoreBanner.classList.add('d-none');
      setIndicator('local', 'Restored from this device — syncing…');
      if (navigator.onLine) {
        stepForms.forEach((form) => {
          const step = form.dataset.step;
          if (store[step]) syncStep(step, store[step].data);
        });
      }
    });
  }
  if (useServerBtn) {
    useServerBtn.addEventListener('click', () => {
      try { localStorage.removeItem(storageKey()); } catch (e) {}
      restoreBanner.classList.add('d-none');
    });
  }
})();
