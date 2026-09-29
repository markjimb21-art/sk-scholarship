<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Program.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Term.php';
require_once BASE_PATH . '/app/models/Renewal.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/TermSchedule.php';

/** Applicant / scholar side of docs/PROCESS.md (A1 is in AuthController). */
final class ApplicantController
{
    public function handle(string $action): void
    {
        switch (trim($action, '/')) {
            case 'dashboard':            $this->dashboard(); break;               // A3–A9 tracker
            case 'application':          $this->application(); break;             // A2 form
            case 'application/autosave': $this->autosave(); break;                // A2 draft
            case 'application/submit':   $this->submit(); break;                  // A2 / A3 resubmit
            case 'documents/upload':     $this->uploadDocument(); break;          // A2 documents
            case 'documents/view':       $this->viewDocument(); break;
            case 'scholarship':          $this->scholarship(); break;             // A7–A9
            case 'claim/upload':         $this->uploadTermDocument('claim'); break;
            case 'claim/submit':         $this->submitClaim(); break;             // A8
            case 'renewal/upload':       $this->uploadTermDocument('renewal'); break;
            case 'renewal/submit':       $this->submitRenewal(); break;           // A9
            case 'term-documents/view':  $this->viewTermDocument(); break;
            case 'notifications':        $this->notifications(); break;
            case 'profile':              $this->profile(); break;
            default:
                http_response_code(404);
                require VIEW_PATH . '/errors/404.php';
        }
    }

    private function applicant(): array
    {
        return Applicant::forUser((int)Auth::user()['id']);
    }

    /* ================= DASHBOARD: one tracker + one "next step" box ================= */

