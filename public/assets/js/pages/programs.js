/**
 * Programs page: the Add / Edit modal (data-program-modal: empty for Add, the program row as JSON for Edit) and the
 * "closing date before opening date" check. Convenience only — the server re-validates everything.
 */
(function () {
  'use strict';

  function openProgramModal(p) {
    var set = function (id, v) { document.getElementById(id).value = (v === null || v === undefined) ? '' : v; };
    set('programId', p ? p.id : '');
    set('pName', p ? p.program_name : '');
    set('pYear', p ? p.academic_year : '');
    set('pStart', p ? p.application_start : '');
    set('pEnd', p ? p.application_end : '');
    set('pSlots', p ? p.slots_available : '');
    set('pAmount', p ? p.amount_per_semester : '');
    set('pMinGpa', p ? p.min_gpa : '');
    set('pMaxIncome', p ? p.max_family_income : '');
    set('pMaxAge', p ? p.max_age : '');
    document.getElementById('pActive').checked = p ? !!Number(p.is_active) : false;
    document.getElementById('programModalTitle').textContent = p ? 'Edit Program' : 'Add Program';
    checkProgramDates();
  }
  function checkProgramDates() {
    var s = document.getElementById('pStart').value, e = document.getElementById('pEnd').value, bad = s && e && e < s;
    document.getElementById('programDateError').classList.toggle('d-none', !bad);
    document.getElementById('programSubmitBtn').disabled = bad;
  }

  document.querySelectorAll('[data-program-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openProgramModal(btn.dataset.programModal ? JSON.parse(btn.dataset.programModal) : null); });
  });
  document.getElementById('pStart').addEventListener('input', checkProgramDates);
  document.getElementById('pEnd').addEventListener('input', checkProgramDates);
})();
