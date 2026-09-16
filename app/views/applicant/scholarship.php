<?php
$totalReleased = 0;
foreach ($releases as $r) $totalReleased += (float)$r['amount'];
?>

<h4 class="fw-bold mb-3">My Scholarship</h4>
<?php if (!$scholar): ?>
  <div class="alert alert-info">You are not yet a scholar. Once approved, your scholarship details will appear here.</div>
<?php else: ?>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-primary"><i class="bi bi-award"></i></div><div><div class="kpi-value" style="font-size:1rem;"><?= e($scholar['scholar_code']) ?></div><div class="kpi-label">Scholar ID</div></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-calendar-check"></i></div><div><div class="kpi-value" style="font-size:1rem;"><?= e($scholar['approval_date']) ?></div><div class="kpi-label">Approved</div></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value" style="font-size:1.1rem;">₱<?= number_format($totalReleased, 0) ?></div><div class="kpi-label">Total Received</div></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-warning"><i class="bi bi-person-badge"></i></div><div><div class="kpi-value" style="font-size:1rem;"><?= e($scholar['status']) ?></div><div class="kpi-label">Status</div></div></div></div>
  </div>

  <?php if (count($academics) >= 2): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white"><h6 class="mb-0">GPA Trend</h6></div>
      <div class="card-body"><canvas id="gpaTrend" height="80"></canvas></div>
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0">Scholarship Releases</h6></div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>Code</th><th>A.Y.</th><th>Sem</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead>
        <tbody>
          <?php if (!$releases): ?><tr><td colspan="6" class="text-center text-muted py-3">No releases yet.</td></tr><?php endif; ?>
          <?php foreach ($releases as $r): ?>
            <tr><td><code><?= e($r['release_code']) ?></code></td><td><?= e($r['academic_year']) ?></td><td><?= e($r['semester']) ?></td>
                <td class="fw-semibold">₱<?= number_format((float)$r['amount'], 2) ?></td>
                <td><?= e(date('M d, Y', strtotime($r['release_date']))) ?></td><td><?= e($r['payment_method']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0">Academic Records</h6></div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>A.Y.</th><th>Sem</th><th>Year Level</th><th>GPA</th><th>Standing</th></tr></thead>
        <tbody>
          <?php if (!$academics): ?><tr><td colspan="5" class="text-center text-muted py-3">No records yet.</td></tr><?php endif; ?>
          <?php foreach ($academics as $a): ?>
            <tr><td><?= e($a['academic_year']) ?></td><td><?= e($a['semester']) ?></td><td><?= e($a['year_level']) ?></td>
                <td><strong><?= e((string)$a['gpa']) ?></strong></td><td><?= e((string)$a['academic_standing']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($renewals)): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><h6 class="mb-0">Renewals</h6></div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light"><tr><th>A.Y.</th><th>Sem</th><th>GPA</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php foreach ($renewals as $r): ?>
              <tr><td><?= e($r['academic_year']) ?></td><td><?= e($r['semester'] ?? '—') ?></td>
                  <td><?= e((string)$r['gpa']) ?></td>
                  <td><span class="badge bg-secondary"><?= e($r['status']) ?></span></td>
                  <td><small><?= e($r['remarks'] ?? '') ?></small></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (count($academics) >= 2):
    $labels = array_map(fn($a) => $a['academic_year'] . ' ' . substr($a['semester'],0,3), array_reverse($academics));
    $data   = array_map(fn($a) => (float)$a['gpa'], array_reverse($academics));
  ?>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('gpaTrend');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
          label: 'GPA',
          data: <?= json_encode($data) ?>,
          borderColor: '#1d4ed8',
          backgroundColor: 'rgba(29,78,216,.1)',
          fill: true, tension: .3,
          pointBackgroundColor: '#1d4ed8'
        }]
      },
      options: {
        scales: { y: { reverse: true, min: 1, max: 5, ticks: { stepSize: 0.5 } } },
        plugins: { legend: { display: false } }
      }
    });
  });
  </script>
  <?php endif; ?>

<?php endif; ?>