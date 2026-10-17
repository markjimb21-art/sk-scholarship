<?php
// Claim (release) or renewal submission windows. Expects $purpose, $schedules, $needed, $missed, $programs, $years.
$isRelease = $purpose === 'release';
$stateColor = ['open' => 'success', 'not_open_yet' => 'info', 'deadline_passed' => 'warning', 'closed' => 'secondary', 'cancelled' => 'danger', 'not_created' => 'secondary'];
$dt = static fn(?string $v): string => $v ? fmt_datetime($v) : '—';
$local = static fn(?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$editId = get_int('edit');   // opened straight away by pages/term-schedules.js
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><?= $isRelease ? 'Claim schedule' : 'Renewal schedule' ?></h4>
    <p class="text-muted small mb-0">
      <?= $isRelease
        ? 'Scholars can upload their Grades and School ID for a semester only while its claim window is open.'
        : 'Scholars can upload their Enrollment Form and School ID for the next semester only while its renewal window is open.' ?>
      Creating a window e-mails every scholar it applies to; a reminder goes out <?= TERM_SCHEDULE_REMINDER_HOURS ?> hours before the deadline.
    </p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal" data-schedule-modal><i class="bi bi-plus-lg"></i> New window</button>
</div>

<?php if ($needed): ?>
  <div class="alert alert-warning">
    <strong>These semesters have scholars waiting but no <?= $isRelease ? 'claim' : 'renewal' ?> window yet:</strong>
    <ul class="mb-0 mt-1">
      <?php foreach ($needed as $n): ?>
        <li><?= e($n['program_name'] . ' — ' . $n['academic_year'] . ' ' . $n['semester']) ?> (<?= (int)$n['n'] ?> scholar(s))
          <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#scheduleModal"
                  data-schedule-prefill="<?= e(js_json(['program_id' => (int)$n['program_id'], 'academic_year' => $n['academic_year'], 'semester' => $n['semester']])) ?>">Create window</button></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light"><tr>
        <th>Program</th><th><?= $isRelease ? 'Semester' : 'Renewal for' ?></th><th>Opens</th><th>Deadline</th>
        <th><?= $isRelease ? 'Planned release' : 'Semester starts' ?></th><th>Scholars</th><th>State</th><th></th>
      </tr></thead>
      <tbody>
        <?php if (!$schedules): ?><tr><td colspan="8" class="text-center text-muted py-4">No windows yet.</td></tr><?php endif; ?>
        <?php foreach ($schedules as $s): ?>
          <tr>
            <td class="fw-semibold"><?= e($s['program_name']) ?></td>
            <td><?= e($s['academic_year'] . ' ' . $s['semester']) ?></td>
            <td><small><?= e($dt($s['opens_at'])) ?></small></td>
            <td><small><?= e($dt($s['submission_deadline'])) ?></small></td>
            <td><small><?= $s['planned_date'] ? e(fmt_date($s['planned_date'])) : '—' ?></small></td>
            <td><small><?= (int)$s['affected_count'] - (int)$s['pending_count'] ?> submitted · <?= (int)$s['pending_count'] ?> not yet</small></td>
            <td><span class="badge bg-<?= e($stateColor[$s['state']]) ?>"><?= e(TermSchedule::STATE_LABELS[$s['state']]) ?></span></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal" data-edit-id="<?= (int)$s['id'] ?>"
                      data-schedule-modal="<?= e(js_json(array_intersect_key($s, array_flip(['id', 'program_id', 'program_name', 'academic_year', 'semester', 'status', 'notes', 'planned_date', 'submitted_count'])) + ['opens_at' => $local($s['opens_at']), 'submission_deadline' => $local($s['submission_deadline'])])) ?>"
                      aria-label="Edit window"><i class="bi bi-pencil" aria-hidden="true"></i></button>
              <?php if ((int)$s['submitted_count'] === 0): // nobody submitted under it yet (TermSchedule::delete) ?>
                <form method="POST" action="<?= url('admin/term-schedule-delete') ?>" class="d-inline"
                      data-confirm="<?= e('Delete the ' . strtolower(TermSchedule::TITLES[$purpose]) . ' window ' . $s['academic_year'] . ' ' . $s['semester'] . ' (' . $s['program_name'] . ')?'
                          . ($s['status'] !== 'Cancelled' && (int)$s['pending_count'] > 0 ? ' The ' . (int)$s['pending_count'] . ' scholar(s) it applies to will be told it was cancelled.' : '')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="purpose" value="<?= e($purpose) ?>">
                  <button class="btn btn-sm btn-outline-danger" aria-label="Delete window"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($s['notes']): ?><tr><td></td><td colspan="7" class="small text-muted border-top-0 pt-0"><?= e($s['notes']) ?></td></tr><?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require VIEW_PATH . '/admin/_missed_deadlines.php'; ?>

<div class="modal fade" id="scheduleModal" data-open-edit="<?= $editId ?>" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/term-schedule-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="purpose" value="<?= e($purpose) ?>">
      <input type="hidden" name="id" id="schId">
      <div class="modal-header"><h5 class="modal-title" id="schTitle">New window</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div id="schFixed">
          <div class="mb-2">
            <label class="form-label">Program</label>
            <select name="program_id" id="schProgram" class="form-select" required>
              <option value="">Choose…</option>
              <?php foreach ($programs as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label"><?= $isRelease ? 'Academic year' : 'Renewal into academic year' ?></label>
              <select name="academic_year" id="schYear" class="form-select" required>
                <?php foreach ($years as $y): ?><option value="<?= e($y) ?>"><?= e($y) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label">Semester</label>
              <select name="semester" id="schSemester" class="form-select" required>
                <?php foreach (SEMESTERS as $sem): ?><option value="<?= e($sem) ?>"><?= e($sem) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if (!$isRelease): ?><div class="form-text mb-2">The semester scholars are renewing <em>into</em> (the one after their current semester).</div><?php endif; ?>
        </div>
        <div id="schFixedText" class="mb-2 d-none"><div class="fw-semibold" id="schFixedName"></div>
          <div class="form-text">Program and semester can't change: scholars already submitted under this window. Close or cancel it instead.</div></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label">Opens</label><input type="datetime-local" name="opens_at" id="schOpens" class="form-control" required></div>
          <div class="col-6"><label class="form-label">Submission deadline</label><input type="datetime-local" name="submission_deadline" id="schDeadline" class="form-control" required></div>
        </div>
        <div class="mb-2">
          <label class="form-label"><?= $isRelease ? 'Planned release date' : 'Semester start date' ?> <span class="text-muted small">(optional)</span></label>
          <input type="date" name="planned_date" id="schPlanned" class="form-control">
        </div>
        <div class="mb-2" id="schStatusWrap">
          <label class="form-label">Status</label>
          <select name="status" id="schStatus" class="form-select">
            <?php foreach (TermSchedule::STATUSES as $st): ?><option><?= e($st) ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">Closed stops new submissions; Cancelled also stops re-uploads and notifies the scholars.</div>
        </div>
        <div class="mb-1"><label class="form-label">Notes <span class="text-muted small">(admin only)</span></label><textarea name="notes" id="schNotes" class="form-control" rows="2"></textarea></div>
        <div class="form-text">Changing the deadline notifies the scholars who haven't submitted and resets the <?= TERM_SCHEDULE_REMINDER_HOURS ?>-hour reminder.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
