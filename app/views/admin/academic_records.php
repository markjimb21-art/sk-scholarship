<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Academic Records</h4>
    <p class="text-muted small mb-0">All scholars · latest GPA per scholar</p>
  </div>
</div>

<form method="GET" action="<?= url('admin/academic-records') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-5"><input name="q" class="form-control" placeholder="Search scholar…" value="<?= e($filters['q']) ?>"></div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Scholar Status</option>
          <?php foreach (SCHOLAR_STATUS as $s): ?>
            <option <?= $filters['status']===$s?'selected':'' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/academic-records') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Scholar</th><th>School</th><th>Course</th><th>Latest GPA</th><th>Standing</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No scholars.</td></tr><?php endif; ?>
        <?php foreach ($rows as $s):
          $gBadge = 'secondary';
          if ($s['latest_gpa'] !== null) {
            $g = (float)$s['latest_gpa'];
            $gBadge = $g <= 1.75 ? 'success' : ($g <= 2.75 ? 'info' : ($g <= 3.00 ? 'warning' : 'danger'));
          }
          $stBadge = ['Active'=>'success','On Probation'=>'warning','Suspended'=>'danger','Graduated'=>'primary','Withdrawn'=>'secondary','Disqualified'=>'dark'][$s['status']] ?? 'secondary';
        ?>
          <tr>
            <td><div class="fw-semibold"><?= e($s['full_name']) ?></div><small class="text-muted"><code><?= e($s['scholar_code']) ?></code></small></td>
            <td><?= e($s['school_name'] ?? '—') ?></td>
            <td><?= e($s['course_name'] ?? '—') ?></td>
            <td>
              <?php if ($s['latest_gpa'] !== null): ?>
                <span class="badge bg-<?= $gBadge ?>"><?= e((string)$s['latest_gpa']) ?></span>
              <?php else: ?><span class="text-muted small">No record</span><?php endif; ?>
            </td>
            <td><?= e($s['latest_standing'] ?? '—') ?></td>
            <td><span class="badge bg-<?= $stBadge ?>"><?= e($s['status']) ?></span></td>
            <td class="text-end">
              <a href="<?= url('admin/scholar-view&id=' . (int)$s['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-journal-text"></i> Manage</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>