<?php
/**
 * One claim or renewal document: a box per side (School ID: Front and Back; the others: one file). A chosen file is
 * saved at once by pages/term-documents.js (no Upload button); without JavaScript each box is a plain upload form.
 * $set: side key => newest row (TermDocument::forTerm).
 */
$docCard = static function (string $purpose, string $type, array $set, bool $canEdit, int $termId): void {
  $sides = Document::sidesFor($type);
  $twoSided = count($sides) > 1;
  $whole = $twoSided && isset($set['']) ? $set[''] : null;   // one file uploaded before sides existed: counts for both
  $statusText = static fn(?array $d) => !$d ? 'Missing'
      : ($d['status'] === 'Invalid' ? 'Invalid — upload again' : ($d['status'] === 'Resubmit' ? 'Please re-upload' : ($d['status'] === 'Verified' ? 'Verified' : 'Uploaded')));
  $statusColor = static fn(?array $d) => $d ? TERM_DOC_STATUS_COLORS[$d['status']] : 'secondary'; ?>
  <div class="border rounded p-3 mb-2" data-term-doc-card>
    <strong><?= e(TermDocument::label($type)) ?></strong><?= $twoSided ? ' <span class="small text-muted">— a picture of the front and of the back</span>' : '' ?>
    <?php if ($whole): ?>
      <div class="small text-muted mt-1" data-term-doc-whole>
        On file as one file with both sides: <a href="<?= url('applicant/term-documents/view&id=' . (int)$whole['id']) ?>" target="_blank" rel="noopener"><?= e($whole['original_filename']) ?></a>
        <span class="badge bg-<?= e($statusColor($whole)) ?>"><?= e($statusText($whole)) ?></span>
        <?= $canEdit ? '· Uploading a front or back picture replaces it.' : '' ?>
      </div>
    <?php endif; ?>
    <div class="row g-2 mt-0">
      <?php foreach ($sides as $side):
        $d = $set[Document::sideKey($side)] ?? null;
        $hasFile = $d && $d['status'] !== 'Invalid';
        $isImage = $hasFile && in_array($d['mime_type'], ['image/jpeg', 'image/png'], true);
        $viewUrl = $d ? url('applicant/term-documents/view&id=' . (int)$d['id']) : ''; ?>
        <div class="<?= $twoSided ? 'col-sm-6' : 'col-12' ?>">
          <form method="POST" action="<?= url("applicant/$purpose/upload") ?>" enctype="multipart/form-data" class="h-100"
                data-term-doc-slot data-key="<?= e("$termId::$purpose::$type::" . ($side ?? '')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="term_id" value="<?= $termId ?>">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="side" value="<?= e($side ?? '') ?>">
            <div class="d-flex justify-content-between align-items-center small mb-1">
              <span class="fw-semibold"><?= $side ? e($side) : 'File' ?></span>
              <span class="badge bg-<?= e($statusColor($d)) ?>" data-slot-status><?= e($statusText($d)) ?></span>
            </div>
            <?php if ($d && $d['status'] === 'Invalid'): ?><div class="small text-danger mb-1">Removed: <?= e($d['invalid_reason']) ?></div><?php endif; ?>
            <?php if ($d && $d['status'] === 'Resubmit' && $d['remarks']): ?><div class="small text-warning-emphasis mb-1"><?= e($d['remarks']) ?></div><?php endif; ?>
            <?php if ($canEdit): ?>
              <div class="doc-dropzone" data-doc-dropzone tabindex="0" role="button" aria-label="Choose the <?= e(strtolower($side ?? 'file') . ' of your ' . TermDocument::label($type)) ?>">
                <div class="doc-preview" data-doc-preview style="<?= $hasFile ? '' : 'display:none' ?>">
                  <img src="<?= $isImage ? e($viewUrl) : '' ?>" alt="Preview" data-doc-preview-img class="<?= $isImage ? '' : 'd-none' ?>">
                  <i class="bi bi-file-earmark-pdf display-6 text-danger <?= $hasFile && !$isImage ? '' : 'd-none' ?>" data-doc-preview-icon></i>
                </div>
                <div class="doc-dropzone-empty" data-doc-dropzone-empty style="<?= $hasFile ? 'display:none' : '' ?>">
                  <i class="bi bi-cloud-arrow-up fs-3 text-muted"></i>
                  <div class="small">Drop the <?= e(strtolower($side ?? 'file')) ?> here or <span class="text-primary text-decoration-underline">Choose File</span></div>
                </div>
                <div class="small text-primary mt-1" data-doc-replace-hint style="<?= $hasFile ? '' : 'display:none' ?>">Click or drop a file to replace it</div>
              </div>
              <input type="file" name="file" class="form-control form-control-sm mt-2" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-doc-file>
              <noscript><button class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-upload"></i> Upload</button></noscript>
            <?php endif; ?>
            <div class="small text-muted mt-1" data-slot-meta style="<?= $hasFile ? '' : 'display:none' ?>">
              <a href="<?= e($viewUrl) ?>" target="_blank" rel="noopener" data-slot-link><?= e($hasFile ? $d['original_filename'] : '') ?></a>
              · <span data-slot-time><?= $hasFile ? e(fmt_datetime($d['uploaded_at'])) : '' ?></span>
            </div>
            <div class="small text-danger mt-1 d-none" data-doc-error></div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php };
