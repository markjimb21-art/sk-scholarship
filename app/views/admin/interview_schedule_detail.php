<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/interview-scheduling&program_id=' . (int)$schedule['program_id']) ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to Scheduling
    </a>
    <h4 class="fw-bold mb-1 mt-1">
      <?= !empty($schedule['title']) ? e($schedule['title']) : e(date('F d, Y (l)', strtotime($schedule['interview_date']))) ?>
      <span class="badge bg-<?= $schedule['status']==='Cancelled'?'secondary':($schedule['status']==='Full'?'warning':'primary') ?> ms-2">
        <?= e($schedule['status']) ?>
      </span>
    </h4>
    <?php if (!empty($schedule['title'])): ?>
      <div class="text-muted small"><?= e(date('F d, Y (l)', strtotime($schedule['interview_date']))) ?></div>
    <?php endif; ?>
    <div class="text-muted small">
      <?= e(date('g:i A', strtotime($schedule['start_time']))) ?> – <?= e(date('g:i A', strtotime($schedule['end_time']))) ?>
      · <?= e($schedule['venue']) ?> · Interviewer: <?= e($schedule['interviewer']) ?>
      · Slots: <strong><?= (int)$schedule['assigned_count'] ?>/<?= (int)$schedule['max_slots'] ?></strong>
    </div>
  </div>
  <div>
    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editScheduleModal2">
      <i class="bi bi-pencil"></i> Edit Schedule
    </button>
  </div>
</div>

<div class="row g-3">
  <!-- ASSIGNED -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><h6 class="mb-0">Assigned Applicants (<?= count($assignments) ?>)</h6></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
          <thead class="table-light"><tr><th>Applicant</th><th>Status</th><th>Result</th><th></th></tr></thead>
          <tbody>
            <?php if (!$assignments): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No applicants assigned yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($assignments as $a): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($a['full_name']) ?></div>
                  <small class="text-muted"><code><?= e($a['application_code']) ?></code></small>
                  <?php if ($a['school_name']): ?>
                    <br><small class="text-muted"><?= e($a['course_name']) ?> · <?= e($a['year_level']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-<?= ['Completed'=>'success','No Show'=>'danger','Cancelled'=>'secondary','Confirmed'=>'info'][$a['assignment_status']] ?? 'primary' ?>">
                    <?= e($a['assignment_status']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($a['eval_result']): ?>
                    <span class="badge bg-secondary"><?= e($a['eval_result']) ?></span>
                    <div class="small text-muted">Score: <?= e((string)$a['eval_score']) ?></div>
                  <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if (in_array($a['assignment_status'], ['Scheduled','Confirmed'])): ?>
                    <a href="<?= url('admin/interview-evaluate&id=' . (int)$a['id']) ?>" class="btn btn-sm btn-success">
                      <i class="bi bi-clipboard-check"></i> Evaluate
                    </a>
                  <?php endif; ?>
                  <div class="btn-group btn-group-sm mt-1">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                      <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><h6 class="dropdown-header">Mark as</h6></li>
                      <?php foreach (['Confirmed','Completed','No Show','Rescheduled','Cancelled'] as $st): ?>
                        <li>
                          <form method="POST" action="<?= url('admin/interview-assignment-status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <input type="hidden" name="status" value="<?= e($st) ?>">
                            <button class="dropdown-item small"><?= e($st) ?></button>
                          </form>
                        </li>
                      <?php endforeach; ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form method="POST" action="<?= url('admin/interview-unassign') ?>" onsubmit="return confirm('Remove this assignment?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                          <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                          <button class="dropdown-item small text-danger">Remove assignment</button>
                        </form>
                      </li>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- AVAILABLE TO ASSIGN -->
  <div class="col-lg-5">
    <?php
      $remainingSlots = max(0, (int)$schedule['max_slots'] - (int)$schedule['assigned_count']);
      $scheduleLocked = $schedule['status'] === 'Cancelled' || $remainingSlots === 0;
    ?>
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Eligible Applicants (<?= count($eligible) ?>)</h6>
        <?php if ($remainingSlots === 0): ?>
          <span class="badge bg-danger">Schedule full</span>
        <?php else: ?>
          <span class="badge bg-secondary"><?= $remainingSlots ?> slot(s) left</span>
        <?php endif; ?>
      </div>
      <?php if (!$eligible): ?>
        <p class="text-muted text-center py-3 small mb-0">No eligible applicants available.</p>
      <?php else: ?>
        <form method="POST" action="<?= url('admin/interview-assign-batch') ?>" id="batchAssignForm">
          <?= csrf_field() ?>
          <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="selectAllEligible" <?= $scheduleLocked ? 'disabled' : '' ?>>
              <label class="form-check-label small" for="selectAllEligible">Select All (up to <?= $remainingSlots ?> slot<?= $remainingSlots === 1 ? '' : 's' ?>)</label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary" id="assignSelectedBtn" disabled>
              <i class="bi bi-plus-lg"></i> Assign Selected (<span id="selectedCount">0</span>)
            </button>
          </div>
          <div class="list-group list-group-flush" style="max-height: 460px; overflow-y: auto;">
            <?php foreach ($eligible as $e): ?>
              <label class="list-group-item d-flex justify-content-between align-items-start <?= $scheduleLocked ? 'text-muted' : '' ?>">
                <div class="form-check">
                  <input class="form-check-input eligible-checkbox" type="checkbox" name="application_ids[]"
                         value="<?= (int)$e['id'] ?>" <?= $scheduleLocked ? 'disabled' : '' ?>>
                  <div class="ms-1">
                    <div class="fw-semibold small"><?= e($e['full_name']) ?></div>
                    <div class="small text-muted">
                      <code><?= e($e['application_code']) ?></code> ·
                      <?= e($e['course_name'] ?? '—') ?>
                    </div>
                  </div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('batchAssignForm');
  if (!form) return;
  const maxSelectable = <?= (int)$remainingSlots ?>;
  const checkboxes = Array.from(form.querySelectorAll('.eligible-checkbox'));
  const selectAll = document.getElementById('selectAllEligible');
  const assignBtn = document.getElementById('assignSelectedBtn');
  const countEl = document.getElementById('selectedCount');

  function refresh() {
    const checked = checkboxes.filter((c) => c.checked);
    countEl.textContent = checked.length;
    assignBtn.disabled = checked.length === 0;
    // Once the slot cap is reached, disable the remaining unchecked boxes.
    const atCap = checked.length >= maxSelectable;
    checkboxes.forEach((c) => { if (!c.checked) c.disabled = atCap; });
  }

  checkboxes.forEach((c) => c.addEventListener('change', refresh));

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      checkboxes.forEach((c) => { c.disabled = false; c.checked = false; });
      if (selectAll.checked) {
        checkboxes.slice(0, maxSelectable).forEach((c) => { c.checked = true; });
      }
      refresh();
    });
  }

  form.addEventListener('submit', (e) => {
    if (!checkboxes.some((c) => c.checked)) e.preventDefault();
  });
});
</script>

