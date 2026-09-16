<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Reports</h4>
    <p class="text-muted small mb-0">Generate PDF or CSV reports · <?= count($reports) ?> available</p>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($reports as $key => $info): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-semibold mb-1"><?= e($info[0]) ?></h6>
        <p class="text-muted small mb-3"><?= e($info[1]) ?></p>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#genModal"
                data-report="<?= e($key) ?>" data-title="<?= e($info[0]) ?>">
          <i class="bi bi-file-earmark-arrow-down"></i> Generate
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Generate modal -->
<div class="modal fade" id="genModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/reports/generate') ?>" class="modal-content" target="_blank">
      <?= csrf_field() ?>
      <input type="hidden" name="report_type" id="genReportType">
      <div class="modal-header"><h5 class="modal-title" id="genTitle">Generate Report</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
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
          <label class="form-label">Program (optional)</label>
          <select name="program_id" class="form-select">
            <option value="">All Programs</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Date From (optional)</label><input type="date" name="date_from" class="form-control"></div>
          <div class="col-6"><label class="form-label">Date To (optional)</label><input type="date" name="date_to" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary"><i class="bi bi-download"></i> Generate</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('genModal');
  modal.addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    modal.querySelector('#genReportType').value = b.dataset.report;
    modal.querySelector('#genTitle').textContent = b.dataset.title;
  });
});
</script>