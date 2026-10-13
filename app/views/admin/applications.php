<div class="mb-3">
  <h4 class="fw-bold mb-0">Applications</h4>
  <p class="text-muted small mb-0"><?= number_format($total) ?> application(s). This is the complete record; the work happens in Screening, Interviews and Decisions.</p>
</div>

<form method="GET" action="<?= url('admin/applications') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-4"><div><label class="form-label" for="f_q">Search</label><input id="f_q" name="q" class="form-control" placeholder="Name, e-mail or application code" value="<?= e($filters['q']) ?>"></div></div>
    <div class="col-md-3">
      <div><label class="form-label" for="f_status">Status</label><select id="f_status" name="status" class="form-select">
        <option value="">All statuses</option>
        <?php foreach (APP_STATUS as $s): ?>
          <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(Application::label($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
    </div>
    <div class="col-md-3">
      <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select">
        <option value="">All programs</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $filters['program_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button>
      <a href="<?= url('admin/applications') ?>" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-x" aria-hidden="true"></i></a>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Applicant</th><th>Program</th><th>Status</th><th>Submitted</th><th>Last change</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No applications match.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['application_code']) ?></code></td>
            <td><small><?= e($r['program_name']) ?></small></td>
            <td><span class="badge bg-<?= e(APP_STATUS_COLORS[$r['status']]) ?>"><?= e(Application::label($r['status'])) ?></span></td>
            <td><small><?= $r['submitted_at'] ? e(fmt_date($r['submitted_at'])) : '—' ?></small></td>
            <td><small><?= e(fmt_date($r['updated_at'])) ?></small></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('admin/application&id=' . (int)$r['id']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
      <a class="page-link" href="<?= url('admin/applications&' . http_build_query(array_filter($filters + ['page' => $i], static fn($v) => $v !== ''))) ?>"><?= $i ?></a>
    </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>
