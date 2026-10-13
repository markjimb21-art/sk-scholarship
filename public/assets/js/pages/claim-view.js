/** Claim review: warns when the GWA typed in is worse than the program minimum (data-min-gwa). The treasurer still decides. */
(function () {
  'use strict';

  var input = document.getElementById('gwaInput'), warn = document.getElementById('gwaWarning');
  if (!input) return;
  var min = JSON.parse(input.dataset.minGwa);
  if (!min) return;
  input.addEventListener('input', function () { var v = parseFloat(input.value); warn.classList.toggle('d-none', isNaN(v) || v <= min); });
})();
