<?php
$app = $app ?? null;
$editable = $editable ?? false;
$documents = $documents ?? [];
$docByType = [];
foreach ($documents as $d) { $docByType[$d['document_type']] = $d; }
$docsUploaded = 0;
foreach (DOCUMENT_TYPES as $dt) { if (!empty($docByType[$dt]['file_path']) && $docByType[$dt]['status'] !== 'Invalid') $docsUploaded++; }
$flags = $flags ?? [];
$flagLabels = Application::flagOptions();
$updatedAt = $app['updated_at'] ?? null;
$docLabels = ['Not Submitted' => 'Missing', 'Submitted' => 'Uploaded', 'Verified' => 'Verified', 'Invalid' => 'Invalid — upload again'];
$program = $app ?: $openProgram;
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">Scholarship Application</h4>
    <?php if ($program): ?>
      <p class="small mb-1"><strong><?= e($program['program_name']) ?></strong> · applications accepted
        <?= e(date('M d, Y', strtotime($program['application_start']))) ?> – <?= e(date('M d, Y', strtotime($program['application_end']))) ?></p>
    <?php endif; ?>
    <?php if ($editable): ?>
      <p class="text-muted small mb-1">Your information is saved automatically as you fill in the form.</p>
    <?php endif; ?>
    <span id="autosaveIndicator" class="autosave-indicator d-none"></span>
  </div>
  <?php if ($app): ?>
    <span class="badge bg-<?= e(APP_STATUS_COLORS[$app['status']] ?? 'secondary') ?> px-3 py-2"><?= e(Application::label($app['status'])) ?></span>
  <?php endif; ?>
</div>

<?php if ($app && $app['status'] === 'Returned'): ?>
  <div class="alert alert-warning">
    <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle"></i> Returned for correction</h6>
    <p class="mb-1">The SK office asked you to fix these items, then submit again:</p>
    <p class="mb-0 fw-semibold"><?= nl2br(e($app['review_remarks'] ?? '')) ?></p>
    <?php if ($flags): ?>
      <p class="mb-0 mt-2"><strong>Only these items can be changed:</strong> <?= e(implode(', ', array_map(static fn($k) => $flagLabels[$k], $flags))) ?>.
        Everything else stays exactly as you submitted it.</p>
    <?php endif; ?>
  </div>
<?php elseif (!$app && !$editable): ?>
  <div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i> No scholarship program is accepting applications right now, so the form is read-only.
  </div>
<?php elseif ($app && !$editable): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i> This application was submitted and is read-only. See your <a href="<?= url('applicant/dashboard') ?>">dashboard</a> for what happens next.
  </div>
<?php endif; ?>

