<?php /* Step I — Personal Information. Part of applicant/application_form.php; uses its variables. */ ?>
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

