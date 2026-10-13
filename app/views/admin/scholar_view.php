<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/scholars') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Scholars</a>
    <h4 class="fw-bold mb-0 mt-1"><?= e($scholar['full_name']) ?>
      <?= $scholar['status'] === 'Active' ? '<span class="badge bg-success ms-1">Active</span>' : '<span class="badge bg-secondary ms-1">Ended — ' . e($scholar['end_reason']) . '</span>' ?></h4>
    <p class="text-muted small mb-0">
      <code><?= e($scholar['scholar_code']) ?></code> · <?= e($scholar['program_name']) ?> · approved <?= e(fmt_date($scholar['approval_date'])) ?>
      · application <a href="<?= url('admin/application&id=' . (int)$scholar['application_id']) ?>"><?= e($scholar['application_code']) ?></a>
      <?= $scholar['ended_at'] ? ' · ended ' . e(fmt_date($scholar['ended_at'])) : '' ?>
    </p>
    <p class="text-muted small mb-0">
      Born <?= $scholar['date_of_birth'] ? e(fmt_date($scholar['date_of_birth'])) : '—' ?>
      · <?= e(($scholar['contact_number'] ?? '') ?: '—') ?>
      · Height <?= !empty($scholar['height_cm']) ? e(rtrim(rtrim(number_format((float)$scholar['height_cm'], 1), '0'), '.')) . ' cm' : '—' ?>
      · Weight <?= !empty($scholar['weight_kg']) ? e(rtrim(rtrim(number_format((float)$scholar['weight_kg'], 1), '0'), '.')) . ' kg' : '—' ?>
      · Blood type <?= e(($scholar['blood_type'] ?? '') ?: '—') ?>
      · Religion <?= e(($scholar['religion'] ?? '') ?: '—') ?>
      · Citizenship <?= e(($scholar['citizenship'] ?? '') ?: '—') ?>
    </p>
  </div>
  <div class="text-end">
    <div class="small text-muted">Total released (sum of the releases below)</div>
    <div class="fs-4 fw-bold text-success"><?= e(peso($totalReleased)) ?></div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white"><h6 class="mb-0">Semesters</h6></div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light"><tr><th>Semester</th><th>Opened by</th><th>Claim</th><th>GWA</th><th>Release</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($terms as $t): ?>
          <tr>
            <td class="fw-semibold"><?= e($t['academic_year'] . ' ' . $t['semester']) ?></td>
            <td><?= e($t['opened_via'] === 'approval' ? 'Approval' : 'Renewal') ?></td>
            <td><span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$t['claim_status']]) ?>"><?= e($t['claim_status']) ?></span></td>
            <td><?= $t['gwa'] !== null ? e(number_format((float)$t['gwa'], 2)) : '—' ?></td>
            <td><?= $t['released_amount'] !== null
                ? '₱' . e(number_format((float)$t['released_amount'], 2)) . ' · ' . e(fmt_date($t['release_date'])) . ' · ' . e($t['payment_method']) . ($t['reference_number'] ? ' ' . e($t['reference_number']) : '')
                : '—' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/claims/view&id=' . (int)$t['id']) ?>">Claim</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white"><h6 class="mb-0">Current documents</h6></div>
  <div class="card-body"><div class="row g-3">
    <?php foreach ($currentDocs as $type => $cur): ?>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <div class="d-flex justify-content-between mb-2"><strong><?= e($type) ?></strong>
            <?= $cur ? '<span class="badge bg-success">Current</span>' : '<span class="badge bg-secondary">None</span>' ?></div>
          <?php if ($cur): ?>
            <?php if (in_array($cur['mime_type'], ['image/jpeg', 'image/png'], true)): ?>
              <a href="<?= e($cur['admin_url']) ?>" target="_blank" rel="noopener"><img src="<?= e($cur['admin_url']) ?>" class="img-fluid rounded border mb-2" style="max-height:160px" alt="<?= e($type) ?>"></a>
            <?php endif; ?>
            <div class="small">
              <a href="<?= e($cur['admin_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Open</a> ·
              <a href="<?= e($cur['admin_url'] . '&download=1') ?>"><i class="bi bi-download"></i> Download</a>
              <div class="text-muted"><?= e($cur['original_filename']) ?> · <?= e(fmt_date($cur['uploaded_at'])) ?>
                · uploaded during <?= e($cur['uploaded_during']) ?> (<?= e($cur['context']) ?>)</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php require VIEW_PATH . '/admin/_document_history.php'; ?>
  </div>
</div>

<?php require VIEW_PATH . '/admin/_field_history.php'; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><h6 class="mb-0">Renewal requests</h6></div>
      <ul class="list-group list-group-flush small">
        <?php if (!$renewals): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?>
        <?php foreach ($renewals as $rr): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span>For <?= e($rr['academic_year'] . ' ' . $rr['semester']) ?> — <strong><?= e($rr['status']) ?></strong><?= $rr['remarks'] ? ' · ' . e($rr['remarks']) : '' ?></span>
            <a href="<?= url('admin/renewals/view&id=' . (int)$rr['id']) ?>">open</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold">End scholarship</h6>
      <?php if ($scholar['status'] === 'Active'): ?>
        <form method="POST" action="<?= url('admin/scholar-end') ?>" data-confirm="End this scholarship? The scholar can no longer claim or renew.">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$scholar['id'] ?>">
          <label class="form-label" for="endReason">Why is it ending?</label>
          <select name="reason" id="endReason" class="form-select mb-2" required>
            <option value="">Choose a reason…</option>
            <?php foreach (SCHOLAR_END_REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-danger w-100">End scholarship and notify</button>
        </form>
      <?php else: ?>
        <p class="text-muted small mb-0">Ended <?= e(fmt_date($scholar['ended_at'])) ?>: <?= e($scholar['end_reason']) ?>.</p>
      <?php endif; ?>
    </div></div>
  </div>
</div>
