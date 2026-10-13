<?php
$cancelled = $schedule['status'] === 'Cancelled';
$started = $schedule['interview_date'] <= date('Y-m-d');   // attendance and scores only from the interview date
$future = $schedule['interview_date'] >= date('Y-m-d');    // assigning only for today or later
$anyMarked = (bool)array_filter($assignments, static fn($a) => $a['attendance'] !== 'Scheduled');
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/interviews&program_id=' . (int)$schedule['program_id']) ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> All schedules</a>
    <h4 class="fw-bold mb-0 mt-1"><?= e(Interview::describe($schedule)) ?></h4>
    <p class="text-muted small mb-0"><?= e($schedule['program_name']) ?> · <?= (int)$schedule['assigned_count'] ?> / <?= (int)$schedule['max_slots'] ?> assigned
      <?= $cancelled ? '<span class="badge bg-danger ms-1">Cancelled</span>' : '' ?></p>
  </div>
  <?php if (!$cancelled): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Edit</button>
    <?php if ((int)$schedule['assigned_count'] === 0): ?>
      <form method="POST" action="<?= url('admin/interview-schedule-delete') ?>" data-confirm="Delete this empty schedule?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$schedule['id'] ?>">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
      </form>
    <?php elseif (!$anyMarked): ?>
      <form method="POST" action="<?= url('admin/interview-schedule-cancel') ?>" data-confirm="Cancel this schedule? Everyone assigned goes back to the waiting list and is notified.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$schedule['id'] ?>">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Cancel schedule</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white"><h6 class="mb-0">Assigned applicants</h6></div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light"><tr><th>Applicant</th><th>Attendance</th><th>Score</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$assignments): ?><tr><td colspan="4" class="text-center text-muted py-4">Nobody assigned yet.</td></tr><?php endif; ?>
        <?php foreach ($assignments as $a): ?>
          <tr>
            <td>
              <a href="<?= url('admin/application&id=' . (int)$a['application_id']) ?>" class="fw-semibold"><?= e($a['full_name']) ?></a>
              <?= (int)$a['reschedule_count'] ? '<span class="badge bg-warning ms-1">reschedule — last chance</span>' : '' ?>
              <div class="small text-muted"><?= e($a['contact_number'] ?? '') ?> · <?= e($a['email']) ?></div>
            </td>
            <td><span class="badge bg-<?= e(ATTENDANCE_COLORS[$a['attendance']]) ?>"><?= e($a['attendance']) ?></span></td>
            <td><?= $a['total_score'] !== null ? '<strong>' . e(number_format((float)$a['total_score'], 2)) . '</strong> / 100' : '—' ?></td>
            <td class="text-end text-nowrap">
              <?php if ($a['attendance'] === 'Scheduled' && !$cancelled): ?>
                <?php if ($started): ?>
                  <a class="btn btn-sm btn-success" href="<?= url('admin/interview-score&id=' . (int)$a['id']) ?>"><i class="bi bi-check2"></i> Attended — score</a>
                  <form method="POST" action="<?= url('admin/interview-no-show') ?>" class="d-inline"
                        data-confirm="<?= e((int)$a['reschedule_count'] ? 'This was their rescheduled interview. Marking a no-show REJECTS the application. Continue?' : 'Mark as no-show? They go back to the waiting list for one reschedule.') ?>">
                    <?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">No-show</button>
                  </form>
                <?php else: ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/interview-reschedule&id=' . (int)$a['id']) ?>">Reschedule</a>
                  <form method="POST" action="<?= url('admin/interview-unassign') ?>" class="d-inline" data-confirm="Remove this applicant from the schedule? They are notified.">
                    <?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary">Remove</button>
                  </form>
                <?php endif; ?>
              <?php elseif ($a['attendance'] === 'Attended'): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/interview-score&id=' . (int)$a['id']) ?>">View score</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!$cancelled && $future): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Assign Qualified applicants</h6>
    <span class="small text-muted"><?= $free ?> free place(s)</span>
  </div>
  <?php if (!$waiting): ?>
    <div class="card-body text-muted small">Nobody in this program is waiting for a schedule.</div>
  <?php elseif ($free === 0): ?>
    <div class="card-body text-muted small">This schedule is full. Increase the capacity or create another schedule.</div>
  <?php else: ?>
  <form method="POST" action="<?= url('admin/interview-assign') ?>" id="assignForm">
    <?= csrf_field() ?><input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
    <ul class="list-group list-group-flush">
      <?php foreach ($waiting as $w): ?>
        <li class="list-group-item">
          <label class="d-flex gap-2 align-items-center mb-0">
            <input type="checkbox" class="form-check-input assign-box" name="application_ids[]" value="<?= (int)$w['id'] ?>">
            <?= e($w['full_name']) ?> <code class="small"><?= e($w['application_code']) ?></code>
            <?= (int)$w['missed_before'] ? '<span class="badge bg-warning">missed once — last chance</span>' : '' ?>
          </label>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <span class="small text-muted" id="assignCount">0 selected (max <?= $free ?>)</span>
      <button class="btn btn-primary" id="assignBtn" data-max="<?= (int)$free ?>" disabled><i class="bi bi-person-plus"></i> Assign and notify</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$cancelled): ?>
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/interview-schedule-update') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$schedule['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="interview_date" class="form-control" value="<?= e($schedule['interview_date']) ?>" required></div>
        <div class="row g-2 mb-2">
          <div class="col"><label class="form-label small">Start</label><input type="time" name="start_time" class="form-control" value="<?= e(substr($schedule['start_time'], 0, 5)) ?>" required></div>
          <div class="col"><label class="form-label small">End</label><input type="time" name="end_time" class="form-control" value="<?= e(substr($schedule['end_time'], 0, 5)) ?>" required></div>
        </div>
        <div class="mb-2"><label class="form-label small">Venue</label><input name="venue" class="form-control" value="<?= e($schedule['venue']) ?>" required></div>
        <div class="mb-2"><label class="form-label small">Capacity</label><input type="number" name="max_slots" class="form-control" min="<?= max(1, (int)$schedule['assigned_count']) ?>" value="<?= (int)$schedule['max_slots'] ?>" required></div>
        <div class="form-text">Changing the date, time or venue notifies everyone still waiting for this interview.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
