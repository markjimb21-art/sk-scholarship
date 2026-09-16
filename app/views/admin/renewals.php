<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Renewals</h4>
    <p class="text-muted small mb-0">Scholarship renewal evaluation & approval</p>
  </div>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['all'=>'All','For Review'=>'For Review','Eligible for Renewal'=>'Eligible','Not Eligible'=>'Not Eligible','Renewed'=>'Renewed','Renewal Denied'=>'Denied'] as $k=>$lbl): ?>
    <li class="nav-item"><a class="nav-link <?= $filter===$k?'active':'' ?>" href="<?= url('admin/renewals&filter=' . urlencode($k)) ?>"><?= e($lbl) ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Scholar</th><th>A.Y. / Sem</th><th>GPA</th><th>Checks</th><th>Status</th><th>Evaluated By</th><th></th></tr></thead>
      <tbody>
        <?php if (!$renewals): ?><tr><td colspan="7" class="text-center text-muted py-4">No renewal records.</td></tr><?php endif; ?>
        <?php foreach ($renewals as $r):
          $badge = ['Renewed'=>'success','Eligible for Renewal'=>'info','For Review'=>'warning','Not Eligible'=>'danger','Renewal Denied'=>'dark'][$r['status']] ?? 'secondary';
        ?>
          <tr>
            <td><div class="fw-semibold"><?= e($r['scholar_name']) ?></div><small class="text-muted"><code><?= e($r['scholar_code']) ?></code></small></td>
            <td><?= e($r['academic_year']) ?><br><small class="text-muted"><?= e($r['semester'] ?? '—') ?></small></td>
            <td><?= e((string)$r['gpa']) ?></td>
            <td>
              <span class="badge bg-<?= $r['residency_verified']?'success':'secondary' ?>">R</span>
              <span class="badge bg-<?= $r['enrollment_verified']?'success':'secondary' ?>">E</span>
              <span class="badge bg-<?= $r['documents_complete']?'success':'secondary' ?>">D</span>
            </td>
            <td><span class="badge bg-<?= $badge ?>"><?= e($r['status']) ?></span></td>
            <td><small><?= e($r['evaluated_by_name'] ?? '—') ?></small></td>
            <td class="text-end"><a href="<?= url('admin/renewal-view&id=' . (int)$r['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>