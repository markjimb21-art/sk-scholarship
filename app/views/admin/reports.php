<div class="mb-4">
  <h4 class="fw-bold mb-0">Reports</h4>
  <p class="text-muted small mb-0">PDF or CSV. Every report can be filtered by program and date range; the report header says when a filter doesn’t apply.</p>
</div>

<?php
// Which date the From/To filter applies to, per report (same columns as Report::CATALOG).
$dateLabels = ['applicants' => 'submission date', 'interviews' => 'interview date', 'ranking' => 'scoring date',
               'scholars' => 'approval date', 'releases' => 'release date', 'budget' => 'release date',
               'claims' => 'date the claim/renewal was created', 'summary' => 'submission and release dates'];
?>
<div class="row g-3">
  <?php $n = 0; foreach ($reports as $key => [$title, $desc]): $n++; ?>
  <div class="col-md-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h6 class="fw-semibold mb-1"><?= $n ?>. <?= e($title) ?></h6>
        <p class="text-muted small mb-2 flex-grow-1"><?= e($desc) ?></p>
        <p class="small text-muted mb-2">Date filter: <?= e($dateLabels[$key]) ?></p>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#genModal"
                data-report="<?= e($key) ?>" data-title="<?= e($title) ?>"><i class="bi bi-file-earmark-arrow-down"></i> Generate</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal fade" id="genModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/reports/generate') ?>" class="modal-content" target="_blank">
      <?= csrf_field() ?>
      <input type="hidden" name="report_type" id="genReportType">
      <div class="modal-header"><h5 class="modal-title" id="genTitle">Generate report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Format</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="format" id="fmt-pdf" value="pdf" checked>
            <label class="btn btn-outline-primary" for="fmt-pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</label>
            <input type="radio" class="btn-check" name="format" id="fmt-csv" value="csv">
            <label class="btn btn-outline-primary" for="fmt-csv"><i class="bi bi-filetype-csv"></i> CSV / Excel</label>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Program <span id="programRequired" class="text-danger d-none">*</span></label>
          <select name="program_id" id="genProgram" class="form-select">
            <option value="">All programs</option>
            <?php foreach ($programs as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 d-none" id="statusRow">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (APP_STATUS as $s): if ($s === 'Draft') continue; ?><option value="<?= e($s) ?>"><?= e(APP_STATUS_LABELS[$s]) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control"></div>
          <div class="col-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-download"></i> Generate</button></div>
    </form>
  </div>
</div>
<script>
document.getElementById('genModal').addEventListener('show.bs.modal', function (e) {
  var type = e.relatedTarget.dataset.report;
  document.getElementById('genReportType').value = type;
  document.getElementById('genTitle').textContent = e.relatedTarget.dataset.title;
  document.getElementById('statusRow').classList.toggle('d-none', type !== 'applicants');
  var needsProgram = type === 'summary';
  document.getElementById('programRequired').classList.toggle('d-none', !needsProgram);
  document.getElementById('genProgram').required = needsProgram;
});
</script>
