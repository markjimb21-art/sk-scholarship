<?php $canDecide = Renewal::can('approve', $req['status']); ?>
<div class="mb-3">
  <a href="<?= url('admin/renewals') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Renewals</a>
  <h4 class="fw-bold mb-0 mt-1"><?= e($req['full_name']) ?> — renewal for <?= e($req['academic_year'] . ' ' . $req['semester']) ?></h4>
  <p class="text-muted small mb-0"><a href="<?= url('admin/scholar&id=' . (int)$req['scholar_id']) ?>"><?= e($req['scholar_code']) ?></a> · <?= e($req['program_name']) ?>
    · <span class="badge bg-info"><?= e(RENEWAL_STATUS_LABELS[$req['status']]) ?></span>
    <?= $req['scholar_status'] !== 'Active' ? '<span class="badge bg-secondary">Scholarship ended</span>' : '' ?></p>
</div>

<?php $types = TERM_DOCUMENT_TYPES['renewal']; require VIEW_PATH . '/admin/_term_documents.php'; ?>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold">Decision</h6>
      <?php if ($canDecide && $req['scholar_status'] === 'Active'): ?>
        <form method="POST" action="<?= url('admin/renewals/decide') ?>" class="d-inline" onsubmit="return confirm('Approve? This opens <?= e($req['academic_year'] . ' ' . $req['semester']) ?> for the scholar.');">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="decision" value="approve">
          <button class="btn btn-success"><i class="bi bi-check-circle"></i> Approved — open <?= e($req['academic_year'] . ' ' . $req['semester']) ?></button>
        </form>
        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resubmitModal"><i class="bi bi-arrow-return-left"></i> Resubmit</button>
      <?php else: ?>
        <p class="text-muted small mb-0">Nothing to decide right now.</p>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold">Semesters of this scholar</h6>
      <table class="table table-sm small mb-0">
        <thead><tr><th>Semester</th><th>Claim</th><th>GWA</th><th>Released</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr><td><?= e($h['academic_year'] . ' ' . $h['semester']) ?></td><td><?= e($h['claim_status']) ?></td>
              <td><?= $h['gwa'] !== null ? e(number_format((float)$h['gwa'], 2)) : '—' ?></td>
              <td><?= $h['released_amount'] !== null ? '₱' . e(number_format((float)$h['released_amount'], 2)) : '—' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<?php if ($canDecide): ?>
<div class="modal fade" id="resubmitModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/renewals/decide') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="decision" value="resubmit">
      <div class="modal-header"><h5 class="modal-title">Ask for a re-upload</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="small mb-1">Documents to re-upload:</p>
        <?php foreach (TERM_DOCUMENT_TYPES['renewal'] as $t): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="types[]" value="<?= e($t) ?>" id="rn<?= md5($t) ?>" checked><label class="form-check-label" for="rn<?= md5($t) ?>"><?= e($t) ?></label></div>
        <?php endforeach; ?>
        <label class="form-label mt-2">Remarks <span class="text-danger">*</span></label>
        <textarea name="remarks" class="form-control" rows="3" required placeholder="The Enrollment Form is for the wrong semester."></textarea>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Send back</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
