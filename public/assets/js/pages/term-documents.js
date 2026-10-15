/**
 * My Scholarship (A8 claim / A9 renewal): each file uploads as soon as it is chosen or dropped (no Upload button), like
 * step IV of the application form. A file not saved yet is kept in IndexedDB and sent again after a reload. The Submit
 * button is enabled when the server says every required file is saved (the server checks again on Submit).
 */
(function () {
  'use strict';

  const config = document.querySelector('[data-term-docs-config]');
  const slots = document.querySelectorAll('[data-term-doc-slot]');
  if (!config || !slots.length) return;
  const userId = config.dataset.userId || '0';
  const MAX_SIZE = Number(config.dataset.maxBytes);   // MAX_FILE_SIZE (config.php); the server checks it again
  const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
  const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
  const pending = {};                  // { [slot key]: File } — chosen but not saved yet
  let queue = Promise.resolve();       // uploads run one at a time, in the order the files were chosen

  /* A chosen file not saved yet is also kept in this browser (IndexedDB), so a refresh or a power interruption
     mid-upload does not lose it: it is sent again when the page opens next. Removed once the server has it. */
  function pendingDb(mode, work) {
    return new Promise((resolve) => {
      try {
        const req = indexedDB.open('sk_term_pending_documents', 1);
        req.onupgradeneeded = () => req.result.createObjectStore('files');
        req.onerror = () => resolve(undefined);
        req.onsuccess = () => {
          const db = req.result;
          const tx = db.transaction('files', mode);
          const r = work(tx.objectStore('files'));
          tx.oncomplete = () => { db.close(); resolve(r && r.result); };
          tx.onerror = tx.onabort = () => { db.close(); resolve(undefined); };
        };
      } catch (e) { resolve(undefined); }   // IndexedDB unavailable (private mode etc.): uploads still work
    });
  }
  const dbKey = (slot) => `${userId}::${slot.dataset.key}`;
  const keepPending = (slot, file) => pendingDb('readwrite', (s) => s.put({ blob: file, name: file.name, type: file.type }, dbKey(slot)));
  const forgetPending = (slot) => pendingDb('readwrite', (s) => s.delete(dbKey(slot)));

  function setStatus(slot, text, color) {
    const badge = slot.querySelector('[data-slot-status]');
    if (badge) { badge.textContent = text; badge.className = 'badge bg-' + color; }
  }
  function showError(slot, msg) {
    const el = slot.querySelector('[data-doc-error]');
    if (!el) return;
    el.textContent = msg;
    el.classList.toggle('d-none', !msg);
  }

  function showPreview(slot, file) {
    const box = slot.querySelector('[data-doc-preview]');
    const img = slot.querySelector('[data-doc-preview-img]');
    const icon = slot.querySelector('[data-doc-preview-icon]');
    const empty = slot.querySelector('[data-doc-dropzone-empty]');
    const hint = slot.querySelector('[data-doc-replace-hint]');
    if (!box) return;
    box.style.display = '';
    if (empty) empty.style.display = 'none';
    if (hint) hint.style.display = '';
    const isImage = file.type === 'image/jpeg' || file.type === 'image/png';
    if (img) { img.classList.toggle('d-none', !isImage); if (isImage) img.src = URL.createObjectURL(file); }
    if (icon) icon.classList.toggle('d-none', isImage);
  }

  function showSaved(slot, doc) {
    setStatus(slot, doc.status_label || 'Uploaded', 'info');
    const meta = slot.querySelector('[data-slot-meta]');
    const link = slot.querySelector('[data-slot-link]');
    const time = slot.querySelector('[data-slot-time]');
    if (link) { link.href = doc.view_url; link.textContent = doc.original_filename; }
    if (time) time.textContent = doc.uploaded_at;
    if (meta) meta.style.display = '';
    // A front or back picture replaces the old one-file version ("On file as one file with both sides").
    const card = slot.closest('[data-term-doc-card]');
    const whole = card && card.querySelector('[data-term-doc-whole]');
    if (whole && doc.side) whole.remove();
  }

  function setReady(purpose, ready) {
    const btn = document.querySelector(`[data-term-submit="${CSS.escape(purpose)}"]`);
    if (!btn) return;
    btn.disabled = !ready;
    if (ready) btn.removeAttribute('title'); else btn.title = 'Upload every file first.';
  }

  function upload(slot) {
    const run = () => {
      const file = pending[slot.dataset.key];
      if (!file) return Promise.resolve(true);
      setStatus(slot, 'Saving…', 'secondary');
      showError(slot, '');
      const body = new FormData(slot);   // _csrf, term_id, type, side
      body.set('file', file);
      return fetch(slot.action, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        // A server error answers with an error page, not JSON: say so (the catch below is for a lost connection only).
        .then((r) => r.json().catch(() => ({ success: false, message: `The server could not save this file (error ${r.status}). Try again; if it keeps failing, tell the SK office.` })))
        .then((res) => {
          if (pending[slot.dataset.key] !== file) return true;   // a newer file was chosen meanwhile; its upload follows
          if (!res.success) {
            setStatus(slot, 'Not saved', 'danger');
            showError(slot, (res.message || 'Upload failed.') + ' Choose the file again to retry.');
            return false;
          }
          delete pending[slot.dataset.key];
          forgetPending(slot);
          showSaved(slot, res.document);
          setReady(slot.dataset.key.split('::')[1], !!res.ready);
          return true;
        })
        .catch(() => {
          setStatus(slot, 'Not saved', 'danger');
          showError(slot, 'Upload failed — check your connection, then choose the file again.');
          return false;
        });
    };
    const p = queue.then(run);
    queue = p.then(() => undefined, () => undefined);
    return p;
  }

  function choose(slot, file) {
    showError(slot, '');
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXT.includes(ext) || !ALLOWED_MIME.includes(file.type)) { showError(slot, 'Only PDF, JPG, or PNG files are allowed.'); return; }
    if (file.size > MAX_SIZE) { showError(slot, 'File is too large (max ' + config.dataset.maxText + ').'); return; }
    pending[slot.dataset.key] = file;
    keepPending(slot, file);
    showPreview(slot, file);
    upload(slot);   // auto-save: there is no separate upload button
  }

  slots.forEach((slot) => {
    const zone = slot.querySelector('[data-doc-dropzone]');
    const input = slot.querySelector('[data-doc-file]');
    if (!zone || !input) return;   // read-only: nothing to upload
    input.classList.add('d-none'); // the dropzone replaces the plain file input (kept for browsers without JavaScript)
    input.required = false;

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });
    input.addEventListener('change', () => {
      if (input.files[0]) choose(slot, input.files[0]);
      input.value = '';   // choosing the same file again (to retry) still fires "change"
    });
    ['dragenter', 'dragover'].forEach((evt) => zone.addEventListener(evt, (e) => { e.preventDefault(); zone.classList.add('doc-dragover'); }));
    ['dragleave', 'dragend', 'drop'].forEach((evt) => zone.addEventListener(evt, (e) => { e.preventDefault(); zone.classList.remove('doc-dragover'); }));
    zone.addEventListener('drop', (e) => {
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (file) choose(slot, file);
    });
    slot.addEventListener('submit', (e) => e.preventDefault());   // only the plain no-JavaScript form submits

    // Send again any file that was chosen but not saved before the page was closed or reloaded.
    pendingDb('readonly', (s) => s.get(dbKey(slot))).then((rec) => {
      if (rec && rec.blob && !pending[slot.dataset.key]) choose(slot, new File([rec.blob], rec.name, { type: rec.type }));
    });
  });

  // Leaving while a file is still uploading: warn (it is kept and sent again on return anyway).
  window.addEventListener('beforeunload', (e) => {
    if (Object.keys(pending).length) { e.preventDefault(); e.returnValue = ''; }
  });
})();
