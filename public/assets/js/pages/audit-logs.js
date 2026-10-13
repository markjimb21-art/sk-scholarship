/** Audit log: the "what changed" modal shows the old and new values (data-old / data-new on the button) as indented JSON. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('diffModal');
    modal.addEventListener('show.bs.modal', e => {
      const b = e.relatedTarget;
      modal.querySelector('#diffAction').textContent = b.dataset.action;
      try {
        const oldV = JSON.parse(b.dataset.old || '{}');
        const newV = JSON.parse(b.dataset.new || '{}');
        modal.querySelector('#diffOld').textContent = JSON.stringify(oldV, null, 2);
        modal.querySelector('#diffNew').textContent = JSON.stringify(newV, null, 2);
      } catch (err) {
        modal.querySelector('#diffOld').textContent = b.dataset.old;
        modal.querySelector('#diffNew').textContent = b.dataset.new;
      }
    });
  });
})();
