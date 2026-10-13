/**
 * One interview schedule: counts the applicants ticked for assignment and enables "Assign and notify" only for
 * 1..free seats (data-max on the button). Convenience only: the server re-checks capacity for every applicant.
 */
(function () {
  'use strict';

  var boxes = document.querySelectorAll('.assign-box'), btn = document.getElementById('assignBtn'), out = document.getElementById('assignCount');
  if (!btn) return;
  var max = parseInt(btn.dataset.max, 10);
  boxes.forEach(function (b) { b.addEventListener('change', function () {
    var n = document.querySelectorAll('.assign-box:checked').length;
    out.textContent = n + ' selected (max ' + max + ')';
    btn.disabled = n === 0 || n > max;
  }); });
})();
