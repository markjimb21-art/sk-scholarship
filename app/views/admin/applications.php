<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Applications</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> record(s)</p>
  </div>
</div>

<form method="GET" action="<?= url('admin/applications') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-4">
        <input name="q" class="form-control" placeholder="Search name, email, or code…" value="<?= e($filters['q']) ?>">
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <?php foreach (APP_STATUS as $s): ?>
            <option <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="school" class="form-select">
          <option value="">All Schools</option>
          <?php foreach ($schools as $s): ?>
            <option <?= $filters['school'] === $s['school_name'] ? 'selected' : '' ?>><?= e($s['school_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="year_level" class="form-select">
          <option value="">All Year Levels</option>
          <?php foreach (YEAR_LEVELS as $yl): ?>
            <option <?= $filters['year_level'] === $yl ? 'selected' : '' ?>><?= e($yl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
        <a href="<?= url('admin/applications') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Code</th><th>Applicant</th><th>School / Course</th>
          <th>Year</th><th>Income</th><th>Docs</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No applications found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r):
          $badge = [
            'Draft'=>'secondary','Submitted'=>'info','Under Initial Review'=>'info','Incomplete'=>'warning',
            'Documents Under Review'=>'warning','Documents Verified'=>'success','For Interview'=>'primary',
            'Interview Scheduled'=>'primary','Interview Completed'=>'primary','For Final Evaluation'=>'primary',
            'Approved'=>'success','Rejected'=>'danger','Waitlisted'=>'warning'
          ][$r['status']] ?? 'secondary';
        ?>
          <tr>
            <td><code><?= e($r['application_code']) ?></code></td>
            <td>
              <div class="fw-semibold"><?= e($r['full_name']) ?></div>
              <small class="text-muted"><?= e($r['email']) ?></small>
            </td>
            <td>
              <div><?= e($r['school_name'] ?: '—') ?></div>
              <small class="text-muted"><?= e($r['course_name'] ?: '—') ?></small>
            </td>
            <td><?= e($r['year_level'] ?: '—') ?></td>
            <td><?= $r['total_family_income'] !== null ? '₱' . number_format((float)$r['total_family_income'], 0) : '—' ?></td>
            <td>
              <span class="badge bg-<?= (int)$r['docs_verified'] === (int)$r['docs_total'] ? 'success' : 'secondary' ?>">
                <?= (int)$r['docs_verified'] ?>/<?= (int)$r['docs_total'] ?>
              </span>
            </td>
            <td><span class="badge bg-<?= $badge ?>"><?= e($r['status']) ?></span></td>
            <td class="text-end">
              <a href="<?= url('admin/applications/view&id=' . (int)$r['id']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Review
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
      <nav>
        <ul class="pagination mb-0 justify-content-center">
          <?php for ($i = 1; $i <= $pages; $i++):
            $qs = http_build_query(array_merge($filters, ['page' => $i]));
          ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="<?= url('admin/applications&' . $qs) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>