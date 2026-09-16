<h4 class="fw-bold mb-1">Applicants Registry</h4>
<p class="text-muted small mb-3"><?= number_format($total) ?> total applicant(s)</p>

<form method="GET" action="<?= url('admin/applicants') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-5">
        <input name="q" class="form-control" placeholder="Search name, email, or code…" value="<?= e($filters['q']) ?>">
      </div>
      <div class="col-md-3">
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
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/applicants') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Email</th><th>School</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No applicants.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= e($r['application_code'] ?? '—') ?></code></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['email']) ?></td>
            <td><?= e($r['school_name'] ?? '—') ?></td>
            <td><span class="badge bg-secondary"><?= e($r['app_status'] ?? '—') ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>