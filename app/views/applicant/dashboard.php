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
  <p class="text-muted">Application Code: <strong><?= e($applicant['application_code'] ?? '—') ?></strong></p>
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