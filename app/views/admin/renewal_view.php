<div class="mb-3">
  <a href="<?= url('admin/renewals') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> Back</a>
  <h4 class="fw-bold mt-1 mb-0">Renewal · <?= e($scholar['full_name'] ?? '') ?></h4>
  <p class="text-muted small mb-0"><?= e($renewal['academic_year']) ?> · <?= e($renewal['semester'] ?? '—') ?></p>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <form method="POST" action="<?= url('admin/renewal-update') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$renewal['id'] ?>">

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">GPA</label>
          <input type="number" step="0.01" min="1" max="5" name="gpa" class="form-control" value="<?= e((string)$renewal['gpa']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['For Review','Eligible for Renewal','Not Eligible','Renewed','Renewal Denied'] as $s): ?>
              <option <?= $renewal['status']===$s?'selected':'' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check me-3"><input type="checkbox" class="form-check-input" name="residency_verified" value="1" id="r1" <?= $renewal['residency_verified']?'checked':'' ?>><label class="form-check-label" for="r1">Residency verified</label></div>
          <div class="form-check me-3"><input type="checkbox" class="form-check-input" name="enrollment_verified" value="1" id="r2" <?= $renewal['enrollment_verified']?'checked':'' ?>><label class="form-check-label" for="r2">Enrollment verified</label></div>
          <div class="form-check"><input type="checkbox" class="form-check-input" name="documents_complete" value="1" id="r3" <?= $renewal['documents_complete']?'checked':'' ?>><label class="form-check-label" for="r3">Documents complete</label></div>
        </div>
        <div class="col-12">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" class="form-control" rows="3"><?= e($renewal['remarks'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Renewal</button>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delRenewalModal">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white"><h6 class="mb-0">Recent Academic Records</h6></div>
  <div class="table-responsive">
    <table class="table mb-0 small align-middle">
      <thead class="table-light"><tr><th>A.Y.</th><th>Sem</th><th>GPA</th><th>Standing</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($academics, 0, 6) as $a): ?>
          <tr><td><?= e($a['academic_year']) ?></td><td><?= e($a['semester']) ?></td><td><?= e((string)$a['gpa']) ?></td><td><?= e($a['academic_standing'] ?? '') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="delRenewalModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/renewal-delete') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$renewal['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Delete Renewal?</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">This cannot be undone.</div>
      <div class="modal-footer"><button class="btn btn-danger">Delete</button></div>
    </form>
  </div>
</div>