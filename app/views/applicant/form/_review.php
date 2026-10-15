<?php /* Step V — Review & Submit (every field of steps I–IV, refreshed live by application/review.js). Part of applicant/application_form.php; uses its variables. */ ?>
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
        // Review of everything entered in steps I–IV. Rendered from the saved data here; application/review.js refreshes
        // it from the form itself each time this step is opened, so it always matches what is on screen.
        $show = static fn($v): string => ($v === null || trim((string)$v) === '') ? '—' : (string)$v;
        $unit = static fn($v, string $u): string => ($v === null || $v === '') ? '—' : rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . " $u";
        $yesNo = static fn($flag, $details): string => !empty($flag) ? 'Yes' . (trim((string)$details) !== '' ? ' — ' . $details : '') : 'No';
        $purokName = '';
        foreach ($puroks as $p) { if ((int)$p['id'] === (int)($personal['purok_id'] ?? 0)) $purokName = $p['purok_name']; }
        $yearLevel = ($education['year_level'] ?? '') === 'Other' && trim((string)($education['year_level_other'] ?? '')) !== ''
            ? $education['year_level_other'] : ($education['year_level'] ?? '');
        $reviewSections = [
          ['I. Personal Information', 0, [
            'Full name'        => ['@full_name', implode(' ', array_filter([$personal['first_name'] ?? '', $personal['middle_name'] ?? '', $personal['last_name'] ?? '', $personal['suffix'] ?? ''], static fn($x) => trim((string)$x) !== ''))],
            'Date of birth'    => ['date_of_birth|date', !empty($personal['date_of_birth']) ? fmt_date($personal['date_of_birth']) : ''],
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
        $parentRows = static function (string $who) use ($family): array {
          return [
            'Name'           => ["{$who}_name", $family["{$who}_name"] ?? ''],
            'Age'            => ["{$who}_age", (string)($family["{$who}_age"] ?? '')],
            'Occupation'     => ["{$who}_occupation", $family["{$who}_occupation"] ?? ''],
            'Status'         => ["{$who}_status", $family["{$who}_status"] ?? ''],
            'Address'        => ["{$who}_address", $family["{$who}_address"] ?? ''],
            'Contact number' => ["{$who}_contact", $family["{$who}_contact"] ?? ''],
            'Monthly income' => ["{$who}_income|money", peso($family["{$who}_income"] ?? null, '—')],
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
                    'Total family income' => ['@total_income', peso($family['total_family_income'] ?? 0, '—')],
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
                  $set = $docSets[$dt] ?? [];
                  $ok = Document::covers($set, Document::sidesFor($dt));
                  $files = array_filter($set, static fn($d) => $d['file_path'] !== '' && $d['status'] !== 'Invalid');
                  $first = reset($files); ?>
                  <tr data-review-doc="<?= e($dt) ?>">
                    <th class="text-muted fw-normal w-40"><?= e($dt) ?></th>
                    <td>
                      <span class="fw-semibold <?= $ok ? 'text-success' : 'text-danger' ?>" data-review-doc-status>
                        <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                        <?= e($ok ? ($docLabels[$first['status']] ?? $first['status']) : ($files ? 'Add the other side' : 'Missing')) ?>
                      </span>
                      <span data-review-doc-files>
                        <?php foreach ($files as $k => $f): ?>
                          <span class="small text-muted ms-1"><?= $k !== '' ? e($k) . ': ' : '' ?><?= e($f['original_filename']) ?></span>
                          <a class="small ms-1" href="<?= e(url('applicant/documents/view&id=' . (int)$f['id'])) ?>" target="_blank" rel="noopener">View</a>
                        <?php endforeach; ?>
                      </span>
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

