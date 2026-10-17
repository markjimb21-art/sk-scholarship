<?php
$pct = static fn($r) => $r === null ? 'no data' : number_format($r * 100, 1) . '%';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Data Analytics</h4>
    <p class="text-muted small mb-0">Every number shows the formula that produced it.</p>
  </div>
  <?php if ($programs): ?>
  <form method="GET" action="<?= url('admin/analytics') ?>" class="d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select form-select-sm" data-autosubmit>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select></div>
    <?php if ($data): ?>
    <div><label class="form-label" for="f_academic_year">Academic year</label><select id="f_academic_year" name="academic_year" class="form-select form-select-sm" data-autosubmit title="Academic year for the budget analysis">
      <?php foreach ($years as $y): ?><option value="<?= e($y) ?>" <?= $data['ay'] === $y ? 'selected' : '' ?>>A.Y. <?= e($y) ?></option><?php endforeach; ?>
    </select></div>
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
          <?php foreach (AnalyticsFunnel::OPEN_STAGES as $s): ?>
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
          <?php $seats = $proj['seats']; ?>
          <div class="small text-muted">= returning <?= (int)$seats['returning'] ?> + pending renewals <?= (int)$seats['pending'] ?>
            + new approved <?= (int)$seats['new'] ?> + expected approvals <?= number_format(array_sum($proj['terms']), 2) ?>
            <?= $proj['uncapped'] > $proj['value'] ? ', capped at ' . (int)$seats['slots'] . ' slots' : '' ?></div>
        </div></div>
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">Slots remaining</div>
          <div class="fs-4 fw-bold"><?= (int)$data['slotsRemaining'] ?></div>
          <div class="small text-muted">= slots <?= (int)$seats['slots'] ?> − returning <?= (int)$seats['returning'] ?> − pending renewals <?= (int)$seats['pending'] ?>
            − new approved <?= (int)$seats['new'] ?></div>
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
          <tr><th>Total budget</th><td class="text-end"><?= e(peso($data['budget']['total_budget'] ?? null, '—')) ?></td><td class="text-muted">from the Budget page</td></tr>
          <tr><th>Released</th><td class="text-end"><?= e(peso($data['budget']['released'] ?? null, '—')) ?></td><td class="text-muted">sum of releases for this program’s semesters of this A.Y.</td></tr>
          <tr><th>Remaining</th><td class="text-end"><?= e(peso($data['remaining'], '—')) ?></td><td class="text-muted">= total − released</td></tr>
          <tr><th>Committed</th><td class="text-end"><?= e(peso($data['committed'], '—')) ?></td><td class="text-muted">= Σ Active scholars (<?= e(peso($amount, '—')) ?> × semesters of this A.Y. not yet released)</td></tr>
          <tr><th>Projected need</th><td class="text-end"><?= e(peso($data['projectedNeed'], '—')) ?></td>
              <td class="text-muted">= committed + (projected scholars − returning − new approved = <?= number_format($proj['not_committed'], 2) ?>) × <?= e(peso($amount, '—')) ?> × 2 semesters</td></tr>
          <tr class="<?= $data['surplus'] !== null && $data['surplus'] < 0 ? 'table-danger' : 'table-success' ?>">
            <th><?= $data['surplus'] !== null && $data['surplus'] < 0 ? 'Shortfall' : 'Surplus' ?></th>
            <td class="text-end fw-bold"><?= e($data['surplus'] === null ? '—' : peso(abs($data['surplus']), '—')) ?></td><td class="text-muted">= remaining − projected need</td></tr>
          <tr><th>Extra scholars fundable</th><td class="text-end fw-bold"><?= (int)$data['extraFundable'] ?></td>
              <td class="text-muted">= floor(surplus ÷ (<?= e(peso($amount, '—')) ?> × 2)); 0 when there is a shortfall</td></tr>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<?php $att = $data['attendance']; $ns = $data['noShow']; $sc = $data['scores']; $ot = $data['onTime']; ?>
