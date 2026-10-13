/**
 * Application wizard, step IV: each document uploads as soon as it is chosen (no upload button), a file not saved yet is
 * kept in IndexedDB and re-sent after a reload, and "Next: Review" waits until all 5 are saved. Needs application/shared.js.
 */
(function () {
  'use strict';

  const A = window.SKApplication;
  if (!A || !A.editable) return; // no wizard, or a read-only application: nothing to wire up
  const { wizard, userId, uploadUrl, csrfToken, setIndicator } = A;

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
