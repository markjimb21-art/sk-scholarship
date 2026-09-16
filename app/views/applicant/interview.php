<h4 class="fw-bold mb-3">My Interview</h4>
<?php if (!$interview): ?>
  <div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
    <i class="bi bi-calendar-x text-muted" style="font-size:3rem;"></i>
    <p class="mt-3 text-muted">No interview schedule assigned yet. You'll be notified once scheduled.</p>
  </div></div>
<?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <span class="badge bg-primary mb-3"><?= e($interview['assignment_status']) ?></span>
      <div class="row g-3">
        <div class="col-md-6"><strong>Date:</strong> <?= e(date('F d, Y (l)', strtotime($interview['interview_date']))) ?></div>
        <div class="col-md-6"><strong>Time:</strong> <?= e(date('g:i A', strtotime($interview['start_time']))) ?> – <?= e(date('g:i A', strtotime($interview['end_time']))) ?></div>
        <div class="col-md-6"><strong>Venue:</strong> <?= e($interview['venue']) ?></div>
        <div class="col-md-6"><strong>Interviewer / Panel:</strong> <?= e($interview['interviewer']) ?></div>
      </div>
      <?php if ($interview['instructions']): ?>
        <hr><h6>Instructions</h6><p><?= nl2br(e($interview['instructions'])) ?></p>
      <?php endif; ?>
      <?php if ($interview['result']): ?>
        <hr>
        <h6>Interview Result</h6>
        <p class="mb-1"><strong>Result:</strong> <?= e($interview['result']) ?></p>
        <?php if ($interview['rating']): ?><p class="mb-1"><strong>Rating:</strong> <?= e((string)$interview['rating']) ?></p><?php endif; ?>
        <?php if ($interview['recommendation']): ?><p class="mb-0"><strong>Recommendation:</strong> <?= e($interview['recommendation']) ?></p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>