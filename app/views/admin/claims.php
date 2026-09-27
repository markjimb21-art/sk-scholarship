<div class="mb-3">
  <h4 class="fw-bold mb-0">Claims to verify</h4>
  <p class="text-muted small mb-0"><?= count($rows) ?> semester claim(s) submitted, oldest first. Check the School ID and Grades, record the GWA, then verify or ask for a re-upload.</p>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Submitted</th><th>Scholar</th><th>Program</th><th>Semester</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No claims waiting.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><small><?= $r['submitted_at'] ? e(date('M d, Y g:i A', strtotime($r['submitted_at']))) : '—' ?></small></td>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['scholar_code']) ?></code></td>
            <td><small><?= e($r['program_name']) ?></small></td>
            <td><?= e($r['academic_year'] . ' ' . $r['semester']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/claims/view&id=' . (int)$r['id']) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
