<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/Program.php';

final class ApplicantController
{
    public function handle(string $action): void
    {
        $action = trim($action, '/');
        switch ($action) {
            case 'dashboard':           $this->dashboard(); break;
            case 'application':         $this->application(); break;
            case 'application/save':    $this->saveApplication(); break;
            case 'application/autosave':$this->autosaveApplication(); break;
            case 'application/submit':  $this->submitApplication(); break;
            case 'documents':           $this->documents(); break;
            case 'documents/upload':    $this->uploadDocument(); break;
            case 'documents/view':      $this->viewDocument(); break;
            case 'interview':           $this->interview(); break;
            case 'scholarship':         $this->scholarship(); break;
            case 'notifications':       $this->notifications(); break;
            case 'profile':             $this->profile(); break;
            default:
                http_response_code(404);
                require VIEW_PATH . '/errors/404.php';
        }
    }

    private function applicantRecord(): array
    {
        $userId = (int)Auth::user()['id'];
        $stmt = Database::conn()->prepare("SELECT * FROM applicants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $rec = $stmt->fetch();
        if (!$rec) {
            // auto-heal
            $code = generate_code('APP');
            Database::conn()->prepare("INSERT INTO applicants (user_id, application_code) VALUES (?,?)")
                ->execute([$userId, $code]);
            return ['id' => (int)Database::conn()->lastInsertId(), 'application_code' => $code, 'user_id' => $userId];
        }
        return $rec;
    }

    /**
     * The applicant's CURRENT application:
     *  - while a program is open (Program::open(): active + inside its application window), the application for
     *    THAT program — or null, in which case a new Draft is created for it on the first save (ensureApplication);
     *  - between cycles (no open program), the most recent application, so status/interview pages keep working.
     * Applications for other programs are read-only history (pastApplications()).
     */
    private function activeApplication(int $applicantId): ?array
    {
        $sql = "SELECT a.*, sp.program_name, sp.application_start, sp.application_end
                FROM applications a
                JOIN scholarship_programs sp ON sp.id = a.program_id
                WHERE a.applicant_id = ?";
        $params = [$applicantId];
        $open = Program::open();
        if ($open) {
            $sql .= " AND a.program_id = ?";
            $params[] = (int)$open['id'];
        }
        $stmt = Database::conn()->prepare($sql . " ORDER BY a.id DESC LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Every application except the current one, newest first (read-only "Past Applications"). */
    private function pastApplications(int $applicantId, ?array $current): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.submitted_at, a.decided_at, a.decision_remarks,
                    sp.program_name, sp.academic_year, s.scholar_code, s.status AS scholar_status
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             LEFT JOIN scholars s ON s.application_id = a.id
             WHERE a.applicant_id = ? AND a.id <> ?
             ORDER BY a.id DESC"
        );
        $stmt->execute([$applicantId, (int)($current['id'] ?? 0)]);
        return $stmt->fetchAll();
    }

    /** Applicants may edit/submit only while the application is a Draft or was sent back as Incomplete. */
    private function isEditable(?array $app): bool
    {
        return $app === null || in_array($app['status'], ['Draft', 'Incomplete'], true);
    }

    /** Honors the admin "application_open" system setting (defaults to open when unset). */
    private function applicationsOpen(): bool
    {
        $v = Database::conn()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'application_open'")->fetchColumn();
        return $v === false || $v === null || $v === '' || (string)$v !== '0';
    }

    private function dashboard(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);

        $docCount = 0; $docVerified = 0;
        if ($app) {
            $stmt = Database::conn()->prepare(
                "SELECT COUNT(*) total, SUM(status='Verified') ver FROM documents WHERE application_id = ?"
            );
            $stmt->execute([$app['id']]);
            $row = $stmt->fetch() ?: ['total' => 0, 'ver' => 0];
            $docCount = (int)$row['total'];
            $docVerified = (int)$row['ver'];
        }

        $interview = null;
        if ($app) {
            $stmt = Database::conn()->prepare(
                "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer
                 FROM interview_assignments ia
                 JOIN interview_schedules s ON s.id = ia.schedule_id
                 WHERE ia.application_id = ? LIMIT 1"
            );
            $stmt->execute([$app['id']]);
            $interview = $stmt->fetch() ?: null;
        }

        $pastApplications = $this->pastApplications((int)$applicant['id'], $app);
        $openProgram = Program::open();

        $pageTitle = 'Applicant Dashboard';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/dashboard.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function application(): void
    {
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();

        $stmt = $pdo->prepare("SELECT * FROM applicants_personal_information WHERE applicant_id = ?");
        $stmt->execute([$applicant['id']]);
        $personal = $stmt->fetch() ?: [];
        $personal['address_unit'] = $this->splitAddressUnit($personal['complete_address'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM education_records WHERE applicant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicant['id']]);
        $education = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare("SELECT * FROM family_background WHERE applicant_id = ?");
        $stmt->execute([$applicant['id']]);
        $family = $stmt->fetch() ?: [];

        $app = $this->activeApplication((int)$applicant['id']);
        $documents = $app ? Document::byApplication((int)$app['id']) : [];
        $editable = $this->isEditable($app);

        $puroks = $pdo->query("SELECT id, purok_name FROM puroks ORDER BY purok_name")->fetchAll();
        $schools = $pdo->query("SELECT id, school_name FROM schools ORDER BY school_name")->fetchAll();
        $courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll();

        $pageTitle = 'My Scholarship Application';
        $pageScripts = [asset('js/application-autosave.js')];
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/application_form.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /**
     * Ensures a (Draft) application row + empty document rows exist, so documents can be uploaded. Returns the
     * application id, or null if no program is open. A new Draft is only ever created for the OPEN program
     * (active and today inside application_start..application_end), so drafts can't be started outside the window.
     */
    private function ensureApplication(array $applicant): ?int
    {
        $pdo = Database::conn();
        $existing = $this->activeApplication((int)$applicant['id']);
        if ($existing) return (int)$existing['id'];

        $program = Program::open();
        if (!$program) return null;

        $appCode = generate_code('APP');
        $pdo->prepare("INSERT INTO applications (application_code, applicant_id, program_id, status)
                    VALUES (?, ?, ?, 'Draft')")
            ->execute([$appCode, $applicant['id'], $program['id']]);
        $appId = (int)$pdo->lastInsertId();

        foreach (DOCUMENT_TYPES as $type) {
            $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, status)
                        VALUES (?, ?, '', '', 'Not Submitted')")
                ->execute([$appId, $type]);
        }
        return $appId;
    }

    /**
     * Composes the stored Complete Address as "<unit> / <purok/subdivision> / BARANGAY ESTEFANIA / BACOLOD CITY".
     * The purok name is looked up server-side from the `puroks` table by id (never taken as free text from the
     * client), and the barangay/city suffix is ALWAYS this hardcoded constant pair — so a manually crafted
     * request can neither spoof the purok name nor register an address outside the scholarship's service area.
     */
    private function composeAddress(string $unit, string $purokName): string
    {
        $unit = up($unit);
        $parts = array_values(array_filter([$unit, $purokName], fn($v) => $v !== ''));
        $parts[] = ADDRESS_BARANGAY;
        $parts[] = ADDRESS_CITY;
        return implode(' / ', $parts);
    }

    private function purokName(?int $purokId): string
    {
        if (!$purokId) return '';
        $stmt = Database::conn()->prepare("SELECT purok_name FROM puroks WHERE id = ?");
        $stmt->execute([$purokId]);
        return up((string)($stmt->fetchColumn() ?: ''));
    }

    /** Best-effort extraction of the unit/house-number portion, for pre-filling the edit form. A legacy/free-form address (saved before this format existed) yields an empty unit rather than guessing. */
    private function splitAddressUnit(string $full): string
    {
        $suffix = ' / ' . ADDRESS_BARANGAY . ' / ' . ADDRESS_CITY;
        if (!str_ends_with(strtoupper($full), strtoupper($suffix))) return '';
        $head = substr($full, 0, strlen($full) - strlen($suffix));
        $parts = array_map('trim', explode(' / ', $head, 2));
        return $parts[0] ?? '';
    }

    private function savePersonalData(int $applicantId, array $post): void
    {
        // date_of_birth is NOT NULL in the schema; autosave fires long before the applicant reaches
        // that field, so skip the DB write entirely until it's provided rather than throwing a SQL
        // error on every keystroke. The browser's local autosave copy still protects the typed data.
        if (trim((string)($post['date_of_birth'] ?? '')) === '') return;

        $age = calculate_age($post['date_of_birth'] ?? '');
        $purokId = ($post['purok_id'] ?? '') !== '' ? (int)$post['purok_id'] : null;
        $data = [
            'applicant_id' => $applicantId,
            'first_name' => up($post['first_name'] ?? ''),
            'middle_name' => up($post['middle_name'] ?? ''),
            'last_name' => up($post['last_name'] ?? ''),
            'suffix' => up($post['suffix'] ?? ''),
            'date_of_birth' => $post['date_of_birth'],
            'age' => $age,
            'civil_status' => $post['civil_status'] ?? 'Single', // controlled vocabulary (fixed <select> options) — not free text, left as-is
            'purok_id' => $purokId,
            'complete_address' => $this->composeAddress($post['address_unit'] ?? '', $this->purokName($purokId)),
            'contact_number' => digits_only($post['contact_number'] ?? ''),
            'email' => Auth::user()['email'], // enforce registered email — never uppercased, kept as normal email format
            'height_cm' => ($post['height_cm'] ?? '') ?: null,
            'weight_kg' => ($post['weight_kg'] ?? '') ?: null,
            'blood_type' => trim($post['blood_type'] ?? ''),
            'religion' => up($post['religion'] ?? ''),
            'citizenship' => up($post['citizenship'] ?? 'Filipino'),
        ];
        $sql = "INSERT INTO applicants_personal_information
                (applicant_id, first_name, middle_name, last_name, suffix, date_of_birth, age,
                 civil_status, complete_address, purok_id, contact_number, email,
                 height_cm, weight_kg, blood_type, religion, citizenship)
                VALUES (:applicant_id, :first_name, :middle_name, :last_name, :suffix, :date_of_birth, :age,
                        :civil_status, :complete_address, :purok_id, :contact_number, :email,
                        :height_cm, :weight_kg, :blood_type, :religion, :citizenship)
                ON DUPLICATE KEY UPDATE
                    first_name=VALUES(first_name), middle_name=VALUES(middle_name), last_name=VALUES(last_name),
                    suffix=VALUES(suffix), date_of_birth=VALUES(date_of_birth), age=VALUES(age),
                    civil_status=VALUES(civil_status), complete_address=VALUES(complete_address),
                    purok_id=VALUES(purok_id), contact_number=VALUES(contact_number), email=VALUES(email),
                    height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg), blood_type=VALUES(blood_type),
                    religion=VALUES(religion), citizenship=VALUES(citizenship)";
        Database::conn()->prepare($sql)->execute($data);
    }

    private function saveEducationData(int $applicantId, array $post): void
    {
        // Optional General Weighted Average on the Philippine 1.00 (highest) – 5.00 scale; anything else is stored as NULL.
        $ga = trim((string)($post['general_average'] ?? ''));
        $ga = (is_numeric($ga) && (float)$ga >= 1 && (float)$ga <= 5) ? round((float)$ga, 2) : null;

        $data = [
            'applicant_id' => $applicantId,
            'school_name' => trim($post['school_name'] ?? ''),
            'year_level' => $post['year_level'] ?? '1st Year',
            'year_level_other' => trim($post['year_level_other'] ?? ''),
            'course_name' => trim($post['course_name'] ?? ''),
            'major' => trim($post['major'] ?? '') ?: null,
            'general_average' => $ga,
            'previous_scholarship' => isset($post['previous_scholarship']) && $post['previous_scholarship'] ? 1 : 0,
            'previous_scholarship_details' => trim($post['previous_scholarship_details'] ?? ''),
            'current_scholarship' => isset($post['current_scholarship']) && $post['current_scholarship'] ? 1 : 0,
            'current_scholarship_details' => trim($post['current_scholarship_details'] ?? ''),
        ];
        $pdo = Database::conn();
        $pdo->prepare("DELETE FROM education_records WHERE applicant_id = ?")->execute([$applicantId]);
        $pdo->prepare("INSERT INTO education_records
            (applicant_id, school_name, year_level, year_level_other, course_name, major, general_average,
             previous_scholarship, previous_scholarship_details,
             current_scholarship, current_scholarship_details)
            VALUES (:applicant_id, :school_name, :year_level, :year_level_other, :course_name, :major, :general_average,
                    :previous_scholarship, :previous_scholarship_details,
                    :current_scholarship, :current_scholarship_details)")->execute($data);
    }

    private function saveFamilyData(int $applicantId, array $post): void
    {
        $fi = (float)($post['father_income'] ?? 0);
        $mi = (float)($post['mother_income'] ?? 0);
        $data = [
            'applicant_id' => $applicantId,
            'father_name' => trim($post['father_name'] ?? ''),
            'father_age' => ($post['father_age'] ?? '') ?: null,
            'father_occupation' => trim($post['father_occupation'] ?? ''),
            'father_address' => trim($post['father_address'] ?? ''),
            'father_contact' => digits_only($post['father_contact'] ?? ''),
            'father_income' => $fi,
            'father_status' => $post['father_status'] ?? 'Available',
            'mother_name' => trim($post['mother_name'] ?? ''),
            'mother_age' => ($post['mother_age'] ?? '') ?: null,
            'mother_occupation' => trim($post['mother_occupation'] ?? ''),
            'mother_address' => trim($post['mother_address'] ?? ''),
            'mother_contact' => digits_only($post['mother_contact'] ?? ''),
            'mother_income' => $mi,
            'mother_status' => $post['mother_status'] ?? 'Available',
            'combined_income' => $fi + $mi,
            'total_family_income' => $fi + $mi,
            'family_size' => ($post['family_size'] ?? '') ?: null,
        ];
        $sql = "INSERT INTO family_background
                (applicant_id, father_name, father_age, father_occupation, father_address, father_contact,
                 father_income, father_status, mother_name, mother_age, mother_occupation, mother_address,
                 mother_contact, mother_income, mother_status, combined_income, total_family_income, family_size)
                VALUES (:applicant_id, :father_name, :father_age, :father_occupation, :father_address, :father_contact,
                        :father_income, :father_status, :mother_name, :mother_age, :mother_occupation, :mother_address,
                        :mother_contact, :mother_income, :mother_status, :combined_income, :total_family_income, :family_size)
                ON DUPLICATE KEY UPDATE
                    father_name=VALUES(father_name), father_age=VALUES(father_age), father_occupation=VALUES(father_occupation),
                    father_address=VALUES(father_address), father_contact=VALUES(father_contact),
                    father_income=VALUES(father_income), father_status=VALUES(father_status),
                    mother_name=VALUES(mother_name), mother_age=VALUES(mother_age), mother_occupation=VALUES(mother_occupation),
                    mother_address=VALUES(mother_address), mother_contact=VALUES(mother_contact),
                    mother_income=VALUES(mother_income), mother_status=VALUES(mother_status),
                    combined_income=VALUES(combined_income), total_family_income=VALUES(total_family_income),
                    family_size=VALUES(family_size)";
        Database::conn()->prepare($sql)->execute($data);
    }

    /** Legacy full-page step save (kept for compatibility). The wizard itself now relies on application/autosave. */
    private function saveApplication(): void
    {
        verify_csrf();
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();
        $step = $_POST['step'] ?? '';

        $current = $this->activeApplication((int)$applicant['id']);
        if (!$this->isEditable($current)) {
            flash('warning', 'Your application has already been submitted and can no longer be edited.');
            redirect('applicant/application');
        }
        if (!$current && !$this->applicationsOpen()) {
            flash('warning', 'Applications are currently closed.');
            redirect('applicant/application');
        }

        try {
            $pdo->beginTransaction();
            $this->ensureApplication($applicant);

            if ($step === 'personal') $this->savePersonalData((int)$applicant['id'], $_POST);
            if ($step === 'education') $this->saveEducationData((int)$applicant['id'], $_POST);
            if ($step === 'family') $this->saveFamilyData((int)$applicant['id'], $_POST);

            $pdo->commit();
            AuditLog::write('application_step_saved', 'applicants', (int)$applicant['id'], "Saved step: $step");
            flash('success', 'Progress saved.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', APP_DEBUG ? $e->getMessage() : 'Failed to save.');
        }
        redirect('applicant/application');
    }

    /**
     * Background autosave endpoint used by the application wizard. Authenticates via the normal
     * applicant session, verifies CSRF, confirms the application belongs to this applicant and is
     * still editable (Draft/Incomplete), validates the step name, saves only that section, and
     * always responds with JSON — never a redirect, never a page reload.
     */
    private function autosaveApplication(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Invalid request.'], 405); }
        verify_csrf();

        $applicant = $this->applicantRecord();
        $step = $_POST['step'] ?? '';
        if (!in_array($step, ['personal', 'education', 'family'], true)) {
            json_response(['success' => false, 'message' => 'Unknown step.'], 400);
        }

        $current = $this->activeApplication((int)$applicant['id']);
        if (!$this->isEditable($current)) {
            json_response(['success' => false, 'message' => 'This application is no longer editable.', 'status' => $current['status'] ?? null]);
        }
        if (!$current && !$this->applicationsOpen()) {
            json_response(['success' => false, 'message' => 'Applications are currently closed.']);
        }

        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $appId = $this->ensureApplication($applicant);
            if ($appId === null) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'No active scholarship program is open right now.']);
            }

