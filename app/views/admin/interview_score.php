<?php $maxTotal = array_sum(array_column(INTERVIEW_RUBRIC, 'max')); ?>
<div class="mb-3">
  <a href="<?= url('admin/interview-schedule&id=' . (int)$assignment['schedule_id']) ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Back to the schedule</a>
  <h4 class="fw-bold mb-0 mt-1">Interview score — <?= e($assignment['full_name']) ?></h4>
  <p class="text-muted small mb-0"><code><?= e($assignment['application_code']) ?></code> · <?= e(Interview::describe($assignment)) ?></p>
</div>

<div class="card border-0 shadow-sm" style="max-width: 720px">
  <form method="POST" action="<?= url('admin/interview-score-save') ?>" class="card-body">
    <?= csrf_field() ?>
    <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
    <?php if ($assignment['attendance'] === 'Scheduled'): ?>
      <div class="alert alert-info small">Saving this score marks the applicant as <strong>Attended</strong> and moves the application to “Interviewed — waiting for final result”.</div>
    <?php elseif (!$editable): ?>
      <div class="alert alert-secondary small">A final decision was made, so this score can no longer be changed.</div>
    <?php endif; ?>

    <fieldset <?= $editable ? '' : 'disabled' ?>>
    <table class="table align-middle">
      <thead><tr><th>Criterion</th><th style="width:160px">Score</th><th>Maximum</th></tr></thead>
      <tbody>
        <?php foreach (INTERVIEW_RUBRIC as $field => $c): ?>
          <tr>
            <td><label for="score_<?= e($field) ?>" class="mb-0"><?= e($c['label']) ?></label></td>
            <td><input type="number" id="score_<?= e($field) ?>" name="<?= e($field) ?>" class="form-control score-input" min="0" max="<?= (int)$c['max'] ?>" step="0.5"
                       value="<?= e((string)($evaluation[$field] ?? '')) ?>" required></td>
            <td>0 – <?= (int)$c['max'] ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="table-light"><th>Total (sum of the scores above)</th><th><span id="scoreTotal"><?= e((string)($evaluation['total_score'] ?? '0')) ?></span></th><th><?= $maxTotal ?></th></tr>
      </tbody>
    </table>
    <label class="form-label">Remarks</label>
    <textarea name="remarks" class="form-control mb-3" rows="3"><?= e($evaluation['remarks'] ?? '') ?></textarea>
    <?php if ($editable): ?><button class="btn btn-primary"><i class="bi bi-save"></i> Save score</button><?php endif; ?>
    </fieldset>
    <?php if ($evaluation): ?>
      <p class="small text-muted mt-2 mb-0">Last saved <?= e(date('M d, Y g:i A', strtotime($evaluation['evaluated_at']))) ?> by <?= e($evaluation['interviewer_name'] ?? '—') ?>.</p>
    <?php endif; ?>
  </form>
</div>
