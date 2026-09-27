<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Scholarship Programs</h4>
    <p class="text-muted small mb-0">
      <?= number_format(count($programs)) ?> program(s) ·
      <?php if ($openProgram): ?>
        Accepting applications for <strong><?= e($openProgram['program_name']) ?></strong> until <?= e(date('M d, Y', strtotime($openProgram['application_end']))) ?>
      <?php else: ?>
        <span class="text-warning">No program is currently accepting applications</span>
      <?php endif; ?>
    </p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#programModal" onclick="openProgramModal()">
    <i class="bi bi-plus-lg me-1"></i>Add Program
  </button>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Program</th><th>A.Y. / Semester</th><th>Application Window</th><th>Eligibility Rules</th>
          <th>Slots</th><th>Applications</th><th>Status</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$programs): ?><tr><td colspan="8" class="text-center text-muted py-4">No programs yet.</td></tr><?php endif; ?>
        <?php foreach ($programs as $p):
          $isOpen = $openProgram && (int)$openProgram['id'] === (int)$p['id']; ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($p['program_name']) ?></div>
              <?php if (!empty($p['description'])): ?><small class="text-muted"><?= e(mb_strimwidth($p['description'], 0, 80, '…')) ?></small><?php endif; ?>
            </td>
            <td><?= e($p['academic_year']) ?><br><small class="text-muted"><?= e($p['semester'] ?? '—') ?></small></td>
            <td>
              <small><?= e(date('M d, Y', strtotime($p['application_start']))) ?> – <?= e(date('M d, Y', strtotime($p['application_end']))) ?></small>
              <?php if ($isOpen): ?><br><span class="badge bg-success">Open now</span><?php endif; ?>
            </td>
            <td><small class="text-muted">
              Age ≤ <?= $p['max_age'] ? (int)$p['max_age'] : '—' ?><br>
              Income ≤ <?= (float)$p['max_family_income'] > 0 ? '₱' . number_format((float)$p['max_family_income'], 2) : '—' ?><br>
              GPA ≤ <?= (float)$p['min_gpa'] > 0 ? e(number_format((float)$p['min_gpa'], 2)) : '—' ?> · <?= e($p['citizenship_required'] ?: 'Any citizenship') ?>
            </small></td>
            <td><?= (int)$p['scholar_count'] ?> / <?= (int)$p['slots_available'] > 0 ? (int)$p['slots_available'] : '∞' ?></td>
            <td><?= (int)$p['application_count'] ?></td>
            <td>
              <span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span>
            </td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" title="Edit"
                      data-bs-toggle="modal" data-bs-target="#programModal"
                      onclick='openProgramModal(<?= js_json($p) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" action="<?= url('admin/program-toggle') ?>" class="d-inline"
                    onsubmit="return confirm(<?= e(js_json(($p['is_active'] ? 'Deactivate' : 'Activate') . ' "' . $p['program_name'] . '"?')) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>" title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="bi bi-<?= $p['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
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
    <form method="POST" action="<?= url('admin/program-save') ?>" class="modal-content" id="programForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="programId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="programModalTitle">Add Program</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Program Name *</label>
            <input name="program_name" id="pName" class="form-control" maxlength="150" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Academic Year *</label>
            <input name="academic_year" id="pYear" class="form-control" placeholder="2026-2027" pattern="\d{4}-\d{4}" required>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" id="pDescription" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Semester</label>
            <select name="semester" id="pSemester" class="form-select">
              <option value="">—</option><option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Applications Open *</label>
            <input type="date" name="application_start" id="pStart" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Applications Close *</label>
            <input type="date" name="application_end" id="pEnd" class="form-control" required>
          </div>

          <div class="col-12"><hr class="my-1"><small class="text-muted">Eligibility rules — leave blank for no limit. They are checked on submission and shown to staff; they never auto-reject.</small></div>
          <div class="col-md-3">
            <label class="form-label">Max Age</label>
            <input type="number" name="max_age" id="pMaxAge" class="form-control" min="0" step="1">
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Family Income (₱)</label>
            <input type="number" name="max_family_income" id="pMaxIncome" class="form-control" min="0" step="0.01">
          </div>
          <div class="col-md-3">
            <label class="form-label">Min GPA</label>
            <input type="number" name="min_gpa" id="pMinGpa" class="form-control" min="1" max="5" step="0.01" placeholder="e.g. 2.50">
            <div class="form-text">1.00 (highest) – 5.00</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Citizenship</label>
            <input name="citizenship_required" id="pCitizenship" class="form-control" maxlength="50" placeholder="Filipino">
          </div>
          <div class="col-md-6">
            <label class="form-label">Required Residency</label>
            <input name="required_residency" id="pResidency" class="form-control" maxlength="100" placeholder="Barangay Estefania">
          </div>
          <div class="col-md-3">
            <label class="form-label">Budget Amount (₱)</label>
            <input type="number" name="budget_amount" id="pBudget" class="form-control" min="0" step="0.01">
          </div>
          <div class="col-md-3">
            <label class="form-label">Slots Available</label>
            <input type="number" name="slots_available" id="pSlots" class="form-control" min="0" step="1">
            <div class="form-text">0 or blank = no limit</div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" name="is_active" value="1" class="form-check-input" id="pActive" checked>
              <label class="form-check-label" for="pActive">Active (applicants can apply while the window is open)</label>
            </div>
          </div>
        </div>
        <div id="programDateError" class="alert alert-danger py-2 small d-none mt-3">The closing date cannot be before the opening date.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="programSubmitBtn">Save Program</button>
      </div>
    </form>
  </div>
</div>

<script>
function openProgramModal(p) {
  var set = function (id, v) { document.getElementById(id).value = (v === null || v === undefined) ? '' : v; };
  set('programId', p ? p.id : '');
  set('pName', p ? p.program_name : '');
  set('pYear', p ? p.academic_year : '');
  set('pDescription', p ? p.description : '');
  set('pSemester', p ? p.semester : '');
  set('pStart', p ? p.application_start : '');
  set('pEnd', p ? p.application_end : '');
  set('pMaxAge', p ? p.max_age : '');
  set('pMaxIncome', p ? p.max_family_income : '');
  set('pMinGpa', p ? p.min_gpa : '');
  set('pCitizenship', p ? p.citizenship_required : 'Filipino');
  set('pResidency', p ? p.required_residency : 'Barangay Estefania');
  set('pBudget', p ? p.budget_amount : '');
  set('pSlots', p ? p.slots_available : '');
  document.getElementById('pActive').checked = p ? !!Number(p.is_active) : true;
  document.getElementById('programModalTitle').textContent = p ? 'Edit Program' : 'Add Program';
  checkProgramDates();
}
// Client-side convenience only — the server re-validates everything (dates, non-negative numbers).
function checkProgramDates() {
  var s = document.getElementById('pStart').value, e = document.getElementById('pEnd').value;
  var bad = s !== '' && e !== '' && e < s;
  document.getElementById('programDateError').classList.toggle('d-none', !bad);
  document.getElementById('programSubmitBtn').disabled = bad;
}
document.getElementById('pStart').addEventListener('input', checkProgramDates);
document.getElementById('pEnd').addEventListener('input', checkProgramDates);
</script>