<!-- (3) INTERVIEW EFFECTIVENESS -->
<h5 class="fw-semibold mb-2 mt-4">3. Interview effectiveness — <?= e($program['program_name']) ?></h5>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <p class="small text-muted mb-2">Interviews that took place on this program’s schedules (not cancelled). Attempts still marked Scheduled are not counted yet.</p>
      <div class="row g-2">
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">Attendance rate</div>
          <div class="fs-4 fw-bold"><?= e($pct($att['rate'])) ?></div>
          <div class="small text-muted"><?= (int)$att['attended'] ?> of <?= (int)$att['total'] ?> · = Attended ÷ (Attended + No-show)</div>
        </div></div>
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">No-show, first attempt</div>
          <div class="fs-4 fw-bold"><?= e($pct($ns[0]['rate'])) ?></div>
          <div class="small text-muted"><?= (int)$ns[0]['noshow'] ?> of <?= (int)$ns[0]['total'] ?> · = No-show ÷ attempts with no reschedule</div>
        </div></div>
        <div class="col-sm-4"><div class="border rounded p-2 h-100">
          <div class="small text-muted">No-show after the reschedule</div>
          <div class="fs-4 fw-bold"><?= e($pct($ns[1]['rate'])) ?></div>
          <div class="small text-muted"><?= (int)$ns[1]['noshow'] ?> of <?= (int)$ns[1]['total'] ?> · = No-show ÷ rescheduled attempts. Lower than the first attempt = rescheduling helps.</div>
        </div></div>
      </div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold mb-1">Interview scores</h6>
      <p class="small text-muted mb-1">
        Average <strong><?= $sc['average'] === null ? 'no data' : e(number_format($sc['average'], 2)) . ' / 100' ?></strong>
        = average total score of <?= (int)$sc['count'] ?> scored interview(s). Scores bunched in one band suggest the rubric isn’t separating applicants.
      </p>
      <div style="height:150px"><canvas id="scoreChart" role="img" aria-label="Interviews per score band"></canvas></div>
    </div></div>
  </div>
</div>

<!-- (4) ON-TIME SUBMISSIONS -->
<h5 class="fw-semibold mb-2 mt-4">4. On-time claim submissions — A.Y. <?= e($data['ay']) ?></h5>
<div class="row g-3">
  <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">On-time rate</div>
    <div class="fs-4 fw-bold"><?= e($pct($ot['rate'])) ?></div>
    <div class="small text-muted"><?= (int)$ot['on_time'] ?> of <?= (int)$ot['total'] ?> · = claims submitted on or before their window’s deadline ÷ submitted claims with a window
      (last submission counts)<?= $ot['without_window'] ? '. ' . (int)$ot['without_window'] . ' claim(s) from before claim windows existed are not counted' : '' ?>.</div>
  </div></div></div>
  <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Currently overdue</div>
    <div class="fs-4 fw-bold <?= $data['overdue'] ? 'text-danger' : '' ?>"><?= (int)$data['overdue'] ?></div>
    <div class="small text-muted">= scholars still Waiting for Requirements / Resubmit after their claim window’s deadline — the same list as
      <a href="<?= url('admin/claims') ?>">Claims → Missed deadline</a> and the red deadlines on the <a href="<?= url('admin/calendar&program_id=' . (int)$program['id']) ?>">calendar</a>.</div>
  </div></div></div>
  <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Average time to submit</div>
    <div class="fs-4 fw-bold"><?= $data['avgDays'] === null ? 'no data' : e(number_format($data['avgDays'], 1)) . ' days' ?></div>
    <div class="small text-muted">= average (claim submitted − window opened), same claims as the on-time rate. Close to the window length = people wait for the deadline.</div>
  </div></div></div>
</div>

<?php require VIEW_PATH . '/admin/_analytics_profiles.php'; ?>

<script type="application/json" id="analyticsChartData"><?= js_json([
  'funnel' => ['labels' => AnalyticsFunnel::STAGES, 'values' => array_values($c)],
  'budget' => [(float)($data['budget']['total_budget'] ?? 0), (float)($data['budget']['released'] ?? 0), round($data['committed'], 2), round($data['projectedNeed'], 2)],
  'scores' => ['labels' => array_keys($sc['bands']), 'values' => array_values($sc['bands'])],
]) ?></script>
<?php endif; ?>
