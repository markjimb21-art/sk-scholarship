<?php $full = $slotsUsed >= $slots; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Final Decisions</h4>
    <p class="text-muted small mb-0">Ranked by interview total (highest first); ties go to the lower family income, then the better GWA.</p>
  </div>
  <form method="GET" action="<?= url('admin/decisions') ?>">
    <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select form-select-sm" data-autosubmit>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $program && (int)$program['id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select></div>
  </form>
</div>

<?php if (!$program): ?>
  <div class="alert alert-secondary">Create a scholarship program first.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body d-flex align-items-center gap-4 flex-wrap">
    <div>
      <div class="fs-3 fw-bold <?= $full ? 'text-danger' : 'text-success' ?>">Slots: <?= $slotsUsed ?> approved / <?= $slots ?> total</div>
      <div class="small text-muted">Approved = scholars from this program, not counting scholars ended before a second semester opened.</div>
    </div>
    <?php if ($full): ?><span class="badge bg-danger">Full — the server blocks further approvals</span><?php endif; ?>
  </div>
</div>

<?php if (!$full && $nextWaitlisted): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><strong>A slot is free.</strong> Promote the next waitlisted applicant, <strong><?= e($nextWaitlisted['full_name']) ?></strong>
    (rank <?= (int)$nextWaitlisted['rank'] ?>, score <?= e(number_format((float)$nextWaitlisted['total_score'], 2)) ?>)?</div>
  <form method="POST" action="<?= url('admin/decisions/decide') ?>" data-confirm="Approve <?= e($nextWaitlisted['full_name']) ?>? This creates the scholar and opens Semester 1.">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$nextWaitlisted['id'] ?>"><input type="hidden" name="decision" value="approve">
    <button class="btn btn-success btn-sm">Promote and approve</button>
  </form>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Applicant</th><th>Interview total</th><th>Family income</th><th>GWA</th><th>Eligibility</th><th>Status</th><th class="text-end">Decision</th></tr>
      </thead>
      <tbody>
        <?php if (!$ranking): ?><tr><td colspan="8" class="text-center text-muted py-4">No interviewed applicants waiting for a decision.</td></tr><?php endif; ?>
        <?php foreach ($ranking as $r): ?>
          <tr>
            <td><?= (int)$r['rank'] ?></td>
            <td><a href="<?= url('admin/application&id=' . (int)$r['id']) ?>" class="fw-semibold"><?= e($r['full_name']) ?></a><br><code class="small"><?= e($r['application_code']) ?></code></td>
            <td><strong><?= $r['total_score'] !== null ? e(number_format((float)$r['total_score'], 2)) : '—' ?></strong> / 100</td>
            <td><?= $r['total_family_income'] !== null ? '₱' . e(number_format((float)$r['total_family_income'], 2)) : '—' ?></td>
            <td><?= $r['general_average'] !== null ? e(number_format((float)$r['general_average'], 2)) : '—' ?></td>
            <td><span class="badge bg-<?= $r['eligibility']['failed'] ? 'warning' : 'success' ?>" title="<?= e(implode(' · ', array_map(static fn($x) => $x['label'] . ': ' . $x['result'], $r['eligibility']['rows']))) ?>"><?= e($r['eligibility']['summary']) ?></span></td>
            <td><span class="badge bg-<?= e(APP_STATUS_COLORS[$r['status']]) ?>"><?= e($r['status']) ?></span></td>
            <td class="text-end text-nowrap">
              <?php if (Application::can('approve', $r['status'])): ?>
                <form method="POST" action="<?= url('admin/decisions/decide') ?>" class="d-inline" data-confirm="Approve this applicant? This creates the scholar and opens Semester 1.">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approve">
                  <button class="btn btn-sm btn-success" <?= $full ? 'disabled title="All slots are filled."' : '' ?>>Approve</button>
                </form>
              <?php endif; ?>
              <?php if (Application::can('waitlist', $r['status'])): ?>
                <form method="POST" action="<?= url('admin/decisions/decide') ?>" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="waitlist">
                  <button class="btn btn-sm btn-outline-warning">Waitlist</button>
                </form>
              <?php endif; ?>
              <?php if (Application::can('reject', $r['status'])): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"
                        data-reject-id="<?= (int)$r['id'] ?>" data-reject-name="<?= e($r['full_name']) ?>">Reject</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/decisions/decide') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" id="rejectId"><input type="hidden" name="decision" value="reject">
      <div class="modal-header"><h5 class="modal-title text-danger">Reject <span id="rejectName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label">Reason <span class="text-danger">*</span></label>
        <textarea name="reason" class="form-control" rows="3" required></textarea>
        <div class="form-text">The applicant sees this reason. This is final.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
