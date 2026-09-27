<?php
$statusBadge = [
  'Draft'=>'secondary','Submitted'=>'info','Under Initial Review'=>'info','Incomplete'=>'warning',
  'Documents Under Review'=>'warning','Documents Verified'=>'success','For Interview'=>'primary',
  'Interview Scheduled'=>'primary','Interview Completed'=>'primary','For Final Evaluation'=>'primary',
  'Approved'=>'success','Rejected'=>'danger','Waitlisted'=>'warning'
][$app['status']] ?? 'secondary';
$canAct = Auth::hasRole('admin','staff');
// Buttons come from the same transition map the server enforces (Application::TRANSITIONS).
$can = static fn(string $action): bool => Application::actionAllowed($action, $app);
// Marks a button that is only available because an admin override is active on this application.
$viaOverride = static fn(string $action): string => Application::transitionAllowed($action, $app['status'])
    ? '' : ' <span class="badge bg-dark ms-1">override</span>';
$review = $can('for_interview') ? Application::reviewCompleteness((int)$app['id']) : null;
$slotLimit = (int)($program['slots_available'] ?? 0);
$slotsFull = $slotLimit > 0 && $slotsUsed >= $slotLimit;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/applications') ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to Applications
    </a>
    <h4 class="fw-bold mb-1 mt-1">
      <?= e($app['applicant']['full_name'] ?? '') ?>
      <span class="badge bg-<?= $statusBadge ?> ms-2"><?= e($app['status']) ?></span>
      <?php if (!empty($app['admin_override'])): ?>
        <span class="badge bg-dark ms-1" title="<?= e($app['override_reason'] ?? '') ?>"><i class="bi bi-shield-lock"></i> Override active</span>
      <?php endif; ?>
    </h4>
    <div class="text-muted small">
      <code><?= e($app['application_code']) ?></code> ·
      <?= e($app['program_name']) ?> · <?= e($app['academic_year']) ?>
    </div>
  </div>
  <div>
    <a href="mailto:<?= e($app['applicant']['email'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-envelope"></i> Email
    </a>
  </div>
</div>

<?php if ($app['status'] === 'Incomplete' && !empty($app['review_remarks'])): ?>
<div class="alert alert-warning mb-4">
  <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-circle"></i> Application Status: Incomplete</h6>
  <p class="mb-2"><strong>Remarks:</strong> <?= nl2br(e($app['review_remarks'])) ?></p>
  <p class="small text-muted mb-0">
    Marked Incomplete on <?= e(date('M d, Y g:i A', strtotime($app['review_remarks_at']))) ?>
    by <?= e($app['review_remarks_by_name'] ?? 'SK Office') ?>
  </p>
</div>
<?php endif; ?>

<!-- REVIEW SUMMARY (only meaningful while the application is awaiting initial review) -->
<?php if ($review): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <h6 class="fw-semibold mb-3">Application Review</h6>
    <ul class="list-unstyled mb-3">
      <li class="mb-1">
        <i class="bi bi-<?= $review['personal_ok'] ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning' ?>"></i>
        Personal Information <?= $review['personal_ok'] ? 'complete' : 'needs attention' ?>
      </li>
      <li class="mb-1">
        <i class="bi bi-<?= $review['education_ok'] ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning' ?>"></i>
        Education <?= $review['education_ok'] ? 'complete' : 'needs attention' ?>
      </li>
      <li class="mb-1">
        <i class="bi bi-<?= $review['family_ok'] ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning' ?>"></i>
        Family Background <?= $review['family_ok'] ? 'complete' : 'needs attention' ?>
      </li>
      <li class="mb-1">
        <i class="bi bi-<?= empty($review['missing_documents']) ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning' ?>"></i>
        Required Documents <?= $review['docs_uploaded'] ?>/<?= $review['docs_required'] ?>
      </li>
    </ul>
    <?php if ($review['complete']): ?>
      <div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle"></i> <strong>Ready for Interview</strong> — everything required has been submitted.</div>
    <?php else: ?>
      <div class="alert alert-warning py-2 mb-0">
        <i class="bi bi-exclamation-triangle"></i> <strong>Needs correction</strong> —
        <?php if ($review['missing_documents']): ?>missing: <?= e(implode(', ', $review['missing_documents'])) ?>. <?php endif; ?>
        <?php if ($review['missing_fields']): ?>check: <?= e(implode(', ', $review['missing_fields'])) ?>.<?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ELIGIBILITY (program rules checked at submission; informational only — staff make the decision) -->
