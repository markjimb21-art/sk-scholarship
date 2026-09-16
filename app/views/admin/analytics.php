<?php
$toChart = function(array $rows, string $key = 'value'): array {
    return [
        'labels' => array_map(fn($r) => $r['label'], $rows),
        'values' => array_map(fn($r) => (float)$r[$key], $rows),
    ];
};
$colors = ['#1d4ed8','#0891b2','#f59e0b','#dc2626','#16a34a','#7c3aed','#64748b','#be185d','#0ea5e9','#84cc16','#f97316','#14b8a6'];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Data Analytics</h4>
    <p class="text-muted small mb-0">Evidence-based decision support for SK officials</p>
  </div>
  <form method="GET" action="<?= url('admin/analytics') ?>" class="d-flex gap-2">
    <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All Programs</option>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- KPI GRID -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-primary"><i class="bi bi-file-earmark-text"></i></div><div><div class="kpi-value"><?= $kpis['total_applicants'] ?></div><div class="kpi-label">Total Applicants</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-check2-circle"></i></div><div><div class="kpi-value"><?= $kpis['approved'] ?></div><div class="kpi-label">Approved</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-mortarboard"></i></div><div><div class="kpi-value"><?= $kpis['active_scholars'] ?></div><div class="kpi-label">Active Scholars</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-warning"><i class="bi bi-hourglass"></i></div><div><div class="kpi-value"><?= $kpis['pending'] ?></div><div class="kpi-label">Pending</div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-purple"><i class="bi bi-calendar-check"></i></div><div><div class="kpi-value"><?= $kpis['attendance_rate'] ?>%</div><div class="kpi-label">Attendance Rate</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-graph-up-arrow"></i></div><div><div class="kpi-value"><?= $kpis['approval_rate'] ?>%</div><div class="kpi-label">Approval Rate</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-journal"></i></div><div><div class="kpi-value"><?= $kpis['avg_gpa'] ?? '—' ?></div><div class="kpi-label">Average GPA</div></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-danger"><i class="bi bi-exclamation-triangle"></i></div><div><div class="kpi-value"><?= $atRisk ?></div><div class="kpi-label">At-Risk Scholars</div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format($kpis['total_released'], 0) ?></div><div class="kpi-label">Total Released</div></div></div></div>
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-wallet2"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format($kpis['total_budget'], 0) ?></div><div class="kpi-label">Total Budget</div></div></div></div>
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-<?= $kpis['remaining_budget'] < 0 ? 'danger' : 'primary' ?>"><i class="bi bi-piggy-bank"></i></div><div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format($kpis['remaining_budget'], 0) ?></div><div class="kpi-label">Remaining</div></div></div></div>
</div>

<!-- APPLICANT ANALYTICS -->
<h6 class="fw-semibold mt-4 mb-2"><i class="bi bi-people me-1"></i> Applicant Analytics</h6>
<div class="row g-3">
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By Status</h6></div><div class="card-body"><canvas id="cStatus" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By Year Level</h6></div><div class="card-body"><canvas id="cYear" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By School</h6></div><div class="card-body"><canvas id="cSchool" height="200"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By Course</h6></div><div class="card-body"><canvas id="cCourse" height="200"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By Civil Status</h6></div><div class="card-body"><canvas id="cCivil" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">By Purok</h6></div><div class="card-body"><canvas id="cPurok" height="180"></canvas></div></div></div>
</div>

<!-- FINANCIAL -->
<h6 class="fw-semibold mt-4 mb-2"><i class="bi bi-cash me-1"></i> Family & Financial Analytics</h6>
<div class="row g-3">
  <div class="col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Applicants by Family Income Range</h6></div><div class="card-body"><canvas id="cIncome" height="150"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Average Parental Income</h6></div>
    <div class="card-body d-flex flex-column justify-content-center">
      <div class="mb-3"><div class="text-muted small">Father</div><div class="fw-bold fs-5">₱<?= number_format((float)($avgIncome['avg_father'] ?? 0), 2) ?></div></div>
      <div class="mb-3"><div class="text-muted small">Mother</div><div class="fw-bold fs-5">₱<?= number_format((float)($avgIncome['avg_mother'] ?? 0), 2) ?></div></div>
      <div><div class="text-muted small">Combined Average</div><div class="fw-bold fs-5 text-success">₱<?= number_format((float)($avgIncome['avg_combined'] ?? 0), 2) ?></div></div>
    </div>
  </div></div>
