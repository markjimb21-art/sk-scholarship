<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Audit Logs</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> record(s)</p>
  </div>
</div>

<form method="GET" action="<?= url('admin/audit-logs') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3"><input name="q" class="form-control form-control-sm" placeholder="Search description…" value="<?= e($filters['q']) ?>"></div>
      <div class="col-md-2">
        <select name="action" class="form-select form-select-sm">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
            <option <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="entity_type" class="form-select form-select-sm">
          <option value="">All Entities</option>
          <?php foreach ($entityTypes as $e2): ?>
            <option <?= $filters['entity_type'] === $e2['entity_type'] ? 'selected' : '' ?>><?= e($e2['entity_type']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="user_id" class="form-select form-select-sm">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)$filters['user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>"></div>
      <div class="col-md-1"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>"></div>
      <div class="col-md-1 d-flex gap-1">
        <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/audit-logs') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle small">
      <thead class="table-light">
        <tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$logs): ?><tr><td colspan="7" class="text-center text-muted py-4">No logs.</td></tr><?php endif; ?>
        <?php foreach ($logs as $log):
          $hasDiff = !empty($log['old_values']) || !empty($log['new_values']);
        ?>
          <tr>
            <td><?= e(date('M d, Y g:i A', strtotime($log['created_at']))) ?></td>
            <td>
              <?php if ($log['actor_name']): ?>
                <div><?= e($log['actor_name']) ?></div>
                <small class="text-muted"><?= e($log['actor_email']) ?></small>
              <?php else: ?>
                <span class="text-muted">system</span>
              <?php endif; ?>
            </td>
            <td><code><?= e($log['action']) ?></code></td>
            <td>
              <?php if ($log['entity_type']): ?>
                <span class="badge bg-light text-dark"><?= e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int)$log['entity_id'] : '' ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= e($log['description'] ?? '') ?></td>
            <td><small class="text-muted"><?= e($log['ip_address'] ?? '') ?></small></td>
            <td class="text-end">
              <?php if ($hasDiff): ?>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#diffModal"
                        data-old='<?= e($log['old_values'] ?? '{}') ?>'
                        data-new='<?= e($log['new_values'] ?? '{}') ?>'
                        data-action='<?= e($log['action']) ?>'>
                  <i class="bi bi-arrow-left-right"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
      <ul class="pagination mb-0 justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++):
          $qs = http_build_query(array_merge($filters, ['page' => $i])); ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= url('admin/audit-logs&' . $qs) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- Diff Modal -->
<div class="modal fade" id="diffModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Details · <code id="diffAction"></code></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="text-danger">Before</h6>
            <pre class="bg-light p-3 rounded small mb-0" id="diffOld" style="max-height: 400px; overflow:auto;"></pre>
          </div>
          <div class="col-md-6">
            <h6 class="text-success">After</h6>
            <pre class="bg-light p-3 rounded small mb-0" id="diffNew" style="max-height: 400px; overflow:auto;"></pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('diffModal');
  modal.addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    modal.querySelector('#diffAction').textContent = b.dataset.action;
    try {
      const oldV = JSON.parse(b.dataset.old || '{}');
      const newV = JSON.parse(b.dataset.new || '{}');
      modal.querySelector('#diffOld').textContent = JSON.stringify(oldV, null, 2);
      modal.querySelector('#diffNew').textContent = JSON.stringify(newV, null, 2);
    } catch (err) {
      modal.querySelector('#diffOld').textContent = b.dataset.old;
      modal.querySelector('#diffNew').textContent = b.dataset.new;
    }
  });
});
</script>