<div class="mb-3">
  <h4 class="fw-bold mb-0">Renewals to review</h4>
  <p class="text-muted small mb-0"><?= count($rows) ?> renewal request(s), oldest first. Approving opens the scholar’s next semester.</p>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Submitted</th><th>Scholar</th><th>From</th><th>For</th><th>Last GWA</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No renewals waiting.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><small><?= $r['submitted_at'] ? e(fmt_datetime($r['submitted_at'])) : '—' ?></small></td>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['scholar_code']) ?></code> · <small><?= e($r['program_name']) ?></small></td>
            <td><?= e(trim(($r['from_academic_year'] ?? '') . ' ' . ($r['from_semester'] ?? '')) ?: '—') ?></td>
            <td class="fw-semibold"><?= e($r['academic_year'] . ' ' . $r['semester']) ?></td>
            <td><?= $r['from_gwa'] !== null ? e(number_format((float)$r['from_gwa'], 2)) : '—' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/renewals/view&id=' . (int)$r['id']) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
// Renewal::didNotRenew: 'end' = the deadline passed, the 2nd Semester claim of the previous A.Y. is Verified or
// Released, no request (D7);
// 'wait' = notified, but their latest semester is earlier, so they couldn't renew yet. Nothing is ended automatically.
$canEnd = AdminController::canAccess('renewals/end-did-not-renew');
?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h6 class="mb-0">Did not renew <span class="badge bg-<?= $didNotRenew['end'] ? 'danger' : 'secondary' ?> ms-1"><?= count($didNotRenew['end']) ?></span></h6>
    <?php if ($didNotRenew['end'] && $canEnd): ?>
      <form method="POST" action="<?= url('admin/renewals/end-did-not-renew') ?>" class="m-0"
            data-confirm-title="End these scholarships?" data-confirm-subject="<?= count($didNotRenew['end']) ?> scholar(s) · Did not renew"
            data-confirm="Each one is notified. Anyone who submitted a renewal since this page loaded is skipped." data-confirm-ok="End all">
        <?= csrf_field() ?>
        <?php foreach ($didNotRenew['end'] as $m): ?><input type="hidden" name="scholar_ids[]" value="<?= (int)$m['scholar_id'] ?>"><?php endforeach; ?>
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x"></i> End all as Did not renew</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light"><tr><th>Scholar</th><th>Last semester</th><th>Renewal into</th><th>Deadline</th><th></th></tr></thead>
      <tbody>
        <?php if (!$didNotRenew['end']): ?><tr><td colspan="5" class="text-center text-muted py-3">Nobody — every scholar whose renewal deadline passed has submitted.</td></tr><?php endif; ?>
        <?php foreach ($didNotRenew['end'] as $m): $s = $m['schedule']; ?>
          <tr>
            <td><div class="fw-semibold"><?= e($m['full_name']) ?></div><code><?= e($m['scholar_code']) ?></code></td>
            <td><?= e($m['latest_year'] . ' ' . $m['latest_semester']) ?> <span class="text-muted">· claim <?= e($m['latest_claim']) ?></span></td>
            <td><?= e($s['program_name'] . ' · ' . $s['academic_year'] . ' ' . $s['semester']) ?></td>
            <td class="text-danger"><?= e(fmt_datetime($s['submission_deadline'])) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/scholar&id=' . (int)$m['scholar_id']) ?>">Scholar</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($didNotRenew['wait']): ?>
    <div class="card-body border-top small">
      <div class="fw-semibold mb-1">Couldn’t renew yet (<?= count($didNotRenew['wait']) ?>) — not included in “End all”</div>
      <p class="text-muted mb-2">Their latest semester isn’t the 2nd Semester of the previous academic year, or that claim isn’t verified yet, so a renewal wasn’t possible for them. Follow up from the scholar page.</p>
      <ul class="mb-0">
        <?php foreach ($didNotRenew['wait'] as $m): ?>
          <li><a href="<?= url('admin/scholar&id=' . (int)$m['scholar_id']) ?>"><?= e($m['full_name']) ?></a> <code><?= e($m['scholar_code']) ?></code>
            — latest <?= e($m['latest_year'] . ' ' . $m['latest_semester']) ?>, claim <?= e($m['latest_claim']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <div class="card-footer bg-white small text-muted">“End all” re-checks each scholar when you click it and ends them one by one, with the same notification and record as ending a scholarship from the scholar page.</div>
</div>
<?php $purpose = 'renewal'; require VIEW_PATH . '/admin/_missed_deadlines.php'; ?>
