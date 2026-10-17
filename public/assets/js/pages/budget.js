/**
 * Budget page: the Add / Edit modal. Each Add/Edit button carries data-budget-modal (empty for Add, the budget row as
 * JSON for Edit). The suggested total comes from the server (Budget::suggestedTotal) on each program <option>.
 */
(function () {
  'use strict';

  function openBudgetModal(b) {
    document.getElementById('budgetId').value = b ? b.id : '';
    document.getElementById('budgetProgram').value = b ? b.program_id : '';
    document.getElementById('budgetYear').value = b ? b.academic_year : '';
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
    var y = document.getElementById('budgetYear'), opt = this.options[this.selectedIndex];
    if (!y.value && opt && opt.dataset.year) y.value = opt.dataset.year;
    showBudgetSuggestion();
  });
})();
