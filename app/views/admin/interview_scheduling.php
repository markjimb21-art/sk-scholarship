<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Interview Scheduling</h4>
    <p class="text-muted small mb-0"><?= $program ? e($program['program_name'] . ' · ' . $program['academic_year']) : 'Select a program' ?></p>
  </div>
  <div class="d-flex gap-2">
    <form method="GET" action="<?= url('admin/interview-scheduling') ?>" class="d-flex gap-2">
      <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach ($programs as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>>
            <?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newScheduleModal"
            <?= $programId ? '' : 'disabled' ?>>
      <i class="bi bi-plus-lg"></i> New Schedule
    </button>
    <a href="<?= url('admin/interview-calendar' . ($programId ? '&program_id=' . $programId : '')) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-calendar3"></i> Calendar View
    </a>
  </div>
</div>

<?php if (!$programId): ?>
  <div class="alert alert-warning">No scholarship program configured. <a href="<?= url('admin/settings') ?>">Configure one</a>.</div>
<?php else: ?>

  <!-- SCHEDULES -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h6 class="mb-0">Schedules (<?= count($schedules) ?>)</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Date</th><th>Time</th><th>Venue</th><th>Interviewer</th>
            <th>Slots</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$schedules): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No schedules yet. Click <strong>New Schedule</strong>.</td></tr>
          <?php endif; ?>
          <?php foreach ($schedules as $s):
            $isFull = (int)$s['assigned_count'] >= (int)$s['max_slots'];
            $slotBadge = $isFull ? 'danger' : ((int)$s['assigned_count'] > 0 ? 'warning' : 'success');
          ?>
          <tr>
            <td><strong><?= e(date('D, M d, Y', strtotime($s['interview_date']))) ?></strong></td>
            <td><?= e(date('g:i A', strtotime($s['start_time']))) ?> – <?= e(date('g:i A', strtotime($s['end_time']))) ?></td>
            <td><?= e($s['venue']) ?></td>
            <td><?= e($s['interviewer']) ?></td>
            <td>
              <span class="badge bg-<?= $slotBadge ?>">
                <?= (int)$s['assigned_count'] ?> / <?= (int)$s['max_slots'] ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?= $s['status']==='Cancelled'?'secondary':($s['status']==='Full'?'warning':'primary') ?>">
                <?= e($s['status']) ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?= url('admin/interview-schedule-detail&id=' . (int)$s['id']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-people"></i> Manage
              </a>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editScheduleModal"
                      data-sched='<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelScheduleModal"
                      data-id="<?= (int)$s['id'] ?>">
                <i class="bi bi-x-circle"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ELIGIBLE APPLICANTS -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
      <h6 class="mb-0">
        Eligible for Interview
        <span class="badge bg-primary"><?= count($eligible) ?></span>
        <small class="text-muted ms-2">Applicants with "For Interview" status & no assignment yet</small>
      </h6>
    </div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>Code</th><th>Name</th><th>School</th><th>Course</th><th>Docs</th></tr></thead>
        <tbody>
          <?php if (!$eligible): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">
              No applicants awaiting interview assignment.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($eligible as $a): ?>
            <tr>
              <td><code><?= e($a['application_code']) ?></code></td>
              <td><?= e($a['full_name']) ?></td>
              <td><?= e($a['school_name'] ?? '—') ?></td>
              <td><?= e($a['course_name'] ?? '—') ?></td>
              <td><span class="badge bg-success"><?= (int)$a['docs_verified'] ?>/5</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<!-- NEW SCHEDULE MODAL -->
<div class="modal fade" id="newScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="program_id" value="<?= $programId ?>">
      <div class="modal-header"><h5 class="modal-title">New Interview Schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Interview Date *</label>
          <input type="date" name="interview_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Start Time *</label>
            <input type="time" name="start_time" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">End Time *</label>
            <input type="time" name="end_time" class="form-control" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Venue *</label>
          <input name="venue" class="form-control" required placeholder="e.g., Barangay Estefania Hall">
        </div>
        <div class="mb-3">
          <label class="form-label">Interviewer / Panel *</label>
          <input name="interviewer" class="form-control" required placeholder="e.g., SK Scholarship Committee">
        </div>
        <div class="mb-3">
          <label class="form-label">Maximum Slots *</label>
          <input type="number" min="1" name="max_slots" class="form-control" value="30" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Create Schedule</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT SCHEDULE MODAL -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="edit-id">
      <input type="hidden" name="program_id" value="<?= $programId ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="interview_date" id="edit-date" class="form-control" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Start *</label><input type="time" name="start_time" id="edit-start" class="form-control" required></div>
          <div class="col-6"><label class="form-label">End *</label><input type="time" name="end_time" id="edit-end" class="form-control" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Venue *</label><input name="venue" id="edit-venue" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Interviewer *</label><input name="interviewer" id="edit-interviewer" class="form-control" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Max Slots *</label><input type="number" min="1" name="max_slots" id="edit-slots" class="form-control" required></div>
          <div class="col-6">
            <label class="form-label">Status *</label>
            <select name="status" id="edit-status" class="form-select">
              <option>Open</option><option>Full</option><option>Cancelled</option><option>Completed</option>
            </select>
          </div>
        </div>
        <div class="mb-3"><label class="form-label">Remarks</label><textarea name="remarks" id="edit-remarks" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- CANCEL SCHEDULE MODAL -->
<div class="modal fade" id="cancelScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-cancel') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="cancel-id">
      <input type="hidden" name="program_id" value="<?= $programId ?>">
      <div class="modal-header"><h5 class="modal-title text-danger">Cancel Schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="alert alert-warning small">
          This will <strong>cancel the entire schedule</strong>. All assigned applicants will be notified and unassigned.
        </div>
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control" rows="2" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
        <button class="btn btn-danger">Yes, Cancel Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('editScheduleModal');
  editModal.addEventListener('show.bs.modal', e => {
    const s = JSON.parse(e.relatedTarget.dataset.sched);
    editModal.querySelector('#edit-id').value          = s.id;
    editModal.querySelector('#edit-date').value        = s.interview_date;
    editModal.querySelector('#edit-start').value       = s.start_time;
    editModal.querySelector('#edit-end').value         = s.end_time;
    editModal.querySelector('#edit-venue').value       = s.venue;
    editModal.querySelector('#edit-interviewer').value = s.interviewer;
    editModal.querySelector('#edit-slots').value       = s.max_slots;
    editModal.querySelector('#edit-status').value      = s.status;
    editModal.querySelector('#edit-remarks').value     = s.remarks || '';
  });
  const cancelModal = document.getElementById('cancelScheduleModal');
  cancelModal.addEventListener('show.bs.modal', e => {
    cancelModal.querySelector('#cancel-id').value = e.relatedTarget.dataset.id;
  });
});
</script>