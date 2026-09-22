<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h3 class="fw-bold mb-1">SK Admin Dashboard</h3>
    <p class="text-muted small mb-0">Real-time overview of the scholarship program</p>
  </div>
  <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('F d, Y (l)') ?></div>
</div>

<!-- KPI CARDS ROW 1 -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-primary"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($appStats['total'] ?? 0) ?></div>
        <div class="kpi-label">Total Applications</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-warning"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($appStats['pending'] ?? 0) ?></div>
        <div class="kpi-label">Pending</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-info"><i class="bi bi-calendar-check"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($appStats['for_interview'] ?? 0) ?></div>
        <div class="kpi-label">For Interview</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-success"><i class="bi bi-check2-circle"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($appStats['approved'] ?? 0) ?></div>
        <div class="kpi-label">Approved</div>
      </div>
    </div>
  </div>
</div>

<!-- KPI CARDS ROW 2 -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-danger"><i class="bi bi-x-circle"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($appStats['rejected'] ?? 0) ?></div>
        <div class="kpi-label">Rejected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-purple"><i class="bi bi-mortarboard"></i></div>
      <div>
        <div class="kpi-value"><?= (int)($scholarStats['active'] ?? 0) ?></div>
        <div class="kpi-label">Active Scholars</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-success"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)($fundsRow['total_released'] ?? 0), 0) ?></div>
        <div class="kpi-label">Funds Released</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon kpi-info"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format((float)($budgetRow['total_budget'] ?? 0), 0) ?></div>
        <div class="kpi-label">Total Budget</div>
      </div>
    </div>
  </div>
</div>

<?php
// Fetch at-risk + pending docs
$atRiskScholars = \Scholar::atRiskScholars(2.50);
$pendingDocs    = \Document::pendingCount();
?>

<?php if ($pendingDocs > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
  <div>
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong><?= $pendingDocs ?></strong> document(s) are awaiting verification.
  </div>
  <a href="<?= url('admin/documents&status=Submitted') ?>" class="btn btn-sm btn-warning">
    Review Documents
  </a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-1"></i> At-Risk Scholars</h6>
        <a href="<?= url('admin/academic-records') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="table-responsive" style="max-height: 260px;">
        <table class="table table-sm mb-0 align-middle small">
          <thead class="table-light"><tr><th>Scholar</th><th>School</th><th>GPA</th></tr></thead>
          <tbody>
            <?php if (!$atRiskScholars): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">No at-risk scholars.</td></tr>
            <?php endif; ?>
            <?php foreach (array_slice($atRiskScholars, 0, 8) as $s): ?>
              <tr>
                <td><a href="<?= url('admin/scholar-view&id=' . (int)$s['id']) ?>"><?= e($s['full_name']) ?></a></td>
                <td><small class="text-muted"><?= e($s['school_name'] ?? '—') ?></small></td>
                <td><span class="badge bg-warning text-dark"><?= e((string)$s['gpa']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-calendar-week text-primary me-1"></i> This Week's Interviews</h6>
        <a href="<?= url('admin/interview-calendar') ?>" class="btn btn-sm btn-outline-secondary">Calendar</a>
      </div>
      <div class="card-body small">
        <?php
          $weekInterviews = $pdo->query(
            "SELECT s.*, (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id=s.id) AS assigned
             FROM interview_schedules s
             WHERE s.interview_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND s.status <> 'Cancelled'
             ORDER BY s.interview_date ASC, s.start_time ASC"
          )->fetchAll();
        ?>
        <?php if (!$weekInterviews): ?>
          <p class="text-muted text-center py-3 mb-0">No interviews scheduled this week.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($weekInterviews as $s): ?>
              <li class="mb-2 pb-2 border-bottom">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="fw-semibold"><?= e(date('D, M d', strtotime($s['interview_date']))) ?></div>
                    <small class="text-muted"><?= e(date('g:i A', strtotime($s['start_time']))) ?> · <?= e($s['venue']) ?></small>
                  </div>
                  <span class="badge bg-primary align-self-center"><?= (int)$s['assigned'] ?>/<?= (int)$s['max_slots'] ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><h6 class="mb-0">Applications by Status</h6></div>
      <div class="card-body"><canvas id="chartStatus" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><h6 class="mb-0">Applications Over Time (12 months)</h6></div>
      <div class="card-body"><canvas id="chartTrend" height="200"></canvas></div>
    </div>
  </div>
</div>

<!-- UPCOMING INTERVIEWS -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Upcoming Interview Schedules</h6>
    <a href="<?= url('admin/interview-scheduling') ?>" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Date</th><th>Time</th><th>Venue</th><th>Interviewer</th><th>Assigned</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (!$upcomingInterviews): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No upcoming interviews.</td></tr>
        <?php endif; ?>
        <?php foreach ($upcomingInterviews as $s):
          $pct = ((int)$s['assigned']) . '/' . (int)$s['max_slots'];
        ?>
          <tr>
            <td><?= e(date('M d, Y', strtotime($s['interview_date']))) ?></td>
            <td><?= e(date('g:i A', strtotime($s['start_time']))) ?> – <?= e(date('g:i A', strtotime($s['end_time']))) ?></td>
            <td><?= e($s['venue']) ?></td>
            <td><?= e($s['interviewer']) ?></td>
            <td><span class="badge bg-<?= (int)$s['assigned'] >= (int)$s['max_slots'] ? 'danger' : 'primary' ?>"><?= e($pct) ?></span></td>
            <td><span class="badge bg-secondary"><?= e($s['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$statusLabels = array_column($statusDist, 'status');
$statusCounts = array_column($statusDist, 'c');
$trendLabels  = array_column($overTime, 'ym');
$trendCounts  = array_column($overTime, 'c');
$pageScripts = [];
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const statusCtx = document.getElementById('chartStatus');
  if (statusCtx) new Chart(statusCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($statusLabels) ?>,
      datasets: [{
        data: <?= json_encode($statusCounts) ?>,
        backgroundColor: ['#1d4ed8','#0891b2','#f59e0b','#dc2626','#16a34a','#7c3aed','#64748b','#be185d','#0ea5e9','#84cc16']
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'right', labels: {boxWidth:12, font:{size:11}} } } }
  });

  const trendCtx = document.getElementById('chartTrend');
  if (trendCtx) new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode($trendLabels) ?>,
      datasets: [{
        label: 'Applications',
        data: <?= json_encode($trendCounts) ?>,
        borderColor: '#1d4ed8', backgroundColor: 'rgba(29,78,216,.1)',
        fill: true, tension: .35
      }]
    },
    options: { plugins: { legend: {display:false} }, scales: { y: { beginAtZero: true, ticks: {precision:0} } } }
  });
});
</script>