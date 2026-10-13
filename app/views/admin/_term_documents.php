<?php
// Semester documents for review. Expects $docs (keyed by type) and $types (required types, in order).
?>
<div class="row g-3">
  <?php foreach ($types as $type): $d = $docs[$type] ?? null; ?>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <strong><?= e($type) ?></strong>
          <?php if ($d): ?>
            <span class="badge bg-<?= ['Resubmit' => 'warning', 'Verified' => 'success', 'Invalid' => 'danger'][$d['status']] ?? 'info' ?>"><?= e($d['status'] === 'Submitted' ? 'Uploaded' : $d['status']) ?></span>
          <?php else: ?><span class="badge bg-secondary">Missing</span><?php endif; ?>
        </div>
        <?php if ($d && $d['status'] === 'Invalid'): ?>
          <div class="alert alert-danger py-2 small mb-0">Marked invalid <?= $d['invalidated_at'] ? e(fmt_date($d['invalidated_at'])) : '' ?>: <?= e($d['invalid_reason']) ?>
            <div class="text-muted">The file was removed; the record is kept. Waiting for the scholar to upload a new one.</div></div>
        <?php elseif ($d): $src = url('admin/term-documents/view&id=' . (int)$d['id']); ?>
          <?php if (in_array($d['mime_type'], ['image/jpeg', 'image/png'], true)): ?>
            <a href="<?= $src ?>" target="_blank" rel="noopener"><img src="<?= $src ?>" class="img-fluid rounded border mb-2" style="max-height:240px" alt="<?= e($type) ?>"></a>
          <?php endif; ?>
          <div class="small">
            <a href="<?= $src ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Open</a> ·
            <a href="<?= $src ?>&download=1"><i class="bi bi-download"></i> Download</a>
            <div class="text-muted"><?= e($d['original_filename']) ?> · uploaded <?= e(fmt_datetime($d['uploaded_at'])) ?></div>
            <?php if (in_array($type, VERSIONED_DOCUMENT_TYPES, true)): ?>
              <?= (int)$d['is_current']
                ? '<span class="badge bg-success-subtle text-success border mt-1">Current on file</span>'
                : '<span class="badge bg-light text-muted border mt-1">Replaced by a newer upload — see the scholar’s document history</span>' ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>
