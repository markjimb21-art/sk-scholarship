/** Interview scheduling: a suggested slot button (data-slot-date/-start/-end) fills the new-schedule form. */
(function () {
  'use strict';

  document.querySelectorAll('[data-slot-date]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('schedDate').value = b.dataset.slotDate;
      document.getElementById('schedStart').value = b.dataset.slotStart;
      document.getElementById('schedEnd').value = b.dataset.slotEnd;
      document.querySelectorAll('[data-slot-date]').forEach(function (o) { o.classList.toggle('active', o === b); });
    });
  });
})();