</div>

<!-- INTERVIEW -->
<h6 class="fw-semibold mt-4 mb-2"><i class="bi bi-calendar-check me-1"></i> Interview Analytics</h6>
<div class="row g-3">
  <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Interview Results</h6></div><div class="card-body"><canvas id="cIntRes" height="180"></canvas></div></div></div>
  <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Attendance Trend (12 months)</h6></div><div class="card-body"><canvas id="cIntTrend" height="180"></canvas></div></div></div>
  <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-0">Average Score per Criterion (0–20)</h6></div><div class="card-body"><canvas id="cIntScores" height="90"></canvas></div></div></div>
</div>

<!-- ACADEMIC -->
<h6 class="fw-semibold mt-4 mb-2"><i class="bi bi-journal-text me-1"></i> Academic Analytics</h6>
<div class="row g-3">
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">GPA Distribution</h6></div><div class="card-body"><canvas id="cGpaDist" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">GPA Trend by Academic Year</h6></div><div class="card-body"><canvas id="cGpaTrend" height="180"></canvas></div></div></div>
  <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-0">Average GPA by School</h6></div><div class="card-body"><canvas id="cGpaSchool" height="90"></canvas></div></div></div>
</div>

<!-- SCHOLARSHIP / BUDGET -->
<h6 class="fw-semibold mt-4 mb-2"><i class="bi bi-award me-1"></i> Scholarship & Budget Analytics</h6>
<div class="row g-3">
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Scholars by Status</h6></div><div class="card-body"><canvas id="cSchStatus" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Renewal Statuses</h6></div><div class="card-body"><canvas id="cRenewals" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Releases by Academic Year</h6></div><div class="card-body"><canvas id="cRelYear" height="180"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Releases by Month (12 months)</h6></div><div class="card-body"><canvas id="cRelMonth" height="180"></canvas></div></div></div>
  <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-0">Budget Utilization per Program</h6></div><div class="card-body"><canvas id="cBudget" height="100"></canvas></div></div></div>
</div>

<script>
const C = {
  status:  <?= json_encode($toChart($byStatus)) ?>,
  year:    <?= json_encode($toChart($byYear)) ?>,
  school:  <?= json_encode($toChart($bySchool)) ?>,
  course:  <?= json_encode($toChart($byCourse)) ?>,
  civil:   <?= json_encode($toChart($byCivil)) ?>,
  purok:   <?= json_encode($toChart($byPurok)) ?>,
  income:  <?= json_encode($toChart($byIncome)) ?>,
  intRes:  <?= json_encode($toChart($interviewResults)) ?>,
  intTrend:<?= json_encode(['labels'=>array_column($interviewTrend,'label'),
                            'completed'=>array_map('intval',array_column($interviewTrend,'completed')),
                            'noshow'=>array_map('intval',array_column($interviewTrend,'no_shows'))]) ?>,
  intScores: <?= json_encode($interviewScores) ?>,
  gpaDist: <?= json_encode($toChart($gpaDistribution)) ?>,
  gpaTrend:<?= json_encode(['labels'=>array_column($gpaTrend,'label'),
                            'values'=>array_map(fn($r)=>round((float)$r['value'],2), $gpaTrend)]) ?>,
  gpaSchool:<?= json_encode(['labels'=>array_column($gpaBySchool,'label'),
                             'values'=>array_map(fn($r)=>round((float)$r['value'],2), $gpaBySchool)]) ?>,
  schStatus:<?= json_encode($toChart($scholarsByStatus)) ?>,
  renewals:<?= json_encode($toChart($renewalRate)) ?>,
  relYear: <?= json_encode(['labels'=>array_column($releaseByYear,'label'),
                            'values'=>array_map(fn($r)=>(float)$r['value'], $releaseByYear)]) ?>,
  relMonth:<?= json_encode(['labels'=>array_column($releaseByMonth,'label'),
                            'values'=>array_map(fn($r)=>(float)$r['value'], $releaseByMonth)]) ?>,
  budget:  <?= json_encode(['labels'=>array_column($budgetUtilization,'label'),
                            'budget'=>array_map(fn($r)=>(float)$r['budget'], $budgetUtilization),
                            'released'=>array_map(fn($r)=>(float)$r['released'], $budgetUtilization)]) ?>
};
const PALETTE = ['#1d4ed8','#0891b2','#f59e0b','#dc2626','#16a34a','#7c3aed','#64748b','#be185d','#0ea5e9','#84cc16','#f97316','#14b8a6'];

