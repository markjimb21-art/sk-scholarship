<?php
declare(strict_types=1);

final class Application
{
    /**
     * Staff decision workflow: action => statuses the application must currently be in.
     * Single source of truth for BOTH the server-side guard in AdminController::updateApplication()
     * and the action buttons in admin/application_view.php, so the UI and the server always agree.
     * The only bypass is an admin acting on an application where admin_override is enabled
     * (see canBypassTransitions()); every bypass is written to the audit log.
     */
    public const TRANSITIONS = [
        'start_review'     => ['Submitted'],
        'mark_incomplete'  => ['Submitted', 'Under Initial Review', 'Documents Under Review'],
        'verify_documents' => ['Under Initial Review', 'Documents Under Review'],
        'for_interview'    => ['Submitted', 'Under Initial Review', 'Documents Verified'],
        'for_final'        => ['Interview Completed', 'Waitlisted'],
        'approve'          => ['For Final Evaluation', 'Waitlisted'],
        'waitlist'         => ['For Final Evaluation', 'Waitlisted'],
        'reject'           => ['Draft', 'Submitted', 'Under Initial Review', 'Incomplete', 'Documents Under Review',
                               'Documents Verified', 'For Interview', 'Interview Scheduled', 'Interview Completed',
                               'For Final Evaluation', 'Waitlisted'],
    ];

    /** Final decisions: nothing (not even an override) moves an application out of these. */
    public const FINAL_STATUSES = ['Approved', 'Rejected'];

