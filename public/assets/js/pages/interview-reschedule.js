/** Reschedule an interview: a suggested slot button (data-slot-date/-start/-end) fills the form and moves focus to it. */
(function () {
  'use strict';

  document.querySelectorAll('[data-slot-date]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('rsDate').value = b.dataset.slotDate;
      document.getElementById('rsStart').value = b.dataset.slotStart;
      document.getElementById('rsEnd').value = b.dataset.slotEnd;
      document.getElementById('rsDate').focus();
    });
  });
})();
