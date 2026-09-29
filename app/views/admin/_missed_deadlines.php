<?php
// Scholars who hadn't submitted when a window's deadline passed. Expects $missed (TermSchedule::missedDeadline) and $purpose.
// Nothing happens automatically: extend the deadline (edit the schedule), follow up, or end the scholarship.
$scheduleRoute = $purpose === 'release' ? 'admin/release-schedule' : 'admin/renewal-schedule';
?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Missed deadline <span class="badge bg-<?= $missed ? 'danger' : 'secondary' ?> ms-1"><?= count($missed) ?></span></h6>
    <a class="small" href="<?= url($scheduleRoute) ?>"><?= $purpose === 'release' ? 'Claim' : 'Renewal' ?> schedule</a>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light"><tr><th>Scholar</th><th>Program</th><th><?= $purpose === 'release' ? 'Semester' : 'Renewal for' ?></th><th>Deadline</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$missed): ?><tr><td colspan="6" class="text-center text-muted py-3">Nobody missed a deadline.</td></tr><?php endif; ?>
        <?php foreach ($missed as $m): $s = $m['schedule']; ?>
          <tr>
            <td><div class="fw-semibold"><?= e($m['full_name']) ?></div><code><?= e($m['scholar_code']) ?></code></td>
            <td><?= e($s['program_name']) ?></td>
            <td><?= e($s['academic_year'] . ' ' . $s['semester']) ?></td>
            <td class="text-danger"><?= e(date('M d, Y g:i A', strtotime($s['submission_deadline']))) ?></td>
            <td><?= e($m['progress'] ?? 'Not submitted') ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/scholar&id=' . (int)$m['scholar_id']) ?>">Scholar</a>
              <a class="btn btn-sm btn-outline-primary" href="<?= url($scheduleRoute . '&edit=' . (int)$s['id']) ?>">Extend</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">Nothing happens automatically. Extend the deadline, follow up with the scholar, or end the scholarship from the scholar page.</div>
</div>
