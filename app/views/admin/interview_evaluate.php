<?php $ev = $evaluation ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('admin/interview-evaluation') ?>" class="text-decoration-none small text-muted">
      <i class="bi bi-arrow-left"></i> Back to Evaluations
    </a>
    <h4 class="fw-bold mb-0 mt-1">Interview Evaluation</h4>
    <p class="text-muted small mb-0"><?= e($assignment['full_name']) ?> · <code><?= e($assignment['application_code']) ?></code></p>
  </div>
</div>

<div class="row g-3">
  <!-- LEFT: applicant context -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><h6 class="mb-0">Applicant Context</h6></div>
      <div class="card-body small">
        <p class="mb-1"><strong>Email:</strong> <?= e($assignment['email']) ?></p>
        <p class="mb-1"><strong>Contact:</strong> <?= e($assignment['contact_number'] ?? '—') ?></p>
        <p class="mb-1"><strong>School:</strong> <?= e($assignment['school_name'] ?? '—') ?></p>
        <p class="mb-1"><strong>Course:</strong> <?= e($assignment['course_name'] ?? '—') ?></p>
        <p class="mb-1"><strong>Year Level:</strong> <?= e($assignment['year_level'] ?? '—') ?></p>
        <p class="mb-1"><strong>Family Income:</strong> ₱<?= number_format((float)($assignment['total_family_income'] ?? 0), 2) ?></p>
        <hr>
        <p class="mb-1"><strong>Interview Date:</strong> <?= e(date('F d, Y', strtotime($assignment['interview_date']))) ?></p>
        <p class="mb-1"><strong>Time:</strong> <?= e(date('g:i A', strtotime($assignment['start_time']))) ?> – <?= e(date('g:i A', strtotime($assignment['end_time']))) ?></p>
        <p class="mb-1"><strong>Venue:</strong> <?= e($assignment['venue']) ?></p>
        <p class="mb-1"><strong>Interviewer:</strong> <?= e($assignment['interviewer']) ?></p>
      </div>
    </div>
  </div>

  <!-- RIGHT: evaluation form -->
  <div class="col-lg-8">
    <form method="POST" action="<?= url('admin/interview-evaluation-save') ?>" class="card border-0 shadow-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">

      <div class="card-body">
        <h6 class="fw-semibold mb-3">Rating Criteria <small class="text-muted">(each 0 – 20)</small></h6>

        <?php
          $criteria = [
            'financial_need_score' => 'Financial Need',
            'academic_score'       => 'Academic Performance',
            'motivation_score'     => 'Motivation',
            'community_score'      => 'Community Involvement',
            'purpose_score'        => 'Scholarship Purpose',
          ];
        ?>
        <?php foreach ($criteria as $key => $label): ?>
          <div class="row align-items-center mb-3">
            <label class="col-md-4 col-form-label small fw-semibold"><?= e($label) ?></label>
            <div class="col-md-5">
              <input type="range" min="0" max="20" step="1" class="form-range criterion"
                     name="<?= e($key) ?>" id="crit-<?= e($key) ?>"
                     value="<?= e((string)($ev[$key] ?? 10)) ?>">
            </div>
            <div class="col-md-3">
              <input type="number" min="0" max="20" class="form-control form-control-sm criterion-mirror"
                     data-mirror="<?= e($key) ?>" value="<?= e((string)($ev[$key] ?? 10)) ?>">
            </div>
          </div>
        <?php endforeach; ?>

        <div class="row align-items-center mb-3">
          <label class="col-md-4 col-form-label small fw-semibold">Overall Assessment</label>
          <div class="col-md-5">
            <input type="range" min="0" max="20" step="1" class="form-range criterion"
                   name="overall_score" id="crit-overall_score" value="<?= e((string)($ev['overall_score'] ?? 10)) ?>">
          </div>
          <div class="col-md-3">
            <input type="number" min="0" max="20" class="form-control form-control-sm criterion-mirror"
                   data-mirror="overall_score" value="<?= e((string)($ev['overall_score'] ?? 10)) ?>">
          </div>
        </div>

        <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
          <div>
            <span class="text-muted small">Total Score (5 criteria, 0–100)</span>
            <div class="fw-bold fs-4" id="totalScore">0</div>
          </div>
          <div class="text-end">
            <span class="text-muted small">Rating (0–5)</span>
            <div class="fw-bold fs-4" id="ratingScore">0.00</div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Evaluation Result *</label>
          <select name="result" class="form-select" required>
            <?php foreach (['Recommended','Recommended with Conditions','For Further Review','Not Recommended','No Show','Rescheduled'] as $r): ?>
              <option <?= ($ev['result'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Recommendation</label>
          <textarea name="recommendation" class="form-control" rows="2"
                    placeholder="e.g., Recommend for approval, subject to submission of updated Form 138."><?= e($ev['recommendation'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Interview Remarks</label>
          <textarea name="remarks" class="form-control" rows="3"><?= e($ev['interview_remarks'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Additional Notes (internal)</label>
          <textarea name="notes" class="form-control" rows="2"><?= e($ev['additional_notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= url('admin/interview-evaluation') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-success"><i class="bi bi-save"></i> Save Evaluation</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const criteriaKeys = ['financial_need_score','academic_score','motivation_score','community_score','purpose_score'];
  const ranges = document.querySelectorAll('.criterion');
  const mirrors = document.querySelectorAll('.criterion-mirror');

  const recalc = () => {
    let total = 0;
    criteriaKeys.forEach(k => {
      const el = document.querySelector(`[name="${k}"]`);
      if (el) total += parseFloat(el.value) || 0;
    });
    document.getElementById('totalScore').textContent = total;
    document.getElementById('ratingScore').textContent = (total / 20).toFixed(2);
  };

  // Sync range ↔ number
  ranges.forEach(r => {
    const key = r.name;
    const mirror = document.querySelector(`[data-mirror="${key}"]`);
    r.addEventListener('input', () => { mirror.value = r.value; recalc(); });
    mirror.addEventListener('input', () => {
      let v = Math.max(0, Math.min(20, parseFloat(mirror.value) || 0));
      r.value = v; recalc();
    });
  });

  recalc();
});
</script>