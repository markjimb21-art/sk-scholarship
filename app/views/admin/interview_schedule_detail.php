<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/interview-scheduling&program_id=' . (int)$schedule['program_id']) ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to Scheduling
    </a>
    <h4 class="fw-bold mb-1 mt-1">
      <?= e(date('F d, Y (l)', strtotime($schedule['interview_date']))) ?>
      <span class="badge bg-<?= $schedule['status']==='Cancelled'?'secondary':($schedule['status']==='Full'?'warning':'primary') ?> ms-2">
        <?= e($schedule['status']) ?>
      </span>
    </h4>
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
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Eligible Applicants (<?= count($eligible) ?>)</h6>
        <?php if ((int)$schedule['assigned_count'] >= (int)$schedule['max_slots']): ?>
          <span class="badge bg-danger">Schedule full</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (!$eligible): ?>
          <p class="text-muted text-center py-3 small mb-0">No eligible applicants available.</p>
        <?php endif; ?>
        <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
          <?php foreach ($eligible as $e):
            $full = (int)$schedule['assigned_count'] >= (int)$schedule['max_slots'];
          ?>
            <div class="list-group-item">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold small"><?= e($e['full_name']) ?></div>
                  <div class="small text-muted">
                    <code><?= e($e['application_code']) ?></code> ·
                    <?= e($e['course_name'] ?? '—') ?>
                  </div>
                </div>
                <form method="POST" action="<?= url('admin/interview-assign') ?>" class="ms-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                  <input type="hidden" name="application_id" value="<?= (int)$e['id'] ?>">
                  <button class="btn btn-sm btn-primary" <?= $full || $schedule['status']==='Cancelled' ? 'disabled' : '' ?>>
                    <i class="bi bi-plus"></i>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- INLINE EDIT MODAL (reuse) -->
<div class="modal fade" id="editScheduleModal2" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-update') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$schedule['id'] ?>">
      <input type="hidden" name="program_id" value="<?= (int)$schedule['program_id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
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