<?php if ($eligibility):
  $eScore = (float)$eligibility['score'];
  $eLines = array_values(array_filter(explode("\n", (string)($eligibility['notes'] ?? '')), static fn($l) => trim($l) !== ''));
  $eFailed = array_values(array_filter($eLines, static fn($l) => !str_starts_with($l, 'Not checked:')));
  $eColor = $eFailed ? ($eScore >= 50 ? 'warning' : 'danger') : 'success';
?>
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-<?= $eColor ?>">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-clipboard-data"></i> Eligibility Check</h6>
      <span class="badge bg-<?= $eColor ?> px-3 py-2">Score <?= e(number_format($eScore, 0)) ?>%</span>
    </div>
    <?php if ($eligibility['live']): ?>
      <p class="small text-muted mb-2">Computed now from the applicant's current data (this application was submitted before eligibility checks were recorded).</p>
    <?php endif; ?>
    <?php if (!$eFailed): ?>
      <div class="text-success small mb-2"><i class="bi bi-check-circle-fill"></i> Meets all checked program criteria.</div>
    <?php endif; ?>
    <?php if ($eLines): ?>
      <ul class="list-unstyled small mb-2">
        <?php foreach ($eLines as $line): $notChecked = str_starts_with($line, 'Not checked:'); ?>
          <li class="mb-1">
            <i class="bi bi-<?= $notChecked ? 'dash-circle text-secondary' : 'x-circle-fill text-danger' ?>"></i>
            <?= e($line) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($program): ?>
      <div class="small text-muted border-top pt-2">
        <strong>Program rules:</strong>
        max age <?= $program['max_age'] ? (int)$program['max_age'] : 'none' ?> ·
        max family income <?= (float)$program['max_family_income'] > 0 ? '₱' . number_format((float)$program['max_family_income'], 2) : 'none' ?> ·
        citizenship <?= e($program['citizenship_required'] ?: 'any') ?> ·
        min GPA <?= (float)$program['min_gpa'] > 0 ? e(number_format((float)$program['min_gpa'], 2)) : 'none' ?>
        (applicant: <?= isset($app['education']['general_average']) ? e(number_format((float)$app['education']['general_average'], 2)) : 'not provided' ?>) ·
        slots <?= $slotLimit > 0 ? $slotsUsed . ' / ' . $slotLimit . ' filled' : 'unlimited' ?>
      </div>
    <?php endif; ?>
    <div class="small text-muted mt-1">This check never rejects automatically — use it to inform your decision.</div>
  </div>
</div>
<?php endif; ?>

