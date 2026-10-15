<?php
// Semester documents for review. Expects $docs (type => [side key => newest row], TermDocument::forTerm) and $types
// (required types, in order). The School ID shows its Front and Back; a one-file upload from before sides shows alone.
?>
<div class="row g-3">
  <?php foreach ($types as $type):
    $set = $docs[$type] ?? [];
    $keys = isset($set['']) ? [''] : array_map([Document::class, 'sideKey'], Document::sidesFor($type));
    $statuses = array_column($set, 'status'); ?>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <strong><?= e(TermDocument::label($type)) ?></strong>
          <?php if (!$set): ?><span class="badge bg-secondary">Missing</span>
          <?php elseif (count($set) < count($keys)): ?><span class="badge bg-warning">Incomplete</span>
          <?php else: $s = in_array('Invalid', $statuses, true) ? 'Invalid' : (in_array('Resubmit', $statuses, true) ? 'Resubmit' : (array_unique($statuses) === ['Verified'] ? 'Verified' : 'Submitted')); ?>
            <span class="badge bg-<?= e(TERM_DOC_STATUS_COLORS[$s]) ?>"><?= e($s === 'Submitted' ? 'Uploaded' : $s) ?></span>
          <?php endif; ?>
        </div>
        <div class="row g-2">
          <?php foreach ($keys as $k): $d = $set[$k] ?? null; ?>
            <div class="<?= count($keys) > 1 ? 'col-6' : 'col-12' ?>">
              <?php if ($d && $d['status'] === 'Invalid'): ?>
                <?php if ($k !== ''): ?><div class="small fw-semibold text-muted"><?= e($k) ?></div><?php endif; ?>
                <div class="alert alert-danger py-2 small mb-0">Marked invalid <?= $d['invalidated_at'] ? e(fmt_date($d['invalidated_at'])) : '' ?>: <?= e($d['invalid_reason']) ?>
                  <div class="text-muted">The file was removed; the record is kept. Waiting for the scholar to upload a new one.</div></div>
              <?php elseif ($d): ?>
                <?php $f = $d; $src = url('admin/term-documents/view&id=' . (int)$d['id']); $sideLabel = $k; $imgHeight = 240; $invalidBack = null;
                  require VIEW_PATH . '/admin/_document_file.php'; ?>
                <?php if (in_array($type, VERSIONED_DOCUMENT_TYPES, true)): ?>
                  <?= (int)$d['is_current']
                    ? '<span class="badge bg-success-subtle text-success border">Current on file</span>'
                    : '<span class="badge bg-light text-muted border">Replaced by a newer upload — see the scholar’s document history</span>' ?>
                <?php endif; ?>
              <?php else: ?>
                <?php if ($k !== ''): ?><div class="small fw-semibold text-muted"><?= e($k) ?></div><?php endif; ?>
                <div class="small text-muted">Not uploaded.</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>
