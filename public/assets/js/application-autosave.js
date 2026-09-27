/**
 * Scholarship application wizard: debounced local + server autosave, offline handling,
 * accidental-reload recovery, and AJAX document uploads for Step 4.
 *
 * Local storage is a per-applicant, per-application convenience cache only — never the
 * source of truth. The server (and its `updated_at` timestamp) always wins ties.
 */
(function () {
  'use strict';

  const wizard = document.querySelector('[data-wizard]');
  if (!wizard) return;

  const editable = wizard.dataset.editable === '1';
  const userId = wizard.dataset.userId || '0';
  const autosaveUrl = wizard.dataset.autosaveUrl;
  const uploadUrl = wizard.dataset.uploadUrl;
  const csrfToken = wizard.dataset.csrf;
  const serverUpdatedAt = wizard.dataset.updatedAt ? Date.parse(wizard.dataset.updatedAt.replace(' ', 'T')) : 0;

  function appId() { return wizard.dataset.applicationId || '0'; }
  function storageKey() { return `sk_application_autosave_${userId}_${appId()}`; }
  function stepStorageKey() { return `sk_application_current_step_${userId}_${appId()}`; }

  function readStore() {
    try { return JSON.parse(localStorage.getItem(storageKey()) || '{}'); }
    catch (e) { return {}; }
  }
  function writeStore(store) {
    try { localStorage.setItem(storageKey(), JSON.stringify(store)); } catch (e) { /* storage unavailable — ignore, server sync still attempted */ }
  }

  /* ---------------- Autosave status indicator ---------------- */
  const indicator = document.getElementById('autosaveIndicator');
  function setIndicator(state, text) {
    if (!indicator) return;
    indicator.classList.remove('d-none', 'autosave-saving', 'autosave-saved', 'autosave-local', 'autosave-error');
    indicator.classList.add('autosave-' + state);
    indicator.textContent = text;
  }

  if (!editable) return; // read-only application: no autosave, no upload wiring needed

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

  /* ---------------- Remember + restore current wizard step (UI convenience only) ---------------- */
  const formSteps = Array.from(wizard.querySelectorAll('.form-step'));
  const indicators = Array.from(wizard.querySelectorAll('.progress-step'));
  function showStep(i) {
    formSteps.forEach((s, idx) => s.classList.toggle('active', idx === i));
    indicators.forEach((s, idx) => {
      s.classList.toggle('active', idx === i);
      s.classList.toggle('done', idx < i);
    });
  }
  wizard.querySelectorAll('[data-next]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const i = formSteps.findIndex((s) => s.classList.contains('active'));
      if (i >= 0) { try { localStorage.setItem(stepStorageKey(), String(Math.min(i + 1, formSteps.length - 1))); } catch (e) {} }
    });
  });
  wizard.querySelectorAll('[data-prev]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const i = formSteps.findIndex((s) => s.classList.contains('active'));
      if (i >= 0) { try { localStorage.setItem(stepStorageKey(), String(Math.max(i - 1, 0))); } catch (e) {} }
    });
  });
  try {
    const saved = parseInt(localStorage.getItem(stepStorageKey()) || '0', 10);
    if (saved > 0 && saved < formSteps.length) showStep(saved);
  } catch (e) {}

  /* ---------------- Step 4: drag-and-drop selection + single "Upload Documents" button ---------------- */
  const MAX_SIZE = 5 * 1024 * 1024;
  const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
  const pendingFiles = {}; // { [docType]: File } — selected but not yet uploaded
  const uploadAllBtn = document.getElementById('uploadAllDocsBtn');
  const docsWarning = document.getElementById('docsIncompleteWarning');
  const docCards = wizard.querySelectorAll('[data-doc-card]');

  function showDocError(card, msg) {
    const el = card.querySelector('[data-doc-error]');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function clearDocError(card) {
    const el = card.querySelector('[data-doc-error]');
    if (el) el.classList.add('d-none');
  }

  function refreshUploadButton() {
    if (!uploadAllBtn) return;
    uploadAllBtn.disabled = Object.keys(pendingFiles).length === 0;
  }

  function allRequiredDocsPresent() {
    return Array.from(docCards).every((card) => card.dataset.docHasFile === '1' || pendingFiles[card.dataset.docType]);
  }

  function showPreview(card, file) {
    const previewBox = card.querySelector('[data-doc-preview]');
    const emptyBox = card.querySelector('[data-doc-dropzone-empty]');
    const img = card.querySelector('[data-doc-preview-img]');
    const icon = card.querySelector('[data-doc-preview-icon]');
    if (!previewBox) return;
    previewBox.style.display = '';
    if (emptyBox) emptyBox.style.display = 'none';
    const isImage = file.type === 'image/jpeg' || file.type === 'image/png';
    if (isImage && img) {
      img.src = URL.createObjectURL(file);
      img.classList.remove('d-none');
      if (icon) icon.style.display = 'none';
    } else {
      if (img) img.classList.add('d-none');
      if (icon) icon.style.display = '';
    }
  }

  function selectFile(card, file) {
    clearDocError(card);
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXT.includes(ext) || !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)) {
      showDocError(card, 'Only PDF, JPG, or PNG files are allowed.');
      return;
    }
    if (file.size > MAX_SIZE) {
      showDocError(card, 'File is too large (max 5MB).');
      return;
    }
    const docType = card.dataset.docType;
    pendingFiles[docType] = file;
    showPreview(card, file);
    const filenameEl = card.querySelector('[data-doc-filename]');
    const filesizeEl = card.querySelector('[data-doc-filesize]');
    const meta = card.querySelector('[data-doc-meta]');
    if (filenameEl) filenameEl.textContent = file.name;
    if (filesizeEl) filesizeEl.textContent = ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    if (meta) meta.style.display = '';
    const pill = card.querySelector('[data-doc-status-pill]');
    if (pill) { pill.textContent = 'Selected'; pill.className = 'doc-pill doc-Submitted'; }
    card.classList.remove('border-danger');
    refreshUploadButton();
  }

  docCards.forEach((card) => {
    const dropzone = card.querySelector('[data-doc-dropzone]');
    const fileInput = card.querySelector('[data-doc-file]');
    if (!dropzone || !fileInput) return; // not editable — no dropzone rendered

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    fileInput.addEventListener('change', () => {
      if (fileInput.files[0]) selectFile(card, fileInput.files[0]);
    });
    ['dragenter', 'dragover'].forEach((evt) => {
      dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('doc-dragover'); });
    });
    ['dragleave', 'dragend', 'drop'].forEach((evt) => {
      dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('doc-dragover'); });
    });
    dropzone.addEventListener('drop', (e) => {
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (file) selectFile(card, file);
    });
  });

  if (uploadAllBtn) {
    uploadAllBtn.addEventListener('click', () => {
      const entries = Object.entries(pendingFiles);
      if (entries.length === 0) return;

      uploadAllBtn.disabled = true;
      const originalText = uploadAllBtn.innerHTML;
      uploadAllBtn.innerHTML = 'Uploading…';

      const uploadOne = ([docType, file]) => {
        const card = wizard.querySelector(`[data-doc-card][data-doc-type="${CSS.escape(docType)}"]`);
        const body = new FormData();
        body.append('document_type', docType);
        body.append('file', file);
        body.append('_csrf', csrfToken);
        return fetch(uploadUrl, {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then((r) => r.json())
          .then((res) => {
            if (!res.success) {
              if (card) showDocError(card, res.message || 'Upload failed.');
              return;
            }
            delete pendingFiles[docType];
            if (card) updateDocCard(card, res.document);
          })
          .catch(() => {
            if (card) showDocError(card, 'Upload failed — please check your connection and try again.');
          });
      };

      // Sequential uploads keep this simple and avoid overwhelming slow connections.
      entries.reduce((chain, entry) => chain.then(() => uploadOne(entry)), Promise.resolve())
        .then(() => {
          uploadAllBtn.innerHTML = originalText;
          refreshUploadButton();
          if (allRequiredDocsPresent() && docsWarning) docsWarning.classList.add('d-none');
        });
    });
  }

  function updateDocCard(card, doc) {
    card.dataset.docHasFile = '1';
    const pill = card.querySelector('[data-doc-status-pill]');
    if (pill) {
      pill.textContent = doc.status;
      pill.className = 'doc-pill doc-' + doc.status.replace(/ /g, '-') + '';
      pill.setAttribute('data-doc-status-pill', '');
    }
    const meta = card.querySelector('[data-doc-meta]');
    const filenameEl = card.querySelector('[data-doc-filename]');
    if (meta && filenameEl) {
      filenameEl.textContent = doc.original_filename;
      meta.style.display = '';
      let link = card.querySelector('[data-doc-view-link]');
      if (!link) {
        link = document.createElement('a');
        link.setAttribute('data-doc-view-link', '');
        link.target = '_blank';
        link.rel = 'noopener';
        meta.appendChild(link);
      }
      link.href = doc.view_url;
      link.textContent = 'View';
    }
    const remarks = card.querySelector('[data-doc-remarks]');
    if (remarks) remarks.style.display = 'none';
  }

  // Block "Next: Review" until all 5 required documents are uploaded (server also enforces this on submit).
  wizard.querySelectorAll('[data-next][data-requires-documents]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      if (!allRequiredDocsPresent()) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (docsWarning) docsWarning.classList.remove('d-none');
        docCards.forEach((card) => {
          const missing = card.dataset.docHasFile !== '1' && !pendingFiles[card.dataset.docType];
          card.querySelector('.card')?.classList.toggle('border-danger', missing);
        });
      } else if (docsWarning) {
        docsWarning.classList.add('d-none');
      }
    }, true); // capture phase: must run before app.js's own [data-next] handler
  });

  refreshUploadButton();
})();