?>
<div class="d-none" data-term-docs-config data-user-id="<?= (int)Auth::user()['id'] ?>" data-max-bytes="<?= MAX_FILE_SIZE ?>" data-max-text="<?= e(MAX_FILE_SIZE_TEXT) ?>"></div>
<div class="mb-4">
  <h4 class="fw-bold mb-1">My Scholarship</h4>
  <?php if ($scholar): ?>
    <p class="text-muted mb-0"><?= e($scholar['program_name']) ?> · Scholar ID <code><?= e($scholar['scholar_code']) ?></code>
      · <?= $scholar['status'] === 'Active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-' . e(Scholar::statusBadge($scholar)[1]) . '">' . e(Scholar::statusBadge($scholar)[0]) . '</span>' ?></p>
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
        <strong><?= e(peso($term['released_amount'])) ?></strong> released on
        <?= e(fmt_long_date($term['release_date'])) ?> via <?= e($term['payment_method']) ?>
        <?= $term['reference_number'] ? '· Reference no. <strong>' . e($term['reference_number']) . '</strong>' : '' ?>
      </div>
    <?php else: ?>
      <?php if (in_array($term['claim_status'], ['Waiting for Requirements', 'Resubmit'], true) && $scholar['status'] === 'Active'): ?>
        <div class="alert alert-<?= $claimWindow['can_upload'] ? 'info' : ($claimWindow['state'] === 'not_created' || $claimWindow['state'] === 'not_open_yet' ? 'secondary' : 'danger') ?> py-2">
          <i class="bi bi-calendar-event"></i> <?= e($claimWindow['message']) ?>
          <?php if (!empty($claimWindow['schedule']['planned_date'])): ?>
            <div class="small">Planned release date: <?= e(fmt_long_date($claimWindow['schedule']['planned_date'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (in_array('School ID', $claimTypes, true)): ?>
        <p class="text-muted small">Upload the front and back of your School ID and your college grades for <?= e($sem) ?>
          (PDF, JPG or PNG, max <?= e(MAX_FILE_SIZE_TEXT) ?> each). Each file is <strong>saved as soon as you choose it</strong>; then submit.</p>
      <?php else: ?>
        <p class="text-muted small">Upload your college grades for <?= e($sem) ?> (PDF, JPG or PNG, max <?= e(MAX_FILE_SIZE_TEXT) ?>).
          The file is <strong>saved as soon as you choose it</strong>; then submit.</p>
      <?php endif; ?>
      <?php if ($schoolIdOnFile): ?>
        <div class="border rounded p-3 mb-2 bg-light">
          <strong>School ID</strong> <span class="badge bg-success-subtle text-success border">On file for <?= e($term['academic_year']) ?></span>
          <div class="small text-muted mb-1">You give your School ID once per academic year, so this claim needs only your grades.</div>
          <?php foreach ($schoolIdOnFile as $k => $d): ?>
            <div class="small"><?= $k !== '' ? e($k) . ': ' : '' ?><a href="<?= e($d['applicant_url']) ?>" target="_blank" rel="noopener"><?= e($d['original_filename']) ?></a>
              · <?= e(fmt_date($d['uploaded_at'])) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($claimTypes as $type) $docCard('claim', $type, $claimDocs[$type] ?? [], $claimEditable, (int)$term['id']); ?>

      <?php if ($claimEditable): ?>
        <form method="POST" action="<?= url('applicant/claim/submit') ?>" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>">
          <button class="btn btn-success" data-term-submit="claim" <?= TermDocument::ready((int)$term['id'], 'claim', $claimTypes) ? '' : 'disabled title="Upload every file first."' ?>>
            <i class="bi bi-send-check"></i> Submit claim
          </button>
        </form>
      <?php elseif (!in_array($term['claim_status'], ['Waiting for Requirements', 'Resubmit'], true)): ?>
        <p class="small text-muted mt-2 mb-0">Nothing to do — we will e-mail you when this changes.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- A9: RENEWAL INTO THE NEXT ACADEMIC YEAR (once the 2nd Semester claim is Verified or Released) -->
<?php if ($showRenewal):
  $nextSem = $renewal ? $renewal['academic_year'] . ' ' . $renewal['semester'] : $nextSemester['academic_year'] . ' ' . $nextSemester['semester']; ?>
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
    <?php if ((!$renewal || $renewal['status'] === 'Resubmit') && $renewalWindow): ?>
      <div class="alert alert-<?= $renewalWindow['can_upload'] ? 'info' : ($renewalWindow['state'] === 'not_created' || $renewalWindow['state'] === 'not_open_yet' ? 'secondary' : 'danger') ?> py-2">
        <i class="bi bi-calendar-event"></i> <?= e($renewalWindow['message']) ?>
        <?php if (!empty($renewalWindow['schedule']['planned_date'])): ?>
          <div class="small">Semester starts: <?= e(fmt_long_date($renewalWindow['schedule']['planned_date'])) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <p class="text-muted small">Upload your updated Enrollment Form and the front and back of your School ID for <?= e($nextSem) ?>.
      Each file is <strong>saved as soon as you choose it</strong>; then submit.</p>
    <?php foreach (TERM_DOCUMENT_TYPES['renewal'] as $type) $docCard('renewal', $type, $renewalDocs[$type] ?? [], $renewalEditable, (int)$term['id']); ?>
    <?php if ($renewalEditable): ?>
      <?php $yl = $renewal['year_level'] ?? ($education['year_level'] ?? '');
        $ylOther = $renewal ? ($renewal['year_level_other'] ?? '') : ($education['year_level_other'] ?? ''); ?>
      <form method="POST" action="<?= url('applicant/renewal/submit') ?>" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>">
        <div class="row g-2 mb-3">
          <div class="col-sm-6 col-lg-4">
            <label class="form-label" for="renewYearLevel">Your year level for <?= e($nextSem) ?> <span class="text-danger">*</span></label>
            <select name="year_level" id="renewYearLevel" class="form-select" required>
              <option value="">Choose…</option>
              <?php foreach (YEAR_LEVELS as $opt): ?>
                <option <?= $yl === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Updates the year level on your application form.</div>
          </div>
          <div class="col-sm-6 col-lg-4">
            <label class="form-label" for="renewYearLevelOther">If Other, specify</label>
            <input name="year_level_other" id="renewYearLevelOther" class="form-control" maxlength="50" value="<?= e((string)$ylOther) ?>">
          </div>
        </div>
        <button class="btn btn-success" data-term-submit="renewal" <?= TermDocument::ready((int)$term['id'], 'renewal') ? '' : 'disabled title="Upload every file first."' ?>>
          <i class="bi bi-send-check"></i> Submit renewal
        </button>
      </form>
    <?php elseif ($renewal && $renewal['status'] !== 'Resubmit'): ?>
      <?php if (!empty($renewal['year_level'])): ?>
        <p class="small mt-2 mb-0">Year level for <?= e($nextSem) ?>: <strong><?= e($renewal['year_level'] === 'Other' ? $renewal['year_level_other'] : $renewal['year_level']) ?></strong></p>
      <?php endif; ?>
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
            <td><?= e(TERM_OPENED_VIA_LABELS[$t['opened_via']] ?? $t['opened_via']) ?></td>
            <td><span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$t['claim_status']]) ?>"><?= e($t['claim_status']) ?></span></td>
            <td><?= $t['gwa'] !== null ? e(number_format((float)$t['gwa'], 2)) : '—' ?></td>
            <td><?= $t['released_amount'] !== null
                    ? '₱' . e(number_format((float)$t['released_amount'], 2)) . ' · ' . e(fmt_date($t['release_date']))
                      . ($t['reference_number'] ? ' · ref. ' . e($t['reference_number']) : '')
                    : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
