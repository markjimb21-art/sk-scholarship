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

<?php if ($todayInterviews !== null): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-people me-1"></i> Interviews today — <?= e(date('l, F j')) ?></h6>
    <a class="small" href="<?= url('admin/calendar') ?>">Calendar</a>
  </div>
  <?php if (!$todayInterviews): ?>
    <div class="card-body text-muted small">No interviews scheduled today.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table mb-0 align-middle small">
        <thead class="table-light"><tr><th>Time</th><th>Venue · program</th><th>Applicants</th><th>Not yet marked</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($todayInterviews as $s): ?>
            <tr>
              <td class="text-nowrap fw-semibold"><?= e(date('g:i A', strtotime($s['start_time'])) . ' – ' . date('g:i A', strtotime($s['end_time']))) ?></td>
              <td><?= e($s['venue']) ?><div class="text-muted"><?= e($s['program_name']) ?></div></td>
              <td>
                <?php if (!$s['attempts']): ?><span class="text-muted">Nobody assigned</span><?php endif; ?>
                <?php foreach ($s['attempts'] as $at): ?>
                  <div><a href="<?= url('admin/application&id=' . (int)$at['application_id']) ?>"><?= e($at['full_name']) ?></a>
                    <?php if ($at['attendance'] !== 'Scheduled'): ?><span class="badge bg-<?= $at['attendance'] === 'Attended' ? 'success' : 'danger' ?>"><?= e($at['attendance']) ?></span><?php endif; ?>
                    <?= (int)$at['reschedule_count'] ? '<span class="badge bg-warning text-dark">reschedule</span>' : '' ?></div>
                <?php endforeach; ?>
              </td>
              <td><span class="badge bg-<?= $s['unmarked'] ? 'primary' : 'secondary' ?>"><?= (int)$s['unmarked'] ?></span></td>
              <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/interview-schedule&id=' . (int)$s['id']) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <h5 class="fw-semibold mb-0">Program</h5>
  <?php if ($programs): ?>
  <form method="GET" action="<?= url('admin/dashboard') ?>">
    <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select form-select-sm" data-autosubmit>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select></div>
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
  <?php if ($showBudget): ?>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">Budget remaining — <?= e($program['academic_year']) ?></div>
      <?php if ($budget): ?>
        <div class="fs-3 fw-bold <?= (float)$budget['remaining'] < (float)$budget['committed'] ? 'text-danger' : '' ?>"><?= e(peso($budget['remaining'])) ?></div>
        <div class="small text-muted">
          Remaining = total <?= e(peso($budget['total_budget'])) ?> − released <?= e(peso($budget['released'])) ?>.
          Still committed to current scholars this A.Y.: <?= e(peso($budget['committed'])) ?>.
        </div>
      <?php else: ?>
        <div class="fs-5 text-warning">No budget yet</div>
        <div class="small"><a href="<?= url('admin/budget') ?>">Create the budget</a> — releases are blocked without one.</div>
      <?php endif; ?>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<?php require VIEW_PATH . '/admin/_dashboard_charts.php'; ?>
<?php endif; ?>