    private function dashboard(): void
    {
        $applicant = $this->applicant();
        $app = Application::currentForApplicant((int)$applicant['id']);
        $openProgram = Program::open();
        $scholar = $app && $app['status'] === 'Approved' ? Scholar::forApplication((int)$app['id']) : null;
        $interview = $app ? Interview::assignmentByApplication((int)$app['id']) : null;
        $term = $scholar ? Term::current((int)$scholar['id']) : null;
        $renewal = $term ? Renewal::forTerm((int)$term['id']) : null;

        $journey = $this->journey($app, $openProgram, $interview, $scholar, $term, $renewal);
        $pastApplications = Application::pastForApplicant((int)$applicant['id'], $app);

        $pageTitle = 'My Dashboard';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/dashboard.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /**
     * Where the applicant is in the process and the ONE thing to do next.
     * Tracker steps: 0 Apply, 1 Screening, 2 Interview, 3 Result, 4 Claim, 5 Renewal.
     * Returns ['step','status','next' => text, 'action' => ['label','route'] | null, 'tone' => bootstrap colour, 'details' => []].
     */
    private function journey(?array $app, ?array $open, ?array $interview, ?array $scholar, ?array $term, ?array $renewal): array
    {
        $j = ['step' => 0, 'status' => '', 'next' => '', 'action' => null, 'tone' => 'primary', 'details' => []];
        $wait = static fn(string $when): string => "Nothing to do — we will e-mail you when $when.";

        if (!$app) {
            if ($open) {
                $j['status'] = 'Not started';
                $j['next'] = "Apply to {$open['program_name']} before " . date('F j, Y', strtotime($open['application_end']))
                    . ': fill in the form and upload the 5 required documents.';
                $j['action'] = ['label' => 'Start my application', 'route' => 'applicant/application'];
            } else {
                $j['status'] = 'No program is accepting applications';
                $j['next'] = 'Nothing to do right now. Applications open when the SK announces the next scholarship program.';
                $j['tone'] = 'secondary';
            }
            return $j;
        }

        switch ($app['status']) {
            case 'Draft':
                $j['status'] = Application::label('Draft');
                if ($app['program_open'] && Program::windowOpen($app)) {
                    $j['next'] = 'Complete every section and upload the 5 documents, then submit before '
                        . date('F j, Y', strtotime($app['application_end'])) . '.';
                    $j['action'] = ['label' => 'Continue my application', 'route' => 'applicant/application'];
                } else {
                    $j['next'] = 'The application period ended on ' . date('F j, Y', strtotime($app['application_end'])) . '.';
                    $j['tone'] = 'secondary';
                }
                return $j;
            case 'Submitted':
                return ['step' => 1, 'status' => Application::label('Submitted'), 'next' => $wait('screening is done'),
                        'action' => null, 'tone' => 'info', 'details' => []];
            case 'Returned':
                return ['step' => 1, 'status' => Application::label('Returned'),
                        'next' => 'Fix the items the SK office listed below, then submit again.',
                        'action' => ['label' => 'Fix and resubmit', 'route' => 'applicant/application'], 'tone' => 'warning',
                        'details' => ['Items to correct' => (string)$app['review_remarks']]];
            case 'Rejected':
                $atScreening = empty($app['qualified_at']);
                return ['step' => $atScreening ? 1 : 3, 'status' => Application::label('Rejected'),
                        'next' => 'Nothing to do for this application. You may apply again when a new program opens.',
                        'action' => null, 'tone' => 'danger',
                        'details' => ['Reason' => (string)($app['decision_remarks'] ?: 'Not given')]];
            case 'Qualified':
                $missed = $interview && $interview['attendance'] === 'No-show';
                return ['step' => 2, 'status' => $missed ? 'Missed interview — waiting for your one new schedule' : Application::label('Qualified'),
                        'next' => $wait('your interview date, time and venue are set'), 'action' => null,
                        'tone' => $missed ? 'warning' : 'info',
                        'details' => $missed ? ['Important' => 'You get one reschedule. Missing the next interview ends your application.'] : []];
            case 'Interview Scheduled':
                $details = ['When and where' => $interview ? Interview::describe($interview) : '—'];
                if ($interview && (int)$interview['reschedule_count'] > 0) {
                    $details['Important'] = 'This is your rescheduled interview. Missing it ends your application.';
                }
                return ['step' => 2, 'status' => Application::label('Interview Scheduled'),
                        'next' => 'Attend your interview. Bring a valid ID and arrive 15 minutes early.',
                        'action' => null, 'tone' => 'primary', 'details' => $details];
            case 'Interviewed':
                return ['step' => 3, 'status' => Application::label('Interviewed'), 'next' => $wait('the final result is out'),
                        'action' => null, 'tone' => 'info', 'details' => []];
            case 'Waitlisted':
                return ['step' => 3, 'status' => Application::label('Waitlisted'), 'next' => $wait('a slot opens for you'),
                        'action' => null, 'tone' => 'warning', 'details' => []];
        }

        // Approved: the scholar part (A7–A9).
        $j['step'] = 4;
        if (!$scholar || !$term) {
            $j['status'] = 'Approved';
            $j['next'] = $wait('your first semester opens');
            return $j;
        }
        $sem = "{$term['academic_year']} {$term['semester']}";
        if ($scholar['status'] === 'Ended') {
            return ['step' => 5, 'status' => 'Scholarship ended', 'tone' => 'secondary', 'action' => null,
                    'next' => 'Nothing to do. Your past semesters stay available on My Scholarship.',
                    'details' => ['Reason' => (string)$scholar['end_reason']]];
        }
        $toScholarship = ['label' => 'Go to My Scholarship', 'route' => 'applicant/scholarship'];
        switch ($term['claim_status']) {
            case 'Waiting for Requirements':
                $window = Term::claimWindow($term);
                if (!$window['can_upload']) {
                    return ['step' => 4, 'status' => "$sem — claim " . lcfirst(TermSchedule::STATE_LABELS[$window['state']]),
                            'tone' => in_array($window['state'], ['deadline_passed', 'closed', 'cancelled'], true) ? 'danger' : 'secondary',
                            'action' => null, 'next' => $window['message'], 'details' => []];
                }
                return ['step' => 4, 'status' => "$sem — claim not submitted", 'tone' => 'primary', 'action' => $toScholarship,
                        'next' => "Upload your School ID and Grades to claim your cash for $sem. " . $window['message'], 'details' => []];
            case 'Resubmit':
                $window = Term::claimWindow($term);
                return ['step' => 4, 'status' => "$sem — please re-upload", 'tone' => 'warning',
                        'action' => $window['can_upload'] ? $toScholarship : null,
                        'next' => $window['can_upload'] ? 'Re-upload the documents the SK office asked for, then submit again.' : $window['message'],
                        'details' => ['Remarks' => (string)$term['remarks']]];
            case 'Submitted':
                return ['step' => 4, 'status' => "$sem — claim submitted", 'tone' => 'info', 'action' => null,
                        'next' => $wait('your documents are verified'), 'details' => []];
            case 'Verified':
                return ['step' => 4, 'status' => "$sem — verified, waiting for release", 'tone' => 'info', 'action' => null,
                        'next' => $wait('your cash is released'), 'details' => []];
        }

        // Released: renewal for the next semester.
        $next = Term::nextSemester($term['academic_year'], $term['semester']);
        $nextSem = "{$next['academic_year']} {$next['semester']}";
        $released = '₱' . number_format((float)$term['released_amount'], 2) . ' on '
            . date('F j, Y', strtotime((string)$term['release_date'])) . ' (' . $term['payment_method']
            . ($term['reference_number'] ? ', ref. ' . $term['reference_number'] : '') . ')';
        $details = ["Released for $sem" => $released];
        if (!$renewal || $renewal['status'] === 'Resubmit') {
            $window = Renewal::window($term, $renewal);
            if (!$renewal) {
                return ['step' => 5, 'status' => "$sem — cash released", 'tone' => 'success',
                        'action' => $window['can_upload'] ? $toScholarship : null,
                        'next' => $window['can_upload']
                            ? "Renew for $nextSem: upload your updated Enrollment Form and School ID. " . $window['message']
                            : "Renewal for $nextSem: " . $window['message'],
                        'details' => $details];
            }
            return ['step' => 5, 'status' => "Renewal for $nextSem — please re-upload", 'tone' => 'warning',
                    'action' => $window['can_upload'] ? $toScholarship : null,
                    'next' => $window['can_upload'] ? 'Re-upload the documents the SK office asked for, then submit again.' : $window['message'],
                    'details' => $details + ['Remarks' => (string)$renewal['remarks']]];
        }
        return ['step' => 5, 'status' => "Renewal for $nextSem submitted", 'tone' => 'info', 'action' => null,
                'next' => $wait('your renewal is reviewed'), 'details' => $details];
    }

    /* ================= A2: APPLICATION FORM ================= */

    private function application(): void
    {
        $applicant = $this->applicant();
        $pdo = Database::conn();

        $app = Application::currentForApplicant((int)$applicant['id']);
        $openProgram = Program::open();
        $personal = Applicant::personalInfo((int)$applicant['id']) ?? [];
        $personal['address_unit'] = $this->splitAddressUnit($personal['complete_address'] ?? '');
        $education = Applicant::education((int)$applicant['id']) ?? [];
        $family = Applicant::familyBackground((int)$applicant['id']) ?? [];
        $documents = $app ? Document::byApplication((int)$app['id']) : [];
        // Editable: a Draft/Returned application, or none yet while a program is open (and not already a scholar).
        $editable = $app ? Application::isEditable($app) : ($openProgram !== null && !Scholar::hasActive((int)$applicant['id']));
        $completeness = $app ? Application::completeness((int)$app['id']) : null;

        $puroks = $pdo->query("SELECT id, purok_name FROM puroks ORDER BY purok_name")->fetchAll();
        $schools = $pdo->query("SELECT school_name FROM schools WHERE is_active = 1 ORDER BY school_name")->fetchAll();
        $courses = $pdo->query("SELECT course_name FROM courses WHERE is_active = 1 ORDER BY course_name")->fetchAll();

        $pageTitle = 'My Application';
        $pageScripts = [asset('js/application-autosave.js')];
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/application_form.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /**
     * The current application if it is editable, creating a Draft for the OPEN program when there is none.
     * Returns [app|null, error|null].
     */
    private function editableApplication(array $applicant): array
    {
        $app = Application::currentForApplicant((int)$applicant['id']);
        if ($app) {
            return Application::isEditable($app) ? [$app, null] : [null, 'This application can no longer be edited.'];
        }
        if (Scholar::hasActive((int)$applicant['id'])) return [null, 'You already have an active scholarship.'];
        $program = Program::open();
        if (!$program) return [null, 'No scholarship program is accepting applications right now.'];

        $pdo = Database::conn();
        $code = generate_code('APP');
        $pdo->prepare("INSERT INTO applications (application_code, applicant_id, program_id, status) VALUES (?, ?, ?, 'Draft')")
            ->execute([$code, (int)$applicant['id'], (int)$program['id']]);
        $appId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, status) VALUES (?, ?, '', '', 'Not Submitted')");
        foreach (DOCUMENT_TYPES as $type) $ins->execute([$appId, $type]);
        AuditLog::write('application_draft_created', 'applications', $appId, "Draft started for {$program['program_name']}");
        return [Application::find($appId), null];
    }

    /** A2 autosave: saves one section; always JSON. */
    private function autosave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid request.'], 405);
        verify_csrf();

        $step = $_POST['step'] ?? '';
        if (!in_array($step, ['personal', 'education', 'family'], true)) {
            json_response(['success' => false, 'message' => 'Unknown step.'], 400);
        }
        $applicant = $this->applicant();
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            [$app, $error] = $this->editableApplication($applicant);
            if (!$app) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => $error]);
            }
            match ($step) {
                'personal'  => $this->savePersonal((int)$applicant['id'], $_POST),
                'education' => $this->saveEducation((int)$applicant['id'], $_POST),
                'family'    => $this->saveFamily((int)$applicant['id'], $_POST),
            };
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Autosave failed: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Unable to save to the server.']);
        }
        json_response(['success' => true, 'saved_at' => date('Y-m-d H:i:s'), 'status' => $app['status'], 'application_id' => (int)$app['id']]);
    }

    /** A2 submit / A3 resubmit. */
    private function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/application');
        verify_csrf();
        $applicant = $this->applicant();
        $app = Application::currentForApplicant((int)$applicant['id']);
        if (!$app) { flash('danger', 'Please start your application first.'); redirect('applicant/application'); }

        $result = Application::submit((int)$app['id'], (int)Auth::user()['id']);
        if (!$result['ok']) { flash('danger', $result['error']); redirect('applicant/application'); }

        flash('success', $result['resubmitted']
            ? 'Application resubmitted. The SK office will screen it again.'
            : 'Application submitted. We will e-mail you when screening is done.');
        redirect('applicant/dashboard');
    }

    /**
     * Composes the stored Complete Address as "<unit> / <purok/subdivision> / BARANGAY ESTEFANIA / BACOLOD CITY".
     * The purok name is looked up server-side from the `puroks` table by id (never taken as free text from the
     * client), and the barangay/city suffix is ALWAYS this hardcoded constant pair — so a manually crafted
     * request can neither spoof the purok name nor register an address outside the scholarship's service area.
     */
    private function composeAddress(string $unit, string $purokName): string
    {
        $parts = array_values(array_filter([up($unit), $purokName], fn($v) => $v !== ''));
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

    /** Unit/house-number part of a stored address, for pre-filling the form (legacy free-form addresses give ''). */
    private function splitAddressUnit(string $full): string
    {
        $suffix = ' / ' . ADDRESS_BARANGAY . ' / ' . ADDRESS_CITY;
        if (!str_ends_with(strtoupper($full), strtoupper($suffix))) return '';
        $parts = array_map('trim', explode(' / ', substr($full, 0, strlen($full) - strlen($suffix)), 2));
        return $parts[0] ?? '';
    }

    private function savePersonal(int $applicantId, array $post): void
    {
        // date_of_birth is NOT NULL; autosave fires before the applicant reaches it, so wait until it is given.
        if (trim((string)($post['date_of_birth'] ?? '')) === '') return;
        $purokId = ($post['purok_id'] ?? '') !== '' ? (int)$post['purok_id'] : null;
        $bloodType = (string)($post['blood_type'] ?? '');
        $citizenship = mb_substr(up($post['citizenship'] ?? ''), 0, 50);
        Database::conn()->prepare(
            "INSERT INTO applicants_personal_information
                (applicant_id, first_name, middle_name, last_name, suffix, date_of_birth, complete_address, purok_id, contact_number,
                 blood_type, religion, citizenship)
             VALUES (:applicant_id, :first_name, :middle_name, :last_name, :suffix, :date_of_birth, :complete_address, :purok_id, :contact_number,
                 :blood_type, :religion, :citizenship)
             ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name), middle_name = VALUES(middle_name), last_name = VALUES(last_name),
                suffix = VALUES(suffix), date_of_birth = VALUES(date_of_birth), complete_address = VALUES(complete_address),
                purok_id = VALUES(purok_id), contact_number = VALUES(contact_number),
                blood_type = VALUES(blood_type), religion = VALUES(religion), citizenship = VALUES(citizenship)"
        )->execute([
            // Only a listed blood type is stored; anything else (including a crafted value) is saved as "not given".
            'blood_type'       => in_array($bloodType, BLOOD_TYPES, true) ? $bloodType : null,
            'religion'         => mb_substr(up($post['religion'] ?? ''), 0, 80) ?: null,
            'citizenship'      => $citizenship !== '' ? $citizenship : 'FILIPINO',
            'applicant_id'     => $applicantId,
            'first_name'       => up($post['first_name'] ?? ''),
            'middle_name'      => up($post['middle_name'] ?? ''),
            'last_name'        => up($post['last_name'] ?? ''),
            'suffix'           => up($post['suffix'] ?? ''),
            'date_of_birth'    => $post['date_of_birth'],
            'complete_address' => $this->composeAddress($post['address_unit'] ?? '', $this->purokName($purokId)),
            'purok_id'         => $purokId,
            'contact_number'   => digits_only($post['contact_number'] ?? ''),
        ]);
    }

    private function saveEducation(int $applicantId, array $post): void
    {
        // GWA on the Philippine 1.00 (highest) – 5.00 scale; anything else is stored as NULL.
        $ga = trim((string)($post['general_average'] ?? ''));
        $ga = (is_numeric($ga) && (float)$ga >= 1 && (float)$ga <= 5) ? round((float)$ga, 2) : null;
        $yearLevel = in_array($post['year_level'] ?? '', YEAR_LEVELS, true) ? $post['year_level'] : '1st Year';

        $pdo = Database::conn();
        $pdo->prepare("DELETE FROM education_records WHERE applicant_id = ?")->execute([$applicantId]);
        $pdo->prepare(
            "INSERT INTO education_records
                (applicant_id, school_name, year_level, year_level_other, course_name, major, general_average,
                 previous_scholarship, previous_scholarship_details, current_scholarship, current_scholarship_details)
             VALUES (:applicant_id, :school_name, :year_level, :year_level_other, :course_name, :major, :general_average,
                     :previous_scholarship, :previous_scholarship_details, :current_scholarship, :current_scholarship_details)"
        )->execute([
            'applicant_id'                 => $applicantId,
            'school_name'                  => trim($post['school_name'] ?? ''),
            'year_level'                   => $yearLevel,
            'year_level_other'             => trim($post['year_level_other'] ?? ''),
            'course_name'                  => trim($post['course_name'] ?? ''),
            'major'                        => trim($post['major'] ?? '') ?: null,
            'general_average'              => $ga,
            'previous_scholarship'         => !empty($post['previous_scholarship']) ? 1 : 0,
            'previous_scholarship_details' => trim($post['previous_scholarship_details'] ?? ''),
            'current_scholarship'          => !empty($post['current_scholarship']) ? 1 : 0,
            'current_scholarship_details'  => trim($post['current_scholarship_details'] ?? ''),
        ]);
    }

    private function saveFamily(int $applicantId, array $post): void
    {
        $status = static fn($v) => in_array($v, ['Available', 'Deceased', 'Unknown', 'Not Applicable'], true) ? $v : 'Available';
        $fi = max(0.0, (float)($post['father_income'] ?? 0));
        $mi = max(0.0, (float)($post['mother_income'] ?? 0));
        Database::conn()->prepare(
            "INSERT INTO family_background
                (applicant_id, father_name, father_age, father_occupation, father_address, father_contact, father_income, father_status,
                 mother_name, mother_age, mother_occupation, mother_address, mother_contact, mother_income, mother_status,
                 total_family_income, family_size)
             VALUES (:applicant_id, :father_name, :father_age, :father_occupation, :father_address, :father_contact, :father_income, :father_status,
                     :mother_name, :mother_age, :mother_occupation, :mother_address, :mother_contact, :mother_income, :mother_status,
                     :total_family_income, :family_size)
             ON DUPLICATE KEY UPDATE
                father_name = VALUES(father_name), father_age = VALUES(father_age), father_occupation = VALUES(father_occupation),
                father_address = VALUES(father_address), father_contact = VALUES(father_contact),
                father_income = VALUES(father_income), father_status = VALUES(father_status),
                mother_name = VALUES(mother_name), mother_age = VALUES(mother_age), mother_occupation = VALUES(mother_occupation),
                mother_address = VALUES(mother_address), mother_contact = VALUES(mother_contact),
                mother_income = VALUES(mother_income), mother_status = VALUES(mother_status),
                total_family_income = VALUES(total_family_income), family_size = VALUES(family_size)"
        )->execute([
            'applicant_id'        => $applicantId,
            'father_name'         => trim($post['father_name'] ?? ''),
            'father_age'          => ($post['father_age'] ?? '') !== '' ? (int)$post['father_age'] : null,
            'father_occupation'   => trim($post['father_occupation'] ?? ''),
            'father_address'      => trim($post['father_address'] ?? ''),
            'father_contact'      => digits_only($post['father_contact'] ?? ''),
            'father_income'       => $fi,
            'father_status'       => $status($post['father_status'] ?? ''),
            'mother_name'         => trim($post['mother_name'] ?? ''),
            'mother_age'          => ($post['mother_age'] ?? '') !== '' ? (int)$post['mother_age'] : null,
            'mother_occupation'   => trim($post['mother_occupation'] ?? ''),
            'mother_address'      => trim($post['mother_address'] ?? ''),
            'mother_contact'      => digits_only($post['mother_contact'] ?? ''),
            'mother_income'       => $mi,
            'mother_status'       => $status($post['mother_status'] ?? ''),
            'total_family_income' => $fi + $mi,
            'family_size'         => ($post['family_size'] ?? '') !== '' ? (int)$post['family_size'] : null,
        ]);
    }

    /**
     * A2 document upload — from the wizard (AJAX, answered with JSON) or a plain form (redirect).
     * Only while the application is editable; replaces the previous file of that type.
     */
    private function uploadDocument(): void
    {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $fail = function (string $message, int $code = 400) use ($isAjax): never {
            if ($isAjax) json_response(['success' => false, 'message' => $message], $code);
            flash('danger', $message);
            redirect('applicant/application');
        };
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail('Invalid request.', 405);
        verify_csrf();

        $type = $_POST['document_type'] ?? '';
        if (!in_array($type, DOCUMENT_TYPES, true)) $fail('Invalid document type.');

        $applicant = $this->applicant();
        [$app, $error] = $this->editableApplication($applicant);
        if (!$app) $fail($error);

        $stored = Document::storeUpload($_FILES['file'] ?? [], DOCUMENT_PATH, 'storage/documents/', (string)(int)$app['id']);
        if (!$stored['ok']) $fail($stored['error']);

        // A draft/returned application replaces its own file in place (decision E6); the upload still becomes the
        // applicant's current version of that type, superseding any older one (e.g. from an earlier scholarship).
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE application_id = ? AND document_type = ?");
        $stmt->execute([(int)$app['id'], $type]);
        $row = $stmt->fetch();
        $pdo->beginTransaction();
        if ($row) {
            $pdo->prepare("UPDATE documents SET file_path = ?, original_filename = ?, file_size = ?, mime_type = ?,
                           status = 'Submitted', uploaded_at = NOW(), remarks = NULL, is_current = 1, superseded_at = NULL WHERE id = ?")
                ->execute([$stored['rel'], $stored['original'], $stored['size'], $stored['mime'], $row['id']]);
            $docId = (int)$row['id'];
        } else {
            $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, file_size, mime_type, status, is_current)
                           VALUES (?, ?, ?, ?, ?, ?, 'Submitted', 1)")
                ->execute([(int)$app['id'], $type, $stored['rel'], $stored['original'], $stored['size'], $stored['mime']]);
            $docId = (int)$pdo->lastInsertId();
        }
        AuditLog::write('document_uploaded', 'documents', $docId, "Uploaded $type for application {$app['application_code']}");
        Document::supersede((int)$applicant['id'], $type, 'documents', $docId);
        $pdo->commit();
        if ($row) Document::deleteFile($row['file_path']);

        if ($isAjax) {
            json_response(['success' => true, 'document' => [
                'id' => $docId, 'document_type' => $type, 'status' => 'Submitted', 'status_label' => 'Uploaded',
                'original_filename' => $stored['original'], 'uploaded_at' => date('Y-m-d H:i:s'),
                'view_url' => url('applicant/documents/view&id=' . $docId),
            ]]);
        }
        flash('success', "$type uploaded.");
        redirect('applicant/application');
    }

    /** An applicant may only open documents of their OWN applications (404 otherwise, so ids can't be probed). */
    private function viewDocument(): void
    {
        $applicant = $this->applicant();
        $doc = Document::find((int)($_GET['id'] ?? 0));
        $owned = false;
        if ($doc && $doc['file_path'] !== '') {
            $stmt = Database::conn()->prepare("SELECT 1 FROM applications WHERE id = ? AND applicant_id = ?");
            $stmt->execute([$doc['application_id'], $applicant['id']]);
            $owned = (bool)$stmt->fetchColumn();
        }
        if (!$owned) { http_response_code(404); die('Document not found.'); }
        Document::sendApplicationDocument($doc, isset($_GET['download']));
    }

    /* ================= A7–A9: MY SCHOLARSHIP ================= */

    private function scholarship(): void
    {
        $applicant = $this->applicant();
        $scholar = Scholar::forApplicant((int)$applicant['id']);
        $terms = $scholar ? Term::forScholar((int)$scholar['id']) : [];
        $term = $scholar ? Term::current((int)$scholar['id']) : null;
        $claimDocs = $term ? Term::documents((int)$term['id'], 'claim') : [];
        $renewalDocs = $term ? Term::documents((int)$term['id'], 'renewal') : [];
        $renewal = $term ? Renewal::forTerm((int)$term['id']) : null;
        $nextSemester = $term ? Term::nextSemester($term['academic_year'], $term['semester']) : null;
        $claimWindow = $term ? Term::claimWindow($term) : null;
        $renewalWindow = $term && $term['claim_status'] === 'Released' ? Renewal::window($term, $renewal) : null;

        $pageTitle = 'My Scholarship';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/scholarship.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /** The current semester of the logged-in scholar, only if $_POST['term_id'] is theirs. */
    private function ownTerm(): ?array
    {
        $term = Term::find((int)($_POST['term_id'] ?? 0));
        return ($term && (int)$term['applicant_id'] === (int)$this->applicant()['id']) ? $term : null;
    }

    private function uploadTermDocument(string $purpose): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $type = (string)($_POST['type'] ?? '');
        $result = Term::uploadDocument($term, $purpose, $type, $_FILES['file'] ?? []);
        if ($result['ok']) flash('success', "$type uploaded.");
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    private function submitClaim(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        try {
            $result = Term::submitClaim($term, (int)Auth::user()['id']);
        } catch (DomainException $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($result['ok']) flash('success', 'Claim submitted. We will e-mail you when your documents are verified.');
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    private function submitRenewal(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $result = Renewal::submit($term, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', 'Renewal submitted. We will e-mail you when it is reviewed.');
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    private function viewTermDocument(): void
    {
        $doc = Term::document((int)($_GET['id'] ?? 0));
        if (!$doc || (int)$doc['applicant_id'] !== (int)$this->applicant()['id']) { http_response_code(404); die('Document not found.'); }
        Document::send($doc['file_path'], $doc['original_filename'], $doc['type'], 'term_documents', (int)$doc['id'], isset($_GET['download']));
    }

    /* ================= ACCOUNT ================= */

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
            $contact = digits_only($_POST['contact_number'] ?? '');
            if ($name === '') { flash('danger', 'Full name required.'); redirect('applicant/profile'); }
            if (!empty($_POST['new_password'])) {
                if (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) { flash('danger', 'Password too short.'); redirect('applicant/profile'); }
                if (!password_verify($_POST['current_password'] ?? '', $user['password_hash'])) { flash('danger', 'Current password incorrect.'); redirect('applicant/profile'); }
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            }
            $pdo->prepare("UPDATE users SET full_name = ?, contact_number = ? WHERE id = ?")->execute([$name, $contact, $user['id']]);
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
