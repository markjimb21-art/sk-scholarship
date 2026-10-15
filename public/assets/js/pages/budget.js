/**
 * Budget page: the Add / Edit modal. Each Add/Edit button carries data-budget-modal (empty for Add, the budget row as
 * JSON for Edit). The suggested total comes from the server (Budget::suggestedTotal) on each program <option>.
 * Add defaults to the current academic year (data-current-year); data-open-new (?new=1 from the Academic years page)
 * opens Add straight away with that program and year.
 */
(function () {
  'use strict';

  var modal = document.getElementById('budgetModal');
  function openBudgetModal(b, prefill) {
    var p = prefill || {};
    document.getElementById('budgetId').value = b ? b.id : '';
    document.getElementById('budgetProgram').value = b ? b.program_id : (p.program_id || '');
    document.getElementById('budgetYear').value = b ? b.academic_year : (p.academic_year || modal.dataset.currentYear || '');
    document.getElementById('budgetTotal').value = b ? b.total_budget : '';
    document.getElementById('budgetTitle').textContent = b ? 'Edit budget' : 'Add budget';
    showBudgetSuggestion();
  }
  // Shows the server-computed suggestion for the chosen program; never fills the field by itself.
  function showBudgetSuggestion() {
    var sel = document.getElementById('budgetProgram'), opt = sel.options[sel.selectedIndex],
        box = document.getElementById('budgetSuggestion');
    var has = !!(opt && opt.dataset.suggested);
    box.classList.toggle('d-none', !has);
    document.getElementById('budgetSuggestionText').textContent = has ? opt.dataset.suggestedText : '';
  }

  document.querySelectorAll('[data-budget-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openBudgetModal(btn.dataset.budgetModal ? JSON.parse(btn.dataset.budgetModal) : null); });
  });
  document.getElementById('budgetUseSuggestion').addEventListener('click', function () {
    var sel = document.getElementById('budgetProgram'), opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.suggested) document.getElementById('budgetTotal').value = opt.dataset.suggested;
  });
  document.getElementById('budgetProgram').addEventListener('change', function () {
    showBudgetSuggestion();
  });

  if (modal.dataset.openNew) {
    openBudgetModal(null, JSON.parse(modal.dataset.openNew));
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }
})();
