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
    // A program with scholars keeps its A.Y. (their semesters use it); otherwise it follows the opening date until typed.
    fixedYear = p && Number(p.scholar_count) > 0;
    set('pYear', p ? p.academic_year : '');
    yearTyped = !!p;
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
    suggestYear(s);
  }

  /* The academic year from the opening date (Program::academicYearFor — same rule), filled in until the admin types one. */
  var fixedYear = false, yearTyped = false;
  var yearInput = document.getElementById('pYear');
  function suggestYear(start) {
    yearInput.readOnly = fixedYear;
    document.getElementById('pYearRule').textContent = fixedYear
      ? 'Fixed: this program already has scholars, whose semesters use this academic year.'
      : 'Filled in from the opening date (June–December → that year\'s A.Y., January–May → the A.Y. that began the year before); change it if needed. Semester 1 of approved scholars is its 1st Semester.';
    if (fixedYear || yearTyped || !/^\d{4}-\d{2}/.test(start)) return;
    var y = Number(start.slice(0, 4));
    if (Number(start.slice(5, 7)) < Number(yearInput.dataset.month)) y--;
    yearInput.value = y + '-' + (y + 1);
  }
  yearInput.addEventListener('input', function () { yearTyped = yearInput.value.trim() !== ''; });

  document.querySelectorAll('[data-program-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openProgramModal(btn.dataset.programModal ? JSON.parse(btn.dataset.programModal) : null); });
  });
  document.getElementById('pStart').addEventListener('input', checkProgramDates);
  document.getElementById('pEnd').addEventListener('input', checkProgramDates);
})();
