<?php
// Dashboard charts for the selected program. Expects $program, $budget, $charts (AdminController::dashboard).
// Each chart has its numbers in a small table next to it, and an empty state when there is nothing to show.
$status = array_filter($charts['status']);
$budgetRows = $budget ? ['Total budget' => (float)$budget['total_budget'], 'Released' => (float)$budget['released'], 'Remaining' => (float)$budget['remaining']] : [];
$gwaRows = array_filter($charts['gwa']);
?>
<h5 class="fw-semibold mt-4 mb-2">Charts — <?= e($program['program_name']) ?></h5>
<div class="row g-3">
  <?php if (AdminController::canAccess('screening')): ?>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Applications by status</h6>
    <p class="small text-muted mb-2">Count of this program’s applications in each status now.</p>
    <?php if (!$status): ?><p class="text-muted small mb-0">No applications yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="dashStatus" role="img" aria-label="Applications by status"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><tbody>
        <?php foreach ($status as $s => $n): ?><tr><td><?= e(APP_STATUS_LABELS[$s] ?? $s) ?></td><td class="text-end"><?= (int)$n ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
  <?php endif; ?>

  <?php if (AdminController::canAccess('budget')): ?>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Budget — <?= e($budgetYear ?? 'no current academic year') ?></h6>
    <p class="small text-muted mb-2">Total budget, released so far, and remaining (= total − released).</p>
    <?php if (!$budgetRows): ?><p class="text-muted small mb-0">No budget for this academic year yet. <a href="<?= url('admin/budget') ?>">Create it</a>.</p><?php else: ?>
      <div style="height:180px"><canvas id="dashBudget" role="img" aria-label="Budget total, released and remaining"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><tbody>
        <?php foreach ($budgetRows as $k => $v): ?><tr><td><?= e($k) ?></td><td class="text-end"><?= e(peso($v)) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
  <?php endif; ?>

  <?php if (AdminController::canAccess('budget')): ?>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Releases per month</h6>
    <p class="small text-muted mb-2">Cash released to this program’s scholars, by release month.</p>
    <?php if (!$charts['months']): ?><p class="text-muted small mb-0">Nothing released yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="dashMonths" role="img" aria-label="Releases per month"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><tbody>
        <?php foreach ($charts['months'] as $m => $v): ?><tr><td><?= e(date('F Y', strtotime($m . '-01'))) ?></td><td class="text-end"><?= e(peso($v)) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
  <?php endif; ?>

  <?php if (AdminController::canAccess('scholars')): ?>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Active scholars by GWA</h6>
    <p class="small text-muted mb-2">Latest recorded semester GWA (else the application GWA); 1.00 is highest.</p>
    <?php if (!$gwaRows): ?><p class="text-muted small mb-0">No active scholars yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="dashGwa" role="img" aria-label="Active scholars by GWA band"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><tbody>
        <?php foreach ($charts['gwa'] as $band => $n): ?><tr><td><?= e($band) ?></td><td class="text-end"><?= (int)$n ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
  <?php endif; ?>
</div>
<script type="application/json" id="dashboardChartData"><?= js_json([
  'status' => ['labels' => array_map(static fn($s) => APP_STATUS_LABELS[$s] ?? $s, array_keys($status)), 'values' => array_values($status)],
  'budget' => ['labels' => array_keys($budgetRows), 'values' => array_values($budgetRows)],
  'months' => ['labels' => array_map(static fn($m) => date('M Y', strtotime($m . '-01')), array_keys($charts['months'])), 'values' => array_values($charts['months'])],
  'gwa' => ['labels' => array_keys($charts['gwa']), 'values' => array_values($charts['gwa'])],
]) ?></script>
