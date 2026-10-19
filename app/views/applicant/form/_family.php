<?php /* Step III — Family Background. Part of applicant/application_form.php; uses its variables. */ ?>
      <!-- STEP 3: FAMILY -->
      <form class="form-step" data-no-submit data-step="family">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="family">
        <h5 class="fw-semibold mb-3">III. Family Background</h5>

        <fieldset <?= $editable ? '' : 'disabled' ?>>
        <h6 class="text-primary">Father's Information</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label">Father's Name</label>
            <input name="father_name" class="form-control" data-uppercase value="<?= e($family['father_name'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input type="number" name="father_age" class="form-control" value="<?= e((string)($family['father_age'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Occupation</label>
            <input name="father_occupation" class="form-control" data-uppercase value="<?= e($family['father_occupation'] ?? '') ?>">
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
            <input name="father_address" class="form-control" data-uppercase value="<?= e($family['father_address'] ?? '') ?>">
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
            <input name="mother_name" class="form-control" data-uppercase value="<?= e($family['mother_name'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Age</label>
            <input type="number" name="mother_age" class="form-control" value="<?= e((string)($family['mother_age'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Occupation</label>
            <input name="mother_occupation" class="form-control" data-uppercase value="<?= e($family['mother_occupation'] ?? '') ?>">
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
            <input name="mother_address" class="form-control" data-uppercase value="<?= e($family['mother_address'] ?? '') ?>">
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

