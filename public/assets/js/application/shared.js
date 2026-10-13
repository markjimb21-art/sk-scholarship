/**
 * Application wizard — shared by the other application/*.js files (load this first): the wizard element, its settings
 * (data-* attributes set by the server), the per-applicant storage keys and the autosave status indicator.
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

  window.SKApplication = {
    wizard, editable, userId, autosaveUrl, uploadUrl, csrfToken, serverUpdatedAt,
    appId, storageKey, stepStorageKey, readStore, writeStore, setIndicator,
  };
})();
