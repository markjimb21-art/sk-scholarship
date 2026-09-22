<h4 class="fw-bold mb-3">My Documents</h4>
<?php if (!$app): ?>
  <div class="alert alert-warning">Please submit your application first (Step 5 of the form).</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach (DOCUMENT_TYPES as $type):
    $doc = null;
    foreach ($documents as $d) if ($d['document_type'] === $type) { $doc = $d; break; }
    $status = $doc['status'] ?? 'Not Submitted';
  ?>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="fw-semibold mb-0"><?= e($type) ?></h6>
          <span class="doc-pill doc-<?= e(str_replace(' ','-',$status)) ?>"><?= e($status) ?></span>
        </div>
        <?php if ($doc && $doc['file_path']): ?>
          <p class="small text-muted mb-2">
            <i class="bi bi-paperclip"></i> <?= e($doc['original_filename']) ?><br>
            Uploaded: <?= e(date('M d, Y g:i A', strtotime($doc['uploaded_at']))) ?>
            &middot; <a href="<?= e(url('applicant/documents/view&id=' . (int)$doc['id'])) ?>" target="_blank" rel="noopener">View</a>
          </p>
          <?php if ($doc['remarks']): ?>
            <div class="alert alert-<?= $doc['status']==='Rejected'?'danger':'info' ?> py-2 small mb-2">
              <strong>Remarks:</strong> <?= e($doc['remarks']) ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        <form method="POST" action="<?= url('applicant/documents/upload') ?>" enctype="multipart/form-data" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="document_type" value="<?= e($type) ?>">
          <div class="input-group input-group-sm">
            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            <button class="btn btn-primary">Upload</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>