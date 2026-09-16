<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Scholars</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> scholar(s)</p>
  </div>
</div>

<form method="GET" action="<?= url('admin/scholars') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, code, email…" value="<?= e($filters['q']) ?>"></div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <?php foreach (SCHOLAR_STATUS as $s): ?>
            <option <?= $filters['status']===$s?'selected':'' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="school" class="form-select">
          <option value="">All Schools</option>
          <?php foreach ($schools as $s): ?>
            <option <?= $filters['school']===$s['school_name']?'selected':'' ?>><?= e($s['school_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="year_level" class="form-select">
          <option value="">All Year Levels</option>
          <?php foreach (YEAR_LEVELS as $yl): ?>
            <option <?= $filters['year_level']===$yl?'selected':'' ?>><?= e($yl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/scholars') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Scholar ID</th><th>Name</th><th>School / Course</th><th>Year</th><th>Latest GPA</th><th>Released</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No scholars yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $s):
          $gpaBadge = 'secondary';
          if ($s['latest_gpa'] !== null) {
            $g = (float)$s['latest_gpa'];
            $gpaBadge = $g <= 1.75 ? 'success' : ($g <= 2.75 ? 'info' : ($g <= 3.00 ? 'warning' : 'danger'));
          }
          $stBadge = ['Active'=>'success','On Probation'=>'warning','Suspended'=>'danger','Graduated'=>'primary','Withdrawn'=>'secondary','Disqualified'=>'dark'][$s['status']] ?? 'secondary';
        ?>
          <tr>
            <td><code><?= e($s['scholar_code']) ?></code></td>
            <td>
              <div class="fw-semibold"><?= e($s['full_name']) ?></div>
              <small class="text-muted"><?= e($s['email']) ?></small>
            </td>
            <td>
              <div><?= e($s['school_name'] ?? '—') ?></div>
              <small class="text-muted"><?= e($s['course_name'] ?? '') ?></small>
            </td>
            <td><?= e($s['year_level'] ?? '—') ?></td>
            <td>
              <?php if ($s['latest_gpa'] !== null): ?>
                <span class="badge bg-<?= $gpaBadge ?>"><?= e((string)$s['latest_gpa']) ?></span>
                <small class="d-block text-muted"><?= e($s['latest_standing'] ?? '') ?></small>
              <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
            </td>
            <td>₱<?= number_format((float)$s['total_released'], 2) ?></td>
            <td><span class="badge bg-<?= $stBadge ?>"><?= e($s['status']) ?></span></td>
            <td class="text-end">
              <a href="<?= url('admin/scholar-view&id=' . (int)$s['id']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> View
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
      <ul class="pagination mb-0 justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++): $qs = http_build_query(array_merge($filters, ['page'=>$i])); ?>
          <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= url('admin/scholars&' . $qs) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>