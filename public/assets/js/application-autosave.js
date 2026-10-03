/**
 * Scholarship application wizard: debounced local + server autosave, offline handling,
 * accidental-reload recovery, and Step 4 documents that save themselves as soon as they are chosen.
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
  wizard.querySelectorAll('[data-goto]').forEach((btn) => {
    btn.addEventListener('click', () => {
      try { localStorage.setItem(stepStorageKey(), String(parseInt(btn.dataset.goto, 10) || 0)); } catch (e) {}
    });
  });
  try {
    const saved = parseInt(localStorage.getItem(stepStorageKey()) || '0', 10);
    if (saved > 0 && saved < formSteps.length) showStep(saved);
  } catch (e) {}

  /* ---------------- Step 4: documents auto-save — each file uploads as soon as it is chosen ---------------- */
  const MAX_SIZE = 5 * 1024 * 1024;
  const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
  const pendingFiles = {}; // { [docType]: File } — chosen but not saved yet (uploading, or failed and waiting for a retry)
  let uploadQueue = Promise.resolve(); // uploads run one at a time, in the order the files were chosen
  const docsWarning = document.getElementById('docsIncompleteWarning');
  const docCards = wizard.querySelectorAll('[data-doc-card]');

  /* A chosen file not saved yet is also kept in this browser (IndexedDB), so a power interruption or an accidental
     refresh mid-upload does not lose it: it is sent again when the page opens next. Removed once the server has it. */
  function pendingDb(mode, work) {
    return new Promise((resolve) => {
      try {
        const req = indexedDB.open('sk_application_pending_documents', 1);
        req.onupgradeneeded = () => req.result.createObjectStore('files');
        req.onerror = () => resolve(undefined);
        req.onsuccess = () => {
          const db = req.result;
          const tx = db.transaction('files', mode);
          const r = work(tx.objectStore('files'));
          tx.oncomplete = () => { db.close(); resolve(r && r.result); };
          tx.onerror = tx.onabort = () => { db.close(); resolve(undefined); };
        };
      } catch (e) { resolve(undefined); } // IndexedDB unavailable (private mode etc.): uploads still work, just not kept
    });
  }
  function pendingKey(docType) { return `${userId}::${docType}`; }
  function keepPending(docType, file) {
    return pendingDb('readwrite', (s) => s.put({ blob: file, name: file.name, type: file.type }, pendingKey(docType)));
  }
  function forgetPending(docType) { return pendingDb('readwrite', (s) => s.delete(pendingKey(docType))); }

  function cardFor(docType) {
    return wizard.querySelector(`[data-doc-card][data-doc-type="${CSS.escape(docType)}"]`);
  }
  function setPill(card, text, status) {
    const pill = card.querySelector('[data-doc-status-pill]');
    if (pill) { pill.textContent = text; pill.className = 'doc-pill doc-' + status; }
  }
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

  /** Only documents actually SAVED on the server count — a file still uploading, or one that failed, does not. */
  function allRequiredDocsSaved() {
    return Array.from(docCards).every((card) => card.dataset.docHasFile === '1');
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

  /** Upload one document now (queued behind any upload already running). Resolves true when it is saved. */
  function uploadDoc(docType) {
    const run = () => {
      const file = pendingFiles[docType];
      const card = cardFor(docType);
      if (!file || !card) return Promise.resolve(true);
      setPill(card, 'Saving…', 'Submitted');
      setIndicator('saving', 'Saving documents…');
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
          if (pendingFiles[docType] !== file) return true; // a newer file was chosen meanwhile; its own upload follows
          if (!res.success) {
            setPill(card, 'Not saved', 'Not-Submitted');
            showDocError(card, (res.message || 'Upload failed.') + ' Choose the file again to retry.');
            setIndicator('error', 'A document was not saved');
            return false;
          }
          delete pendingFiles[docType];
          forgetPending(docType);
          clearDocError(card);
          updateDocCard(card, res.document);
          setIndicator('saved', 'Documents saved');
          if (allRequiredDocsSaved() && docsWarning) docsWarning.classList.add('d-none');
          return true;
        })
        .catch(() => {
          setPill(card, 'Not saved', 'Not-Submitted');
          showDocError(card, 'Upload failed — check your connection. It is tried again when you click Next.');
          setIndicator('error', 'A document was not saved');
          return false;
        });
    };
    const p = uploadQueue.then(run);
    uploadQueue = p.then(() => undefined, () => undefined);
    return p;
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
    keepPending(docType, file);
    showPreview(card, file);
    const filenameEl = card.querySelector('[data-doc-filename]');
    const filesizeEl = card.querySelector('[data-doc-filesize]');
    const meta = card.querySelector('[data-doc-meta]');
    if (filenameEl) filenameEl.textContent = file.name;
    if (filesizeEl) filesizeEl.textContent = ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    if (meta) meta.style.display = '';
    card.querySelector('.card')?.classList.remove('border-danger');
    uploadDoc(docType); // auto-save: there is no separate upload button
  }

  // Re-send any file that was chosen but not saved before the page was closed or reloaded.
  docCards.forEach((card) => {
    if (!card.querySelector('[data-doc-file]')) return; // this document cannot be changed now
    const docType = card.dataset.docType;
    pendingDb('readonly', (s) => s.get(pendingKey(docType))).then((rec) => {
      if (!rec || !rec.blob || pendingFiles[docType]) return;
      selectFile(card, new File([rec.blob], rec.name, { type: rec.type }));
    });
  });

  // Leaving while a document is still uploading: warn (the file is kept and re-sent on return anyway).
  window.addEventListener('beforeunload', (e) => {
    if (Object.keys(pendingFiles).length) { e.preventDefault(); e.returnValue = ''; }
  });

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
      fileInput.value = ''; // choosing the same file again (to retry) still fires "change"
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

  function updateDocCard(card, doc) {
    card.dataset.docHasFile = '1';
    setPill(card, doc.status_label || doc.status, doc.status.replace(/ /g, '-'));
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

  // "Next: Review": first finish (or retry) any document still being saved, then continue only when all 5 are saved.
  // The server checks completeness again on Submit.
  let passThrough = false;
  wizard.querySelectorAll('[data-next][data-requires-documents]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      if (passThrough) { passThrough = false; return; } // our own re-click after saving: let it through
      const unsaved = Object.keys(pendingFiles);
      if (unsaved.length === 0 && allRequiredDocsSaved()) {
        if (docsWarning) docsWarning.classList.add('d-none');
        return; // everything saved: normal Next
      }
      e.preventDefault();
      e.stopImmediatePropagation();
      const label = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = 'Saving documents…';
      // Retry every file that is still unsaved (uploads are queued, so this also waits for any running one).
      Promise.all(unsaved.map((t) => uploadDoc(t))).then(() => uploadQueue).then(() => {
        btn.disabled = false;
        btn.innerHTML = label;
        if (allRequiredDocsSaved()) {
          if (docsWarning) docsWarning.classList.add('d-none');
          passThrough = true;
          btn.click();
          return;
        }
        if (docsWarning) docsWarning.classList.remove('d-none');
        docCards.forEach((card) => {
          card.querySelector('.card')?.classList.toggle('border-danger', card.dataset.docHasFile !== '1');
        });
      });
    }, true); // capture phase: must run before app.js's own [data-next] handler
  });

})();
