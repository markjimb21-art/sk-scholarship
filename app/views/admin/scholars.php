<div class="mb-3">
  <h4 class="fw-bold mb-0">Scholars</h4>
  <p class="text-muted small mb-0"><?= count($rows) ?> scholar(s). Open a scholar to see every semester or to end the scholarship.</p>
</div>

<form method="GET" action="<?= url('admin/scholars') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-5"><div><label class="form-label" for="f_q">Search</label><input id="f_q" name="q" class="form-control" placeholder="Name, e-mail or scholar ID" value="<?= e($filters['q']) ?>"></div></div>
    <div class="col-md-2">
      <div><label class="form-label" for="f_status">Status</label><select id="f_status" name="status" class="form-select">
        <?php foreach (['Active' => 'Active', 'Ended' => 'Ended', 'all' => 'All'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select></div>
    </div>
    <div class="col-md-3">
      <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select">
        <option value="">All programs</option>
        <?php foreach ($programs as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $filters['program_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button></div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Scholar</th><th>Program</th><th>Current semester</th><th>Total released</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No scholars match.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): [$sem, $claim] = array_pad(explode('|', (string)$r['current_term']), 2, ''); ?>
          <tr>
            <td><div class="fw-semibold"><?= e($r['full_name']) ?></div><code class="small"><?= e($r['scholar_code']) ?></code></td>
            <td><small><?= e($r['program_name']) ?></small></td>
            <td><?= e($sem ?: '—') ?><?php if ($claim): ?><br><span class="badge bg-<?= e(CLAIM_STATUS_COLORS[$claim] ?? 'secondary') ?>"><?= e($claim) ?></span><?php endif; ?></td>
            <td>₱<?= e(number_format((float)$r['total_released'], 2)) ?></td>
            <td><?= $r['status'] === 'Active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended — ' . e($r['end_reason']) . '</span>' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('admin/scholar&id=' . (int)$r['id']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
