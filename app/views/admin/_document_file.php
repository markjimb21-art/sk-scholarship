<?php
// One document file for staff: side label, preview (images), Open · Download, name and date, and optionally
// "Mark this file invalid…". Expects $f (a documents/term_documents/Document::history row), $src (admin view URL),
// $sideLabel ('Front' / 'Back' / '' for one file), $imgHeight (px) and $invalidBack (route to return to after marking
// it invalid, or null for no form). $invalidTable is the row's table when $invalidBack is set.
?>
<div class="mb-2">
  <?php if ($sideLabel !== ''): ?><div class="small fw-semibold text-muted"><?= e($sideLabel) ?></div><?php endif; ?>
  <?php if (in_array($f['mime_type'], ['image/jpeg', 'image/png'], true)): ?>
    <a href="<?= e($src) ?>" target="_blank" rel="noopener">
      <img src="<?= e($src) ?>" class="img-fluid rounded border mb-2" style="max-height:<?= (int)$imgHeight ?>px" alt="<?= e(trim(($f['document_type'] ?? $f['type']) . ' ' . strtolower($sideLabel))) ?>">
    </a>
  <?php endif; ?>
  <div class="small">
    <a href="<?= e($src) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Open</a> ·
    <a href="<?= e($src . '&download=1') ?>"><i class="bi bi-download"></i> Download</a>
    <div class="text-muted"><?= e($f['original_filename']) ?> · <?= e(fmt_date($f['uploaded_at'])) ?></div>
  </div>
  <?php if ($invalidBack !== null): $invKey = $invalidTable . (int)$f['id']; ?>
    <details class="small mt-1">
      <summary class="text-danger">Mark this file invalid…</summary>
      <form method="POST" action="<?= url('admin/documents/invalidate') ?>" class="mt-1"
            data-confirm-title="Mark this file invalid?" data-confirm-subject="<?= e($f['original_filename']) ?>"
            data-confirm="The file is removed. The record is kept, marked Invalid with your reason." data-confirm-ok="Mark invalid">
        <?= csrf_field() ?><input type="hidden" name="table" value="<?= e($invalidTable) ?>"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
        <input type="hidden" name="back" value="<?= e($invalidBack) ?>">
        <label class="form-label" for="invReason<?= e($invKey) ?>">Why is this file invalid?</label>
        <input name="reason" id="invReason<?= e($invKey) ?>" class="form-control form-control-sm mb-1" required maxlength="500" placeholder="e.g. another person's ID">
        <button class="btn btn-sm btn-outline-danger">Mark invalid and remove the file</button>
      </form>
    </details>
  <?php endif; ?>
</div>
