<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Budget Management</h4>
    <p class="text-muted small mb-0"><?= number_format(count($budgets)) ?> budget record(s)</p>
  </div>
  <?php if ($canEdit): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#budgetModal" onclick="openBudgetModal()">
      <i class="bi bi-plus-lg me-1"></i>Add Budget
    </button>
  <?php endif; ?>
</div>

<?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-wallet2"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)$summary['total_budget'], 2) ?></div><div class="kpi-label">Total Budget</div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-primary"><i class="bi bi-bar-chart"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)$summary['total_allocated'], 2) ?></div><div class="kpi-label">Total Allocated</div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)$summary['total_released'], 2) ?></div><div class="kpi-label">Total Released <span class="text-muted">(<?= e((string)$summary['utilization_pct']) ?>%)</span></div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-<?= (float)$summary['remaining'] < 0 ? 'danger' : 'primary' ?>"><i class="bi bi-piggy-bank"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)$summary['remaining'], 2) ?></div><div class="kpi-label">Remaining Budget</div></div></div></div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Scholarship Program</th><th>Academic Year</th><th>Total Budget</th><th>Allocated</th>
          <th>Released</th><th>Remaining</th><th>Notes</th><th>Created</th>
          <?php if ($canEdit): ?><th class="text-end">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$budgets): ?><tr><td colspan="9" class="text-center text-muted py-4">No budget records yet.</td></tr><?php endif; ?>
        <?php foreach ($budgets as $b): ?>
          <tr>
            <td class="fw-semibold"><?= e($b['program_name']) ?></td>
            <td><?= e($b['academic_year']) ?></td>
            <td>₱<?= number_format((float)$b['total_budget'], 2) ?></td>
            <td>₱<?= number_format((float)$b['allocated'], 2) ?></td>
            <td class="text-success">₱<?= number_format((float)$b['released'], 2) ?></td>
            <td class="<?= (float)$b['remaining'] < 0 ? 'text-danger fw-bold' : '' ?>">₱<?= number_format((float)$b['remaining'], 2) ?></td>
            <td><small class="text-muted"><?= e($b['notes'] ?? '—') ?></small></td>
            <td><small><?= e(date('M d, Y', strtotime($b['created_at']))) ?></small></td>
            <?php if ($canEdit): ?>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary"
                        onclick='openBudgetModal(<?= js_json($b) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" action="<?= url('admin/budget-delete') ?>" class="d-inline"
                      onsubmit="return confirm('Delete the budget for <?= e(addslashes($b['program_name'])) ?> (<?= e($b['academic_year']) ?>)? This cannot be undone.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canEdit): ?>
<div class="modal fade" id="budgetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/budget-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="budgetId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="budgetModalTitle">Add Budget</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Scholarship Program</label>
          <select name="program_id" id="budgetProgramId" class="form-select" required>
            <option value="">Select program…</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-year="<?= e($p['academic_year']) ?>"><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Academic Year</label>
          <select name="academic_year" id="budgetYear" class="form-select" required>
            <option value="">Select academic year…</option>
            <?php foreach ($years as $y): ?>
              <option value="<?= e($y) ?>"><?= e($y) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Years come from existing scholarship programs and budgets. For a new year, create the program first.</div>
        </div>
        <div class="row g-2">
          <div class="col-6 mb-3">
            <label class="form-label">Total Budget (₱)</label>
            <input type="number" name="total_budget" id="budgetTotal" class="form-control" min="0" step="0.01" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label">Allocated (₱)</label>
            <input type="number" name="allocated" id="budgetAllocated" class="form-control" min="0" step="0.01" value="0">
          </div>
        </div>
        <div id="budgetAllocError" class="alert alert-danger py-2 small d-none">Allocated amount cannot exceed the total budget.</div>
        <div class="mb-1">
          <label class="form-label">Notes</label>
          <textarea name="notes" id="budgetNotes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="budgetSubmitBtn">Save Budget</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBudgetModal(b) {
  document.getElementById('budgetId').value = b ? b.id : '';
  document.getElementById('budgetProgramId').value = b ? b.program_id : '';
  document.getElementById('budgetYear').value = b ? b.academic_year : '';
  document.getElementById('budgetTotal').value = b ? b.total_budget : '';
  document.getElementById('budgetAllocated').value = b ? b.allocated : '0';
  document.getElementById('budgetNotes').value = b ? (b.notes || '') : '';
  document.getElementById('budgetModalTitle').textContent = b ? 'Edit Budget' : 'Add Budget';
}
// Client-side convenience only — the server re-validates everything (allocated <= total, non-negative, uniqueness).
(function () {
  var total = document.getElementById('budgetTotal'), alloc = document.getElementById('budgetAllocated'),
      err = document.getElementById('budgetAllocError'), btn = document.getElementById('budgetSubmitBtn');
  function check() {
    var bad = parseFloat(alloc.value || '0') > parseFloat(total.value || '0');
    err.classList.toggle('d-none', !bad);
    btn.disabled = bad;
  }
  total.addEventListener('input', check);
  alloc.addEventListener('input', check);
  // Default the year to the chosen program's academic year when none is picked yet.
  var prog = document.getElementById('budgetProgramId'), year = document.getElementById('budgetYear');
  prog.addEventListener('change', function () {
    var opt = prog.options[prog.selectedIndex];
    if (!year.value && opt && opt.dataset.year) year.value = opt.dataset.year;
  });
})();
</script>
<?php endif; ?>
