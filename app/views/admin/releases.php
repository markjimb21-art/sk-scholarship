<?php $peso = static fn($v) => '₱' . number_format((float)$v, 2); ?>
<div class="mb-3">
  <h4 class="fw-bold mb-0">Releases</h4>
  <p class="text-muted small mb-0">Release cash for verified claims. Each semester can be released once, only against a budget for its program and academic year, and never above the remaining balance.</p>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between"><h6 class="mb-0">Ready to release</h6><span class="badge bg-primary"><?= count($ready) ?></span></div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light"><tr><th>Scholar</th><th>Semester</th><th>Budget remaining</th><th style="min-width:560px">Release</th></tr></thead>
      <tbody>
        <?php if (!$ready): ?><tr><td colspan="4" class="text-center text-muted py-4">No verified claims waiting.</td></tr><?php endif; ?>
        <?php foreach ($ready as $t): ?>
          <tr>
            <td><div class="fw-semibold"><?= e($t['full_name']) ?></div><code class="small"><?= e($t['scholar_code']) ?></code> · <small><?= e($t['program_name']) ?></small></td>
            <td><?= e($t['academic_year'] . ' ' . $t['semester']) ?><br><small class="text-muted">GWA <?= $t['gwa'] !== null ? e(number_format((float)$t['gwa'], 2)) : '—' ?></small></td>
            <td><?= $t['budget_remaining'] !== null ? e($peso($t['budget_remaining'])) : '<span class="text-danger">No budget for ' . e($t['academic_year']) . '</span>' ?></td>
            <td>
              <form method="POST" action="<?= url('admin/release-save') ?>" class="row g-1 align-items-end"
                    onsubmit="return confirm('Record this release? The scholar will be notified.');">
                <?= csrf_field() ?><input type="hidden" name="term_id" value="<?= (int)$t['id'] ?>">
                <div class="col-3"><label class="form-label small mb-0" for="relAmount<?= (int)$t['id'] ?>">Amount (₱)</label><input type="number" id="relAmount<?= (int)$t['id'] ?>" name="amount" class="form-control form-control-sm" min="0.01" step="0.01" value="<?= e((string)$t['amount_per_semester']) ?>" required title="Amount (₱) — pre-filled with the program's amount per semester"></div>
                <div class="col-3"><label class="form-label small mb-0" for="relDate<?= (int)$t['id'] ?>">Release date</label><input type="date" id="relDate<?= (int)$t['id'] ?>" name="release_date" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-2">
                  <label class="form-label small mb-0" for="relMethod<?= (int)$t['id'] ?>">Method</label><select name="payment_method" id="relMethod<?= (int)$t['id'] ?>" class="form-select form-select-sm"><?php foreach (PAYMENT_METHODS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-2"><label class="form-label small mb-0" for="relRef<?= (int)$t['id'] ?>">Reference no.</label><input name="reference_number" id="relRef<?= (int)$t['id'] ?>" class="form-control form-control-sm" maxlength="100"></div>
                <div class="col-2"><button class="btn btn-sm btn-success w-100" <?= $t['budget_remaining'] === null ? 'disabled title="Create a budget first"' : '' ?>>Release</button></div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">A reference number is required for GCash, Bank and Check.</div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <h5 class="fw-semibold mb-0">Released</h5>
  <form method="GET" action="<?= url('admin/releases') ?>" class="d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label" for="f_program_id">Program</label><select id="f_program_id" name="program_id" class="form-select form-select-sm">
      <option value="">All programs</option>
      <?php foreach ($programs as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $filters['program_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option><?php endforeach; ?>
    </select></div>
    <div><label class="form-label" for="f_academic_year">Academic year</label><select id="f_academic_year" name="academic_year" class="form-select form-select-sm">
      <option value="">All years</option>
      <?php foreach ($years as $y): ?><option value="<?= e($y) ?>" <?= $filters['academic_year'] === $y ? 'selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
    </select></div>
    <button class="btn btn-sm btn-outline-primary">Filter</button>
  </form>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
      <thead class="table-light"><tr><th>Date</th><th>Code</th><th>Scholar</th><th>Semester</th><th>Amount</th><th>Method / reference</th><th>By</th></tr></thead>
      <tbody>
        <?php if (!$history): ?><tr><td colspan="7" class="text-center text-muted py-3">No releases.</td></tr><?php endif; ?>
        <?php foreach ($history as $r): ?>
          <tr>
            <td><?= e(date('M d, Y', strtotime($r['release_date']))) ?></td><td><code><?= e($r['release_code']) ?></code></td>
            <td><?= e($r['full_name']) ?> <small class="text-muted"><?= e($r['program_name']) ?></small></td>
            <td><?= e($r['academic_year'] . ' ' . $r['semester']) ?></td>
            <td><?= e($peso($r['amount'])) ?></td>
            <td><?= e($r['payment_method']) ?><?= $r['reference_number'] ? ' · ' . e($r['reference_number']) : '' ?></td>
            <td><small><?= e($r['released_by'] ?? '—') ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr class="table-light"><th colspan="4">Total shown (sum of the rows above)</th><th><?= e($peso($historyTotal)) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
  </div>
</div>
