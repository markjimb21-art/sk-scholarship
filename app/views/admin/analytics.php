<?php
$peso = static fn($v) => $v === null ? '—' : '₱' . number_format((float)$v, 2);
$pct = static fn($r) => $r === null ? 'no data' : number_format($r * 100, 1) . '%';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Data Analytics</h4>
    <p class="text-muted small mb-0">Every number shows the formula that produced it.</p>
  </div>
  <?php if ($programs): ?>
  <form method="GET" action="<?= url('admin/analytics') ?>" class="d-flex gap-2">
    <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <?php if ($data): ?>
    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()" title="Academic year for the budget analysis">
      <?php foreach ($years as $y): ?><option value="<?= e($y) ?>" <?= $data['ay'] === $y ? 'selected' : '' ?>>A.Y. <?= e($y) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>

<?php if (!$data): ?>
  <div class="alert alert-secondary">Create a scholarship program first.</div>
<?php else:
  $c = $data['counts']; $rates = $data['conversion']['rates']; $proj = $data['projected'];
  $amount = (float)$program['amount_per_semester']; ?>

<!-- (1) POSSIBLE SCHOLARS -->
<h5 class="fw-semibold mb-2">1. Possible scholars — <?= e($program['program_name']) ?></h5>
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold mb-1">Applications per stage</h6>
      <p class="small text-muted">Count of this program’s applications currently in each stage.</p>
      <div style="height:300px"><canvas id="funnelChart" role="img" aria-label="Applications per stage"></canvas></div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold mb-1">Conversion to Approved</h6>
      <p class="small text-muted mb-2">
        Rate of a stage = applications that reached it and ended Approved ÷ applications that reached it.
        Based on <strong><?= e($data['conversion']['basis']) ?></strong>.
        <?php if ($data['conversion']['current_only']): ?><span class="badge bg-warning text-dark">based on current data only</span><?php endif; ?>
      </p>
      <table class="table table-sm small align-middle mb-2">
        <thead><tr><th>Stage</th><th class="text-end">Now in stage</th><th class="text-end">Rate</th><th class="text-end">Expected approvals</th></tr></thead>
        <tbody>
          <?php foreach (Analytics::OPEN_STAGES as $s): ?>
            <tr>
              <td><?= e(APP_STATUS_LABELS[$s]) ?></td>
              <td class="text-end"><?= (int)$c[$s] ?></td>
              <td class="text-end" title="<?= (int)$rates[$s]['approved'] ?> of <?= (int)$rates[$s]['reached'] ?>"><?= e($pct($rates[$s]['rate'])) ?>
                <span class="text-muted">(<?= (int)$rates[$s]['approved'] ?>/<?= (int)$rates[$s]['reached'] ?>)</span></td>
              <td class="text-end"><?= number_format($proj['terms'][$s], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="row g-2">
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">Projected scholars</div>
          <div class="fs-4 fw-bold"><?= number_format($proj['value'], 1) ?></div>
          <div class="small text-muted">= slots used <?= (int)$proj['slots_used'] ?> + expected approvals <?= number_format(array_sum($proj['terms']), 2) ?>
            <?= $proj['uncapped'] > $proj['value'] ? ', capped at ' . (int)$program['slots_available'] . ' slots' : '' ?></div>
        </div></div>
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">Slots remaining</div>
          <div class="fs-4 fw-bold"><?= (int)$data['slotsRemaining'] ?></div>
          <div class="small text-muted">= slots <?= (int)$program['slots_available'] ?> − slots used <?= (int)$proj['slots_used'] ?></div>
        </div></div>
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">Waitlisted available</div>
          <div class="fs-4 fw-bold"><?= (int)$data['waitlisted'] ?></div>
          <div class="small text-muted">= applications now Waitlisted</div>
        </div></div>
      </div>
    </div></div>
  </div>
</div>

<!-- (2) POSSIBLE BUDGET -->
<h5 class="fw-semibold mb-2">2. Possible budget — A.Y. <?= e($data['ay']) ?></h5>
<?php if (!$data['budget']): ?>
  <div class="alert alert-warning">There is no budget for <?= e($program['program_name']) ?> <?= e($data['ay']) ?>. <a href="<?= url('admin/budget') ?>">Create it</a> to compare.</div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold mb-1">Budget vs needs (₱)</h6>
      <p class="small text-muted">Total budget, released so far, committed to current scholars, and projected need.</p>
      <div style="height:240px"><canvas id="budgetChart" role="img" aria-label="Budget total, released, committed and projected need"></canvas></div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <table class="table table-sm small align-middle mb-0">
        <tbody>
          <tr><th>Total budget</th><td class="text-end"><?= e($peso($data['budget']['total_budget'] ?? null)) ?></td><td class="text-muted">from the Budget page</td></tr>
          <tr><th>Released</th><td class="text-end"><?= e($peso($data['budget']['released'] ?? null)) ?></td><td class="text-muted">sum of releases for this program’s semesters of this A.Y.</td></tr>
          <tr><th>Remaining</th><td class="text-end"><?= e($peso($data['remaining'])) ?></td><td class="text-muted">= total − released</td></tr>
          <tr><th>Committed</th><td class="text-end"><?= e($peso($data['committed'])) ?></td><td class="text-muted">= Σ Active scholars (<?= e($peso($amount)) ?> × semesters of this A.Y. not yet released)</td></tr>
          <tr><th>Projected need</th><td class="text-end"><?= e($peso($data['projectedNeed'])) ?></td>
              <td class="text-muted">= committed + (projected scholars − slots used = <?= number_format($proj['expected_new'], 2) ?>) × <?= e($peso($amount)) ?> × 2 semesters</td></tr>
          <tr class="<?= $data['surplus'] !== null && $data['surplus'] < 0 ? 'table-danger' : 'table-success' ?>">
            <th><?= $data['surplus'] !== null && $data['surplus'] < 0 ? 'Shortfall' : 'Surplus' ?></th>
            <td class="text-end fw-bold"><?= e($data['surplus'] === null ? '—' : $peso(abs($data['surplus']))) ?></td><td class="text-muted">= remaining − projected need</td></tr>
          <tr><th>Extra scholars fundable</th><td class="text-end fw-bold"><?= (int)$data['extraFundable'] ?></td>
              <td class="text-muted">= floor(surplus ÷ (<?= e($peso($amount)) ?> × 2)); 0 when there is a shortfall</td></tr>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.Chart) return;
  // One series per chart: one hue, no legend; thin bars with a rounded data end; recessive grid; value on hover.
  var hue = '#2a78d6', ink = '#52514e', grid = 'rgba(0,0,0,.06)';
  function bar(id, labels, values, money) {
    var fmt = function (v) { return money ? '₱' + Number(v).toLocaleString('en-PH', {minimumFractionDigits: 2}) : String(v); };
    new Chart(document.getElementById(id), {
      type: 'bar',
      data: { labels: labels, datasets: [{ data: values, backgroundColor: hue, maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }] },
      options: {
        indexAxis: 'y', maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return fmt(c.parsed.x); } } } },
        scales: {
          x: { beginAtZero: true, grid: { color: grid }, ticks: { color: ink, precision: 0, callback: function (v) { return money ? '₱' + Number(v).toLocaleString('en-PH') : v; } } },
          y: { grid: { display: false }, ticks: { color: ink } }
        }
      }
    });
  }
  bar('funnelChart', <?= js_json(array_map(static fn($s) => $s, Analytics::STAGES)) ?>, <?= js_json(array_values($c)) ?>, false);
  bar('budgetChart', ['Total budget', 'Released', 'Committed', 'Projected need'],
      <?= js_json([(float)($data['budget']['total_budget'] ?? 0), (float)($data['budget']['released'] ?? 0), round($data['committed'], 2), round($data['projectedNeed'], 2)]) ?>, true);
});
</script>
<?php endif; ?>
