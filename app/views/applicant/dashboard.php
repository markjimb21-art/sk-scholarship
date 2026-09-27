<?php
$status = $app['status'] ?? 'Draft';
$statusColors = [
  'Draft'=>'secondary','Submitted'=>'info','Under Initial Review'=>'info','Incomplete'=>'warning',
  'Documents Under Review'=>'warning','Documents Verified'=>'success','For Interview'=>'primary',
  'Interview Scheduled'=>'primary','Interview Completed'=>'primary','For Final Evaluation'=>'primary',
  'Approved'=>'success','Rejected'=>'danger','Waitlisted'=>'warning'
];
$badge = $statusColors[$status] ?? 'secondary';
?>
<div class="mb-4">
  <h3 class="fw-bold mb-1">Welcome back, <?= e(explode(' ', Auth::user()['full_name'])[0]) ?> 👋</h3>
  <p class="text-muted mb-1">Application Code: <strong><?= e($applicant['application_code'] ?? '—') ?></strong></p>
  <?php if ($app): ?>
    <p class="text-muted small mb-0">Program: <strong><?= e($app['program_name']) ?></strong></p>
  <?php elseif ($openProgram): ?>
    <div class="alert alert-info py-2 mt-2 mb-0 small">
      <i class="bi bi-megaphone"></i> Now accepting applications for <strong><?= e($openProgram['program_name']) ?></strong>
      until <?= e(date('F j, Y', strtotime($openProgram['application_end']))) ?>.
      <a href="<?= url('applicant/application') ?>" class="alert-link">Start your application</a>.
    </div>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h5 class="fw-semibold mb-0">Application Progress</h5>
      <span class="badge bg-<?= $badge ?> px-3 py-2"><?= e($status) ?></span>
    </div>

    <?php
      $steps = ['Personal Information','Education','Family Background','Documents','Review','Interview','Final Evaluation','Decision'];
      $statusMap = [
        'Draft' => 0,
        'Submitted' => 4,
        'Under Initial Review' => 4,
        'Incomplete' => 3,
        'Documents Under Review' => 3,
        'Documents Verified' => 4,
        'For Interview' => 5,
        'Interview Scheduled' => 5,
        'Interview Completed' => 6,
        'For Final Evaluation' => 6,
        'Approved' => 8,
        'Rejected' => 8,
        'Waitlisted' => 8,
      ];
      $currentStep = $statusMap[$status] ?? 0;
    ?>
    <div class="progress-tracker">
      <?php foreach ($steps as $i => $s): 
        $cls = $i < $currentStep ? 'done' : ($i === $currentStep ? 'active' : '');
      ?>
        <div class="progress-step <?= $cls ?>">
          <div class="step-circle"><?= $i + 1 ?></div>
          <div class="step-label"><?= e($s) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="kpi-card">
      <div class="kpi-icon kpi-info"><i class="bi bi-file-earmark-check"></i></div>
      <div>
        <div class="kpi-value"><?= $docVerified ?>/<?= max(5, $docCount) ?></div>
        <div class="kpi-label">Documents Verified</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi-card">
      <div class="kpi-icon kpi-primary"><i class="bi bi-calendar-event"></i></div>
      <div>
        <div class="kpi-value" style="font-size:1.1rem;"><?= $interview ? e($interview['interview_date']) : 'Not scheduled' ?></div>
        <div class="kpi-label">Interview Date</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi-card">
      <div class="kpi-icon kpi-success"><i class="bi bi-award"></i></div>
      <div>
        <div class="kpi-value" style="font-size:1.1rem;"><?= e($status === 'Approved' ? 'Approved!' : 'Pending') ?></div>
        <div class="kpi-label">Scholarship Decision</div>
      </div>
    </div>
  </div>
</div>

<h5 class="fw-semibold mb-3">Quick Actions</h5>
<div class="row g-3">
  <div class="col-md-4 col-lg-3">
    <a href="<?= url('applicant/application') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-4">
        <i class="bi bi-pencil-square text-primary" style="font-size:2rem;"></i>
        <h6 class="mt-2 mb-0 text-dark">Complete Application</h6>
      </div>
    </a>
  </div>
  <div class="col-md-4 col-lg-3">
    <a href="<?= url('applicant/documents') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-4">
        <i class="bi bi-cloud-upload text-info" style="font-size:2rem;"></i>
        <h6 class="mt-2 mb-0 text-dark">Upload Documents</h6>
      </div>
    </a>
  </div>
  <div class="col-md-4 col-lg-3">
    <a href="<?= url('applicant/interview') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-4">
        <i class="bi bi-calendar2-check text-warning" style="font-size:2rem;"></i>
        <h6 class="mt-2 mb-0 text-dark">View Interview</h6>
      </div>
    </a>
  </div>
  <div class="col-md-4 col-lg-3">
    <a href="<?= url('applicant/notifications') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-4">
        <i class="bi bi-bell text-danger" style="font-size:2rem;"></i>
        <h6 class="mt-2 mb-0 text-dark">Notifications</h6>
      </div>
    </a>
  </div>
</div>
<?php if (!empty($pastApplications)): ?>
<h5 class="fw-semibold mt-4 mb-3">Past Applications</h5>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light">
        <tr><th>Program</th><th>Application Code</th><th>Submitted</th><th>Status</th><th>Scholarship</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pastApplications as $pa): ?>
          <tr>
            <td><?= e($pa['program_name']) ?><br><span class="text-muted"><?= e($pa['academic_year']) ?></span></td>
            <td><code><?= e($pa['application_code']) ?></code></td>
            <td><?= $pa['submitted_at'] ? e(date('M d, Y', strtotime($pa['submitted_at']))) : '—' ?></td>
            <td><span class="badge bg-<?= e($statusColors[$pa['status']] ?? 'secondary') ?>"><?= e($pa['status']) ?></span></td>
            <td><?= $pa['scholar_code'] ? e($pa['scholar_code'] . ' (' . $pa['scholar_status'] . ')') : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">Past applications are kept for your records and can no longer be edited.</div>
</div>
<?php endif; ?>
