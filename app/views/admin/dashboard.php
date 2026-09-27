<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">Needs your action</h4>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($counters as [$label, $n, $route, $icon, $formula]): ?>
    <div class="col-sm-6 col-lg-3">
      <a href="<?= url($route) ?>" class="text-decoration-none">
        <div class="kpi-card h-100 <?= $n ? '' : 'opacity-75' ?>">
          <div class="kpi-icon kpi-<?= $n ? 'primary' : 'info' ?>"><i class="bi <?= e($icon) ?>"></i></div>
          <div>
            <div class="kpi-value text-dark"><?= (int)$n ?></div>
            <div class="kpi-label"><?= e($label) ?></div>
            <div class="small text-muted"><?= e($formula) ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <h5 class="fw-semibold mb-0">Program</h5>
  <?php if ($programs): ?>
  <form method="GET" action="<?= url('admin/dashboard') ?>">
    <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<?php if (!$program): ?>
  <div class="alert alert-secondary">No scholarship program yet. <a href="<?= url('admin/programs') ?>">Create one</a>.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Slots used</div>
      <div class="fs-3 fw-bold"><?= (int)$slotsUsed ?> / <?= (int)$program['slots_available'] ?></div>
      <div class="progress my-2" style="height:8px">
        <div class="progress-bar" style="width: <?= $program['slots_available'] > 0 ? min(100, round($slotsUsed / $program['slots_available'] * 100)) : 0 ?>%"></div>
      </div>
      <div class="small text-muted">Scholars from this program, not counting scholars ended before a second semester opened.</div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Budget remaining — <?= e($program['academic_year']) ?></div>
      <?php if ($budget): ?>
        <div class="fs-3 fw-bold <?= (float)$budget['remaining'] < (float)$budget['committed'] ? 'text-danger' : '' ?>">₱<?= e(number_format((float)$budget['remaining'], 2)) ?></div>
        <div class="small text-muted">
          Remaining = total ₱<?= e(number_format((float)$budget['total_budget'], 2)) ?> − released ₱<?= e(number_format((float)$budget['released'], 2)) ?>.
          Still committed to current scholars this A.Y.: ₱<?= e(number_format((float)$budget['committed'], 2)) ?>.
        </div>
      <?php else: ?>
        <div class="fs-5 text-warning">No budget yet</div>
        <div class="small"><a href="<?= url('admin/budget') ?>">Create the budget</a> — releases are blocked without one.</div>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>
