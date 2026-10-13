<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Screening</h4>
    <p class="text-muted small mb-0"><?= count($rows) ?> submitted application(s) waiting, oldest first. Open one to qualify, return or reject it.</p>
  </div>
  <form method="GET" action="<?= url('admin/screening') ?>" class="d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select form-select-sm" data-autosubmit>
      <option value="">All programs</option>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  </form>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Submitted</th><th>Applicant</th><th>Program</th><th>School / course</th><th>GWA</th><th>Family income</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Nothing to screen. 🎉</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><small><?= e(fmt_datetime($r['submitted_at'])) ?></small></td>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['application_code']) ?></code></td>
            <td><small><?= e($r['program_name']) ?></small></td>
            <td><small><?= e($r['school_name'] ?? '—') ?><br><span class="text-muted"><?= e($r['course_name'] ?? '') ?></span></small></td>
            <td><?= $r['general_average'] !== null ? e(number_format((float)$r['general_average'], 2)) : '—' ?></td>
            <td><?= $r['total_family_income'] !== null ? '₱' . e(number_format((float)$r['total_family_income'], 2)) : '—' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('admin/application&id=' . (int)$r['id']) ?>">Screen</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
