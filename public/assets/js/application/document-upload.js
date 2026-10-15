/**
 * Application wizard, step IV: each document uploads as soon as it is chosen (no upload button), a file not saved yet is
 * kept in IndexedDB and re-sent after a reload, and "Next: Review" waits until all 5 are saved. Needs application/shared.js.
 *
 * A card holds one box per side ([data-doc-slot], data-side "" = one file, "Front", "Back"). Two-sided cards need the
 * front AND the back (data-doc-sided="1": School ID, Form 138); one file with the whole document, uploaded before sides
 * existed, covers both (Document::covers on the server).
 */
(function () {
  'use strict';

  const A = window.SKApplication;
  if (!A || !A.editable) return; // no wizard, or a read-only application: nothing to wire up
  const { wizard, userId, uploadUrl, csrfToken, setIndicator } = A;

  /* ---------------- Step 4: documents auto-save — each file uploads as soon as it is chosen ---------------- */
  const MAX_SIZE = Number(wizard.dataset.maxBytes);   // MAX_FILE_SIZE (config.php); the server checks it again
  const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
  const pendingFiles = {}; // { ["type::side"]: File } — chosen but not saved yet (uploading, or failed and waiting for a retry)
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
  function slotKey(slot) { return slot.closest('[data-doc-card]').dataset.docType + '::' + slot.dataset.side; }
  function pendingKey(key) { return `${userId}::${key}`; }
  function keepPending(key, file) {
    return pendingDb('readwrite', (s) => s.put({ blob: file, name: file.name, type: file.type }, pendingKey(key)));
  }
  function forgetPending(key) { return pendingDb('readwrite', (s) => s.delete(pendingKey(key))); }

  function slotFor(key) {
    const i = key.lastIndexOf('::');
    const card = wizard.querySelector(`[data-doc-card][data-doc-type="${CSS.escape(key.slice(0, i))}"]`);
    return card && card.querySelector(`[data-doc-slot][data-side="${CSS.escape(key.slice(i + 2))}"]`);
  }
  function slotOf(card, side) { return card.querySelector(`[data-doc-slot][data-side="${side}"]`); }
  function hasFile(card, side) { const s = slotOf(card, side); return !!s && s.dataset.hasFile === '1'; }

  /* ---------------- two-sided or one file ---------------- */
  function isSided(card) { return card.dataset.docSided === '1'; }
  /** The rule of Document::covers(): one file covers both sides; two-sided needs both; one-file needs any file. */
  function isComplete(card) {
    if (hasFile(card, '')) return true;
    return isSided(card) ? hasFile(card, 'Front') && hasFile(card, 'Back') : hasFile(card, 'Front') || hasFile(card, 'Back');
  }
  function show(el, visible) { if (el) el.style.display = visible ? '' : 'none'; }

  /** Show the boxes that apply (a replaced one-file version disappears), and refresh the card's "complete" flag and pill. */
  function refreshCard(card) {
    const sided = isSided(card);
    card.querySelectorAll('[data-doc-slot]').forEach((slot) => {
      const whole = slot.dataset.side === '';
      const has = slot.dataset.hasFile === '1' || !!pendingFiles[slotKey(slot)];
      show(slot, whole ? (!sided || has) : (sided || has));
      show(slot.querySelector('[data-doc-dropzone]'), whole ? !sided : sided);
      show(slot.querySelector('[data-doc-whole-hint]'), sided);
    });
    show(card.querySelector('[data-doc-sides-hint]'), sided);
    const complete = isComplete(card);
    card.dataset.docHasFile = complete ? '1' : '0';
    const pill = card.querySelector('[data-doc-status-pill]');
    if (pill && !pill.dataset.saving) {
      const any = ['', 'Front', 'Back'].some((s) => hasFile(card, s));
      pill.textContent = complete ? (pill.dataset.savedLabel || 'Uploaded') : (any ? 'Add the other side' : 'Missing');
      pill.className = 'doc-pill doc-' + (complete ? 'Submitted' : 'Not-Submitted');
    }
  }

  function setPill(card, text, status, saving) {
    const pill = card.querySelector('[data-doc-status-pill]');
    if (!pill) return;
    pill.textContent = text;
    pill.className = 'doc-pill doc-' + status;
    if (saving) pill.dataset.saving = '1'; else delete pill.dataset.saving;
  }
  function showDocError(slot, msg) {
    const el = slot.querySelector('[data-doc-error]');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function clearDocError(slot) {
    const el = slot.querySelector('[data-doc-error]');
    if (el) el.classList.add('d-none');
  }

  /** Only documents actually SAVED on the server count — a file still uploading, or one that failed, does not. */
  function allRequiredDocsSaved() {
    return Array.from(docCards).every((card) => card.dataset.docHasFile === '1');
  }

  function showPreview(slot, file) {
    const previewBox = slot.querySelector('[data-doc-preview]');
    const emptyBox = slot.querySelector('[data-doc-dropzone-empty]');
    const img = slot.querySelector('[data-doc-preview-img]');
    const icon = slot.querySelector('[data-doc-preview-icon]');
    if (!previewBox) return;
    previewBox.style.display = '';
    if (emptyBox) emptyBox.style.display = 'none';
    const isImage = file.type === 'image/jpeg' || file.type === 'image/png';
    if (img) { img.classList.toggle('d-none', !isImage); if (isImage) img.src = URL.createObjectURL(file); }
    if (icon) icon.classList.toggle('d-none', isImage);
  }

  /** A box whose version was replaced on the server (Document::supersede): it no longer holds a file. */
  function clearSlot(slot) {
    if (!slot || slot.dataset.hasFile !== '1') return;
    slot.dataset.hasFile = '0';
    show(slot.querySelector('[data-doc-preview]'), false);
    show(slot.querySelector('[data-doc-dropzone-empty]'), true);
    show(slot.querySelector('[data-doc-meta]'), false);
    const wrong = slot.querySelector('form[action*="documents/invalidate"]');
    if (wrong) wrong.remove();
  }

  /** Upload one box now (queued behind any upload already running). Resolves true when it is saved. */
  function uploadDoc(key) {
    const run = () => {
      const file = pendingFiles[key];
      const slot = slotFor(key);
      if (!file || !slot) return Promise.resolve(true);
      const card = slot.closest('[data-doc-card]');
      const side = slot.dataset.side;
      setPill(card, 'Saving…', 'Submitted', true);
      setIndicator('saving', 'Saving documents…');
      const body = new FormData();
      body.append('document_type', card.dataset.docType);
      body.append('side', side);
      body.append('file', file);
      body.append('_csrf', csrfToken);
      return fetch(uploadUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      })
        // A server error answers with an error page, not JSON: say so (the catch below is for a lost connection only).
        .then((r) => r.json().catch(() => ({ success: false, message: `The server could not save this file (error ${r.status}). Try again; if it keeps failing, tell the SK office.` })))
        .then((res) => {
          if (pendingFiles[key] !== file) return true; // a newer file was chosen meanwhile; its own upload follows
          if (!res.success) {
            setPill(card, 'Not saved', 'Not-Submitted', true);
            showDocError(slot, (res.message || 'Upload failed.') + ' Choose the file again to retry.');
            setIndicator('error', 'A document was not saved');
            return false;
          }
          delete pendingFiles[key];
          forgetPending(key);
          clearDocError(slot);
          // The server replaced what this upload covers: one file replaces both sides, a side replaces one file.
          if (side === '') { clearSlot(slotOf(card, 'Front')); clearSlot(slotOf(card, 'Back')); }
          else clearSlot(slotOf(card, ''));
          updateSlot(slot, res.document);
          setPill(card, '', 'Submitted', false);
          card.querySelector('[data-doc-status-pill]').dataset.savedLabel = res.document.status_label || 'Uploaded';
          refreshCard(card);
          setIndicator('saved', 'Documents saved');
          if (allRequiredDocsSaved() && docsWarning) docsWarning.classList.add('d-none');
          return true;
        })
        .catch(() => {
          setPill(card, 'Not saved', 'Not-Submitted', true);
          showDocError(slot, 'Upload failed — check your connection. It is tried again when you click Next.');
          setIndicator('error', 'A document was not saved');
          return false;
        });
    };
    const p = uploadQueue.then(run);
    uploadQueue = p.then(() => undefined, () => undefined);
    return p;
  }

  function selectFile(slot, file) {
    clearDocError(slot);
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXT.includes(ext) || !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)) {
      showDocError(slot, 'Only PDF, JPG, or PNG files are allowed.');
      return;
    }
    if (file.size > MAX_SIZE) {
      showDocError(slot, 'File is too large (max ' + wizard.dataset.maxText + ').');
      return;
    }
    const key = slotKey(slot);
    pendingFiles[key] = file;
    keepPending(key, file);
    showPreview(slot, file);
    const filenameEl = slot.querySelector('[data-doc-filename]');
    const filesizeEl = slot.querySelector('[data-doc-filesize]');
    const meta = slot.querySelector('[data-doc-meta]');
    if (filenameEl) filenameEl.textContent = file.name;
    if (filesizeEl) filesizeEl.textContent = ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    if (meta) meta.style.display = '';
    slot.closest('[data-doc-card]').querySelector('.card')?.classList.remove('border-danger');
    uploadDoc(key); // auto-save: there is no separate upload button
  }

  // Re-send any file that was chosen but not saved before the page was closed or reloaded.
  wizard.querySelectorAll('[data-doc-slot]').forEach((slot) => {
    if (!slot.querySelector('[data-doc-file]')) return; // this document cannot be changed now
    const key = slotKey(slot);
    pendingDb('readonly', (s) => s.get(pendingKey(key))).then((rec) => {
      if (!rec || !rec.blob || pendingFiles[key]) return;
      selectFile(slot, new File([rec.blob], rec.name, { type: rec.type }));
    });
  });

  // Leaving while a document is still uploading: warn (the file is kept and re-sent on return anyway).
  window.addEventListener('beforeunload', (e) => {
    if (Object.keys(pendingFiles).length) { e.preventDefault(); e.returnValue = ''; }
  });

  wizard.querySelectorAll('[data-doc-slot]').forEach((slot) => {
    const dropzone = slot.querySelector('[data-doc-dropzone]');
    const fileInput = slot.querySelector('[data-doc-file]');
    if (!dropzone || !fileInput) return; // not editable — no dropzone rendered

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    fileInput.addEventListener('click', (e) => e.stopPropagation()); // the input sits inside the dropzone
    fileInput.addEventListener('change', () => {
      if (fileInput.files[0]) selectFile(slot, fileInput.files[0]);
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
      if (file) selectFile(slot, file);
    });
  });

  function updateSlot(slot, doc) {
    slot.dataset.hasFile = '1';
    const meta = slot.querySelector('[data-doc-meta]');
    const filenameEl = slot.querySelector('[data-doc-filename]');
    if (meta && filenameEl) {
      filenameEl.textContent = doc.original_filename;
      meta.style.display = '';
      const link = slot.querySelector('[data-doc-view-link]');
      if (link) { link.href = doc.view_url; link.textContent = 'View'; }
    }
    const remarks = slot.querySelector('[data-doc-remarks]');
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
      Promise.all(unsaved.map((k) => uploadDoc(k))).then(() => uploadQueue).then(() => {
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
