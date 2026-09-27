<?php
$canDecide = Term::canClaim('verify', $term['claim_status']);
$minGwa = $term['min_gpa'] !== null ? (float)$term['min_gpa'] : 0.0;
?>
<div class="mb-3">
  <a href="<?= url('admin/claims') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Claims</a>
  <h4 class="fw-bold mb-0 mt-1"><?= e($term['full_name']) ?> — claim for <?= e($term['academic_year'] . ' ' . $term['semester']) ?></h4>
  <p class="text-muted small mb-0"><a href="<?= url('admin/scholar&id=' . (int)$term['scholar_id']) ?>"><?= e($term['scholar_code']) ?></a> · <?= e($term['program_name']) ?>
    · <span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$term['claim_status']]) ?>"><?= e($term['claim_status']) ?></span></p>
</div>

<?php $types = TERM_DOCUMENT_TYPES['claim']; require VIEW_PATH . '/admin/_term_documents.php'; ?>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h6 class="fw-semibold">Decision</h6>
      <?php if ($canDecide): ?>
        <form method="POST" action="<?= url('admin/claims/decide') ?>" class="mb-3">
          <?= csrf_field() ?><input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>"><input type="hidden" name="decision" value="verify">
          <label class="form-label">GWA shown on the Grades <span class="text-danger">*</span></label>
          <input type="number" name="gwa" id="gwaInput" class="form-control mb-1" min="1" max="5" step="0.01" required>
          <div class="form-text mb-2">Program minimum: <?= $minGwa > 0 ? e(number_format($minGwa, 2)) . ' or better (1.00 is highest)' : 'none' ?>.</div>
          <div id="gwaWarning" class="alert alert-warning py-2 small d-none">This GWA is below the program minimum. You decide — nothing happens automatically.</div>
          <button class="btn btn-success"><i class="bi bi-check-circle"></i> Verified</button>
        </form>
        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resubmitModal"><i class="bi bi-arrow-return-left"></i> Resubmit</button>
      <?php else: ?>
        <p class="text-muted small mb-0">Nothing to decide: this claim is "<?= e($term['claim_status']) ?>".</p>
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
    <form method="POST" action="<?= url('admin/claims/decide') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>"><input type="hidden" name="decision" value="resubmit">
      <div class="modal-header"><h5 class="modal-title">Ask for a re-upload</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="small mb-1">Documents to re-upload:</p>
        <?php foreach (TERM_DOCUMENT_TYPES['claim'] as $t): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="types[]" value="<?= e($t) ?>" id="rt<?= md5($t) ?>" checked><label class="form-check-label" for="rt<?= md5($t) ?>"><?= e($t) ?></label></div>
        <?php endforeach; ?>
        <label class="form-label mt-2">Remarks <span class="text-danger">*</span></label>
        <textarea name="remarks" class="form-control" rows="3" required placeholder="The Grades are blurred — please upload a clearer copy."></textarea>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Send back</button></div>
    </form>
  </div>
</div>
<script>
(function () {
  var min = <?= json_encode($minGwa) ?>, input = document.getElementById('gwaInput'), warn = document.getElementById('gwaWarning');
  if (!input || !min) return;
  input.addEventListener('input', function () { var v = parseFloat(input.value); warn.classList.toggle('d-none', isNaN(v) || v <= min); });
})();
</script>
<?php endif; ?>