const mk = (id, config) => { const el = document.getElementById(id); if (el) new Chart(el, config); };
const doughnut = (id, data) => mk(id, {
  type: 'doughnut',
  data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: PALETTE }] },
  options: { plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
});
const barV = (id, data, horizontal=false) => mk(id, {
  type: 'bar',
  data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: '#1d4ed8' }] },
  options: { indexAxis: horizontal ? 'y' : 'x', plugins: { legend: { display: false } } }
});

doughnut('cStatus', C.status);
barV('cYear', C.year);
barV('cSchool', C.school, true);
barV('cCourse', C.course, true);
doughnut('cCivil', C.civil);
barV('cPurok', C.purok, true);
barV('cIncome', C.income);
doughnut('cIntRes', C.intRes);

mk('cIntTrend', {
  type: 'bar',
  data: { labels: C.intTrend.labels, datasets: [
    { label: 'Completed', data: C.intTrend.completed, backgroundColor: '#16a34a' },
    { label: 'No Show',   data: C.intTrend.noshow,   backgroundColor: '#dc2626' }
  ]},
  options: { scales: { x: { stacked: true }, y: { stacked: true } } }
});

mk('cIntScores', {
  type: 'bar',
  data: {
    labels: ['Financial Need','Academic','Motivation','Community','Purpose','Total (avg)'],
    datasets: [{ data: [
      +(C.intScores.financial_need||0), +(C.intScores.academic||0), +(C.intScores.motivation||0),
      +(C.intScores.community||0), +(C.intScores.purpose||0), +(C.intScores.total||0)
    ], backgroundColor: ['#1d4ed8','#0891b2','#f59e0b','#16a34a','#7c3aed','#334155'] }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
});

barV('cGpaDist', C.gpaDist);
mk('cGpaTrend', {
  type: 'line',
  data: { labels: C.gpaTrend.labels, datasets: [{ label: 'Avg GPA', data: C.gpaTrend.values,
    borderColor: '#1d4ed8', backgroundColor: 'rgba(29,78,216,.1)', fill: true, tension: .3 }] },
  options: { scales: { y: { reverse: true, min: 1, max: 5 } } }
});
barV('cGpaSchool', C.gpaSchool, true);
doughnut('cSchStatus', C.schStatus);
doughnut('cRenewals', C.renewals);

mk('cRelYear', { type: 'bar',
  data: { labels: C.relYear.labels, datasets: [{ label: '₱', data: C.relYear.values, backgroundColor: '#16a34a' }] },
  options: { plugins: { legend: { display: false } } }
});
mk('cRelMonth', { type: 'line',
  data: { labels: C.relMonth.labels, datasets: [{ label: '₱', data: C.relMonth.values,
    borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.15)', fill: true, tension: .3 }] },
  options: { plugins: { legend: { display: false } } }
});
mk('cBudget', { type: 'bar',
  data: { labels: C.budget.labels, datasets: [
    { label: 'Budget', data: C.budget.budget, backgroundColor: '#94a3b8' },
    { label: 'Released', data: C.budget.released, backgroundColor: '#16a34a' }
  ]},
  options: { scales: { x: { stacked: false } } }
});
</script>