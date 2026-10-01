<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Interviews</h4>
    <p class="text-muted small mb-0">Create a schedule, then open it to assign Qualified applicants, record attendance and scores.</p>
  </div>
  <form method="GET" action="<?= url('admin/interviews') ?>">
    <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$program): ?>
  <div class="alert alert-secondary">Create a scholarship program first.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><h6 class="mb-0">Schedules — <?= e($program['program_name']) ?></h6></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th>Date & time</th><th>Venue</th><th>Assigned / capacity</th><th>Attendance</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if (!$schedules): ?><tr><td colspan="6" class="text-center text-muted py-4">No schedules yet.</td></tr><?php endif; ?>
            <?php foreach ($schedules as $s):
              // Same rule and colours as the calendar (Interview::displayStatus).
              $status = Interview::displayStatus($s);
              $color = Interview::STATUS_COLORS[$status]; ?>
              <tr>
                <td><div class="fw-semibold"><?= e(date('D, M d, Y', strtotime($s['interview_date']))) ?></div>
                    <small class="text-muted"><?= e(date('g:i A', strtotime($s['start_time']))) ?> – <?= e(date('g:i A', strtotime($s['end_time']))) ?></small></td>
                <td><?= e($s['venue']) ?></td>
                <td><?= (int)$s['assigned_count'] ?> / <?= (int)$s['max_slots'] ?></td>
                <td><small><?= (int)$s['attended_count'] ?> attended · <?= (int)$s['noshow_count'] ?> no-show · <?= (int)$s['waiting_count'] ?> not yet marked</small></td>
                <td><span class="badge bg-<?= $color ?>"><?= $status ?></span></td>
                <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/interview-schedule&id=' . (int)$s['id']) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><h6 class="mb-0">New schedule</h6></div>
      <form method="POST" action="<?= url('admin/interview-schedule-save') ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="program_id" value="<?= (int)$program['id'] ?>">
        <div class="mb-3">
          <div class="form-label small mb-1">Suggested slots <span class="text-muted">(optional)</span></div>
          <?php if ($suggestions): ?>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($suggestions as $s): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-slot-date="<?= e($s['date']) ?>"
                        data-slot-start="<?= e($s['start']) ?>" data-slot-end="<?= e($s['end']) ?>"><?= e($s['label']) ?></button>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Free 2-hour blocks after the application period: Monday–Saturday, no holidays (Settings), no day another program already
              interviews on, not overlapping any open schedule. A suggestion only — you can pick any date and time.</div>
          <?php else: ?>
            <div class="form-text"><?= e($noSuggestionReason ?? 'No suggestions.') ?> Pick a date and time below.</div>
          <?php endif; ?>
        </div>
        <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="interview_date" id="schedDate" class="form-control" min="<?= date('Y-m-d') ?>" required></div>
        <div class="row g-2 mb-2">
          <div class="col"><label class="form-label small">Start</label><input type="time" name="start_time" id="schedStart" class="form-control" required></div>
          <div class="col"><label class="form-label small">End</label><input type="time" name="end_time" id="schedEnd" class="form-control" required></div>
        </div>
        <div class="mb-2"><label class="form-label small">Venue</label><input name="venue" class="form-control" maxlength="200" required></div>
        <div class="mb-3"><label class="form-label small">Capacity</label><input type="number" name="max_slots" class="form-control" min="1" value="20" required></div>
        <button class="btn btn-primary w-100"><i class="bi bi-calendar-plus"></i> Create schedule</button>
      </form>
    </div>

    <?php if ($slotRequests): ?>
    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
      <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0">Slot requests to confirm</h6><span class="badge bg-primary"><?= count($slotRequests) ?></span>
      </div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($slotRequests as $sr): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <span><strong><?= e($sr['full_name']) ?></strong><br><span class="text-muted"><?= e(SlotRequest::describe($sr)) ?></span></span>
            <a class="btn btn-sm btn-primary" href="<?= url('admin/interview-reschedule&application_id=' . (int)$sr['application_id']) ?>">Review</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between">
        <h6 class="mb-0">Qualified, waiting for a schedule</h6><span class="badge bg-primary"><?= count($waiting) ?></span>
      </div>
      <ul class="list-group list-group-flush small">
        <?php if (!$waiting): ?><li class="list-group-item text-muted">Nobody is waiting.</li><?php endif; ?>
        <?php foreach ($waiting as $w): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <span><a href="<?= url('admin/application&id=' . (int)$w['id']) ?>"><?= e($w['full_name']) ?></a>
              <?= (int)$w['missed_before'] ? '<span class="badge bg-warning ms-1">missed once — last chance</span>' : '' ?></span>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/interview-reschedule&application_id=' . (int)$w['id']) ?>">Schedule</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($waiting): ?><div class="card-footer bg-white small text-muted">Open a schedule to assign them.</div><?php endif; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-slot-date]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('schedDate').value = b.dataset.slotDate;
    document.getElementById('schedStart').value = b.dataset.slotStart;
    document.getElementById('schedEnd').value = b.dataset.slotEnd;
    document.querySelectorAll('[data-slot-date]').forEach(function (o) { o.classList.toggle('active', o === b); });
  });
});
</script>
<?php endif; ?>
