/** Interview score sheet: live total of the criteria scores (the server computes the stored total itself). */
(function () {
  'use strict';

  var inputs = document.querySelectorAll('.score-input'), out = document.getElementById('scoreTotal');
  function sum() { var t = 0; inputs.forEach(function (i) { var v = parseFloat(i.value); if (!isNaN(v)) t += v; }); out.textContent = t.toFixed(2); }
  inputs.forEach(function (i) { i.addEventListener('input', sum); });
})();
