<?php
$statusBadge = [
  'Draft'=>'secondary','Submitted'=>'info','Under Initial Review'=>'info','Incomplete'=>'warning',
  'Documents Under Review'=>'warning','Documents Verified'=>'success','For Interview'=>'primary',
  'Interview Scheduled'=>'primary','Interview Completed'=>'primary','For Final Evaluation'=>'primary',
  'Approved'=>'success','Rejected'=>'danger','Waitlisted'=>'warning'
][$app['status']] ?? 'secondary';
$canAct = Auth::hasRole('admin','staff');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/applications') ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to Applications
    </a>
    <h4 class="fw-bold mb-1 mt-1">
      <?= e($app['applicant']['full_name'] ?? '') ?>
      <span class="badge bg-<?= $statusBadge ?> ms-2"><?= e($app['status']) ?></span>
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

<!-- STATUS ACTION BAR -->
<?php if ($canAct && !in_array($app['status'], ['Approved','Rejected'])): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h6 class="fw-semibold mb-3">Available Actions</h6>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($app['status'] === 'Submitted'): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="start_review">
          <button class="btn btn-primary btn-sm"><i class="bi bi-hourglass"></i> Start Initial Review</button>
        </form>
      <?php endif; ?>

      <?php if ($app['status'] === 'Under Initial Review'): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="mark_incomplete">
          <button class="btn btn-warning btn-sm"><i class="bi bi-exclamation-circle"></i> Mark Incomplete</button>
        </form>
      <?php endif; ?>

      <?php if (in_array($app['status'], ['Under Initial Review','Documents Under Review'])): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="verify_documents">
          <button class="btn btn-success btn-sm" <?= Document::verifiedCount((int)$app['id']) < count(DOCUMENT_TYPES) ? 'disabled' : '' ?>>
            <i class="bi bi-check2-circle"></i> Mark Documents Verified
          </button>
        </form>
      <?php endif; ?>

    <?php if ($app['status'] === 'Documents Verified'): ?>
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
        <input type="hidden" name="action" value="for_interview">
        <button class="btn btn-info btn-sm text-white">
        <i class="bi bi-calendar-plus"></i> Mark as For Interview
        </button>
    </form>
    <?php endif; ?>

    <?php if (in_array($app['status'], ['For Final Evaluation','Interview Completed','Documents Verified','Waitlisted'])): ?>
    <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
        <input type="hidden" name="action" value="for_final">
        <button class="btn btn-primary btn-sm">
        <i class="bi bi-clipboard-check"></i> Move to Final Evaluation
        </button>
    </form>
    <?php endif; ?>

    <?php if (in_array($app['status'], ['For Final Evaluation','Interview Completed','Interview Scheduled','Documents Verified','Waitlisted'])): ?>
        <form method="POST" action="<?= url('admin/applications/update') ?>" class="d-inline" onsubmit="return confirm('Approve this application? This will create a scholar record.');">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="remarks" value="Approved after final evaluation.">
          <button class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Approve & Create Scholar</button>
        </form>

        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
          <i class="bi bi-x-circle"></i> Reject
        </button>
        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#waitlistModal">
          <i class="bi bi-hourglass"></i> Waitlist
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

  <!-- DOCUMENTS -->
  <div class="tab-pane fade" id="tab-docs">
    <div class="card border-0 shadow-sm"><div class="card-body">
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
                <?php if ($canAct): ?>
                  <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#docReviewModal"
                          data-id="<?= (int)$d['id'] ?>" data-type="<?= e($d['document_type']) ?>" data-status="Verified">
                    Verify
                  </button>
                  <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#docReviewModal"
                          data-id="<?= (int)$d['id'] ?>" data-type="<?= e($d['document_type']) ?>" data-status="Rejected">
                    Reject
                  </button>
                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#docReviewModal"
                          data-id="<?= (int)$d['id'] ?>" data-type="<?= e($d['document_type']) ?>" data-status="Resubmission Required">
                    Resubmit
                  </button>
                <?php endif; ?>
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
      <?php else: ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="small text-muted">Date</label><div class="fw-semibold"><?= e($app['interview']['interview_date']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Time</label><div class="fw-semibold"><?= e($app['interview']['start_time']) ?> – <?= e($app['interview']['end_time']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Venue</label><div class="fw-semibold"><?= e($app['interview']['venue']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Interviewer</label><div class="fw-semibold"><?= e($app['interview']['interviewer']) ?></div></div>
          <div class="col-md-6"><label class="small text-muted">Status</label><div><span class="badge bg-primary"><?= e($app['interview']['assignment_status']) ?></span></div></div>
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

<!-- Doc review modal -->
<div class="modal fade" id="docReviewModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/documents/review') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" id="docReviewId">
      <input type="hidden" name="status" value="" id="docReviewStatus">
      <input type="hidden" name="redirect" value="admin/applications/view&id=<?= (int)$app['id'] ?>">
      <div class="modal-header">
        <h5 class="modal-title">Review Document</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><strong>Document:</strong> <span id="docReviewType"></span></p>
        <p class="mb-2"><strong>Action:</strong> <span id="docReviewAction"></span></p>
        <label class="form-label">Remarks (optional)</label>
        <textarea name="remarks" class="form-control" rows="3" placeholder="Add remarks for the applicant…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Confirm</button>
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
          Enabling override allows approval even if mandatory documents are not all verified. Use sparingly and document your reason.
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
  const modal = document.getElementById('docReviewModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', e => {
      const btn = e.relatedTarget;
      modal.querySelector('#docReviewId').value    = btn.dataset.id;
      modal.querySelector('#docReviewType').textContent   = btn.dataset.type;
      modal.querySelector('#docReviewStatus').value = btn.dataset.status;
      modal.querySelector('#docReviewAction').textContent = btn.dataset.status;
    });
  }
});
</script>