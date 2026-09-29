<?php
// "Document history" for the versioned types. Expects $docHistory (type => Document::history() rows, newest first).
$historyId = 'docHistory' . substr(md5(json_encode(array_keys($docHistory))), 0, 6);
$versionCount = array_sum(array_map('count', $docHistory));
?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-white">
    <a class="text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#<?= e($historyId) ?>" role="button" aria-expanded="false">
      <i class="bi bi-clock-history"></i> Document history <span class="badge bg-secondary ms-1"><?= (int)$versionCount ?> version(s)</span>
    </a>
    <div class="small text-muted">Every School ID and Enrollment Form this applicant uploaded. The newest one is shown everywhere; older ones are kept here.</div>
  </div>
  <div class="collapse" id="<?= e($historyId) ?>">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle small">
        <thead class="table-light"><tr><th>Document</th><th>Uploaded</th><th>Uploaded during</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php if (!$versionCount): ?><tr><td colspan="5" class="text-center text-muted py-3">No uploads yet.</td></tr><?php endif; ?>
          <?php foreach ($docHistory as $type => $versions): foreach ($versions as $v): ?>
            <tr class="<?= $v['is_current'] ? '' : 'text-muted' ?>">
              <td><?= e($type) ?> <?= $v['is_current'] ? '<span class="badge bg-success ms-1">Current</span>' : '<span class="badge bg-light text-muted border ms-1">Older</span>' ?></td>
              <td class="text-nowrap"><?= e(date('M d, Y g:i A', strtotime($v['uploaded_at']))) ?>
                <?php if ($v['superseded_at']): ?><div class="text-muted">replaced <?= e(date('M d, Y', strtotime($v['superseded_at']))) ?></div><?php endif; ?></td>
              <td><?= e($v['uploaded_during']) ?> <span class="text-muted">· <?= e($v['context']) ?></span></td>
              <td><?= e($v['status'] === 'Submitted' ? 'Uploaded' : $v['status']) ?></td>
              <td class="text-end"><a href="<?= e($v['admin_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Open</a></td>
            </tr>
          <?php endforeach; endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
