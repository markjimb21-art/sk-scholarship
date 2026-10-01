<?php
// Scheduling page for ONE application: applicant's slot request, ranked suggestions, and a free date/time picker.
// Expects $app (withDetails), $currentAssignment, $missed, $suggestions, $why, $pending, $defaultVenue, $schedulable.
$name = $app['applicant']['full_name'] ?? '';
$moving = $app['status'] === 'Interview Scheduled';
?>
<div class="mb-3">
  <a href="<?= url('admin/interviews&program_id=' . (int)$app['program_id']) ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Interviews</a>
  <h4 class="fw-bold mb-0 mt-1">Schedule an interview — <?= e($name) ?></h4>
  <p class="text-muted small mb-0">
    <a href="<?= url('admin/application&id=' . (int)$app['id']) ?>"><code><?= e($app['application_code']) ?></code></a> · <?= e($app['program_name']) ?>
    · <span class="badge bg-<?= e(APP_STATUS_COLORS[$app['status']]) ?>"><?= e(Application::label($app['status'])) ?></span>
    <?php if ($missed): ?> · <span class="badge bg-warning text-dark">missed once — this is the one reschedule</span><?php endif; ?>
  </p>
</div>

<?php if ($currentAssignment && $currentAssignment['attendance'] === 'Scheduled' && $moving): ?>
  <div class="alert alert-info">Currently scheduled: <strong><?= e(Interview::describe($currentAssignment)) ?></strong>.
    Choosing another slot moves the applicant and sends them one "Interview changed" notification.</div>
<?php endif; ?>
<?php if (!$schedulable): ?>
  <div class="alert alert-secondary">This application can’t be scheduled now (it is "<?= e(Application::label($app['status'])) ?>"). Only Qualified applications —
    including after a first no-show — and upcoming interviews can be scheduled.</div>
<?php endif; ?>

<?php if ($pending): ?>
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
  <div class="card-body">
    <h6 class="fw-semibold">The applicant asked for a slot</h6>
    <p class="mb-2"><strong><?= e(SlotRequest::describe($pending)) ?></strong>
      <span class="text-muted small">· requested <?= e(date('M d, Y g:i A', strtotime($pending['created_at']))) ?></span></p>
    <div class="d-flex flex-wrap gap-2 align-items-start">
      <form method="POST" action="<?= url('admin/slot-request-decide') ?>" class="d-flex gap-2 flex-wrap align-items-end">
        <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$pending['id'] ?>"><input type="hidden" name="decision" value="confirm">
        <?php if (!$pending['schedule_id']): ?>
          <div><label class="form-label small mb-0">Venue (new schedule)</label>
            <input name="venue" class="form-control form-control-sm" value="<?= e($defaultVenue) ?>" maxlength="200" required></div>
          <div><label class="form-label small mb-0">Capacity</label>
            <input type="number" name="max_slots" class="form-control form-control-sm" value="10" min="1" style="width:6rem" required></div>
        <?php endif; ?>
        <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Confirm</button>
      </form>
      <form method="POST" action="<?= url('admin/slot-request-decide') ?>" class="d-flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$pending['id'] ?>"><input type="hidden" name="decision" value="decline">
        <input name="note" class="form-control form-control-sm" placeholder="Reason (shown to the applicant)" maxlength="255">
        <button class="btn btn-outline-danger btn-sm">Decline</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($schedulable): ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><h6 class="mb-0">Suggested slots <span class="text-muted small fw-normal">— best first</span></h6></div>
      <ul class="list-group list-group-flush">
        <?php if (!$suggestions): ?><li class="list-group-item text-muted small"><?= e($why ?? 'No suggestions.') ?></li><?php endif; ?>
        <?php foreach ($suggestions as $i => $s): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <div>
              <span class="badge bg-light text-dark border me-1"><?= $i + 1 ?></span>
              <strong><?= e(date('D, M j, Y', strtotime($s['date']))) ?></strong> <?= e(date('g:i', strtotime($s['start'])) . '–' . date('g:i A', strtotime($s['end']))) ?>
              <div class="small text-muted ms-4"><?= $s['kind'] === 'schedule'
                  ? 'Existing schedule at ' . e($s['venue']) . ' · ' . (int)$s['free'] . ' place(s) left'
                  : 'New 2-hour block — no other interview at this time' ?></div>
            </div>
            <?php if ($s['kind'] === 'schedule'): ?>
              <form method="POST" action="<?= url('admin/interview-reschedule-save') ?>">
                <?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                <input type="hidden" name="mode" value="schedule"><input type="hidden" name="schedule_id" value="<?= (int)$s['schedule_id'] ?>">
                <button class="btn btn-sm btn-primary text-nowrap">Assign here</button>
              </form>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-slot-date="<?= e($s['date']) ?>" data-slot-start="<?= e($s['start']) ?>" data-slot-end="<?= e($s['end']) ?>">Use this time</button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="card-footer bg-white small text-muted">Open schedules of this program with room come first, then free blocks (Mon–Sat, no holidays, no other program’s interview day).</div>
    </div>
  </div>
  <div class="col-lg-5">
    <form method="POST" action="<?= url('admin/interview-reschedule-save') ?>" class="card border-0 shadow-sm h-100">
      <?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>"><input type="hidden" name="mode" value="new">
      <div class="card-header bg-white"><h6 class="mb-0">Pick a date and time</h6></div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="interview_date" id="rsDate" class="form-control" min="<?= date('Y-m-d') ?>" required></div>
        <div class="row g-2 mb-2">
          <div class="col"><label class="form-label small">Start</label><input type="time" name="start_time" id="rsStart" class="form-control" required></div>
          <div class="col"><label class="form-label small">End</label><input type="time" name="end_time" id="rsEnd" class="form-control" required></div>
        </div>
        <div class="mb-2"><label class="form-label small">Venue</label><input name="venue" class="form-control" maxlength="200" value="<?= e($defaultVenue) ?>" required></div>
        <div class="mb-1"><label class="form-label small">Capacity</label><input type="number" name="max_slots" class="form-control" min="1" value="10" required></div>
        <div class="form-text">Uses this program’s open schedule at that exact date and time if there is one; otherwise creates it.</div>
      </div>
      <div class="card-footer bg-white"><button class="btn btn-primary w-100"><i class="bi bi-calendar-check"></i> <?= $moving ? 'Move the interview' : 'Schedule the interview' ?></button></div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('[data-slot-date]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('rsDate').value = b.dataset.slotDate;
    document.getElementById('rsStart').value = b.dataset.slotStart;
    document.getElementById('rsEnd').value = b.dataset.slotEnd;
    document.getElementById('rsDate').focus();
  });
});
</script>
<?php endif; ?>
