<?php /* Step II — Education. Part of applicant/application_form.php; uses its variables. */ ?>
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

