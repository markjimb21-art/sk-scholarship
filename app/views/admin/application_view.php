<?php
// One page per application. The three screening buttons appear only when the transition map allows them.
$p = $app['personal'] ?? [];
$edu = $app['education'] ?? [];
$f = $app['family'] ?? [];
$screening = Application::can('qualify', $app['status']);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url($screening ? 'admin/screening' : 'admin/applications') ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to <?= $screening ? 'screening' : 'applications' ?>
    </a>
    <h4 class="fw-bold mb-1 mt-1"><?= e($app['applicant']['full_name'] ?? '') ?>
      <span class="badge bg-<?= e(APP_STATUS_COLORS[$app['status']]) ?> ms-2"><?= e(Application::label($app['status'])) ?></span>
    </h4>
    <div class="text-muted small">
      <code><?= e($app['application_code']) ?></code> · <?= e($app['program_name']) ?> (<?= e($app['academic_year']) ?>)
      · submitted <?= $app['submitted_at'] ? e(fmt_datetime($app['submitted_at'])) : '—' ?>
      <?php if ($scholar): ?> · Scholar <a href="<?= url('admin/scholar&id=' . (int)$scholar['id']) ?>"><?= e($scholar['scholar_code']) ?></a><?php endif; ?>
    </div>
  </div>
  <a href="mailto:<?= e($app['applicant']['email'] ?? '') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-envelope"></i> E-mail</a>
</div>

<?php if ($app['status'] === 'Returned'): ?>
  <div class="alert alert-warning"><strong>Returned for correction</strong> on <?= e(fmt_date($app['review_remarks_at'])) ?>: <?= nl2br(e($app['review_remarks'])) ?>
    <?php if ($flags): $labels = Application::flagOptions(); ?>
      <div class="mt-1 small"><strong>Flagged (only these can change):</strong> <?= e(implode(', ', array_map(static fn($k) => $labels[$k], $flags))) ?></div>
    <?php endif; ?>
  </div>
<?php elseif ($flags && $app['status'] === 'Submitted'): $labels = Application::flagOptions(); ?>
  <div class="alert alert-info small">Resubmitted after correction. Flagged last time: <?= e(implode(', ', array_map(static fn($k) => $labels[$k], $flags))) ?>.</div>
<?php elseif ($app['status'] === 'Rejected'): ?>
  <div class="alert alert-danger"><strong>Rejected</strong><?= $app['decided_at'] ? ' on ' . e(fmt_date($app['decided_at'])) : '' ?>: <?= e($app['decision_remarks'] ?? '') ?></div>
<?php elseif (in_array($app['status'], ['Interviewed', 'Waitlisted'], true)): ?>
  <div class="alert alert-info">This application is waiting for the final decision — decide it on the <a href="<?= url('admin/decisions&program_id=' . (int)$app['program_id']) ?>">ranking page</a>.</div>
