/**
 * Programs page: the Add / Edit modal (data-program-modal: empty for Add, the program row as JSON for Edit) and the
 * "closing date before opening date" check. Convenience only — the server re-validates everything.
 * New program only (decision D9): the "Renewal for current scholars" and "Budget" sections (#pSetup), filled in from
 * the application dates and from slots × amount × 2 until the admin types their own; data-reopen on the modal = a
 * New program form the server refused (values + error), opened again.
 */
(function () {
  'use strict';

  var setup = document.getElementById('pSetup');
  var renewalCounts = JSON.parse(setup.dataset.renewalCounts || '{}');
  var semesters = Number(setup.dataset.semesters) || 2;
  var renewTyped = false, budgetTyped = false;
  var val = function (id) { return document.getElementById(id).value; };
  var set = function (id, v) { document.getElementById(id).value = (v === null || v === undefined) ? '' : v; };
  var peso = function (n) { return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

  function openProgramModal(p) {
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
    document.getElementById('programFormError').classList.add('d-none');
    // Editing changes the program only; its window and budget keep their own pages.
    setup.classList.toggle('d-none', !!p);
    document.getElementById('pWithRenewal').checked = true;
    document.getElementById('pWithBudget').checked = true;
    renewTyped = budgetTyped = false;
    set('pRenewOpens', ''); set('pRenewDeadline', ''); set('pBudgetTotal', '');
    applySetupToggles(!!p);
    checkProgramDates();
    updateBudget();
  }
  function checkProgramDates() {
    var s = val('pStart'), e = val('pEnd'), bad = s && e && e < s;
    document.getElementById('programDateError').classList.toggle('d-none', !bad);
    document.getElementById('programSubmitBtn').disabled = bad;
    suggestYear(s);
    fillRenewalDates();
    updateRenewInfo();
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
  yearInput.addEventListener('input', function () { yearTyped = yearInput.value.trim() !== ''; updateRenewInfo(); });

  /* Renewal window: opens at the application start (00:00), closes at the application end (23:59), until typed. */
  function fillRenewalDates() {
    if (renewTyped) return;
    var s = val('pStart'), e = val('pEnd');
    set('pRenewOpens', s ? s + 'T00:00' : '');
    set('pRenewDeadline', e ? e + 'T23:59' : '');
  }
  function updateRenewInfo() {
    var m = /^(\d{4})-(\d{4})$/.exec(yearInput.value.trim()), info = document.getElementById('pRenewInfo');
    if (!m) { info.textContent = 'A renewal window on this program, into the 1st Semester of its academic year. Filled in from the application dates; change them if needed.'; return; }
    var prev = (Number(m[1]) - 1) + '-' + m[1], n = renewalCounts[prev] || 0;
    info.textContent = 'Into ' + m[0] + ' 1st Semester. Goes to ' + n + ' Active scholar' + (n === 1 ? '' : 's')
      + ' whose latest semester is in ' + prev + ' (they are e-mailed once everything is saved). Filled in from the application dates; change them if needed.';
  }

  /* Budget: slots × amount per semester × 2, until typed. */
  function updateBudget() {
    var slots = Number(val('pSlots')), amount = Number(val('pAmount')), f = document.getElementById('pBudgetFormula');
    if (!(slots > 0 && amount > 0)) { f.textContent = 'Suggested: slots × ₱ per semester × ' + semesters + ' semesters.'; return; }
    var total = Math.round(slots * amount * semesters * 100) / 100;
    f.textContent = 'Suggested: ' + slots + ' slot' + (slots === 1 ? '' : 's') + ' × ' + peso(amount) + ' × ' + semesters + ' semesters = ' + peso(total)
      + (budgetTyped && Number(val('pBudgetTotal')) !== total ? ' (you entered your own amount)' : '') + '.';
    if (!budgetTyped) set('pBudgetTotal', total.toFixed(2));
  }

  /* A section that is not ticked is hidden and not sent (its inputs disabled); on Edit the whole setup is. */
  function applySetupToggles(editing) {
    setup.querySelectorAll('[data-setup-toggle]').forEach(function (box) {
      var on = !editing && box.checked, fields = document.getElementById(box.dataset.setupToggle);
      box.disabled = editing;
      fields.classList.toggle('d-none', !on);
      fields.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
    });
  }
  setup.querySelectorAll('[data-setup-toggle]').forEach(function (box) { box.addEventListener('change', function () { applySetupToggles(false); }); });

  document.querySelectorAll('[data-program-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openProgramModal(btn.dataset.programModal ? JSON.parse(btn.dataset.programModal) : null); });
  });
  document.getElementById('pStart').addEventListener('input', checkProgramDates);
  document.getElementById('pEnd').addEventListener('input', checkProgramDates);
  ['pSlots', 'pAmount'].forEach(function (id) { document.getElementById(id).addEventListener('input', updateBudget); });
  document.getElementById('pBudgetTotal').addEventListener('input', function () { budgetTyped = val('pBudgetTotal') !== ''; updateBudget(); });
  ['pRenewOpens', 'pRenewDeadline'].forEach(function (id) { document.getElementById(id).addEventListener('input', function () { renewTyped = true; }); });

  // The server refused a New program form: open it again with what was typed and the error (the flash says it too).
  var modal = document.getElementById('programModal');
  if (modal.dataset.reopen) {
    document.addEventListener('DOMContentLoaded', function () {
      var r = JSON.parse(modal.dataset.reopen), d = r.data || {};
      var add = document.querySelector('[data-program-modal]:not([data-program-modal^="{"])');
      if (add) add.click();
      ['program_name:pName', 'application_start:pStart', 'application_end:pEnd', 'academic_year:pYear', 'slots_available:pSlots',
        'amount_per_semester:pAmount', 'min_gpa:pMinGpa', 'max_family_income:pMaxIncome', 'max_age:pMaxAge',
        'renewal_opens_at:pRenewOpens', 'renewal_deadline:pRenewDeadline', 'budget_total:pBudgetTotal'].forEach(function (pair) {
        var k = pair.split(':'); if (d[k[0]] !== undefined) set(k[1], d[k[0]]);
      });
      document.getElementById('pActive').checked = !!d.is_active;
      document.getElementById('pWithRenewal').checked = !!d.with_renewal;
      document.getElementById('pWithBudget').checked = !!d.with_budget;
      yearTyped = !!d.academic_year; renewTyped = !!(d.renewal_opens_at || d.renewal_deadline); budgetTyped = !!d.budget_total;
      applySetupToggles(false);
      checkProgramDates();
      updateBudget();
      var err = document.getElementById('programFormError');
      err.textContent = r.error + ' Nothing was saved — correct it and save again.';
      err.classList.remove('d-none');
    });
  }
})();
