<div class="mb-3">
  <h4 class="fw-bold mb-0">Renewals to review</h4>
  <p class="text-muted small mb-0"><?= count($rows) ?> renewal request(s), oldest first. Approving opens the scholar’s next semester.</p>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Submitted</th><th>Scholar</th><th>From</th><th>For</th><th>Last GWA</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No renewals waiting.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><small><?= $r['submitted_at'] ? e(date('M d, Y g:i A', strtotime($r['submitted_at']))) : '—' ?></small></td>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['scholar_code']) ?></code> · <small><?= e($r['program_name']) ?></small></td>
            <td><?= e(trim(($r['from_academic_year'] ?? '') . ' ' . ($r['from_semester'] ?? '')) ?: '—') ?></td>
            <td class="fw-semibold"><?= e($r['academic_year'] . ' ' . $r['semester']) ?></td>
            <td><?= $r['from_gwa'] !== null ? e(number_format((float)$r['from_gwa'], 2)) : '—' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/renewals/view&id=' . (int)$r['id']) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $purpose = 'renewal'; require VIEW_PATH . '/admin/_missed_deadlines.php'; ?>
