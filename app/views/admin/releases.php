<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Scholarship Releases</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> release(s)</p>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-success"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value" style="font-size:1.3rem;">₱<?= number_format($totalAllTime, 2) ?></div><div class="kpi-label">Total Released</div></div></div></div>
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-info"><i class="bi bi-wallet2"></i></div><div><div class="kpi-value" style="font-size:1.3rem;">₱<?= number_format($budget, 2) ?></div><div class="kpi-label">Total Budget</div></div></div></div>
  <div class="col-md-4"><div class="kpi-card"><div class="kpi-icon kpi-<?= $remaining < 0 ? 'danger' : 'primary' ?>"><i class="bi bi-piggy-bank"></i></div><div><div class="kpi-value" style="font-size:1.3rem;">₱<?= number_format($remaining, 2) ?></div><div class="kpi-label">Remaining Budget</div></div></div></div>
</div>

<form method="GET" action="<?= url('admin/releases') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-5"><input name="q" class="form-control" placeholder="Search scholar, code, reference…" value="<?= e($filters['q']) ?>"></div>
      <div class="col-md-3"><input name="year" class="form-control" placeholder="Academic year (e.g., 2025-2026)" value="<?= e($filters['year']) ?>"></div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/releases') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Release ID</th><th>Scholar</th><th>A.Y. / Sem</th><th>Amount</th><th>Date</th><th>Method</th><th>Staff</th></tr>
      </thead>
      <tbody>
        <?php if (!$releases): ?><tr><td colspan="7" class="text-center text-muted py-4">No releases.</td></tr><?php endif; ?>
        <?php foreach ($releases as $r): ?>
          <tr>
            <td><code><?= e($r['release_code']) ?></code></td>
            <td><div class="fw-semibold"><?= e($r['scholar_name']) ?></div><small class="text-muted"><code><?= e($r['scholar_code']) ?></code></small></td>
            <td><?= e($r['academic_year']) ?><br><small class="text-muted"><?= e($r['semester']) ?></small></td>
            <td class="fw-bold text-success">₱<?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= e(date('M d, Y', strtotime($r['release_date']))) ?></td>
            <td><?= e($r['payment_method']) ?></td>
            <td><small><?= e($r['staff_name'] ?? '—') ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white"><ul class="pagination mb-0 justify-content-center">
      <?php for ($i=1;$i<=$pages;$i++): $qs = http_build_query(array_merge($filters,['page'=>$i])); ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= url('admin/releases&' . $qs) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></div>
  <?php endif; ?>
</div>