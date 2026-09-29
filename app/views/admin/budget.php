<?php $peso = static fn($v) => '₱' . number_format((float)$v, 2); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Budget</h4>
    <p class="text-muted small mb-0">One budget per program and academic year. Releases are blocked without one.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#budgetModal" onclick="openBudgetModal()"><i class="bi bi-plus-lg"></i> Add budget</button>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Program</th><th>A.Y.</th><th>Total</th><th>Released</th><th>Remaining</th><th>Committed</th><th>After commitments</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$budgets): ?><tr><td colspan="8" class="text-center text-muted py-4">No budgets yet.</td></tr><?php endif; ?>
        <?php foreach ($budgets as $b): $after = (float)$b['remaining'] - (float)$b['committed']; ?>
          <tr>
            <td class="fw-semibold"><?= e($b['program_name']) ?></td>
            <td><?= e($b['academic_year']) ?></td>
            <td><?= e($peso($b['total_budget'])) ?></td>
            <td class="text-success"><?= e($peso($b['released'])) ?></td>
            <td class="fw-semibold"><?= e($peso($b['remaining'])) ?></td>
            <td><?= e($peso($b['committed'])) ?></td>
            <td class="<?= $after < 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= e(($after < 0 ? 'Shortfall ' : 'Surplus ') . $peso(abs($after))) ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#budgetModal" onclick='openBudgetModal(<?= js_json($b) ?>)'><i class="bi bi-pencil"></i></button>
              <?php if ((float)$b['released'] == 0.0): ?>
                <form method="POST" action="<?= url('admin/budget-delete') ?>" class="d-inline" onsubmit="return confirm('Delete this budget?');">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card border-0 shadow-sm small text-muted"><div class="card-body">
  <strong>How these numbers are computed</strong>
  <ul class="mb-0">
    <li><strong>Released</strong> = sum of every cash release to this program’s scholars for semesters of this academic year (re-summed after every release).</li>
    <li><strong>Remaining</strong> = Total − Released.</li>
    <li><strong>Committed</strong> = for each Active scholar of the program: amount per semester × semesters of this A.Y. not yet released to them (2 semesters per A.Y.).</li>
    <li><strong>After commitments</strong> = Remaining − Committed (negative = shortfall).</li>
  </ul>
</div></div>

<div class="modal fade" id="budgetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/budget-save') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" id="budgetId">
      <div class="modal-header"><h5 class="modal-title" id="budgetTitle">Add budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Program</label>
          <select name="program_id" id="budgetProgram" class="form-select" required>
            <option value="">Choose…</option>
            <?php foreach ($programs as $p): $s = $suggestions[(int)$p['id']] ?? null; ?>
              <option value="<?= (int)$p['id'] ?>" data-year="<?= e($p['academic_year']) ?>"
                <?php if ($s): ?>data-suggested="<?= e(number_format($s['total'], 2, '.', '')) ?>"
                data-suggested-text="<?= e('Suggested total budget: ' . $peso($s['total']) . ' (based on ' . $s['slots'] . ' slots × ' . $peso($s['amount']) . '/semester × 2 semesters)') ?>"<?php endif; ?>><?= e($p['program_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Academic year</label>
          <select name="academic_year" id="budgetYear" class="form-select" required>
            <option value="">Choose…</option>
            <?php foreach ($years as $y): ?><option value="<?= e($y) ?>"><?= e($y) ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">Years of programs and of scholars’ semesters (scholars renew into later years).</div>
        </div>
        <div class="mb-1">
          <label class="form-label">Total budget (₱)</label>
          <input type="number" name="total_budget" id="budgetTotal" class="form-control" min="0.01" step="0.01" required>
          <div id="budgetSuggestion" class="alert alert-light border small py-2 mt-2 mb-0 d-none">
            <span id="budgetSuggestionText"></span>
            <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="budgetUseSuggestion">Use this</button>
          </div>
          <div class="form-text">Can’t be less than what was already released for that program and year. The suggestion is optional — enter any amount.</div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
<script>
function openBudgetModal(b) {
  document.getElementById('budgetId').value = b ? b.id : '';
  document.getElementById('budgetProgram').value = b ? b.program_id : '';
  document.getElementById('budgetYear').value = b ? b.academic_year : '';
  document.getElementById('budgetTotal').value = b ? b.total_budget : '';
  document.getElementById('budgetTitle').textContent = b ? 'Edit budget' : 'Add budget';
  showBudgetSuggestion();
}
// Shows the server-computed suggestion (Budget::suggestedTotal) for the chosen program; never fills the field by itself.
function showBudgetSuggestion() {
  var sel = document.getElementById('budgetProgram'), opt = sel.options[sel.selectedIndex],
      box = document.getElementById('budgetSuggestion');
  var has = !!(opt && opt.dataset.suggested);
  box.classList.toggle('d-none', !has);
  document.getElementById('budgetSuggestionText').textContent = has ? opt.dataset.suggestedText : '';
}
document.getElementById('budgetUseSuggestion').addEventListener('click', function () {
  var sel = document.getElementById('budgetProgram'), opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.suggested) document.getElementById('budgetTotal').value = opt.dataset.suggested;
});
document.getElementById('budgetProgram').addEventListener('change', function () {
  var y = document.getElementById('budgetYear'), opt = this.options[this.selectedIndex];
  if (!y.value && opt && opt.dataset.year) y.value = opt.dataset.year;
  showBudgetSuggestion();
});
</script>
