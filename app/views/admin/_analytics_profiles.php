<?php
// Analytics sections 5–7 (docs/PROCESS.md §6): profiles, academic performance, fund utilization.
// Expects $data (ReportController::analytics), $program, $pct, $peso. Every number shows its formula.
$gwa = static fn($v) => $v === null ? '—' : number_format((float)$v, 2);
?>
<!-- (5) PROFILES -->
<h5 class="fw-semibold mb-2 mt-4">5. Applicant and scholar profiles — <?= e($program['program_name']) ?></h5>
<p class="small text-muted mb-2">For each group: <strong>applicants</strong> = submitted applications; <strong>approved</strong> = applications
  now Approved; <strong>approval rate</strong> = approved ÷ applicants (still-pending applications count as not approved yet).
  Profile data is the applicant’s current form data.</p>
<div class="row g-3">
  <?php foreach (AnalyticsProfiles::PROFILE_DIMENSIONS as $dim => $label): $rows = $data['profiles'][$dim]; ?>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-semibold mb-2">By <?= e(lcfirst($label)) ?></h6>
        <?php if (!$rows): ?>
          <p class="text-muted small mb-0">No submitted applications yet.</p>
        <?php else: ?>
          <div style="height:<?= max(150, 46 * count($rows) + 70) ?>px"><canvas id="profile_<?= e($dim) ?>" role="img" aria-label="Applicants and approved by <?= e(lcfirst($label)) ?>"></canvas></div>
          <table class="table table-sm small align-middle mb-0 mt-2">
            <thead><tr><th><?= e($label) ?></th><th class="text-end">Applicants</th><th class="text-end">Approved</th><th class="text-end">Approval rate</th></tr></thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr><td><?= e($r['group']) ?></td><td class="text-end"><?= (int)$r['applicants'] ?></td><td class="text-end"><?= (int)$r['approved'] ?></td>
                  <td class="text-end"><?= e($pct($r['rate'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- (6) ACADEMIC PERFORMANCE -->
<?php $gv = $data['gwaVs']; ?>
<h5 class="fw-semibold mb-2 mt-4">6. Academic performance — GWA (1.00 is highest)</h5>
<div class="row g-3">
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Average GWA of applicants</div>
    <div class="fs-4 fw-bold"><?= e($gwa($gv['applicants']['avg'])) ?></div>
    <div class="small text-muted">= average GWA on the form of <?= (int)$gv['applicants']['n'] ?> submitted application(s)</div>
  </div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Average GWA of approved scholars</div>
    <div class="fs-4 fw-bold"><?= e($gwa($gv['scholars']['avg'])) ?></div>
    <div class="small text-muted">= average GWA on the form of <?= (int)$gv['scholars']['n'] ?> approved application(s)</div>
  </div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Scholars below the minimum GWA</div>
    <div class="fs-4 fw-bold <?= $data['atRisk'] ? 'text-danger' : '' ?>"><?= count($data['atRisk']) ?></div>
    <div class="small text-muted">= Active scholars whose latest recorded semester GWA is worse than <?= $program['min_gpa'] !== null ? e($gwa($program['min_gpa'])) : '— (no minimum set)' ?></div>
  </div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <?php $tr = array_count_values(array_column($data['gwaTrend'], 'trend')); ?>
    <div class="small text-muted">GWA trend (2+ semesters)</div>
    <div class="fs-5 fw-bold"><span class="text-success"><?= (int)($tr['Improving'] ?? 0) ?> improving</span> · <span class="text-danger"><?= (int)($tr['Declining'] ?? 0) ?> declining</span></div>
    <div class="small text-muted">= last − first recorded GWA per scholar; lower = improving, ±0.05 = steady (<?= (int)($tr['Steady'] ?? 0) ?>)</div>
  </div></div></div>

  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Average GWA per semester</h6>
    <p class="small text-muted">= average of the GWAs recorded when claims were verified (scholar_terms.gwa), per semester.</p>
    <?php if (!$data['gwaBySemester']): ?><p class="text-muted small mb-0">No semester GWA recorded yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="gwaTrendChart" role="img" aria-label="Average GWA per semester"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><thead><tr><th>Semester</th><th class="text-end">Average GWA</th><th class="text-end">Scholars</th></tr></thead><tbody>
        <?php foreach ($data['gwaBySemester'] as $sem => $g): ?><tr><td><?= e($sem) ?></td><td class="text-end"><?= e($gwa($g['avg'])) ?></td><td class="text-end"><?= (int)$g['n'] ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>

  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">At-risk scholars</h6>
    <p class="small text-muted">Active scholars whose latest semester GWA is worse than the program minimum. Nothing happens automatically.</p>
    <table class="table table-sm small mb-0"><thead><tr><th>Scholar</th><th>Semester</th><th class="text-end">GWA</th><th class="text-end">Minimum</th></tr></thead><tbody>
      <?php if (!$data['atRisk']): ?><tr><td colspan="4" class="text-muted text-center">Nobody is below the minimum.</td></tr><?php endif; ?>
      <?php foreach ($data['atRisk'] as $s): ?>
        <tr class="table-danger"><td><a href="<?= url('admin/scholar&id=' . (int)$s['scholar_id']) ?>"><?= e($s['full_name']) ?></a> <code class="small"><?= e($s['scholar_code']) ?></code></td>
          <td><?= e($s['semester']) ?></td><td class="text-end fw-bold"><?= e($gwa($s['gwa'])) ?></td><td class="text-end"><?= e($gwa($s['min_gpa'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div></div></div>

  <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body">
    <h6 class="fw-semibold mb-1">GWA per semester per scholar — A.Y. <?= e($data['ay']) ?></h6>
    <?php if (!$data['gwaPerScholar']): ?><p class="text-muted small mb-0">No semester GWA recorded for this academic year.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm small mb-0 align-middle">
        <thead><tr><th>Scholar</th><?php foreach ($data['gwaSemesters'] as $sem): ?><th class="text-end"><?= e($sem) ?></th><?php endforeach; ?><th>Trend</th></tr></thead>
        <tbody>
          <?php $trendOf = array_column($data['gwaTrend'], 'trend', 'scholar_id'); ?>
          <?php foreach ($data['gwaPerScholar'] as $s): ?>
            <tr><td><?= e($s['full_name']) ?> <code class="small"><?= e($s['scholar_code']) ?></code></td>
              <?php foreach ($data['gwaSemesters'] as $sem): $g = $s['gwas'][$sem] ?? null; ?>
                <td class="text-end <?= $g !== null && $program['min_gpa'] !== null && $g > (float)$program['min_gpa'] ? 'text-danger fw-bold' : '' ?>"><?= e($gwa($g)) ?></td>
              <?php endforeach; ?>
              <td><?php $t = $trendOf[$s['scholar_id']] ?? null; ?><?= $t ? '<span class="badge bg-' . ($t === 'Improving' ? 'success' : ($t === 'Declining' ? 'danger' : 'secondary')) . '">' . e($t) . '</span>' : '<span class="text-muted">one semester</span>' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div></div></div>
</div>

<!-- (7) FUND UTILIZATION -->
<?php $aps = $data['avgPerScholar']; ?>
<h5 class="fw-semibold mb-2 mt-4">7. Fund utilization — A.Y. <?= e($data['ay']) ?></h5>
<div class="row g-3">
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Released</div><div class="fs-4 fw-bold"><?= e($peso($aps['total'])) ?></div>
    <div class="small text-muted">= sum of releases for this program’s semesters of this A.Y.</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Committed</div><div class="fs-4 fw-bold"><?= e($peso($data['committed'])) ?></div>
    <div class="small text-muted">= Σ Active scholars (amount per semester × semesters of this A.Y. not yet released)</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Release rate</div><div class="fs-4 fw-bold"><?= e($pct($data['releaseRate'])) ?></div>
    <div class="small text-muted">= released ÷ total budget<?= $data['budget'] ? ' (' . e($peso($data['budget']['total_budget'])) . ')' : ' — no budget for this A.Y.' ?></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <div class="small text-muted">Average released per scholar</div><div class="fs-4 fw-bold"><?= e($peso($aps['avg'])) ?></div>
    <div class="small text-muted">= released <?= e($peso($aps['total'])) ?> ÷ <?= (int)$aps['scholars'] ?> scholar(s) who received a release</div></div></div></div>

  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Released per month (₱)</h6>
    <p class="small text-muted">= sum of releases by release date, for this A.Y.’s semesters.</p>
    <?php if (!$data['releasedByMonth']): ?><p class="text-muted small mb-0">Nothing released yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="releasedMonthChart" role="img" aria-label="Released per month"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><thead><tr><th>Month</th><th class="text-end">Released</th></tr></thead><tbody>
        <?php foreach ($data['releasedByMonth'] as $m => $v): ?><tr><td><?= e(date('F Y', strtotime($m . '-01'))) ?></td><td class="text-end"><?= e($peso($v)) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h6 class="fw-semibold mb-1">Released per semester (₱)</h6>
    <p class="small text-muted">= sum of releases per semester, every academic year of this program.</p>
    <?php if (!$data['releasedBySemester']): ?><p class="text-muted small mb-0">Nothing released yet.</p><?php else: ?>
      <div style="height:220px"><canvas id="releasedSemChart" role="img" aria-label="Released per semester"></canvas></div>
      <table class="table table-sm small mb-0 mt-2"><thead><tr><th>Semester</th><th class="text-end">Released</th></tr></thead><tbody>
        <?php foreach ($data['releasedBySemester'] as $sem => $v): ?><tr><td><?= e($sem) ?></td><td class="text-end"><?= e($peso($v)) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div></div></div>
</div>
<script type="application/json" id="profileChartData"><?= js_json([
  'profiles' => array_map(static fn($rows) => ['labels' => array_column($rows, 'group'), 'applicants' => array_column($rows, 'applicants'), 'approved' => array_column($rows, 'approved')], $data['profiles']),
  'gwaTrend' => ['labels' => array_keys($data['gwaBySemester']), 'values' => array_map(static fn($g) => round($g['avg'], 2), array_values($data['gwaBySemester']))],
  'releasedByMonth' => ['labels' => array_map(static fn($m) => date('M Y', strtotime($m . '-01')), array_keys($data['releasedByMonth'])), 'values' => array_values($data['releasedByMonth'])],
  'releasedBySemester' => ['labels' => array_keys($data['releasedBySemester']), 'values' => array_values($data['releasedBySemester'])],
]) ?></script>
