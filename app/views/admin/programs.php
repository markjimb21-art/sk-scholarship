<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Scholarship Programs</h4>
    <p class="text-muted small mb-0">
      <?php if ($openProgram): ?>
        Applicants can apply to <strong><?= e($openProgram['program_name']) ?></strong> until <?= e(fmt_date($openProgram['application_end'])) ?>.
      <?php else: ?>
        <span class="text-warning">No program is accepting applications right now (a program must be open AND today inside its dates).</span>
      <?php endif; ?>
    </p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#programModal" data-program-modal>
    <i class="bi bi-plus-lg me-1"></i>Add Program
  </button>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Program</th><th>Application dates</th><th>Amount / semester</th><th>Eligibility rules</th>
          <th>Slots used</th><th>Applications</th><th>Status</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$programs): ?><tr><td colspan="8" class="text-center text-muted py-4">No programs yet.</td></tr><?php endif; ?>
        <?php foreach ($programs as $p):
          $acceptingNow = $openProgram && (int)$openProgram['id'] === (int)$p['id']; ?>
          <tr>
            <td><div class="fw-semibold"><?= e($p['program_name']) ?></div><small class="text-muted">A.Y. <?= e($p['academic_year']) ?></small></td>
            <td><small><?= e(fmt_date($p['application_start'])) ?> – <?= e(fmt_date($p['application_end'])) ?></small></td>
            <td><?= e(peso($p['amount_per_semester'])) ?></td>
            <td><small class="text-muted">
              Age ≤ <?= $p['max_age'] ? (int)$p['max_age'] : 'no limit' ?><br>
              Income ≤ <?= (float)$p['max_family_income'] > 0 ? '₱' . e(number_format((float)$p['max_family_income'], 2)) : 'no limit' ?><br>
              GWA <?= (float)$p['min_gpa'] > 0 ? e(number_format((float)$p['min_gpa'], 2)) . ' or better' : 'no limit' ?>
            </small></td>
            <td><?= (int)$p['slots_used'] ?> / <?= (int)$p['slots_available'] ?></td>
            <td><?= (int)$p['application_count'] ?></td>
            <td>
              <?php if ($acceptingNow): ?><span class="badge bg-success">Accepting applications</span>
              <?php elseif ($p['is_active']): ?><span class="badge bg-info">Open (outside its dates)</span>
              <?php else: ?><span class="badge bg-secondary">Closed</span><?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#programModal"
                      data-program-modal="<?= e(js_json($p)) ?>" aria-label="Edit program"><i class="bi bi-pencil" aria-hidden="true"></i></button>
              <form method="POST" action="<?= url('admin/program-toggle') ?>" class="d-inline"
                    data-confirm="<?= e(($p['is_active'] ? 'Close' : 'Open') . ' "' . $p['program_name'] . '"?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>"><?= $p['is_active'] ? 'Close' : 'Open' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="programModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="<?= url('admin/program-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="programId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="programModalTitle">Add Program</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Program name *</label>
            <input name="program_name" id="pName" class="form-control" maxlength="150" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Academic year *</label>
            <input name="academic_year" id="pYear" class="form-control" placeholder="2026-2027" pattern="\d{4}-\d{4}" required>
            <div class="form-text">Semester 1 of approved scholars is the 1st Semester of this year.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Applications open *</label>
            <input type="date" name="application_start" id="pStart" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Applications close *</label>
            <input type="date" name="application_end" id="pEnd" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Slots *</label>
            <input type="number" name="slots_available" id="pSlots" class="form-control" min="1" step="1" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">₱ / semester *</label>
            <input type="number" name="amount_per_semester" id="pAmount" class="form-control" min="1" step="0.01" required>
          </div>

          <div class="col-12"><hr class="my-1"><small class="text-muted">Eligibility rules — leave blank for no limit. They are checked on screening; nothing is rejected automatically.</small></div>
          <div class="col-md-4">
            <label class="form-label">Minimum GWA</label>
            <input type="number" name="min_gpa" id="pMinGpa" class="form-control" min="1" max="5" step="0.01" placeholder="e.g. 2.50">
            <div class="form-text">1.00 is highest; applicants pass at this value or better.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Maximum family income (₱/month)</label>
            <input type="number" name="max_family_income" id="pMaxIncome" class="form-control" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Maximum age</label>
            <input type="number" name="max_age" id="pMaxAge" class="form-control" min="0" step="1">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" name="is_active" value="1" class="form-check-input" id="pActive">
              <label class="form-check-label" for="pActive">Open — applicants can apply while today is inside the dates</label>
            </div>
          </div>
        </div>
        <div id="programDateError" class="alert alert-danger py-2 small d-none mt-3">The closing date can’t be before the opening date.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="programSubmitBtn">Save Program</button>
      </div>
    </form>
  </div>
</div>