    /** Does the transition map allow $action from $status (no override considered)? */
    public static function transitionAllowed(string $action, string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$action] ?? [], true);
    }

    /** Admin override: only an admin, only on an application flagged admin_override, never on a final decision. */
    public static function canBypassTransitions(array $app): bool
    {
        return !empty($app['admin_override'])
            && Auth::hasRole('admin')
            && !in_array($app['status'], self::FINAL_STATUSES, true);
    }

    /** What the UI should offer: allowed by the map, or bypassable via admin override. */
    public static function actionAllowed(string $action, array $app): bool
    {
        if (!isset(self::TRANSITIONS[$action])) return false;
        return self::transitionAllowed($action, $app['status']) || self::canBypassTransitions($app);
    }

    /**
     * Checks an applicant against the program's eligibility rules (max_age, max_family_income,
     * citizenship_required, min_gpa). Never auto-rejects — staff see the result and decide.
     * A NULL/0 program limit means "no limit". min_gpa uses Philippine grading (1.00 best, 5.00 failing),
     * so the applicant passes when general_average <= min_gpa. A missing general average (optional field)
     * is reported as "Not checked" and left out of the score.
     *
     * Returns ['score' => float 0..100 (share of checked criteria that passed), 'notes' => ?string (one line each)].
     */
    public static function evaluateEligibility(array $program, ?array $personal, ?array $education, ?array $family): array
    {
        $checked = 0;
        $passed = 0;
        $lines = [];

        if ((int)($program['max_age'] ?? 0) > 0) {
            $checked++;
            $maxAge = (int)$program['max_age'];
            if (empty($personal['date_of_birth'])) {
                $lines[] = "Age: date of birth is missing (maximum age $maxAge).";
            } else {
                $age = calculate_age((string)$personal['date_of_birth']);
                if ($age <= $maxAge) $passed++;
                else $lines[] = "Age: $age is above the maximum age of $maxAge.";
            }
        }

        if ((float)($program['max_family_income'] ?? 0) > 0) {
            $checked++;
            $max = (float)$program['max_family_income'];
            $income = (float)($family['total_family_income'] ?? 0);
            if ($income <= $max) $passed++;
            else $lines[] = 'Family income: ₱' . number_format($income, 2) . ' is above the maximum of ₱' . number_format($max, 2) . '.';
        }

        $requiredCitizenship = trim((string)($program['citizenship_required'] ?? ''));
        if ($requiredCitizenship !== '') {
            $checked++;
            $citizenship = trim((string)($personal['citizenship'] ?? ''));
            if (strcasecmp($citizenship, $requiredCitizenship) === 0) $passed++;
            else $lines[] = 'Citizenship: "' . ($citizenship ?: 'not provided') . "\" does not match the required \"$requiredCitizenship\".";
        }

        if ((float)($program['min_gpa'] ?? 0) > 0) {
            $minGpa = (float)$program['min_gpa'];
            $ga = $education['general_average'] ?? null;
            if ($ga === null || $ga === '') {
                $lines[] = 'Not checked: general average not provided (program requires ' . number_format($minGpa, 2) . ' or better).';
            } else {
                $checked++;
                if ((float)$ga <= $minGpa) $passed++;
                else $lines[] = 'General average: ' . number_format((float)$ga, 2) . ' is below the required ' . number_format($minGpa, 2) . ' (1.00 is highest).';
            }
        }

        return [
            'score' => $checked > 0 ? round($passed / $checked * 100, 2) : 100.0,
            'notes' => $lines ? implode("\n", $lines) : null,
        ];
    }

    /** Shared by search() and count() so paging totals always match the rows. Placeholder names must be unique (native prepares). */
    private static function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q1 OR a.application_code LIKE :q2 OR u.email LIKE :q3)";
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status']))     { $sql .= " AND a.status = :st";              $params['st'] = $filters['status']; }
        if (!empty($filters['school']))     { $sql .= " AND e.school_name = :school";     $params['school'] = $filters['school']; }
        if (!empty($filters['course']))     { $sql .= " AND e.course_name LIKE :course";  $params['course'] = '%' . $filters['course'] . '%'; }
        if (!empty($filters['year_level'])) { $sql .= " AND e.year_level = :yl";          $params['yl'] = $filters['year_level']; }
        if (!empty($filters['program_id'])) { $sql .= " AND a.program_id = :pid";         $params['pid'] = (int)$filters['program_id']; }
        if (isset($filters['max_income']) && $filters['max_income'] !== '') {
            $sql .= " AND f.total_family_income <= :mi";
            $params['mi'] = (float)$filters['max_income'];
        }
        return [$sql, $params];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, sp.program_name, sp.academic_year, sp.semester
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByApplicant(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM applications WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function withDetails(int $id): ?array
    {
        $app = self::find($id);
        if (!$app) return null;

        $app['applicant']   = Applicant::withUser((int)$app['applicant_id']);
        $app['personal']    = Applicant::personalInfo((int)$app['applicant_id']);
        $app['education']   = Applicant::education((int)$app['applicant_id']);
        $app['family']      = Applicant::familyBackground((int)$app['applicant_id']);
        $app['documents']   = Document::byApplication($id);
        $app['interview']   = Interview::assignmentByApplication($id);
        $app['evaluation']  = $app['interview'] ? Interview::evaluation((int)$app['interview']['id']) : null;
        return $app;
    }

    public static function search(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT a.id, a.application_code, a.status, a.submitted_at, a.decided_at,
                       u.full_name, u.email, u.contact_number,
                       e.school_name, e.course_name, e.year_level,
                       f.total_family_income,
                       sp.program_name,
                       (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id AND d.status = 'Verified') AS docs_verified,
                       (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id) AS docs_total
                FROM applications a
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                JOIN scholarship_programs sp ON sp.id = a.program_id
                LEFT JOIN education_records e ON e.applicant_id = ap.id
                LEFT JOIN family_background f ON f.applicant_id = ap.id
                WHERE 1=1";
        [$w, $params] = self::where($filters);
        $sql .= $w;

        $sql .= " ORDER BY a.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM applications a
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                LEFT JOIN education_records e ON e.applicant_id = ap.id
                LEFT JOIN family_background f ON f.applicant_id = ap.id
                WHERE 1=1";
        [$w, $params] = self::where($filters);
        $sql .= $w;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function updateStatus(int $id, string $status, ?int $actorId = null, ?string $remarks = null): void
    {
        $pdo = Database::conn();
        $old = self::find($id);
        $pdo->prepare(
            "UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$status, $id]);

        AuditLog::write('application_status_changed', 'applications', $id,
            "Status changed to $status",
            ['status' => $old['status'] ?? null],
            ['status' => $status]
        );
    }

    /** Marks an application Incomplete with a REQUIRED reason, kept separate from final decision_remarks. */
    public static function markIncomplete(int $id, int $actorId, string $remarks): void
    {
        $pdo = Database::conn();
        $old = self::find($id);
        $pdo->prepare(
            "UPDATE applications
             SET status = 'Incomplete', review_remarks = ?, review_remarks_at = NOW(), review_remarks_by = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([$remarks, $actorId, $id]);

        AuditLog::write('application_marked_incomplete', 'applications', $id,
            'Application marked incomplete: ' . $remarks,
            ['status' => $old['status'] ?? null],
            ['status' => 'Incomplete', 'remarks' => $remarks]
        );
    }

    /**
     * Single source of truth for "is this application ready for interview scheduling?", used by both
     * the application detail page (review summary) and the admin_for_interview action (server-side gate).
     * Never trust the frontend checklist alone — this is the authoritative check.
     */
    public static function reviewCompleteness(int $applicationId): array
    {
        $app = self::find($applicationId);
        if (!$app) {
            return ['complete' => false, 'personal_ok' => false, 'education_ok' => false, 'family_ok' => false,
                     'docs_uploaded' => 0, 'docs_required' => count(DOCUMENT_TYPES),
                     'missing_documents' => [], 'missing_fields' => ['Application not found']];
        }

        $applicantId = (int)$app['applicant_id'];
        $personal = Applicant::personalInfo($applicantId);
        $education = Applicant::education($applicantId);
        $family = Applicant::familyBackground($applicantId);
        $phonePattern = '/^0[0-9]{10}$/';

        $missingFields = [];
        $personalOk = (bool)$personal;
        if (!$personal) {
            $missingFields[] = 'Personal Information';
        } else {
            if (trim((string)($personal['first_name'] ?? '')) === '') { $missingFields[] = 'First Name'; $personalOk = false; }
            if (trim((string)($personal['last_name'] ?? '')) === '') { $missingFields[] = 'Last Name'; $personalOk = false; }
            if (empty($personal['date_of_birth'])) { $missingFields[] = 'Date of Birth'; $personalOk = false; }
            if (!preg_match($phonePattern, (string)($personal['contact_number'] ?? ''))) { $missingFields[] = 'Contact Number (must be 11 digits)'; $personalOk = false; }
            if (empty($personal['purok_id'])) { $missingFields[] = 'Purok/Address'; $personalOk = false; }
        }

        $educationOk = (bool)$education;
        if (!$education) {
            $missingFields[] = 'Education';
        } else {
            if (trim((string)($education['school_name'] ?? '')) === '') { $missingFields[] = 'School Name'; $educationOk = false; }
            if (trim((string)($education['course_name'] ?? '')) === '') { $missingFields[] = 'Course'; $educationOk = false; }
        }

        $familyOk = (bool)$family;
        if (!$family) {
            $missingFields[] = 'Family Background';
        } else {
            foreach (['father_contact' => "Father's Contact Number", 'mother_contact' => "Mother's Contact Number"] as $field => $label) {
                $v = (string)($family[$field] ?? '');
                if ($v !== '' && !preg_match($phonePattern, $v)) { $missingFields[] = "$label (invalid format)"; $familyOk = false; }
            }
        }

        $documents = Document::byApplication($applicationId);
        $byType = [];
        foreach ($documents as $d) $byType[$d['document_type']] = $d;
        $missingDocuments = [];
        $docsUploaded = 0;
        foreach (DOCUMENT_TYPES as $dt) {
            if (!empty($byType[$dt]['file_path'])) $docsUploaded++;
            else $missingDocuments[] = $dt;
        }

        return [
            'complete' => $personalOk && $educationOk && $familyOk && empty($missingDocuments),
            'personal_ok' => $personalOk,
            'education_ok' => $educationOk,
            'family_ok' => $familyOk,
            'docs_uploaded' => $docsUploaded,
            'docs_required' => count(DOCUMENT_TYPES),
            'missing_documents' => $missingDocuments,
            'missing_fields' => $missingFields,
        ];
    }

    public static function stats(): array
    {
        $pdo = Database::conn();
        $row = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'Submitted' OR status = 'Under Initial Review') AS pending,
                SUM(status = 'For Interview' OR status = 'Interview Scheduled') AS for_interview,
                SUM(status = 'Interview Completed' OR status = 'For Final Evaluation') AS interviewed,
                SUM(status = 'Approved') AS approved,
                SUM(status = 'Rejected') AS rejected,
                SUM(status = 'Waitlisted') AS waitlisted
             FROM applications"
        )->fetch();
        return $row ?: [];
    }
}