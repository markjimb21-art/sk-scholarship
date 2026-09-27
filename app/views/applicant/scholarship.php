<?php
/** One upload row for a claim or renewal document. */
$docRow = static function (string $purpose, string $type, ?array $doc, bool $canEdit, int $termId): void { ?>
  <div class="border rounded p-3 mb-2">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <strong><?= e($type) ?></strong>
        <?php if ($doc): ?>
          <span class="badge bg-<?= $doc['status'] === 'Resubmit' ? 'warning' : ($doc['status'] === 'Verified' ? 'success' : 'info') ?> ms-1">
            <?= e($doc['status'] === 'Resubmit' ? 'Please re-upload' : ($doc['status'] === 'Verified' ? 'Verified' : 'Uploaded')) ?>
          </span>
          <div class="small text-muted">
            <a href="<?= url('applicant/term-documents/view&id=' . (int)$doc['id']) ?>" target="_blank" rel="noopener"><?= e($doc['original_filename']) ?></a>
            · <?= e(date('M d, Y g:i A', strtotime($doc['uploaded_at']))) ?>
          </div>
        <?php else: ?>
          <span class="badge bg-secondary ms-1">Missing</span>
        <?php endif; ?>
      </div>
      <?php if ($canEdit): ?>
        <form method="POST" action="<?= url("applicant/$purpose/upload") ?>" enctype="multipart/form-data" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="term_id" value="<?= $termId ?>">
          <input type="hidden" name="type" value="<?= e($type) ?>">
          <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
          <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-upload"></i> <?= $doc ? 'Replace' : 'Upload' ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php };
?>
<div class="mb-4">
  <h4 class="fw-bold mb-1">My Scholarship</h4>
  <?php if ($scholar): ?>
    <p class="text-muted mb-0"><?= e($scholar['program_name']) ?> · Scholar ID <code><?= e($scholar['scholar_code']) ?></code>
      · <?= $scholar['status'] === 'Active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Ended — ' . e($scholar['end_reason']) . '</span>' ?></p>
  <?php endif; ?>
</div>

<?php if (!$scholar): ?>
  <div class="alert alert-secondary">You are not a scholar yet. Your application progress is on your <a href="<?= url('applicant/dashboard') ?>">dashboard</a>.</div>
<?php else: ?>

<?php if ($term):
  $sem = $term['academic_year'] . ' ' . $term['semester'];
  $claimEditable = Term::claimEditable($term);
  $renewalEditable = Renewal::editable($term);
?>
<!-- A8: CLAIM FOR THE CURRENT SEMESTER -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h5 class="fw-semibold mb-0">Cash claim — <?= e($sem) ?></h5>
      <span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$term['claim_status']]) ?> px-3 py-2"><?= e(CLAIM_STATUS_LABELS[$term['claim_status']]) ?></span>
    </div>

    <?php if ($term['claim_status'] === 'Resubmit' && $term['remarks']): ?>
      <div class="alert alert-warning"><strong>SK office remarks:</strong> <?= nl2br(e($term['remarks'])) ?></div>
    <?php endif; ?>

    <?php if ($term['claim_status'] === 'Released'): ?>
      <div class="alert alert-success mb-0">
        <strong>₱<?= e(number_format((float)$term['released_amount'], 2)) ?></strong> released on
        <?= e(date('F j, Y', strtotime($term['release_date']))) ?> via <?= e($term['payment_method']) ?>
        <?= $term['reference_number'] ? '· Reference no. <strong>' . e($term['reference_number']) . '</strong>' : '' ?>
      </div>
    <?php else: ?>
      <p class="text-muted small">Upload your School ID and your Grades for <?= e($sem) ?> (PDF, JPG or PNG, max 5MB each), then submit.</p>
      <?php foreach (TERM_DOCUMENT_TYPES['claim'] as $type) $docRow('claim', $type, $claimDocs[$type] ?? null, $claimEditable, (int)$term['id']); ?>

      <?php if ($claimEditable): ?>
        <form method="POST" action="<?= url('applicant/claim/submit') ?>" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>">
          <button class="btn btn-success" <?= Term::documentsReady((int)$term['id'], 'claim') ? '' : 'disabled title="Upload both documents first."' ?>>
            <i class="bi bi-send-check"></i> Submit claim
          </button>
        </form>
      <?php else: ?>
        <p class="small text-muted mt-2 mb-0">Nothing to do — we will e-mail you when this changes.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- A9: RENEWAL FOR THE NEXT SEMESTER (after the current semester was released) -->
<?php if ($term['claim_status'] === 'Released' && $scholar['status'] === 'Active'):
  $nextSem = $nextSemester['academic_year'] . ' ' . $nextSemester['semester']; ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h5 class="fw-semibold mb-0">Renewal for <?= e($nextSem) ?></h5>
      <span class="badge bg-<?= !$renewal ? 'secondary' : ($renewal['status'] === 'Resubmit' ? 'warning' : 'info') ?> px-3 py-2">
        <?= e($renewal ? RENEWAL_STATUS_LABELS[$renewal['status']] : 'Not submitted') ?>
      </span>
    </div>
    <?php if ($renewal && $renewal['status'] === 'Resubmit' && $renewal['remarks']): ?>
      <div class="alert alert-warning"><strong>SK office remarks:</strong> <?= nl2br(e($renewal['remarks'])) ?></div>
    <?php endif; ?>
    <p class="text-muted small">Upload your updated Enrollment Form and School ID for <?= e($nextSem) ?>, then submit.</p>
    <?php foreach (TERM_DOCUMENT_TYPES['renewal'] as $type) $docRow('renewal', $type, $renewalDocs[$type] ?? null, $renewalEditable, (int)$term['id']); ?>
    <?php if ($renewalEditable): ?>
      <form method="POST" action="<?= url('applicant/renewal/submit') ?>" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>">
        <button class="btn btn-success" <?= Term::documentsReady((int)$term['id'], 'renewal') ? '' : 'disabled title="Upload both documents first."' ?>>
          <i class="bi bi-send-check"></i> Submit renewal
        </button>
      </form>
    <?php else: ?>
      <p class="small text-muted mt-2 mb-0">Nothing to do — we will e-mail you when your renewal is reviewed.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- PAST SEMESTERS (read-only) -->
<h5 class="fw-semibold mb-3">All semesters</h5>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light"><tr><th>Semester</th><th>Opened by</th><th>Claim</th><th>GWA</th><th>Released</th></tr></thead>
      <tbody>
        <?php foreach ($terms as $t): ?>
          <tr>
            <td><?= e($t['academic_year'] . ' ' . $t['semester']) ?></td>
            <td><?= e($t['opened_via'] === 'approval' ? 'Approval' : 'Renewal') ?></td>
            <td><span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$t['claim_status']]) ?>"><?= e($t['claim_status']) ?></span></td>
            <td><?= $t['gwa'] !== null ? e(number_format((float)$t['gwa'], 2)) : '—' ?></td>
            <td><?= $t['released_amount'] !== null
                    ? '₱' . e(number_format((float)$t['released_amount'], 2)) . ' · ' . e(date('M d, Y', strtotime($t['release_date'])))
                      . ($t['reference_number'] ? ' · ref. ' . e($t['reference_number']) : '')
                    : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
