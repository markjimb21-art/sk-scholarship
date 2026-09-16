<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Interview Evaluation</h4>
    <p class="text-muted small mb-0">Score applicants on 5 criteria (each 0–20); total up to 100</p>
  </div>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['pending'=>'Pending Evaluation','done'=>'Completed','all'=>'All','noshow'=>'No Shows'] as $k=>$label): ?>
    <li class="nav-item">
      <a class="nav-link <?= $filter===$k?'active':'' ?>" href="<?= url('admin/interview-evaluation&filter=' . $k) ?>">
        <?= e($label) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Applicant</th><th>School / Course</th><th>Interview</th><th>Status</th><th>Score</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No records.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($r['full_name']) ?></div>
              <small class="text-muted"><code><?= e($r['application_code']) ?></code></small>
            </td>
            <td>
              <div class="small"><?= e($r['school_name'] ?? '—') ?></div>
              <small class="text-muted"><?= e($r['course_name'] ?? '') ?></small>
            </td>
            <td>
              <div class="small"><?= e(date('M d, Y', strtotime($r['interview_date']))) ?></div>
              <small class="text-muted"><?= e(date('g:i A', strtotime($r['start_time']))) ?></small>
            </td>
            <td><span class="badge bg-secondary"><?= e($r['assignment_status']) ?></span></td>
            <td>
              <?php if ($r['evaluation_id']): ?>
                <span class="badge bg-<?= $r['result']==='Recommended'?'success':($r['result']==='Not Recommended'?'danger':'warning') ?>">
                  <?= e((string)$r['result']) ?>
                </span>
                <div class="small text-muted mt-1">Score: <strong><?= e((string)$r['total_score']) ?></strong></div>
              <?php else: ?>
                <span class="text-muted small">Not yet evaluated</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= url('admin/interview-evaluate&id=' . (int)$r['assignment_id']) ?>" class="btn btn-sm btn-<?= $r['evaluation_id']?'outline-primary':'success' ?>">
                <i class="bi bi-<?= $r['evaluation_id']?'pencil':'clipboard-check' ?>"></i>
                <?= $r['evaluation_id'] ? 'Edit' : 'Evaluate' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>