<!-- STATUS ACTION BAR — generated from Application::TRANSITIONS, the same map the server enforces. -->
<?php if ($canAct && !in_array($app['status'], ['Approved','Rejected'])): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h6 class="fw-semibold mb-3">Available Actions</h6>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($can('start_review')): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="start_review">
          <button class="btn btn-primary btn-sm"><i class="bi bi-hourglass"></i> Start Initial Review<?= $viaOverride('start_review') ?></button>
        </form>
      <?php endif; ?>

      <?php if ($can('for_interview')): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="for_interview">
          <button class="btn btn-success btn-sm" <?= (!$review || !$review['complete']) ? 'disabled title="Application is incomplete — see the review summary above."' : '' ?>>
            <i class="bi bi-calendar-check"></i> Mark as For Interview<?= $viaOverride('for_interview') ?>
          </button>
        </form>
      <?php endif; ?>

      <?php if ($can('mark_incomplete')): ?>
        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#incompleteModal">
          <i class="bi bi-exclamation-circle"></i> Mark Incomplete<?= $viaOverride('mark_incomplete') ?>
        </button>
      <?php endif; ?>

      <?php if ($app['status'] === 'For Interview'): ?>
        <a href="<?= url('admin/interview-scheduling&program_id=' . (int)$app['program_id'] . '&focus=' . (int)$app['id']) ?>" class="btn btn-info btn-sm text-white">
          <i class="bi bi-calendar-plus"></i> Schedule Interview
        </a>
      <?php endif; ?>

      <?php if ($app['status'] === 'Interview Scheduled' && $app['interview']): ?>
        <a href="<?= url('admin/interview-schedule-detail&id=' . (int)$app['interview']['schedule_id']) ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-calendar-event"></i> View Interview
        </a>
      <?php endif; ?>

      <?php if ($can('for_final')): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="for_final">
          <button class="btn btn-primary btn-sm">
            <i class="bi bi-clipboard-check"></i> Move to Final Evaluation<?= $viaOverride('for_final') ?>
          </button>
        </form>
      <?php endif; ?>

      <?php if ($can('approve')):
        $slotBlocked = $slotsFull && !Application::canBypassTransitions($app); ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline" onsubmit="return confirm('Approve this application? This will create a scholar record.');">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="remarks" value="Approved after final evaluation.">
          <button class="btn btn-success btn-sm" <?= $slotBlocked ? 'disabled title="All ' . $slotLimit . ' program slots are filled — an admin override is required."' : '' ?>>
            <i class="bi bi-check-circle"></i> Approve & Create Scholar<?= $viaOverride('approve') ?>
          </button>
        </form>
      <?php endif; ?>

      <?php if ($can('reject')): ?>
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
          <i class="bi bi-x-circle"></i> Reject<?= $viaOverride('reject') ?>
        </button>
      <?php endif; ?>

      <?php if ($can('waitlist')): ?>
        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#waitlistModal">
          <i class="bi bi-hourglass"></i> Waitlist<?= $viaOverride('waitlist') ?>
        </button>
      <?php endif; ?>

      <?php if (Auth::hasRole('admin')): ?>
        <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#overrideModal">
          <i class="bi bi-shield-lock"></i> Admin Override
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- TABS -->
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-personal">Personal</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-education">Education</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-family">Family</a></li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab-docs">
      Documents <span class="badge bg-secondary ms-1"><?= count($app['documents']) ?></span>
    </a>
  </li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-interview">Interview</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-audit">Audit Trail</a></li>
</ul>

