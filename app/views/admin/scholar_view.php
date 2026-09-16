<?php
$statusBadge = ['Active'=>'success','On Probation'=>'warning','Suspended'=>'danger','Graduated'=>'primary','Withdrawn'=>'secondary','Disqualified'=>'dark'][$scholar['status']] ?? 'secondary';
$canEdit = Auth::hasRole('admin','staff');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/scholars') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> Back to Scholars</a>
    <h4 class="fw-bold mb-1 mt-1">
      <?= e($scholar['full_name']) ?>
      <span class="badge bg-<?= $statusBadge ?> ms-2"><?= e($scholar['status']) ?></span>
    </h4>
    <div class="text-muted small">
      <code><?= e($scholar['scholar_code']) ?></code>
      · <?= e($scholar['program_name']) ?> · Approved <?= e(date('M d, Y', strtotime($scholar['approval_date']))) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canEdit): ?>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
        <i class="bi bi-pencil-square"></i> Change Status
      </button>
      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#releaseModal">
        <i class="bi bi-cash-coin"></i> Record Release
      </button>
      <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#renewalModal">
        <i class="bi bi-arrow-repeat"></i> Add Renewal
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-cash-stack"></i></div>
    <div><div class="kpi-value" style="font-size:1.2rem;">₱<?= number_format($totalReleased, 0) ?></div><div class="kpi-label">Total Released</div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-journal-text"></i></div>
    <div><div class="kpi-value"><?= count($academics) ?></div><div class="kpi-label">Academic Records</div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-primary"><i class="bi bi-arrow-repeat"></i></div>
    <div><div class="kpi-value"><?= count($renewals) ?></div><div class="kpi-label">Renewals</div></div></div></div>
  <div class="col-md-3"><div class="kpi-card"><div class="kpi-icon kpi-warning"><i class="bi bi-exclamation-triangle"></i></div>
    <div><div class="kpi-value" style="font-size:1.1rem;"><?= $scholar['status']==='On Probation' ? 'At Risk' : 'OK' ?></div><div class="kpi-label">Standing</div></div></div></div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-overview">Overview</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-academics">Academic Records (<?= count($academics) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-releases">Releases (<?= count($releases) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-renewals">Renewals (<?= count($renewals) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-audit">Audit Trail</a></li>
</ul>

<div class="tab-content">

  <!-- OVERVIEW -->
  <div class="tab-pane fade show active" id="tab-overview">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Personal</h6></div>
        <div class="card-body small">
          <?php $p = $personal ?? []; ?>
          <p class="mb-1"><strong>Name:</strong> <?= e(trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?></p>
          <p class="mb-1"><strong>DOB / Age:</strong> <?= e($p['date_of_birth'] ?? '—') ?> · <?= (int)($p['age'] ?? 0) ?></p>
          <p class="mb-1"><strong>Contact:</strong> <?= e($p['contact_number'] ?? '—') ?></p>
          <p class="mb-1"><strong>Email:</strong> <?= e($applicant['email'] ?? '—') ?></p>
          <p class="mb-1"><strong>Address:</strong> <?= e($p['complete_address'] ?? '—') ?> <?= $p['purok_name'] ? ', ' . e($p['purok_name']) : '' ?></p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Education</h6></div>
        <div class="card-body small">
          <?php $ed = $education ?? []; ?>
          <p class="mb-1"><strong>School:</strong> <?= e($ed['school_name'] ?? '—') ?></p>
          <p class="mb-1"><strong>Course:</strong> <?= e($ed['course_name'] ?? '—') ?></p>
          <p class="mb-1"><strong>Year Level:</strong> <?= e($ed['year_level'] ?? '—') ?></p>
          <p class="mb-1"><strong>Prev. Scholarship:</strong> <?= !empty($ed['previous_scholarship']) ? 'Yes — ' . e($ed['previous_scholarship_details'] ?? '') : 'None' ?></p>
          <p class="mb-1"><strong>Current Scholarship:</strong> <?= !empty($ed['current_scholarship']) ? 'Yes — ' . e($ed['current_scholarship_details'] ?? '') : 'None' ?></p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Family</h6></div>
        <div class="card-body small">
          <?php $f = $family ?? []; ?>
          <p class="mb-1"><strong>Father:</strong> <?= e($f['father_name'] ?? '—') ?> (<?= e($f['father_status'] ?? '—') ?>) · ₱<?= number_format((float)($f['father_income'] ?? 0), 2) ?></p>
          <p class="mb-1"><strong>Mother:</strong> <?= e($f['mother_name'] ?? '—') ?> (<?= e($f['mother_status'] ?? '—') ?>) · ₱<?= number_format((float)($f['mother_income'] ?? 0), 2) ?></p>
          <p class="mb-1"><strong>Total Family Income:</strong> ₱<?= number_format((float)($f['total_family_income'] ?? 0), 2) ?></p>
          <p class="mb-0"><strong>Family Size:</strong> <?= e((string)($f['family_size'] ?? '—')) ?></p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h6 class="mb-0">Scholarship</h6></div>
        <div class="card-body small">
          <p class="mb-1"><strong>Program:</strong> <?= e($scholar['program_name']) ?></p>
          <p class="mb-1"><strong>Approval Date:</strong> <?= e(date('M d, Y', strtotime($scholar['approval_date']))) ?></p>
          <p class="mb-1"><strong>Total Released:</strong> ₱<?= number_format($totalReleased, 2) ?></p>
          <p class="mb-1"><strong>Status:</strong> <?= e($scholar['status']) ?></p>
        </div></div>
      </div>
    </div>
  </div>

  <!-- ACADEMICS -->
  <div class="tab-pane fade" id="tab-academics">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0">Academic Records</h6>
        <?php if ($canEdit): ?>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#academicModal"
                  onclick="resetAcademicForm()">
            <i class="bi bi-plus-lg"></i> Add Record
          </button>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle small">
          <thead class="table-light">
            <tr><th>A.Y.</th><th>Semester</th><th>Year Level</th><th>School</th><th>Course</th><th>GPA</th><th>Standing</th><th>Remarks</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (!$academics): ?><tr><td colspan="9" class="text-center text-muted py-3">No records yet.</td></tr><?php endif; ?>
            <?php foreach ($academics as $a):
              $gBadge = (float)$a['gpa'] <= 1.75 ? 'success' : ((float)$a['gpa'] <= 2.75 ? 'info' : ((float)$a['gpa'] <= 3.00 ? 'warning' : 'danger'));
            ?>
              <tr>
                <td><?= e($a['academic_year']) ?></td>
                <td><?= e($a['semester']) ?></td>
                <td><?= e($a['year_level']) ?></td>
                <td><?= e($a['school'] ?? '—') ?></td>
                <td><?= e($a['course'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $gBadge ?>"><?= e((string)$a['gpa']) ?></span></td>
                <td><?= e($a['academic_standing'] ?? '—') ?></td>
                <td><small><?= e($a['remarks'] ?? '') ?></small></td>
                <td class="text-end">
                  <?php if ($canEdit): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#academicModal"
                            onclick='fillAcademicForm(<?= json_encode($a, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= url('admin/academic-record-delete') ?>" class="d-inline" onsubmit="return confirm('Delete this record?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <input type="hidden" name="scholar_id" value="<?= (int)$scholar['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RELEASES -->
  <div class="tab-pane fade" id="tab-releases">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0">Scholarship Releases</h6>
        <?php if ($canEdit): ?>
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#releaseModal">
            <i class="bi bi-plus-lg"></i> Record Release
          </button>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle small">
          <thead class="table-light">
            <tr><th>Release ID</th><th>A.Y. / Sem</th><th>Amount</th><th>Date</th><th>Method</th><th>Reference</th><th>Recipient Confirmed</th><th>Staff</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (!$releases): ?><tr><td colspan="9" class="text-center text-muted py-3">No releases yet.</td></tr><?php endif; ?>
            <?php foreach ($releases as $r): ?>
              <tr>
                <td><code><?= e($r['release_code']) ?></code></td>
                <td><?= e($r['academic_year']) ?> · <?= e($r['semester']) ?></td>
                <td class="fw-bold text-success">₱<?= number_format((float)$r['amount'], 2) ?></td>
                <td><?= e(date('M d, Y', strtotime($r['release_date']))) ?></td>
                <td><?= e($r['payment_method']) ?></td>
                <td><small><?= e($r['reference_number'] ?? '—') ?></small></td>
                <td><?= $r['recipient_confirmed'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-clock text-muted"></i>' ?></td>
                <td><small><?= e($r['staff_name'] ?? '—') ?></small></td>
                <td class="text-end">
                  <?php if ($canEdit): ?>
                    <form method="POST" action="<?= url('admin/release-delete') ?>" class="d-inline" onsubmit="return confirm('Delete this release?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RENEWALS -->
  <div class="tab-pane fade" id="tab-renewals">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0">Scholarship Renewals</h6>
        <?php if ($canEdit): ?>
          <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#renewalModal">
            <i class="bi bi-plus-lg"></i> Add Renewal
          </button>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle small">
          <thead class="table-light">
            <tr><th>A.Y.</th><th>Sem</th><th>GPA</th><th>Residency</th><th>Enrollment</th><th>Docs</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (!$renewals): ?><tr><td colspan="8" class="text-center text-muted py-3">No renewal records.</td></tr><?php endif; ?>
            <?php foreach ($renewals as $r):
              $stBadge = ['Renewed'=>'success','Eligible for Renewal'=>'info','For Review'=>'warning','Not Eligible'=>'danger','Renewal Denied'=>'dark'][$r['status']] ?? 'secondary';
            ?>
              <tr>
                <td><?= e($r['academic_year']) ?></td>
                <td><?= e($r['semester'] ?? '—') ?></td>
                <td><?= e((string)$r['gpa']) ?></td>
                <td><?= $r['residency_verified'] ? '✔' : '—' ?></td>
                <td><?= $r['enrollment_verified'] ? '✔' : '—' ?></td>
                <td><?= $r['documents_complete'] ? '✔' : '—' ?></td>
                <td><span class="badge bg-<?= $stBadge ?>"><?= e($r['status']) ?></span></td>
                <td class="text-end">
                  <a href="<?= url('admin/renewal-view&id=' . (int)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
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

<!-- =================== MODALS =================== -->

<!-- Status modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/scholar-status') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$scholar['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Change Scholar Status</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (SCHOLAR_STATUS as $s): ?>
            <option <?= $scholar['status']===$s?'selected':'' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="form-label mt-3">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2"></textarea>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<!-- Academic record modal -->
<div class="modal fade" id="academicModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/academic-record-save') ?>" class="modal-content" id="academicForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ac-id">
      <input type="hidden" name="scholar_id" value="<?= (int)$scholar['id'] ?>">
      <div class="modal-header"><h5 class="modal-title" id="ac-title">Add Academic Record</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Academic Year *</label>
            <input name="academic_year" id="ac-year" class="form-control" placeholder="2025-2026" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Semester *</label>
            <select name="semester" id="ac-sem" class="form-select" required>
              <option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Year Level *</label>
            <select name="year_level" id="ac-yl" class="form-select" required>
              <?php foreach (YEAR_LEVELS as $yl): ?><option><?= e($yl) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">GPA (1.00 – 5.00) *</label>
            <input type="number" step="0.01" min="1" max="5" name="gpa" id="ac-gpa" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">School</label>
            <input name="school" id="ac-school" class="form-control" value="<?= e($education['school_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Course</label>
            <input name="course" id="ac-course" class="form-control" value="<?= e($education['course_name'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" id="ac-remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Save Record</button></div>
    </form>
  </div>
</div>

<!-- Release modal -->
<div class="modal fade" id="releaseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/release-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="scholar_id" value="<?= (int)$scholar['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Record Scholarship Release</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Academic Year *</label>
            <input name="academic_year" class="form-control" placeholder="2025-2026" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Semester *</label>
            <select name="semester" class="form-select" required>
              <option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Amount (₱) *</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Release Date *</label>
            <input type="date" name="release_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Payment Method *</label>
            <select name="payment_method" class="form-select" required>
              <option>Cash</option><option>Check</option><option>Bank Transfer</option><option>GCash</option><option>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Reference Number</label>
            <input name="reference_number" class="form-control">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" name="recipient_confirmed" value="1" class="form-check-input" id="rc1">
              <label class="form-check-label" for="rc1">Recipient confirmed receipt</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-success">Record Release</button></div>
    </form>
  </div>
</div>

<!-- Renewal modal -->
<div class="modal fade" id="renewalModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/renewal-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="scholar_id" value="<?= (int)$scholar['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Create Renewal Record</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Academic Year *</label>
            <input name="academic_year" class="form-control" placeholder="2025-2026" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
              <option value="">—</option><option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">GPA</label>
            <input type="number" step="0.01" min="1" max="5" name="gpa" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option>For Review</option><option>Eligible for Renewal</option><option>Not Eligible</option><option>Renewed</option><option>Renewal Denied</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check"><input type="checkbox" name="residency_verified" value="1" class="form-check-input" id="rv1"><label class="form-check-label" for="rv1">Residency verified</label></div>
            <div class="form-check"><input type="checkbox" name="enrollment_verified" value="1" class="form-check-input" id="ev1"><label class="form-check-label" for="ev1">Enrollment verified</label></div>
            <div class="form-check"><input type="checkbox" name="documents_complete" value="1" class="form-check-input" id="dc1"><label class="form-check-label" for="dc1">Documents complete</label></div>
          </div>
          <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-warning">Save Renewal</button></div>
    </form>
  </div>
</div>

<script>
function resetAcademicForm() {
  document.getElementById('ac-title').textContent = 'Add Academic Record';
  document.getElementById('ac-id').value = '';
  ['ac-year','ac-sem','ac-yl','ac-gpa','ac-school','ac-course','ac-remarks'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('ac-school').value = <?= json_encode($education['school_name'] ?? '') ?>;
  document.getElementById('ac-course').value = <?= json_encode($education['course_name'] ?? '') ?>;
}
function fillAcademicForm(a) {
  document.getElementById('ac-title').textContent = 'Edit Academic Record';
  document.getElementById('ac-id').value = a.id;
  document.getElementById('ac-year').value = a.academic_year || '';
  document.getElementById('ac-sem').value = a.semester || '1st Semester';
  document.getElementById('ac-yl').value = a.year_level || '1st Year';
  document.getElementById('ac-gpa').value = a.gpa || '';
  document.getElementById('ac-school').value = a.school || '';
  document.getElementById('ac-course').value = a.course || '';
  document.getElementById('ac-remarks').value = a.remarks || '';
}
</script>