<div id="restoreBanner" class="alert alert-secondary d-none">
  <span>We found recently saved information from this device. Would you like to restore it?</span>
  <div class="mt-2">
    <button type="button" id="restoreLocalBtn" class="btn btn-sm btn-primary">Restore Local Information</button>
    <button type="button" id="useServerBtn" class="btn btn-sm btn-outline-secondary">Use Server Information</button>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">

    <div class="progress-tracker mb-4">
      <?php $steps = ['Personal','Education','Family','Documents','Review'];
      foreach ($steps as $i => $s): ?>
        <div class="progress-step">
          <div class="step-circle"><?= $i + 1 ?></div>
          <div class="step-label"><?= $s ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div data-wizard
         data-editable="<?= $editable ? '1' : '0' ?>"
         data-status="<?= e($app['status'] ?? 'Draft') ?>"
         data-user-id="<?= (int)Auth::user()['id'] ?>"
         data-application-id="<?= (int)($app['id'] ?? 0) ?>"
         data-updated-at="<?= e($updatedAt ?? '') ?>"
         data-autosave-url="<?= url('applicant/application/autosave') ?>"
         data-upload-url="<?= url('applicant/documents/upload') ?>"
         data-csrf="<?= e(csrf_token()) ?>">

      <!-- STEP 1: PERSONAL -->
      <form class="form-step" onsubmit="return false;" data-step="personal">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="personal">
        <h5 class="fw-semibold mb-3">I. Personal Information</h5>

        <fieldset <?= $editable ? '' : 'disabled' ?>>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">First Name *</label>
            <input name="first_name" class="form-control" required data-uppercase value="<?= e($personal['first_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input name="middle_name" class="form-control" data-uppercase value="<?= e($personal['middle_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name *</label>
            <input name="last_name" class="form-control" required data-uppercase value="<?= e($personal['last_name'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Suffix</label>
            <input name="suffix" class="form-control" placeholder="JR., SR., III" data-uppercase value="<?= e($personal['suffix'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date of Birth *</label>
            <input type="date" name="date_of_birth" class="form-control" required
                   data-calc="age" value="<?= e($personal['date_of_birth'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input class="form-control bg-light" readonly data-target="age" tabindex="-1"
                   value="<?= !empty($personal['date_of_birth']) ? (int)calculate_age($personal['date_of_birth']) : '' ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Complete Address *</label>
            <div class="row g-2 align-items-center">
              <div class="col-sm-3">
                <input name="address_unit" class="form-control" placeholder="Unit/House No." aria-label="Unit or house number" data-uppercase
                       value="<?= e($personal['address_unit'] ?? '') ?>">
              </div>
              <div class="col-auto text-muted">/</div>
              <div class="col-sm-4">
                <select name="purok_id" class="form-select" required aria-label="Purok or subdivision">
                  <option value="">— Select Purok/Subdivision —</option>
                  <?php foreach ($puroks as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)($personal['purok_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                      <?= e($p['purok_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-auto text-muted">/</div>
              <div class="col-sm-4">
                <input class="form-control bg-light" readonly aria-label="Barangay and city (fixed)" value="<?= e(ADDRESS_BARANGAY . ' / ' . ADDRESS_CITY) ?>">
              </div>
            </div>
            <div class="form-text">Example: 12 / EAST HOMES 3 / BARANGAY ESTEFANIA / BACOLOD CITY. The barangay and city are fixed — this scholarship is exclusive to Barangay Estefania residents.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Contact Number *</label>
            <input name="contact_number" class="form-control" required maxlength="11" inputmode="numeric"
                   pattern="^0[0-9]{10}$" data-phone
                   value="<?= e($personal['contact_number'] ?? '') ?>">
            <div class="form-text">Enter exactly 11 digits. Example: 09171234567</div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Email Address (from your account)</label>
            <input class="form-control bg-light" readonly value="<?= e(Auth::user()['email']) ?>">
            <div class="form-text">Automatically linked to your registered email.</div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Height (cm)</label>
            <input type="number" step="0.1" min="50" max="250" name="height_cm" class="form-control" inputmode="decimal" value="<?= e((string)($personal['height_cm'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Weight (kg)</label>
            <input type="number" step="0.1" min="20" max="300" name="weight_kg" class="form-control" inputmode="decimal" value="<?= e((string)($personal['weight_kg'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Blood Type</label>
            <select name="blood_type" class="form-select">
              <option value="">—</option>
              <?php foreach (BLOOD_TYPES as $bt): ?>
                <option value="<?= e($bt) ?>" <?= ($personal['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= e($bt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Sex <span class="text-muted small">(optional)</span></label>
            <select name="sex" class="form-select">
              <option value="">Prefer not to say</option>
              <?php foreach (SEX_OPTIONS as $sx): ?>
                <option value="<?= e($sx) ?>" <?= ($personal['sex'] ?? '') === $sx ? 'selected' : '' ?>><?= e($sx) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Religion</label>
            <input name="religion" class="form-control" maxlength="80" data-uppercase value="<?= e($personal['religion'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Citizenship</label>
            <input name="citizenship" class="form-control" maxlength="50" data-uppercase value="<?= e($personal['citizenship'] ?? 'FILIPINO') ?>">
          </div>
        </div>
        </fieldset>

        <div class="d-flex justify-content-end mt-4">
          <button type="button" class="btn btn-primary" data-next>Next: Education <i class="bi bi-arrow-right"></i></button>
        </div>
      </form>

      <!-- STEP 2: EDUCATION -->
      <form class="form-step" onsubmit="return false;" data-step="education">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="education">
        <h5 class="fw-semibold mb-3">II. Education</h5>
        <fieldset <?= $editable ? '' : 'disabled' ?>>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name of School *</label>
            <input name="school_name" class="form-control" required list="schoolList" value="<?= e($education['school_name'] ?? '') ?>">
            <datalist id="schoolList">
              <?php foreach ($schools as $s): ?><option value="<?= e($s['school_name']) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label">Year Level *</label>
            <select name="year_level" class="form-select" required>
              <?php foreach (YEAR_LEVELS as $yl): ?>
                <option <?= ($education['year_level'] ?? '') === $yl ? 'selected' : '' ?>><?= $yl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">If Other, specify</label>
            <input name="year_level_other" class="form-control" value="<?= e($education['year_level_other'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Course *</label>
            <input name="course_name" class="form-control" required list="courseList" autocomplete="off" value="<?= e($education['course_name'] ?? '') ?>">
            <div class="form-text">Start typing your course to see available recommendations.</div>
            <datalist id="courseList">
              <?php foreach ($courses as $c): ?><option value="<?= e($c['course_name']) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label">Major</label>
            <input name="major" class="form-control" placeholder="e.g. English, Mathematics" value="<?= e($education['major'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">General Weighted Average (GWA)</label>
            <input type="number" name="general_average" class="form-control" min="1" max="5" step="0.01"
                   placeholder="e.g. 1.75" value="<?= e(isset($education['general_average']) ? number_format((float)$education['general_average'], 2) : '') ?>">
            <div class="form-text">Optional. Your latest GWA on the 1.00 (highest) – 5.00 scale, as shown on your Form 138 / grades.</div>
          </div>

          <div class="col-12"><hr></div>

          <div class="col-md-6">
            <label class="form-label d-block">Previous Scholarship Availed?</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="previous_scholarship" id="prevYes"
                     <?= !empty($education['previous_scholarship']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="prevYes">Yes</label>
            </div>
            <input name="previous_scholarship_details" class="form-control mt-2"
                   placeholder="Scholarship name / details" value="<?= e($education['previous_scholarship_details'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label d-block">Current Scholarship Availed?</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="current_scholarship" id="currYes"
                     <?= !empty($education['current_scholarship']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="currYes">Yes</label>
            </div>
            <input name="current_scholarship_details" class="form-control mt-2"
                   placeholder="Scholarship name / details" value="<?= e($education['current_scholarship_details'] ?? '') ?>">
          </div>
        </div>
        </fieldset>

        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <button type="button" class="btn btn-primary" data-next>Next: Family <i class="bi bi-arrow-right"></i></button>
        </div>
      </form>

      <!-- STEP 3: FAMILY -->
      <form class="form-step" onsubmit="return false;" data-step="family">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="family">
        <h5 class="fw-semibold mb-3">III. Family Background</h5>

        <fieldset <?= $editable ? '' : 'disabled' ?>>
        <h6 class="text-primary">Father's Information</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label">Father's Name</label>
            <input name="father_name" class="form-control" value="<?= e($family['father_name'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input type="number" name="father_age" class="form-control" value="<?= e((string)($family['father_age'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Occupation</label>
            <input name="father_occupation" class="form-control" value="<?= e($family['father_occupation'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="father_status" class="form-select">
              <?php foreach (['Available','Deceased','Unknown','Not Applicable'] as $st): ?>
                <option <?= ($family['father_status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Address</label>
            <input name="father_address" class="form-control" value="<?= e($family['father_address'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Contact Number</label>
            <input name="father_contact" class="form-control" maxlength="11" inputmode="numeric" pattern="^0[0-9]{10}$" data-phone
                   value="<?= e($family['father_contact'] ?? '') ?>">
            <div class="form-text">11 digits, e.g. 09171234567</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Gross Monthly Income (₱)</label>
            <input type="number" step="0.01" min="0" name="father_income" class="form-control" data-sum
                   value="<?= e((string)($family['father_income'] ?? '')) ?>">
          </div>
        </div>

        <h6 class="text-primary">Mother's Information</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label">Mother's Name</label>
            <input name="mother_name" class="form-control" value="<?= e($family['mother_name'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input type="number" name="mother_age" class="form-control" value="<?= e((string)($family['mother_age'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Occupation</label>
            <input name="mother_occupation" class="form-control" value="<?= e($family['mother_occupation'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="mother_status" class="form-select">
              <?php foreach (['Available','Deceased','Unknown','Not Applicable'] as $st): ?>
                <option <?= ($family['mother_status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Address</label>
            <input name="mother_address" class="form-control" value="<?= e($family['mother_address'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Contact Number</label>
            <input name="mother_contact" class="form-control" maxlength="11" inputmode="numeric" pattern="^0[0-9]{10}$" data-phone
                   value="<?= e($family['mother_contact'] ?? '') ?>">
            <div class="form-text">11 digits, e.g. 09171234567</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Gross Monthly Income (₱)</label>
            <input type="number" step="0.01" min="0" name="mother_income" class="form-control" data-sum
                   value="<?= e((string)($family['mother_income'] ?? '')) ?>">
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Total Family Income (father + mother, auto)</label>
            <input class="form-control bg-light" readonly data-target="total_family_income"
                   value="<?= e(number_format((float)($family['total_family_income'] ?? 0), 2)) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Family Size</label>
            <input type="number" name="family_size" class="form-control" value="<?= e((string)($family['family_size'] ?? '')) ?>">
          </div>
        </div>
        </fieldset>

        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <button type="button" class="btn btn-primary" data-next>Next: Documents <i class="bi bi-arrow-right"></i></button>
        </div>
      </form>

      <!-- STEP 4: DOCUMENTS (fully integrated — no navigation away from the wizard) -->
      <div class="form-step" data-step="documents">
        <h5 class="fw-semibold mb-3">IV. Required Documents</h5>
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-1"></i>
          Drag a file into each box below (or click to choose one). Each file is <strong>saved automatically</strong> as soon as you
          choose it, so a power interruption or an accidental refresh will not lose it; <strong>Next</strong> finishes any file still
          saving. Accepted: <strong>PDF, JPG, PNG</strong> (max 5MB each). All 5 documents are required.
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
                  <form method="POST" action="<?= url('applicant/documents/invalidate') ?>" class="small" onsubmit="return confirm('Remove this file? Use this only if you uploaded the wrong file. The record is kept.');">
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

      <!-- STEP 5: REVIEW & SUBMIT -->
      <div class="form-step" data-step="review">
        <h5 class="fw-semibold mb-3">V. Review & Submit</h5>

        <?php if ($editable): ?>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Once submitted, your application will be reviewed by the SK. Please make sure all information is correct.
        </div>
        <?php endif; ?>

        <?php if ($app && $app['status'] === 'Returned' && !empty($app['review_remarks'])): ?>
        <div class="alert alert-warning">
          <strong>Items to correct:</strong> <?= nl2br(e($app['review_remarks'])) ?>
        </div>
        <?php endif; ?>

        <div class="mb-3">
          <p class="mb-1"><strong>Applicant:</strong> <?= e(Auth::user()['full_name']) ?></p>
          <p class="mb-1"><strong>Email:</strong> <?= e(Auth::user()['email']) ?></p>
          <p class="mb-1"><strong>Application Code:</strong> <?= e($app['application_code'] ?? 'Assigned when you start') ?></p>
        </div>

<?php
        // Review of everything entered in steps I–IV. Rendered from the saved data here; application-review.js refreshes
        // it from the form itself each time this step is opened, so it always matches what is on screen.
        $show = static fn($v): string => ($v === null || trim((string)$v) === '') ? '—' : (string)$v;
        $money = static fn($v): string => ($v === null || $v === '') ? '—' : '₱' . number_format((float)$v, 2);
        $unit = static fn($v, string $u): string => ($v === null || $v === '') ? '—' : rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . " $u";
        $yesNo = static fn($flag, $details): string => !empty($flag) ? 'Yes' . (trim((string)$details) !== '' ? ' — ' . $details : '') : 'No';
        $purokName = '';
        foreach ($puroks as $p) { if ((int)$p['id'] === (int)($personal['purok_id'] ?? 0)) $purokName = $p['purok_name']; }
        $yearLevel = ($education['year_level'] ?? '') === 'Other' && trim((string)($education['year_level_other'] ?? '')) !== ''
            ? $education['year_level_other'] : ($education['year_level'] ?? '');
        $reviewSections = [
          ['I. Personal Information', 0, [
            'Full name'        => ['@full_name', implode(' ', array_filter([$personal['first_name'] ?? '', $personal['middle_name'] ?? '', $personal['last_name'] ?? '', $personal['suffix'] ?? ''], static fn($x) => trim((string)$x) !== ''))],
            'Date of birth'    => ['date_of_birth|date', !empty($personal['date_of_birth']) ? date('M d, Y', strtotime($personal['date_of_birth'])) : ''],
            'Age'              => ['@age', !empty($personal['date_of_birth']) ? (string)(int)calculate_age($personal['date_of_birth']) : ''],
            'Sex'              => ['sex', $personal['sex'] ?? ''],
            'Complete address' => ['@address', implode(' / ', array_filter([$personal['address_unit'] ?? '', $purokName], static fn($x) => trim((string)$x) !== '')) . ($purokName !== '' ? ' / ' . ADDRESS_BARANGAY . ' / ' . ADDRESS_CITY : '')],
            'Contact number'   => ['contact_number', $personal['contact_number'] ?? ''],
            'Email'            => ['', Auth::user()['email']],
            'Height'           => ['height_cm|cm', $unit($personal['height_cm'] ?? null, 'cm')],
            'Weight'           => ['weight_kg|kg', $unit($personal['weight_kg'] ?? null, 'kg')],
            'Blood type'       => ['blood_type', $personal['blood_type'] ?? ''],
            'Religion'         => ['religion', $personal['religion'] ?? ''],
            'Citizenship'      => ['citizenship', $personal['citizenship'] ?? ''],
          ]],
          ['II. Education', 1, [
            'School'               => ['school_name', $education['school_name'] ?? ''],
            'Year level'           => ['@year_level', $yearLevel],
            'Course'               => ['course_name', $education['course_name'] ?? ''],
            'Major'                => ['major', $education['major'] ?? ''],
            'GWA'                  => ['general_average', isset($education['general_average']) ? number_format((float)$education['general_average'], 2) : ''],
            'Previous scholarship' => ['previous_scholarship|yesno', $yesNo($education['previous_scholarship'] ?? 0, $education['previous_scholarship_details'] ?? '')],
            'Current scholarship'  => ['current_scholarship|yesno', $yesNo($education['current_scholarship'] ?? 0, $education['current_scholarship_details'] ?? '')],
          ]],
        ];
        $parentRows = static function (string $who) use ($family, $money): array {
          return [
            'Name'           => ["{$who}_name", $family["{$who}_name"] ?? ''],
            'Age'            => ["{$who}_age", (string)($family["{$who}_age"] ?? '')],
            'Occupation'     => ["{$who}_occupation", $family["{$who}_occupation"] ?? ''],
            'Status'         => ["{$who}_status", $family["{$who}_status"] ?? ''],
            'Address'        => ["{$who}_address", $family["{$who}_address"] ?? ''],
            'Contact number' => ["{$who}_contact", $family["{$who}_contact"] ?? ''],
            'Monthly income' => ["{$who}_income|money", $money($family["{$who}_income"] ?? null)],
          ];
        };
        $reviewTable = static function (array $rows) use ($show): void {
          echo '<table class="table table-sm mb-0 review-table"><tbody>';
          foreach ($rows as $label => [$key, $value]) {
            echo '<tr><th class="text-muted fw-normal w-40">' . e($label) . '</th><td class="fw-semibold"'
               . ($key !== '' ? ' data-review="' . e($key) . '"' : '') . '>' . e($show($value)) . '</td></tr>';
          }
          echo '</tbody></table>';
        };
        $editLink = static function (int $step) use ($editable): string {
          return $editable ? '<button type="button" class="btn btn-link btn-sm p-0" data-goto="' . $step . '"><i class="bi bi-pencil"></i> Edit</button>' : '';
        };
        ?>
        <div class="row g-3 mb-3" id="applicationReview">
          <?php foreach ($reviewSections as [$title, $stepIndex, $rows]): ?>
          <div class="col-lg-6">
            <div class="border rounded p-3 h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold text-primary mb-0"><?= e($title) ?></h6><?= $editLink($stepIndex) ?>
              </div>
              <?php $reviewTable($rows); ?>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="col-12">
            <div class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold text-primary mb-0">III. Family Background</h6><?= $editLink(2) ?>
              </div>
              <div class="row g-3">
                <div class="col-lg-6"><div class="small text-muted mb-1">Father</div><?php $reviewTable($parentRows('father')); ?></div>
                <div class="col-lg-6"><div class="small text-muted mb-1">Mother</div><?php $reviewTable($parentRows('mother')); ?></div>
                <div class="col-lg-6">
                  <?php $reviewTable([
                    'Total family income' => ['@total_income', $money($family['total_family_income'] ?? 0)],
                    'Family size'         => ['family_size', (string)($family['family_size'] ?? '')],
                  ]); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold text-primary mb-0">IV. Required Documents
                  <span class="badge bg-light text-dark border ms-1" data-review-doc-count><?= $docsUploaded ?> of <?= count(DOCUMENT_TYPES) ?> uploaded</span>
                </h6><?= $editLink(3) ?>
              </div>
              <table class="table table-sm mb-0 review-table"><tbody>
                <?php foreach (DOCUMENT_TYPES as $dt):
                  $doc = $docByType[$dt] ?? null;
                  $ok = $doc && $doc['file_path'] && $doc['status'] !== 'Invalid'; ?>
                  <tr data-review-doc="<?= e($dt) ?>">
                    <th class="text-muted fw-normal w-40"><?= e($dt) ?></th>
                    <td>
                      <span class="fw-semibold <?= $ok ? 'text-success' : 'text-danger' ?>" data-review-doc-status>
                        <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                        <?= e($ok ? ($docLabels[$doc['status']] ?? $doc['status']) : 'Missing') ?>
                      </span>
                      <span class="small text-muted ms-1" data-review-doc-file><?= $ok ? e($doc['original_filename']) : '' ?></span>
                      <?php if ($ok): ?><a class="small ms-1" href="<?= e(url('applicant/documents/view&id=' . (int)$doc['id'])) ?>" target="_blank" rel="noopener" data-review-doc-link>View</a><?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody></table>
            </div>
          </div>
        </div>

        <?php if ($editable && $completeness): ?>
        <div class="alert <?= $completeness['complete'] ? 'alert-success' : 'alert-light border' ?> small" id="reviewMissing">
          <div data-review-missing-list class="<?= $completeness['complete'] ? 'd-none' : '' ?>">
            <strong>Still missing:</strong>
            <ul class="mb-1"><?php foreach ($completeness['missing'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
            The same check runs again when you press Submit.
          </div>
          <div data-review-complete class="<?= $completeness['complete'] ? '' : 'd-none' ?>"><i class="bi bi-check-circle-fill"></i> Everything required is filled in and all documents are saved.</div>
        </div>
        <?php endif; ?>

        <?php if ($editable): ?>
        <form method="POST" action="<?= url('applicant/application/submit') ?>">
          <?= csrf_field() ?>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="agree" required>
            <label class="form-check-label" for="agree">
              I certify that the information provided is true and correct, and that I am a college student
              residing in <strong>Barangay Estefania</strong>.
            </label>
          </div>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn btn-success px-4">
              <i class="bi bi-send-check"></i> <?= ($app && $app['status'] === 'Returned') ? 'Resubmit Application' : 'Submit Application' ?>
            </button>
          </div>
        </form>
        <?php else: ?>
        <div class="d-flex justify-content-between">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <span class="badge bg-secondary px-3 py-2 align-self-center"><?= e($app ? Application::label($app['status']) : 'Closed') ?></span>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
<?php if ($flags): ?>
<script>
// Returned with flagged items: highlight them and lock everything else (the server keeps unflagged values regardless).
document.addEventListener('DOMContentLoaded', function () {
  var flags = <?= js_json($flags) ?>, fields = <?= js_json(array_map('array_keys', RETURN_FLAG_FIELDS)) ?>;
  Object.keys(fields).forEach(function (step) {
    var form = document.querySelector('form[data-step="' + step + '"]');
    if (!form) return;
    fields[step].forEach(function (name) {
      form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        if (flags.indexOf(step + '.' + name) !== -1) {
          el.classList.add('is-invalid');
          var note = document.createElement('div'); note.className = 'invalid-feedback d-block'; note.textContent = 'Flagged by the SK office — please correct.';
          (el.closest('.col-12, [class*="col-"]') || el.parentNode).appendChild(note);
        } else {
          el.disabled = true;
          el.title = 'Accepted as submitted';
        }
      });
    });
  });
});
</script>
<?php endif; ?>