<div class="tab-content">
  <!-- PERSONAL -->
  <div class="tab-pane fade show active" id="tab-personal">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php $p = $app['personal'] ?? []; ?>
      <div class="row g-3">
        <div class="col-md-4"><label class="small text-muted">Full Name</label><div class="fw-semibold"><?= e(trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Date of Birth</label><div class="fw-semibold"><?= e($p['date_of_birth'] ?? '—') ?> (Age <?= (int)($p['age'] ?? 0) ?>)</div></div>
        <div class="col-md-4"><label class="small text-muted">Civil Status</label><div class="fw-semibold"><?= e($p['civil_status'] ?? '—') ?></div></div>
        <div class="col-md-8"><label class="small text-muted">Address</label><div class="fw-semibold"><?= e($p['complete_address'] ?? '—') ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Purok</label><div class="fw-semibold"><?= e($p['purok_name'] ?? '—') ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Contact</label><div class="fw-semibold"><?= e($p['contact_number'] ?? '—') ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Email</label><div class="fw-semibold"><?= e($p['email'] ?? '—') ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Citizenship</label><div class="fw-semibold"><?= e($p['citizenship'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Height / Weight</label><div class="fw-semibold"><?= e(($p['height_cm'] ?? '—') . ' cm / ' . ($p['weight_kg'] ?? '—') . ' kg') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Blood Type</label><div class="fw-semibold"><?= e($p['blood_type'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Religion</label><div class="fw-semibold"><?= e($p['religion'] ?? '—') ?></div></div>
      </div>
    </div></div>
  </div>

  <!-- EDUCATION -->
  <div class="tab-pane fade" id="tab-education">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php $edu = $app['education'] ?? []; ?>
      <div class="row g-3">
        <div class="col-md-6"><label class="small text-muted">School</label><div class="fw-semibold"><?= e($edu['school_name'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Year Level</label><div class="fw-semibold"><?= e($edu['year_level'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Course</label><div class="fw-semibold"><?= e($edu['course_name'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">Major</label><div class="fw-semibold"><?= e($edu['major'] ?? '—') ?></div></div>
        <div class="col-md-3"><label class="small text-muted">General Average</label><div class="fw-semibold"><?= isset($edu['general_average']) ? e(number_format((float)$edu['general_average'], 2)) : '—' ?></div></div>
        <div class="col-md-6"><label class="small text-muted">Previous Scholarship</label><div class="fw-semibold"><?= !empty($edu['previous_scholarship']) ? 'Yes — ' . e($edu['previous_scholarship_details'] ?? '') : 'None' ?></div></div>
        <div class="col-md-6"><label class="small text-muted">Current Scholarship</label><div class="fw-semibold"><?= !empty($edu['current_scholarship']) ? 'Yes — ' . e($edu['current_scholarship_details'] ?? '') : 'None' ?></div></div>
      </div>
    </div></div>
  </div>

  <!-- FAMILY -->
  <div class="tab-pane fade" id="tab-family">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php $f = $app['family'] ?? []; ?>
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="text-primary">Father</h6>
          <div><strong>Name:</strong> <?= e($f['father_name'] ?? '—') ?></div>
          <div><strong>Age / Occupation:</strong> <?= e($f['father_age'] ?? '—') ?> / <?= e($f['father_occupation'] ?? '—') ?></div>
          <div><strong>Status:</strong> <?= e($f['father_status'] ?? '—') ?></div>
          <div><strong>Income:</strong> ₱<?= number_format((float)($f['father_income'] ?? 0), 2) ?></div>
        </div>
        <div class="col-md-6">
          <h6 class="text-primary">Mother</h6>
          <div><strong>Name:</strong> <?= e($f['mother_name'] ?? '—') ?></div>
          <div><strong>Age / Occupation:</strong> <?= e($f['mother_age'] ?? '—') ?> / <?= e($f['mother_occupation'] ?? '—') ?></div>
          <div><strong>Status:</strong> <?= e($f['mother_status'] ?? '—') ?></div>
          <div><strong>Income:</strong> ₱<?= number_format((float)($f['mother_income'] ?? 0), 2) ?></div>
        </div>
        <div class="col-12"><hr></div>
        <div class="col-md-4"><label class="small text-muted">Combined Parental Income</label><div class="fw-bold text-success">₱<?= number_format((float)($f['combined_income'] ?? 0), 2) ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Total Family Income</label><div class="fw-bold text-success">₱<?= number_format((float)($f['total_family_income'] ?? 0), 2) ?></div></div>
        <div class="col-md-4"><label class="small text-muted">Family Size</label><div class="fw-semibold"><?= e((string)($f['family_size'] ?? '—')) ?></div></div>
      </div>
    </div></div>
  </div>

  <!-- DOCUMENTS: review/view interface only — verification happens automatically via "Mark as For Interview". -->
  <div class="tab-pane fade" id="tab-docs">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php
        $docsUploaded = 0;
        foreach ($app['documents'] as $d) { if ($d['file_path'] !== '') $docsUploaded++; }
        $docsTotal = count(DOCUMENT_TYPES);
        $docsReady = $docsUploaded >= $docsTotal;
      ?>
      <div class="alert <?= $docsReady ? 'alert-success' : 'alert-warning' ?> d-flex justify-content-between align-items-center mb-3">
        <div>
          <strong>Required Documents</strong> — <?= $docsUploaded ?>/<?= $docsTotal ?> Uploaded
        </div>
        <span class="fw-semibold"><?= $docsReady ? 'READY FOR REVIEW' : 'INCOMPLETE' ?></span>
      </div>
      <div class="row g-3">
        <?php foreach ($app['documents'] as $d):
          $docStatusClass = 'doc-' . str_replace(' ', '-', $d['status']);
        ?>
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <div class="d-flex justify-content-between mb-2">
              <strong><?= e($d['document_type']) ?></strong>
              <span class="doc-pill <?= $docStatusClass ?>"><?= e($d['status']) ?></span>
            </div>
            <?php if ($d['file_path']): ?>
              <div class="small text-muted mb-2">
                <i class="bi bi-paperclip"></i> <?= e($d['original_filename']) ?><br>
                Uploaded <?= e(date('M d, Y g:i A', strtotime($d['uploaded_at']))) ?>
                <?php if ($d['reviewed_at']): ?>
                  <br>Reviewed by <?= e($d['reviewer_name'] ?? '—') ?> on <?= e(date('M d, Y', strtotime($d['reviewed_at']))) ?>
                <?php endif; ?>
              </div>
              <?php if ($d['remarks']): ?>
                <div class="alert alert-secondary py-1 px-2 small mb-2"><strong>Remarks:</strong> <?= e($d['remarks']) ?></div>
              <?php endif; ?>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= url('admin/documents/view&id=' . (int)$d['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i> View
                </a>
                <a href="<?= url('admin/documents/view&id=' . (int)$d['id'] . '&download=1') ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-download"></i>
                </a>
              </div>
            <?php else: ?>
              <p class="text-muted small mb-0">Not yet uploaded.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>

  <!-- INTERVIEW -->
  <div class="tab-pane fade" id="tab-interview">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <?php if (!$app['interview']): ?>
        <p class="text-muted mb-0">No interview schedule assigned yet.</p>
        <?php if ($app['status'] === 'For Interview' && $canAct): ?>
          <a href="<?= url('admin/interview-scheduling&program_id=' . (int)$app['program_id'] . '&focus=' . (int)$app['id']) ?>" class="btn btn-info btn-sm text-white mt-2">
            <i class="bi bi-calendar-plus"></i> Schedule Interview
          </a>
        <?php endif; ?>
      <?php else: ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="small text-muted">Date</label><div class="fw-semibold"><?= e($app['interview']['interview_date']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Time</label><div class="fw-semibold"><?= e($app['interview']['start_time']) ?> – <?= e($app['interview']['end_time']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Venue</label><div class="fw-semibold"><?= e($app['interview']['venue']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Interviewer</label><div class="fw-semibold"><?= e($app['interview']['interviewer']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Status</label><div><span class="badge bg-primary"><?= e($app['interview']['assignment_status']) ?></span></div></div>
          <div class="col-md-6">
            <a href="<?= url('admin/interview-schedule-detail&id=' . (int)$app['interview']['schedule_id']) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-calendar-event"></i> View Interview Details
            </a>
          </div>
          <?php if ($app['evaluation']): ?>
            <div class="col-12"><hr></div>
            <div class="col-md-4"><label class="small text-muted">Total Score</label><div class="fw-bold"><?= e((string)$app['evaluation']['total_score']) ?></div></div>
            <div class="col-md-4"><label class="small text-muted">Result</label><div class="fw-bold"><?= e((string)$app['evaluation']['result']) ?></div></div>
            <div class="col-md-4"><label class="small text-muted">Rating</label><div class="fw-bold"><?= e((string)$app['evaluation']['rating']) ?></div></div>
            <?php if ($app['evaluation']['recommendation']): ?>
              <div class="col-12"><label class="small text-muted">Recommendation</label><p><?= nl2br(e($app['evaluation']['recommendation'])) ?></p></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div></div>
  </div>

  <!-- AUDIT -->
  <div class="tab-pane fade" id="tab-audit">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle small">
          <thead class="table-light"><tr><th>When</th><th>By</th><th>Action</th><th>Description</th></tr></thead>
          <tbody>
            <?php if (!$auditTrail): ?><tr><td colspan="4" class="text-center text-muted py-3">No audit records.</td></tr><?php endif; ?>
            <?php foreach ($auditTrail as $a): ?>
              <tr>
                <td><?= e(date('M d, Y g:i A', strtotime($a['created_at']))) ?></td>
                <td><?= e($a['actor'] ?? 'system') ?></td>
                <td><code><?= e($a['action']) ?></code></td>
                <td><?= e($a['description'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Mark Incomplete modal -->
<div class="modal fade" id="incompleteModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
      <input type="hidden" name="action" value="mark_incomplete">
      <div class="modal-header">
        <h5 class="modal-title text-warning">Mark Application as Incomplete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><strong>Applicant:</strong> <?= e($app['applicant']['full_name'] ?? '') ?></p>
        <p class="mb-3"><strong>Application Code:</strong> <code><?= e($app['application_code']) ?></code></p>
        <?php if ($review && !$review['complete']): ?>
          <div class="alert alert-light border small mb-3">
            <strong>Review found:</strong>
            <?php if ($review['missing_documents']): ?><br>Missing documents: <?= e(implode(', ', $review['missing_documents'])) ?>.<?php endif; ?>
            <?php if ($review['missing_fields']): ?><br>Check: <?= e(implode(', ', $review['missing_fields'])) ?>.<?php endif; ?>
          </div>
        <?php endif; ?>
        <label class="form-label">What needs to be corrected? <span class="text-danger">*</span></label>
        <textarea name="remarks" class="form-control" rows="5" required
                  placeholder="Example:&#10;1. Certificate of Residency is missing.&#10;2. School ID image is unclear.&#10;3. Please correct the father's contact number.&#10;4. Please complete the mother's occupation."></textarea>
        <div class="form-text">Specify the missing, incorrect, or unclear information so the applicant knows exactly what to correct. You may list multiple issues in one remark.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Mark as Incomplete</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
      <input type="hidden" name="action" value="reject">
      <div class="modal-header"><h5 class="modal-title text-danger">Reject Application</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Reason for rejection</label>
        <textarea name="remarks" class="form-control" rows="3" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<!-- Waitlist modal -->
<div class="modal fade" id="waitlistModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
      <input type="hidden" name="action" value="waitlist">
      <div class="modal-header"><h5 class="modal-title text-warning">Waitlist Application</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Waitlist remarks</label>
        <textarea name="remarks" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Confirm</button>
      </div>
    </form>
  </div>
</div>

<!-- Override modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
      <input type="hidden" name="action" value="admin_override">
      <div class="modal-header"><h5 class="modal-title text-dark">Admin Override</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="alert alert-warning small">
          Enabling override lets an <strong>admin</strong> take decision actions outside the normal workflow order
          (for example approving before final evaluation) and approve beyond the program's slot limit.
          Every action taken this way is recorded in the audit log. Use sparingly and document your reason.
        </div>
        <label class="form-label">Reason for override</label>
        <textarea name="reason" class="form-control" rows="3" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-dark">Enable Override</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Restore the active tab (e.g. #tab-docs) after a form POST redirects back to this page.
  if (window.location.hash) {
    const trigger = document.querySelector('.nav-link[href="' + window.location.hash + '"]');
    if (trigger && window.bootstrap) {
      new bootstrap.Tab(trigger).show();
    }
  }
});
</script>
