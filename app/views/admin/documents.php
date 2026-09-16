<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Document Verification</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> document(s)</p>
  </div>
</div>

<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= !$status ? 'active' : '' ?>" href="<?= url('admin/documents') ?>">All</a></li>
  <?php foreach (['Submitted','Under Review','Verified','Rejected','Resubmission Required'] as $s): ?>
    <li class="nav-item">
      <a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= url('admin/documents&status=' . urlencode($s)) ?>"><?= e($s) ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Applicant</th><th>App Code</th><th>Document</th><th>Uploaded</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$documents): ?><tr><td colspan="6" class="text-center text-muted py-4">No documents.</td></tr><?php endif; ?>
        <?php foreach ($documents as $d):
          $cls = 'doc-' . str_replace(' ', '-', $d['status']);
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($d['applicant_name']) ?></div>
            <small class="text-muted"><?= e($d['email']) ?></small>
          </td>
          <td><code><?= e($d['application_code']) ?></code></td>
          <td><?= e($d['document_type']) ?></td>
          <td><small><?= e(date('M d, Y g:i A', strtotime($d['uploaded_at']))) ?></small></td>
          <td><span class="doc-pill <?= $cls ?>"><?= e($d['status']) ?></span></td>
          <td class="text-end">
            <a href="<?= url('admin/documents/view&id=' . (int)$d['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
            <?php if (Auth::hasRole('admin','staff')): ?>
              <button class="btn btn-sm btn-success"
                      data-bs-toggle="modal" data-bs-target="#docReviewModal"
                      data-id="<?= (int)$d['id'] ?>" data-type="<?= e($d['document_type']) ?>" data-status="Verified">Verify</button>
              <button class="btn btn-sm btn-danger"
                      data-bs-toggle="modal" data-bs-target="#docReviewModal"
                      data-id="<?= (int)$d['id'] ?>" data-type="<?= e($d['document_type']) ?>" data-status="Rejected">Reject</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
      <ul class="pagination mb-0 justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++): $qs = http_build_query(['status' => $status, 'page' => $i]); ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= url('admin/documents&' . $qs) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- Reuse modal from application_view (define once) -->
<div class="modal fade" id="docReviewModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/documents/review') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" id="docReviewId">
      <input type="hidden" name="status" value="" id="docReviewStatus">
      <input type="hidden" name="redirect" value="admin/documents<?= $status ? '&status=' . urlencode($status) : '' ?>">
      <div class="modal-header">
        <h5 class="modal-title">Review Document</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>Document:</strong> <span id="docReviewType"></span></p>
        <p><strong>Action:</strong> <span id="docReviewAction"></span></p>
        <label class="form-label">Remarks (optional)</label>
        <textarea name="remarks" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('docReviewModal');
  modal.addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    modal.querySelector('#docReviewId').value     = btn.dataset.id;
    modal.querySelector('#docReviewType').textContent    = btn.dataset.type;
    modal.querySelector('#docReviewStatus').value = btn.dataset.status;
    modal.querySelector('#docReviewAction').textContent  = btn.dataset.status;
  });
});
</script>