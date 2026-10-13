<?php
// "History" of key applicant fields (record_history). Expects $fieldHistory (RecordHistory::forApplicant rows).
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white">
    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Changes to key information</h6>
    <div class="small text-muted">GWA, school, course, year level and family income: every change keeps the earlier value.</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle small">
      <thead class="table-light"><tr><th>When</th><th>Field</th><th>Before</th><th>After</th><th>By</th></tr></thead>
      <tbody>
        <?php if (!$fieldHistory): ?><tr><td colspan="5" class="text-center text-muted py-3">No changes since these were first entered.</td></tr><?php endif; ?>
        <?php foreach ($fieldHistory as $h): ?>
          <tr>
            <td class="text-nowrap"><?= e(fmt_datetime($h['changed_at'])) ?></td>
            <td><?= e($h['label']) ?></td>
            <td class="text-muted"><?= e($h['old_value'] ?? '—') ?></td>
            <td class="fw-semibold"><?= e($h['new_value'] ?? '—') ?></td>
            <td><?= e($h['changed_by_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
