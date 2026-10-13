<?php
$app = $app ?? null;
$editable = $editable ?? false;
$documents = $documents ?? [];
$docByType = [];
foreach ($documents as $d) { $docByType[$d['document_type']] = $d; }
$docsUploaded = 0;
foreach (DOCUMENT_TYPES as $dt) { if (!empty($docByType[$dt]['file_path']) && $docByType[$dt]['status'] !== 'Invalid') $docsUploaded++; }
$flags = $flags ?? [];
$flagLabels = Application::flagOptions();
$updatedAt = $app['updated_at'] ?? null;
$docLabels = ['Not Submitted' => 'Missing', 'Submitted' => 'Uploaded', 'Verified' => 'Verified', 'Invalid' => 'Invalid — upload again'];
$program = $app ?: $openProgram;
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">Scholarship Application</h4>
    <?php if ($program): ?>
      <p class="small mb-1"><strong><?= e($program['program_name']) ?></strong> · applications accepted
        <?= e(date('M d, Y', strtotime($program['application_start']))) ?> – <?= e(date('M d, Y', strtotime($program['application_end']))) ?></p>
    <?php endif; ?>
    <?php if ($editable): ?>
      <p class="text-muted small mb-1">Your information is saved automatically as you fill in the form.</p>
    <?php endif; ?>
    <span id="autosaveIndicator" class="autosave-indicator d-none"></span>
  </div>
  <?php if ($app): ?>
    <span class="badge bg-<?= e(APP_STATUS_COLORS[$app['status']] ?? 'secondary') ?> px-3 py-2"><?= e(Application::label($app['status'])) ?></span>
  <?php endif; ?>
</div>

<?php if ($app && $app['status'] === 'Returned'): ?>
  <div class="alert alert-warning">
    <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle"></i> Returned for correction</h6>
    <p class="mb-1">The SK office asked you to fix these items, then submit again:</p>
    <p class="mb-0 fw-semibold"><?= nl2br(e($app['review_remarks'] ?? '')) ?></p>
    <?php if ($flags): ?>
      <p class="mb-0 mt-2"><strong>Only these items can be changed:</strong> <?= e(implode(', ', array_map(static fn($k) => $flagLabels[$k], $flags))) ?>.
        Everything else stays exactly as you submitted it.</p>
    <?php endif; ?>
  </div>
<?php elseif (!$app && !$editable): ?>
  <div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i> No scholarship program is accepting applications right now, so the form is read-only.
  </div>
<?php elseif ($app && !$editable): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i> This application was submitted and is read-only. See your <a href="<?= url('applicant/dashboard') ?>">dashboard</a> for what happens next.
  </div>
<?php endif; ?>

<div id="restoreBanner" class="alert alert-secondary d-none">
  <span>We found recently saved information from this device. Would you like to restore it?</span>
  <div class="mt-2">
    <button type="button" id="restoreLocalBtn" class="btn btn-sm btn-primary">Restore Local Information</button>
    <button type="button" id="useServerBtn" class="btn btn-sm btn-outline-secondary">Use Server Information</button>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">

    <div class="progress-tracker mb-4">
      <?php $steps = ['Personal','Education','Family','Documents','Review'];
      foreach ($steps as $i => $s): ?>
        <div class="progress-step">
          <div class="step-circle"><?= $i + 1 ?></div>
          <div class="step-label"><?= $s ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div data-wizard
         data-editable="<?= $editable ? '1' : '0' ?>"
         data-status="<?= e($app['status'] ?? 'Draft') ?>"
         data-user-id="<?= (int)Auth::user()['id'] ?>"
         data-application-id="<?= (int)($app['id'] ?? 0) ?>"
         data-updated-at="<?= e($updatedAt ?? '') ?>"
         data-autosave-url="<?= url('applicant/application/autosave') ?>"
         data-upload-url="<?= url('applicant/documents/upload') ?>"
         data-csrf="<?= e(csrf_token()) ?>">

<?php require __DIR__ . '/form/_personal.php'; ?>
<?php require __DIR__ . '/form/_education.php'; ?>
<?php require __DIR__ . '/form/_family.php'; ?>
<?php require __DIR__ . '/form/_documents.php'; ?>
<?php require __DIR__ . '/form/_review.php'; ?>
    </div>
  </div>
</div>
<?php if ($flags): ?>
<script type="application/json" id="returnFlagData"><?= js_json(['flags' => $flags, 'fields' => array_map('array_keys', RETURN_FLAG_FIELDS)]) ?></script>
<?php endif; ?>
