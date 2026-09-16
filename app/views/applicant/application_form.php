<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">Scholarship Application</h4>
    <p class="text-muted small mb-0">Complete all steps, then submit. Your progress is saved at each step.</p>
  </div>
  <?php if ($app): ?>
    <span class="badge bg-secondary px-3 py-2"><?= e($app['status']) ?></span>
  <?php endif; ?>
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

    <div data-wizard>

      <!-- STEP 1: PERSONAL -->
      <form method="POST" action="<?= url('applicant/application/save') ?>" class="form-step">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="personal">
        <h5 class="fw-semibold mb-3">I. Personal Information</h5>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">First Name *</label>
            <input name="first_name" class="form-control" required value="<?= e($personal['first_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input name="middle_name" class="form-control" value="<?= e($personal['middle_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name *</label>
            <input name="last_name" class="form-control" required value="<?= e($personal['last_name'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Suffix</label>
            <input name="suffix" class="form-control" placeholder="Jr., Sr., III" value="<?= e($personal['suffix'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date of Birth *</label>
            <input type="date" name="date_of_birth" class="form-control" required
                   data-calc="age" value="<?= e($personal['date_of_birth'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input name="age" class="form-control bg-light" readonly data-target="age"
                   value="<?= e((string)($personal['age'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Civil Status *</label>
            <select name="civil_status" class="form-select" required>
              <?php foreach (['Single','Married','Widowed','Separated','Annulled'] as $cs): ?>
                <option <?= ($personal['civil_status'] ?? '') === $cs ? 'selected' : '' ?>><?= $cs ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-8">
            <label class="form-label">Complete Address *</label>
            <input name="complete_address" class="form-control" required value="<?= e($personal['complete_address'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Purok (within Brgy. Estefania)</label>
            <select name="purok_id" class="form-select">
              <option value="">— Select —</option>
              <?php foreach ($puroks as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)($personal['purok_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                  <?= e($p['purok_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Contact Number *</label>
            <input name="contact_number" class="form-control" required pattern="^[0-9+\-\s()]{7,20}$"
                   value="<?= e($personal['contact_number'] ?? '') ?>">
          </div>
          <div class="col-md-8">
            <label class="form-label">Email Address (from your account)</label>
            <input class="form-control bg-light" readonly value="<?= e(Auth::user()['email']) ?>">
            <div class="form-text">Automatically linked to your registered email.</div>
          </div>

          <div class="col-md-2">
            <label class="form-label">Height (cm)</label>
            <input type="number" step="0.1" name="height_cm" class="form-control" value="<?= e((string)($personal['height_cm'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Weight (kg)</label>
            <input type="number" step="0.1" name="weight_kg" class="form-control" value="<?= e((string)($personal['weight_kg'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Blood Type</label>
            <input name="blood_type" class="form-control" value="<?= e($personal['blood_type'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Religion</label>
            <input name="religion" class="form-control" value="<?= e($personal['religion'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Citizenship</label>
            <input name="citizenship" class="form-control" value="<?= e($personal['citizenship'] ?? 'Filipino') ?>">
          </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
          <button type="submit" class="btn btn-outline-secondary me-2">Save Draft</button>
          <button type="button" class="btn btn-primary" data-next>Next: Education <i class="bi bi-arrow-right"></i></button>
        </div>
      </form>

      <!-- STEP 2: EDUCATION -->
      <form method="POST" action="<?= url('applicant/application/save') ?>" class="form-step">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="education">
        <h5 class="fw-semibold mb-3">II. Education</h5>
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
            <input name="course_name" class="form-control" required value="<?= e($education['course_name'] ?? '') ?>">
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

        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <div>
            <button type="submit" class="btn btn-outline-secondary me-2">Save Draft</button>
            <button type="button" class="btn btn-primary" data-next>Next: Family <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>
      </form>

      <!-- STEP 3: FAMILY -->
      <form method="POST" action="<?= url('applicant/application/save') ?>" class="form-step">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="family">
        <h5 class="fw-semibold mb-3">III. Family Background</h5>

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
            <input name="father_contact" class="form-control" value="<?= e($family['father_contact'] ?? '') ?>">
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
            <input name="mother_contact" class="form-control" value="<?= e($family['mother_contact'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Gross Monthly Income (₱)</label>
            <input type="number" step="0.01" min="0" name="mother_income" class="form-control" data-sum
                   value="<?= e((string)($family['mother_income'] ?? '')) ?>">
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Combined Parental Income (auto)</label>
            <input class="form-control bg-light" readonly data-target="combined_income"
                   value="<?= e(number_format((float)($family['combined_income'] ?? 0), 2)) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Total Family Income (auto)</label>
            <input class="form-control bg-light" readonly data-target="total_family_income"
                   value="<?= e(number_format((float)($family['total_family_income'] ?? 0), 2)) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Family Size</label>
            <input type="number" name="family_size" class="form-control" value="<?= e((string)($family['family_size'] ?? '')) ?>">
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <div>
            <button type="submit" class="btn btn-outline-secondary me-2">Save Draft</button>
            <button type="button" class="btn btn-primary" data-next>Next: Documents <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>
      </form>

      <!-- STEP 4: DOCUMENTS -->
      <div class="form-step">
        <h5 class="fw-semibold mb-3">IV. Required Documents</h5>
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-1"></i>
          Upload each document. Accepted: <strong>PDF, JPG, PNG</strong> (max 5MB each).
        </div>
        <ul class="list-group mb-3">
          <?php foreach (DOCUMENT_TYPES as $dt): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e($dt) ?></span>
              <a href="<?= url('applicant/documents') ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-cloud-upload"></i> Upload
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" data-prev><i class="bi bi-arrow-left"></i> Back</button>
          <div>
            <a href="<?= url('applicant/documents') ?>" class="btn btn-outline-primary me-2">
              <i class="bi bi-folder2-open"></i> Open Documents Page
            </a>
            <button type="button" class="btn btn-primary" data-next>Next: Review <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>
      </div>

      <!-- STEP 5: REVIEW & SUBMIT -->
      <div class="form-step">
        <h5 class="fw-semibold mb-3">V. Review & Submit</h5>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Once submitted, your application will be reviewed by the SK. Please make sure all information is correct.
        </div>
        <div class="mb-3">
          <p class="mb-1"><strong>Applicant:</strong> <?= e(Auth::user()['full_name']) ?></p>
          <p class="mb-1"><strong>Email:</strong> <?= e(Auth::user()['email']) ?></p>
          <p class="mb-1"><strong>Application Code:</strong> <?= e($applicant['application_code']) ?></p>
        </div>
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
            <button class="btn btn-success px-4"><i class="bi bi-send-check"></i> Submit Application</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>