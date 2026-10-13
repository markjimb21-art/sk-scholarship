/** Application wizard: remember which step was open, and reopen it after a reload (convenience only). Needs application/shared.js. */
(function () {
  'use strict';

  const A = window.SKApplication;
  if (!A || !A.editable) return; // no wizard, or a read-only application: nothing to wire up
  const { wizard, stepStorageKey } = A;

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
})();