            match ($step) {
                'personal' => $this->savePersonalData((int)$applicant['id'], $_POST),
                'education' => $this->saveEducationData((int)$applicant['id'], $_POST),
                'family' => $this->saveFamilyData((int)$applicant['id'], $_POST),
            };

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Autosave failed: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Unable to synchronize with the server.']);
        }

        $app = $this->activeApplication((int)$applicant['id']);
        json_response([
            'success' => true,
            'saved_at' => date('Y-m-d H:i:s'),
            'status' => $app['status'] ?? 'Draft',
            'application_id' => $app['id'] ?? null,
        ]);
    }

    private function submitApplication(): void
    {
        verify_csrf();
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();

        // Validate completeness
        $personal = $pdo->prepare("SELECT * FROM applicants_personal_information WHERE applicant_id = ?");
        $personal->execute([$applicant['id']]);
        $personal = $personal->fetch();
        if (!$personal) { flash('danger', 'Please complete Personal Information.'); redirect('applicant/application'); }

        $phonePattern = '/^0[0-9]{10}$/';
        if (!preg_match($phonePattern, (string)($personal['contact_number'] ?? ''))) {
            flash('danger', 'Contact Number must be exactly 11 digits (e.g. 09171234567).');
            redirect('applicant/application');
        }
        // Complete Address requires a selected Purok/Subdivision (the address segment is derived from it server-side).
        if (empty($personal['purok_id'])) {
            flash('danger', 'Please select your Purok/Subdivision to complete your address.');
            redirect('applicant/application');
        }

        $edu = $pdo->prepare("SELECT * FROM education_records WHERE applicant_id = ? ORDER BY id DESC LIMIT 1");
        $edu->execute([$applicant['id']]);
        $edu = $edu->fetch();
        if (!$edu) { flash('danger', 'Please complete Education.'); redirect('applicant/application'); }

        $fam = $pdo->prepare("SELECT * FROM family_background WHERE applicant_id = ?");
        $fam->execute([$applicant['id']]);
        $fam = $fam->fetch();
        if (!$fam) { flash('danger', 'Please complete Family Background.'); redirect('applicant/application'); }

        foreach (['father_contact' => 'Father', 'mother_contact' => 'Mother'] as $field => $label) {
            $v = (string)($fam[$field] ?? '');
            if ($v !== '' && !preg_match($phonePattern, $v)) {
                flash('danger', "$label's Contact Number must be exactly 11 digits (e.g. 09171234567), or left blank.");
                redirect('applicant/application');
            }
        }

        // Find the (Draft) application
        $app = $this->activeApplication((int)$applicant['id']);
        if (!$app) {
            flash('danger', 'Please save at least Step 1 before submitting.');
            redirect('applicant/application');
        }

        if (!$this->isEditable($app)) {
            flash('warning', 'This application has already been submitted.');
            redirect('applicant/dashboard');
        }
        if (!$this->applicationsOpen()) {
            flash('warning', 'Applications are currently closed.');
            redirect('applicant/application');
        }

        // Application window. A resubmission after "Incomplete" is still accepted once the window has closed:
        // the original submission was on time and the SK office itself asked for the correction.
        $program = Program::find((int)$app['program_id']);
        if (!$program) {
            flash('danger', 'This scholarship program is no longer available.');
            redirect('applicant/application');
        }
        if ($app['status'] !== 'Incomplete' && (!$program['is_active'] || !Program::windowOpen($program))) {
            flash('warning', "Applications for {$program['program_name']} are accepted from "
                . date('F j, Y', strtotime($program['application_start'])) . ' to '
                . date('F j, Y', strtotime($program['application_end'])) . '. Submission is not possible right now.');
            redirect('applicant/application');
        }

        // All required documents must be uploaded before the application can be submitted.
        $docStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM documents WHERE application_id = ? AND status != 'Not Submitted' AND file_path != ''"
        );
        $docStmt->execute([$app['id']]);
        $uploadedDocs = (int)$docStmt->fetchColumn();
        if ($uploadedDocs < count(DOCUMENT_TYPES)) {
            flash('danger', 'Please upload all required documents before submitting.');
            redirect('applicant/application');
        }

        $wasIncomplete = ($app['status'] === 'Incomplete');

        // Eligibility against the program rules: recorded for staff, never an automatic rejection.
        $eligibility = Application::evaluateEligibility($program, $personal, $edu, $fam);

        // Update status
        $pdo->prepare("UPDATE applications SET status='Submitted', submitted_at=NOW(), eligibility_score=?, eligibility_notes=? WHERE id=?")
            ->execute([$eligibility['score'], $eligibility['notes'], $app['id']]);
        AuditLog::write('application_eligibility_checked', 'applications', (int)$app['id'],
            'Eligibility score ' . $eligibility['score'] . '%' . ($eligibility['notes'] ? ': ' . str_replace("\n", ' ', $eligibility['notes']) : ''),
            null, ['eligibility_score' => $eligibility['score'], 'eligibility_notes' => $eligibility['notes']]);

        if ($wasIncomplete) {
            AuditLog::write('application_resubmitted', 'applications', (int)$app['id'], 'Application resubmitted after incomplete status');
            Notification::send((int)Auth::user()['id'], 'Application Resubmitted',
                'Your application has been resubmitted and is now awaiting review by the SK office.',
                'success', url('applicant/dashboard'), true);
            flash('success', 'Application resubmitted successfully! Please wait for the SK office to review it.');
        } else {
            AuditLog::write('application_submitted', 'applications', (int)$app['id'], 'Application submitted');
            Notification::send((int)Auth::user()['id'], 'Application Submitted',
                'Your scholarship application has been submitted and is now under initial review.',
                'success', url('applicant/dashboard'), true);
            flash('success', 'Application submitted successfully! Please wait for the initial review.');
        }
        redirect('applicant/dashboard');
    }

    private function documents(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        $editable = $this->isEditable($app);
        $documents = [];
        if ($app) {
            $stmt = Database::conn()->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY FIELD(document_type, 'Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID')");
            $stmt->execute([$app['id']]);
            $documents = $stmt->fetchAll();
        }
        $pageTitle = 'My Documents';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/documents.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /**
     * Used from both the standalone "My Documents" page (normal POST + redirect) and the
     * application wizard's Step 4 (AJAX via fetch/FormData, detected via X-Requested-With and
     * answered with JSON only — never a redirect, never a page reload).
     */
    private function uploadDocument(): void
    {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $back = $isAjax ? null : 'applicant/documents';
        $fail = function (string $message, int $code = 400) use ($isAjax, $back): never {
            if ($isAjax) { json_response(['success' => false, 'message' => $message], $code); }
            flash('danger', $message);
            redirect($back);
        };

        verify_csrf();
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        if (!$app) { $fail('Please start your application first.'); }
        if (!$this->isEditable($app)) {
            $fail('Documents can only be changed while your application is a Draft or marked Incomplete.');
        }

        $type = $_POST['document_type'] ?? '';
        if (!in_array($type, DOCUMENT_TYPES, true)) { $fail('Invalid document type.'); }
        if (empty($_FILES['file']['name']) || is_array($_FILES['file']['name'])) { $fail('Please choose a file.'); }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) { $fail('Upload error.'); }
        if (!is_uploaded_file($file['tmp_name'])) { $fail('Upload error.'); }

        // Size: measured on the server, not trusted from the client.
        $size = filesize($file['tmp_name']);
        if ($size === false || $size <= 0) { $fail('The uploaded file is empty.'); }
        if ($size > MAX_FILE_SIZE) { $fail('File too large (max 5MB).'); }

        // Type: detected from the file CONTENT; the client-supplied name/extension/type are never trusted.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_MIME, true)) { $fail('Only PDF, JPG, or PNG allowed.'); }

        // Structural sanity check so the bytes really are the claimed format.
        if ($mime === 'application/pdf') {
            $head = file_get_contents($file['tmp_name'], false, null, 0, 5);
            $valid = ($head === '%PDF-');
        } else {
            $info = getimagesize($file['tmp_name']);
            $valid = $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
        }
        if (!$valid) { $fail('The file is not a valid PDF, JPG, or PNG.'); }

        // Extension comes ONLY from the detected type; filename is random and unguessable.
        $ext = match($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' };
        $dir = DOCUMENT_PATH . '/' . (int)$app['id'];
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log('Cannot create document directory: ' . $dir);
            $fail('Cannot save file.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) { $fail('Cannot save file.'); }
        chmod($target, 0640);

        $rel = 'storage/documents/' . (int)$app['id'] . '/' . $filename;
        $origName = mb_substr((string)preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string)$file['name']))), 0, 200);

        // Upsert document row
        $existing = Database::conn()->prepare("SELECT id, file_path FROM documents WHERE application_id = ? AND document_type = ?");
        $existing->execute([$app['id'], $type]);
        $row = $existing->fetch();

        if ($row) {
            // remove the previous file (resolver refuses anything outside the approved document roots)
            $oldPath = Document::absolutePath($row['file_path']);
            if ($oldPath !== null && !unlink($oldPath)) {
                error_log('Could not delete replaced document: ' . $row['file_path']);
            }
            Database::conn()->prepare("UPDATE documents SET file_path=?, original_filename=?, file_size=?, mime_type=?,
                                       status='Submitted', uploaded_at=NOW(), reviewed_at=NULL, reviewed_by=NULL, remarks=NULL
                                       WHERE id=?")
                ->execute([$rel, $origName, $size, $mime, $row['id']]);
            $docId = (int)$row['id'];
        } else {
            Database::conn()->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, file_size, mime_type, status)
                                       VALUES (?,?,?,?,?,?, 'Submitted')")
                ->execute([$app['id'], $type, $rel, $origName, $size, $mime]);
            $docId = (int)Database::conn()->lastInsertId();
        }

        AuditLog::write('document_uploaded', 'documents', $docId, "Uploaded $type for application #{$app['application_code']}");

        if ($isAjax) {
            json_response([
                'success' => true,
                'document' => [
                    'id' => $docId,
                    'document_type' => $type,
                    'status' => 'Submitted',
                    'original_filename' => $origName,
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'view_url' => url('applicant/documents/view&id=' . $docId),
                ],
            ]);
        }
        flash('success', "$type uploaded successfully.");
        redirect('applicant/documents');
    }

    /** An applicant may only open documents belonging to their OWN application. */
    private function viewDocument(): void
    {
        $applicant = $this->applicantRecord();
        $doc = Document::find((int)($_GET['id'] ?? 0));
        $owned = false;
        if ($doc) {
            $stmt = Database::conn()->prepare("SELECT 1 FROM applications WHERE id = ? AND applicant_id = ?");
            $stmt->execute([$doc['application_id'], $applicant['id']]);
            $owned = (bool)$stmt->fetchColumn();
        }
        if (!$doc || !$owned) {
            // 404 (not 403) so document ids cannot be probed
            http_response_code(404);
            die('Document not found.');
        }
        Document::send($doc, isset($_GET['download']));
    }

    private function interview(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        $interview = null;
        $evalResult = null;
        // The current application's interview; if it has none yet, the latest interview from any of the
        // applicant's applications (e.g. a previous cycle still in progress when a new program opened).
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer, s.remarks as sched_remarks,
                    e.result, e.rating, e.recommendation
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             JOIN applications a ON a.id = ia.application_id
             LEFT JOIN interview_evaluations e ON e.assignment_id = ia.id
             WHERE a.applicant_id = ?
             ORDER BY (ia.application_id = ?) DESC, ia.id DESC LIMIT 1"
        );
        $stmt->execute([$applicant['id'], (int)($app['id'] ?? 0)]);
        $interview = $stmt->fetch() ?: null;
        $pageTitle = 'My Interview';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/interview.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function scholarship(): void
    {
        $applicant = $this->applicantRecord();
        $stmt = Database::conn()->prepare("SELECT * FROM scholars WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"); // latest program's scholarship
        $stmt->execute([$applicant['id']]);
        $scholar = $stmt->fetch() ?: null;

        $releases = $academics = $renewals = [];
        if ($scholar) {
            $s = Database::conn()->prepare("SELECT * FROM scholarship_releases WHERE scholar_id = ? ORDER BY release_date DESC");
            $s->execute([$scholar['id']]);
            $releases = $s->fetchAll();

            $a = Database::conn()->prepare("SELECT * FROM academic_records WHERE scholar_id = ? ORDER BY academic_year ASC, FIELD(semester,'1st Semester','2nd Semester','Summer') ASC");
            $a->execute([$scholar['id']]);
            $academics = $a->fetchAll();

            $r = Database::conn()->prepare("SELECT * FROM scholarship_renewals WHERE scholar_id = ? ORDER BY created_at DESC");
            $r->execute([$scholar['id']]);
            $renewals = $r->fetchAll();
        }
        $pageTitle = 'My Scholarship';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/scholarship.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function notifications(): void
    {
        $userId = (int)Auth::user()['id'];
        if (!empty($_GET['mark_read'])) {
            Database::conn()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
            json_response(['ok' => true]);
        }
        $stmt = Database::conn()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();
        $pageTitle = 'Notifications';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/notifications.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function profile(): void
    {
        $user = Auth::user();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pdo = Database::conn();
            $name = trim($_POST['full_name'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            if ($name === '') { flash('danger', 'Full name required.'); redirect('applicant/profile'); }
            $pdo->prepare("UPDATE users SET full_name=?, contact_number=? WHERE id=?")
                ->execute([$name, $contact, $user['id']]);
            if (!empty($_POST['new_password'])) {
                if (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) { flash('danger', 'Password too short.'); redirect('applicant/profile'); }
                if (!password_verify($_POST['current_password'] ?? '', $user['password_hash'])) { flash('danger', 'Current password incorrect.'); redirect('applicant/profile'); }
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            }
            AuditLog::write('profile_updated', 'users', (int)$user['id'], 'Profile updated');
            flash('success', 'Profile updated.');
            redirect('applicant/profile');
        }
        $pageTitle = 'My Profile';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/Profile.php';   // file on disk is Profile.php (case-sensitive on Linux)
        require VIEW_PATH . '/layouts/footer.php';
    }
}