<?php elseif ($app['status'] === 'Qualified'): ?>
  <div class="alert alert-info">Qualified — assign it to an interview on the <a href="<?= url('admin/interviews&program_id=' . (int)$app['program_id']) ?>">Interviews page</a>.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <!-- ELIGIBILITY CHECK (computed on the server; informational) -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-semibold mb-2">Eligibility check <span class="badge bg-<?= $eligibility['failed'] ? 'warning' : 'success' ?> ms-1"><?= e($eligibility['summary']) ?></span></h6>
        <table class="table table-sm mb-1 align-middle">
          <thead><tr><th>Rule</th><th>Required</th><th>Applicant</th><th>Result</th></tr></thead>
          <tbody>
            <?php foreach ($eligibility['rows'] as $row): ?>
              <tr>
                <td><?= e($row['label']) ?></td>
                <td class="small text-muted"><?= e($row['rule']) ?></td>
                <td><?= e($row['value']) ?></td>
                <td><span class="badge bg-<?= e(ELIGIBILITY_COLORS[$row['result']]) ?>"><?= e($row['result']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="small text-muted">Nothing is decided automatically — use these rows to decide.</div>
      </div>
    </div>
  </div>

  <!-- B2 ACTIONS -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Screening decision</h6>
        <?php if ($screening): ?>
          <form method="POST" action="<?= url('admin/screening/decide') ?>" class="mb-2"
                data-confirm-title="Qualify for interview?" data-confirm-subject="<?= e($app['applicant']['full_name'] ?? $app['application_code']) ?>"
                data-confirm="This applicant moves on to the interview stage." data-confirm-ok="Qualify">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><input type="hidden" name="decision" value="qualify">
            <button class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Qualify for interview</button>
          </form>
          <button class="btn btn-warning w-100 mb-2" data-bs-toggle="modal" data-bs-target="#returnModal"><i class="bi bi-arrow-return-left"></i> Return for correction</button>
          <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-circle"></i> Reject</button>
        <?php else: ?>
          <p class="text-muted small mb-0">Screening decisions are only possible while the application is "<?= e(Application::label('Submitted')) ?>".</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-docs">Documents</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-personal">Personal</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-education">Education</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-family">Family</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-interview">Interview</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-audit">History</a></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-docs">
    <div class="row g-3">
      <?php
      $docSets = Document::bySide($app['documents']);
      foreach (DOCUMENT_TYPES as $type):
        $set = $docSets[$type] ?? [];
        // School ID / Enrollment Form: show the applicant's CURRENT version (a later claim/renewal upload replaces this one).
        $cur = $currentDocs[$type] ?? [];
        $own = array_map(static fn($d) => 'documents#' . $d['id'], $set);
        $isNewer = $cur && array_diff(array_map(static fn($c) => $c['source_table'] . '#' . $c['id'], $cur), $own);
        $files = [];   // side key => row + its view URL and table
        foreach ($isNewer ? $cur : $set as $k => $d) {
            if (!$isNewer && $d['file_path'] === '') continue;   // empty placeholder of a Draft
            $files[$k] = $d + ['src' => $isNewer ? $d['admin_url'] : url('admin/documents/view&id=' . (int)$d['id']), 'table' => $isNewer ? $d['source_table'] : 'documents'];
        }
        // Boxes shown: one file with the whole document if there is one; otherwise every side this document needs.
        $needed = Document::sidesFor($type);
        $keys = isset($files['']) ? [''] : ($needed === [null] && $files ? array_keys($files) : array_map([Document::class, 'sideKey'], $needed));
        $valid = array_filter($files, static fn($f) => $f['status'] !== 'Invalid');
        $complete = isset($valid['']) || ($valid && !array_diff($keys, array_keys($valid)));
        [$badge, $color] = !$valid ? ['Missing', 'secondary'] : (!$complete ? ['Incomplete', 'warning']
            : (!array_diff(array_column($valid, 'status'), ['Verified']) ? ['Verified', 'success']
            : (in_array('Resubmit', array_column($valid, 'status'), true) ? ['Resubmit', 'info'] : ['Uploaded', 'info']))); ?>
        <div class="col-md-6 col-xl-4">
          <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <strong><?= e($type) ?></strong>
              <span class="badge bg-<?= $color ?>"><?= e($badge) ?></span>
            </div>
            <?php if ($isNewer): $c = reset($cur); ?>
              <div class="small mb-2"><span class="badge bg-primary">Current version</span>
                updated during <?= e($c['uploaded_during']) ?> · <?= e($c['context']) ?>. The one from this application is in the document history.</div>
            <?php endif; ?>
            <div class="row g-2">
              <?php foreach ($keys as $k): $f = $files[$k] ?? null; ?>
                <div class="<?= count($keys) > 1 ? 'col-6' : 'col-12' ?>">
                  <?php if ($f && $f['status'] === 'Invalid'): ?>
                    <?php if ($k !== ''): ?><div class="small fw-semibold text-muted"><?= e($k) ?></div><?php endif; ?>
                    <div class="alert alert-danger py-2 small mb-0">Marked invalid<?= !empty($f['invalidated_at']) ? ' ' . e(fmt_date($f['invalidated_at'])) : '' ?>: <?= e($f['invalid_reason']) ?>
                      <div class="text-muted">The file was removed; the record is kept (see the document history).</div></div>
                  <?php elseif ($f): ?>
                    <?php $src = $f['src']; $sideLabel = $k; $imgHeight = 180; $invalidTable = $f['table'];
                      $invalidBack = 'admin/application&id=' . (int)$app['id']; require VIEW_PATH . '/admin/_document_file.php'; ?>
                  <?php else: ?>
                    <?php if ($k !== ''): ?><div class="small fw-semibold text-muted"><?= e($k) ?></div><?php endif; ?>
                    <div class="small text-muted">Not uploaded.</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php require VIEW_PATH . '/admin/_document_history.php'; ?>
  </div>

  <div class="tab-pane fade" id="tab-personal">
    <div class="card border-0 shadow-sm"><div class="card-body row g-3">
      <div class="col-md-6"><label class="small text-muted">Full name</label><div class="fw-semibold"><?= e(trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['suffix'] ?? ''))) ?: '—' ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Date of birth</label><div class="fw-semibold"><?= e($p['date_of_birth'] ?? '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Contact</label><div class="fw-semibold"><?= e($p['contact_number'] ?? '—') ?></div></div>
      <div class="col-md-9"><label class="small text-muted">Address</label><div class="fw-semibold"><?= e($p['complete_address'] ?? '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">E-mail</label><div class="fw-semibold"><?= e($app['applicant']['email'] ?? '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Sex</label><div class="fw-semibold"><?= e(($p['sex'] ?? '') ?: 'Not given') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Height</label><div class="fw-semibold"><?= isset($p['height_cm']) && $p['height_cm'] !== '' ? e(rtrim(rtrim(number_format((float)$p['height_cm'], 1), '0'), '.')) . ' cm' : '—' ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Weight</label><div class="fw-semibold"><?= isset($p['weight_kg']) && $p['weight_kg'] !== '' ? e(rtrim(rtrim(number_format((float)$p['weight_kg'], 1), '0'), '.')) . ' kg' : '—' ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Blood type</label><div class="fw-semibold"><?= e(($p['blood_type'] ?? '') ?: '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Religion</label><div class="fw-semibold"><?= e(($p['religion'] ?? '') ?: '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Citizenship</label><div class="fw-semibold"><?= e(($p['citizenship'] ?? '') ?: '—') ?></div></div>
    </div></div>
  </div>

  <div class="tab-pane fade" id="tab-education">
    <div class="card border-0 shadow-sm"><div class="card-body row g-3">
      <div class="col-md-6"><label class="small text-muted">School</label><div class="fw-semibold"><?= e($edu['school_name'] ?? '—') ?></div></div>
      <div class="col-md-3"><label class="small text-muted">Year level</label><div class="fw-semibold"><?= e(($edu['year_level'] ?? '—') . (!empty($edu['year_level_other']) ? ' (' . $edu['year_level_other'] . ')' : '')) ?></div></div>
      <div class="col-md-3"><label class="small text-muted">GWA</label><div class="fw-semibold"><?= isset($edu['general_average']) ? e(number_format((float)$edu['general_average'], 2)) : '—' ?></div></div>
      <div class="col-md-6"><label class="small text-muted">Course</label><div class="fw-semibold"><?= e($edu['course_name'] ?? '—') ?></div></div>
      <div class="col-md-6"><label class="small text-muted">Major</label><div class="fw-semibold"><?= e($edu['major'] ?? '—') ?></div></div>
      <div class="col-md-6"><label class="small text-muted">Previous scholarship</label><div class="fw-semibold"><?= !empty($edu['previous_scholarship']) ? 'Yes — ' . e($edu['previous_scholarship_details'] ?? '') : 'None' ?></div></div>
      <div class="col-md-6"><label class="small text-muted">Current scholarship</label><div class="fw-semibold"><?= !empty($edu['current_scholarship']) ? 'Yes — ' . e($edu['current_scholarship_details'] ?? '') : 'None' ?></div></div>
    </div></div>
  </div>

  <div class="tab-pane fade" id="tab-family">
    <div class="card border-0 shadow-sm"><div class="card-body row g-3">
      <?php foreach (['father' => 'Father', 'mother' => 'Mother'] as $k => $label): ?>
        <div class="col-md-6">
          <h6 class="text-primary"><?= $label ?></h6>
          <div><strong>Name:</strong> <?= e($f[$k . '_name'] ?? '—') ?> (<?= e((string)($f[$k . '_age'] ?? '—')) ?>) · <?= e($f[$k . '_status'] ?? '—') ?></div>
          <div><strong>Occupation:</strong> <?= e($f[$k . '_occupation'] ?? '—') ?> · <strong>Income:</strong> <?= e(peso($f[$k . '_income'] ?? null, '—')) ?></div>
          <div><strong>Contact:</strong> <?= e($f[$k . '_contact'] ?? '—') ?> · <strong>Address:</strong> <?= e($f[$k . '_address'] ?? '—') ?></div>
        </div>
      <?php endforeach; ?>
      <div class="col-md-6"><label class="small text-muted">Total family income (monthly)</label><div class="fw-bold text-success"><?= e(peso($f['total_family_income'] ?? null, '—')) ?></div></div>
      <div class="col-md-6"><label class="small text-muted">Family size</label><div class="fw-semibold"><?= e((string)($f['family_size'] ?? '—')) ?></div></div>
    </div></div>
  </div>

  <div class="tab-pane fade" id="tab-interview">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php if (!$attempts): ?><p class="text-muted mb-0">No interview yet.</p><?php endif; ?>
      <?php foreach ($attempts as $at): ?>
        <div class="border rounded p-2 mb-2">
          <strong><?= e(Interview::describe($at)) ?></strong>
          <?= (int)$at['reschedule_count'] ? '<span class="badge bg-warning ms-1">reschedule</span>' : '' ?>
          <span class="badge bg-<?= e(ATTENDANCE_COLORS[$at['attendance']]) ?> ms-1"><?= e($at['attendance']) ?></span>
          <?php if ($at['total_score'] !== null): ?> · Score <strong><?= e(number_format((float)$at['total_score'], 2)) ?></strong> / 100
            (<a href="<?= url('admin/interview-score&id=' . (int)$at['id']) ?>">details</a>)<?php endif; ?>
          · <a href="<?= url('admin/interview-schedule&id=' . (int)$at['schedule_id']) ?>">schedule</a>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>

  <div class="tab-pane fade" id="tab-audit">
    <?php require VIEW_PATH . '/admin/_field_history.php'; ?>
    <div class="card border-0 shadow-sm"><div class="table-responsive">
      <table class="table mb-0 small align-middle">
        <thead class="table-light"><tr><th>When</th><th>By</th><th>What happened</th></tr></thead>
        <tbody>
          <?php if (!$auditTrail): ?><tr><td colspan="3" class="text-center text-muted py-3">No history yet.</td></tr><?php endif; ?>
          <?php foreach ($auditTrail as $a): ?>
            <tr><td class="text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td><td><?= e($a['actor'] ?? 'system') ?></td><td><?= e($a['description'] ?? $a['action']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<?php if ($screening): ?>
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/screening/decide') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><input type="hidden" name="decision" value="return">
      <div class="modal-header"><h5 class="modal-title">Return for correction</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label">Items the applicant must fix <span class="text-danger">*</span></label>
        <textarea name="remarks" class="form-control" rows="4" required placeholder="1. The Certificate of Residency is unreadable — upload a clearer copy.&#10;2. Your father's contact number is incomplete."></textarea>
        <div class="form-text mb-2">The applicant sees exactly this text.</div>
        <p class="small fw-semibold mb-1">Tick what is wrong <span class="fw-normal text-muted">— only these can be changed; everything else stays as submitted. Tick nothing to reopen the whole form.</span></p>
        <div style="max-height:260px;overflow:auto" class="border rounded p-2 small">
          <?php foreach (RETURN_FLAG_FIELDS as $step => $fields): ?>
            <div class="fw-semibold text-uppercase text-muted mt-1 small"><?= e(ucfirst($step)) ?></div>
            <div class="row g-0">
              <?php foreach ($fields as $f => $label): $fid = 'fl_' . md5("$step.$f"); ?>
                <div class="col-6 form-check"><input class="form-check-input" type="checkbox" name="flags[]" value="<?= e("$step.$f") ?>" id="<?= $fid ?>">
                  <label class="form-check-label" for="<?= $fid ?>"><?= e($label) ?></label></div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <div class="fw-semibold text-uppercase text-muted mt-2 small">Documents</div>
          <?php foreach (DOCUMENT_TYPES as $t): $fid = 'fd_' . md5($t); ?>
            <div class="d-flex gap-3">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="flags[]" value="<?= e("documents.$t") ?>" id="<?= $fid ?>">
                <label class="form-check-label" for="<?= $fid ?>"><?= e($t) ?> — re-upload</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="invalid_docs[]" value="<?= e($t) ?>" id="<?= $fid ?>i">
                <label class="form-check-label text-danger" for="<?= $fid ?>i">invalid — remove file</label></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Return to applicant</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/screening/decide') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><input type="hidden" name="decision" value="reject">
      <div class="modal-header"><h5 class="modal-title text-danger">Reject application</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label">Reason <span class="text-danger">*</span></label>
        <textarea name="reason" class="form-control" rows="3" required></textarea>
        <div class="form-text">The applicant sees this reason. This is final.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
