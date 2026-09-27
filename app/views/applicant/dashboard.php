<?php
// One progress tracker, the status in plain words, and ONE "What you need to do next" box (see ApplicantController::journey()).
$steps = ['Apply', 'Screening', 'Interview', 'Final result', 'Claim cash', 'Renewal'];
?>
<div class="mb-4">
  <h3 class="fw-bold mb-1">Welcome, <?= e(explode(' ', Auth::user()['full_name'])[0]) ?></h3>
  <?php if ($app): ?>
    <p class="text-muted mb-0">
      <?= e($app['program_name']) ?> · Application <code><?= e($app['application_code']) ?></code>
      <?php if ($scholar): ?> · Scholar ID <code><?= e($scholar['scholar_code']) ?></code><?php endif; ?>
    </p>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="progress-tracker mb-3">
      <?php foreach ($steps as $i => $label):
        $cls = $i < $journey['step'] ? 'done' : ($i === $journey['step'] ? 'active' : ''); ?>
        <div class="progress-step <?= $cls ?>">
          <div class="step-circle"><?= $i < $journey['step'] ? '<i class="bi bi-check-lg"></i>' : $i + 1 ?></div>
          <div class="step-label"><?= e($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="mb-0">Status: <span class="badge bg-<?= e($journey['tone']) ?> px-3 py-2"><?= e($journey['status']) ?></span></p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4 border-start border-4 border-<?= e($journey['tone']) ?>">
  <div class="card-body p-4">
    <h5 class="fw-semibold mb-2"><i class="bi bi-signpost-2"></i> What you need to do next</h5>
    <p class="mb-3"><?= e($journey['next']) ?></p>
    <?php foreach ($journey['details'] as $label => $value): ?>
      <div class="alert alert-light border py-2 mb-2">
        <strong><?= e($label) ?>:</strong> <?= nl2br(e($value)) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($journey['action']): ?>
      <a href="<?= url($journey['action']['route']) ?>" class="btn btn-primary mt-2">
        <?= e($journey['action']['label']) ?> <i class="bi bi-arrow-right"></i>
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($pastApplications): ?>
<h5 class="fw-semibold mb-3">Past applications</h5>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light">
        <tr><th>Program</th><th>Application</th><th>Submitted</th><th>Result</th><th>Scholarship</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pastApplications as $pa): ?>
          <tr>
            <td><?= e($pa['program_name']) ?><br><span class="text-muted"><?= e($pa['academic_year']) ?></span></td>
            <td><code><?= e($pa['application_code']) ?></code></td>
            <td><?= $pa['submitted_at'] ? e(date('M d, Y', strtotime($pa['submitted_at']))) : '—' ?></td>
            <td>
              <span class="badge bg-<?= e(APP_STATUS_COLORS[$pa['status']] ?? 'secondary') ?>"><?= e(Application::label($pa['status'])) ?></span>
              <?php if ($pa['status'] === 'Rejected' && $pa['decision_remarks']): ?>
                <div class="text-muted"><?= e($pa['decision_remarks']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($pa['scholar_code']): ?>
                <?= e($pa['scholar_code']) ?> — <?= e($pa['scholar_status'] === 'Ended' ? 'Ended (' . $pa['end_reason'] . ')' : 'Active') ?>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">Past applications are read-only.</div>
</div>
<?php endif; ?>
