/** Final decisions page: each Reject button fills the reject modal with its applicant (data-reject-id / data-reject-name). */
(function () {
  'use strict';

  document.querySelectorAll('[data-reject-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('rejectId').value = btn.dataset.rejectId;
      document.getElementById('rejectName').textContent = btn.dataset.rejectName;
    });
  });
})();