<!-- INLINE EDIT MODAL (reuse) -->
<div class="modal fade" id="editScheduleModal2" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$schedule['id'] ?>">
      <input type="hidden" name="program_id" value="<?= (int)$schedule['program_id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title</label>
          <input name="title" class="form-control" placeholder="e.g., Batch 1 Screening" value="<?= e($schedule['title'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Date *</label>
          <input type="date" name="interview_date" class="form-control" value="<?= e($schedule['interview_date']) ?>" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Start *</label>
            <input type="time" name="start_time" class="form-control" value="<?= e($schedule['start_time']) ?>" required></div>
          <div class="col-6"><label class="form-label">End *</label>
            <input type="time" name="end_time" class="form-control" value="<?= e($schedule['end_time']) ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Venue *</label>
          <input name="venue" class="form-control" value="<?= e($schedule['venue']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Interviewer *</label>
          <input name="interviewer" class="form-control" value="<?= e($schedule['interviewer']) ?>" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Max Slots *</label>
            <input type="number" min="1" name="max_slots" class="form-control" value="<?= (int)$schedule['max_slots'] ?>" required></div>
          <div class="col-6"><label class="form-label">Status *</label>
            <select name="status" class="form-select">
              <?php foreach (['Open','Full','Cancelled','Completed'] as $st): ?>
                <option <?= $schedule['status']===$st?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3"><label class="form-label">Remarks</label>
          <textarea name="remarks" class="form-control" rows="2"><?= e($schedule['remarks'] ?? '') ?></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>