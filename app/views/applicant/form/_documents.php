<?php /* Step IV — Required Documents (each file saves itself). Part of applicant/application_form.php; uses its variables. */ ?>
      <!-- STEP 4: DOCUMENTS (fully integrated — no navigation away from the wizard) -->
      <div class="form-step" data-step="documents">
        <h5 class="fw-semibold mb-3">IV. Required Documents</h5>
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-1"></i>
          Drag a file into each box below (or click to choose one). Each file is <strong>saved automatically</strong> as soon as you
          choose it, so a power interruption or an accidental refresh will not lose it; <strong>Next</strong> finishes any file still
          saving. Accepted: <strong>PDF, JPG, PNG</strong> (max <?= e(MAX_FILE_SIZE_TEXT) ?> each). All 5 documents are required.
        </div>
        <div class="row g-3 mb-3" id="wizardDocuments">
          <?php foreach (DOCUMENT_TYPES as $dt):
            $doc = $docByType[$dt] ?? null;
            $status = $doc['status'] ?? 'Not Submitted';
            $hasFile = $doc && $doc['file_path'] && $status !== 'Invalid';
            $docEditable = !$flags || in_array("documents.$dt", $flags, true);
            $docFlagged = $flags && $docEditable;
            $isImage = $hasFile && in_array(($doc['mime_type'] ?? ''), ['image/jpeg', 'image/png'], true);
          ?>
          <div class="col-md-6" data-doc-card data-doc-type="<?= e($dt) ?>" data-doc-has-file="<?= $hasFile ? '1' : '0' ?>">
            <div class="card shadow-sm h-100 <?= $docFlagged ? 'border border-2 border-danger' : 'border-0' ?>">
              <div class="card-body">
                <?php if ($docFlagged): ?><div class="small text-danger fw-semibold mb-1"><i class="bi bi-flag-fill"></i> Flagged by the SK office — upload a new file</div><?php endif; ?>
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="fw-semibold mb-0"><i class="bi bi-file-earmark-text me-1 text-primary"></i><?= e($dt) ?> <span class="text-danger">*</span></h6>
                  <span class="doc-pill doc-<?= e(str_replace(' ','-',$status)) ?>" data-doc-status-pill><?= e($docLabels[$status] ?? $status) ?></span>
                </div>

                <?php if ($editable && !$docEditable): ?>
                  <div class="small text-success"><i class="bi bi-lock-fill"></i> Accepted as submitted — no change needed.</div>
                <?php endif; ?>
                <?php if ($doc && $status === 'Invalid'): ?>
                  <div class="alert alert-danger py-2 small">Removed: <?= e((string)$doc['invalid_reason']) ?></div>
                <?php endif; ?>
                <?php if ($editable && $docEditable): ?>
                <div class="doc-dropzone" data-doc-dropzone tabindex="0" role="button">
                  <div data-doc-preview class="doc-preview" style="<?= $hasFile ? '' : 'display:none' ?>">
                    <?php if ($isImage): ?>
                      <img src="<?= e(url('applicant/documents/view&id=' . (int)($doc['id'] ?? 0))) ?>" alt="Preview" data-doc-preview-img>
                    <?php else: ?>
                      <i class="bi bi-file-earmark-pdf display-6 text-danger" data-doc-preview-icon style="<?= $hasFile ? '' : 'display:none' ?>"></i>
                      <img data-doc-preview-img class="d-none" alt="Preview">
                    <?php endif; ?>
                  </div>
                  <div data-doc-dropzone-empty class="doc-dropzone-empty" style="<?= $hasFile ? 'display:none' : '' ?>">
                    <i class="bi bi-cloud-arrow-up fs-3 text-muted"></i>
                    <div class="small">Drop your file here or <span class="text-primary text-decoration-underline">Choose File</span></div>
                  </div>
                  <input type="file" class="d-none" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-doc-file>
                </div>
                <?php endif; ?>

                <p class="small text-muted mb-1 mt-2" data-doc-meta style="<?= $hasFile ? '' : 'display:none' ?>">
                  <i class="bi bi-paperclip"></i> <span data-doc-filename><?= e($doc['original_filename'] ?? '') ?></span>
                  <span data-doc-filesize><?= $hasFile && !empty($doc['file_size']) ? ' · ' . number_format((int)$doc['file_size'] / 1024 / 1024, 2) . ' MB' : '' ?></span><br>
                  <?php if ($hasFile): ?>
                    <a href="<?= e(url('applicant/documents/view&id=' . (int)$doc['id'])) ?>" target="_blank" rel="noopener" data-doc-view-link>View</a>
                  <?php endif; ?>
                </p>
                <?php if ($editable && $docEditable && $hasFile): ?>
                  <form method="POST" action="<?= url('applicant/documents/invalidate') ?>" class="small" data-confirm="Remove this file? Use this only if you uploaded the wrong file. The record is kept.">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="reason" value="Wrong file uploaded">
                    <button class="btn btn-link btn-sm text-danger p-0">This is the wrong file — remove it</button>
                  </form>
                <?php endif; ?>
                <div class="alert alert-danger py-2 small mb-0" data-doc-remarks style="<?= (!empty($doc['remarks'])) ? '' : 'display:none' ?>">
                  <strong>Remarks:</strong> <span data-doc-remarks-text><?= e($doc['remarks'] ?? '') ?></span>
                </div>
                <div class="small text-danger mt-1 d-none" data-doc-error></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="alert alert-warning d-none" id="docsIncompleteWarning">
          Please add all required documents before continuing (the ones not saved yet are outlined in red).
        </div>

        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <button type="button" class="btn btn-primary" data-next data-requires-documents>Next: Review <i class="bi bi-arrow-right"></i></button>
        </div>
      </div>

