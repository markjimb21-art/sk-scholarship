<?php
// Releasing of budget → Academic years. Expects $years (AcademicYear::all), $overview (label => AcademicYear::overview),
// $suggested (AcademicYear::suggestNext) and $hasCurrent.
$windowCell = static function (string $purpose, string $label, string $semester, array $program, ?array $w): void {
    if ($w) { ?>
      <a href="<?= url("admin/$purpose-schedule&edit=" . (int)$w['schedule']['id']) ?>" class="text-decoration-none">
        <span class="badge bg-<?= e(TermSchedule::STATE_COLORS[$w['state']]) ?>"><?= e(TermSchedule::STATE_LABELS[$w['state']]) ?></span></a>
    <?php } else { ?>
      <a class="btn btn-sm btn-outline-secondary py-0" href="<?= e(url("admin/$purpose-schedule&prefill_program=" . (int)$program['id']
          . '&prefill_year=' . rawurlencode($label) . '&prefill_semester=' . rawurlencode($semester))) ?>">Create</a>
    <?php }
};
?>
<div class="mb-3">
  <h4 class="fw-bold mb-0">Academic years</h4>
  <p class="text-muted small mb-0">Budgets, claim and renewal windows are set per academic year. New scholars start in the
    1st Semester of the <strong>current</strong> one. Programs have no academic year: scholars graduate and new applicants
    come in, year after year.</p>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h6 class="fw-semibold">New academic year</h6>
    <form method="POST" action="<?= url('admin/academic-years-save') ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="ayLabel">Academic year</label>
        <input name="label" id="ayLabel" class="form-control" value="<?= e($suggested) ?>" required pattern="\d{4}-\d{4}" placeholder="2027-2028">
      </div>
      <div class="col-sm-8 col-lg-9">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="make_current" value="1" id="ayCurrent" <?= $hasCurrent ? '' : 'checked' ?>>
          <label class="form-check-label" for="ayCurrent">Make this the current academic year<?= $hasCurrent ? '' : ' (there is none yet, so it will be)' ?></label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="with_budgets" value="1" id="ayBudgets" checked>
          <label class="form-check-label" for="ayBudgets">Also create a budget at the suggested amount for every program with Active scholars
            <span class="text-muted">(Active scholars × amount per semester × <?= SEMESTERS_PER_YEAR ?>; programs that already have one are skipped)</span></label>
        </div>
      </div>
      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create academic year</button>
        <span class="small text-muted ms-2">Claim and renewal windows are not created automatically: they need dates, and creating one e-mails the scholars.</span></div>
    </form>
  </div>
</div>

<?php if (!$years): ?>
  <div class="alert alert-warning">There is no academic year yet. Create one: until there is a current academic year, nobody can be approved as a scholar.</div>
<?php endif; ?>

<?php foreach ($years as $y): $blocker = AcademicYear::deleteBlocker($y); ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="mb-0"><?= e($y['label']) ?>
        <?= (int)$y['is_current'] ? '<span class="badge bg-success ms-1">Current</span>' : '' ?></h5>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if (!(int)$y['is_current']): ?>
          <form method="POST" action="<?= url('admin/academic-years-set-current') ?>" data-confirm="<?= e("Make {$y['label']} the current academic year? New scholars will start in its 1st Semester.") ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$y['id'] ?>">
            <button class="btn btn-sm btn-outline-success">Make current</button>
          </form>
        <?php endif; ?>
        <?php if ($blocker === null): ?>
          <form method="POST" action="<?= url('admin/academic-years-delete') ?>" data-confirm="<?= e("Delete academic year {$y['label']}? Nothing uses it.") ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$y['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" aria-label="Delete academic year <?= e($y['label']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
          </form>
        <?php else: ?>
          <span class="small text-muted">Can't be deleted: <?= e($blocker) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle small">
        <thead class="table-light"><tr>
          <th>Program</th><th>Scholars</th><th>Budget</th>
          <th>Claim · 1st Sem</th><th>Claim · 2nd Sem</th><th>Renewal into 1st Sem</th><th>Renewal into 2nd Sem</th>
        </tr></thead>
        <tbody>
          <?php if (!$overview[$y['label']]): ?><tr><td colspan="7" class="text-center text-muted py-3">No programs yet.</td></tr><?php endif; ?>
          <?php foreach ($overview[$y['label']] as $row): $p = $row['program']; $b = $row['budget']; ?>
            <tr>
              <td class="fw-semibold"><?= e($p['label']) ?></td>
              <td><?= (int)$row['scholars'] ?></td>
              <td>
                <?php if ($b): ?>
                  <?= e(peso($b['total_budget'])) ?> <span class="text-muted">· <?= e(peso($b['remaining'])) ?> left</span>
                <?php else: ?>
                  <span class="text-muted">None</span>
                  <a class="btn btn-sm btn-outline-secondary py-0 ms-1" href="<?= e(url('admin/budget&new=1&program_id=' . (int)$p['id'] . '&academic_year=' . rawurlencode($y['label']))) ?>">Add budget</a>
                <?php endif; ?>
              </td>
              <?php foreach (['release', 'renewal'] as $purpose): foreach (SEMESTERS as $sem): ?>
                <td><?php $windowCell($purpose, $y['label'], $sem, $p, $row['windows'][$purpose][$sem]); ?></td>
              <?php endforeach; endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>
