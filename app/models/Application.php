<?php
declare(strict_types=1);

final class Application
{
    /**
     * The one allowed-transition map (docs/PROCESS.md §1). Every status change goes through
     * changeStatus(), which refuses anything not listed here; views call can() to decide which
     * buttons to show, so the UI and the server always agree.
     */
    public const TRANSITIONS = [
        'submit'        => ['from' => ['Draft', 'Returned'],                     'to' => 'Submitted'],
        'qualify'       => ['from' => ['Submitted'],                             'to' => 'Qualified'],
        'return'        => ['from' => ['Submitted'],                             'to' => 'Returned'],
        'reject'        => ['from' => ['Submitted', 'Interviewed', 'Waitlisted'], 'to' => 'Rejected'],
        'assign'        => ['from' => ['Qualified'],                             'to' => 'Interview Scheduled'],
        'unassign'      => ['from' => ['Interview Scheduled'],                   'to' => 'Qualified'],
        'score'         => ['from' => ['Interview Scheduled'],                   'to' => 'Interviewed'],
        'no_show'       => ['from' => ['Interview Scheduled'],                   'to' => 'Qualified'],
        'no_show_final' => ['from' => ['Interview Scheduled'],                   'to' => 'Rejected'],
        'approve'       => ['from' => ['Interviewed', 'Waitlisted'],             'to' => 'Approved'],
        'waitlist'      => ['from' => ['Interviewed'],                           'to' => 'Waitlisted'],
    ];

    public static function can(string $action, string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$action]['from'] ?? [], true);
    }

    public static function label(string $status): string
    {
        return APP_STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Apply $action to $app (row with id + status). Refuses transitions outside the map and changes made
     * concurrently by someone else (the UPDATE is conditional on the status the caller saw). Extra columns
     * in $set are written in the same UPDATE. Audit-logged. Throws DomainException with a plain message.
     */
    public static function changeStatus(array $app, string $action, ?int $actorId, array $set = [], string $note = ''): void
    {
        $from = (string)$app['status'];
        if (!self::can($action, $from)) {
            throw new DomainException('This action is not possible while the application is "' . self::label($from) . '".');
        }
        $to = self::TRANSITIONS[$action]['to'];

        $cols = ['status' => $to] + $set;
        $assign = implode(', ', array_map(static fn($k) => "`$k` = :$k", array_keys($cols)));
        $stmt = Database::conn()->prepare("UPDATE applications SET $assign WHERE id = :id AND status = :from_status");
        $stmt->execute($cols + ['id' => (int)$app['id'], 'from_status' => $from]);
        if ($stmt->rowCount() !== 1) {
            throw new DomainException('This application was changed by someone else. Please reload the page.');
        }

        AuditLog::write('application_' . $action, 'applications', (int)$app['id'],
            "Status: $from → $to" . ($note !== '' ? " — $note" : ''),
            ['status' => $from], ['status' => $to] + $set + ($actorId ? ['by' => $actorId] : []));
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, sp.program_name, sp.academic_year, sp.application_start, sp.application_end,
                    sp.min_gpa, sp.max_family_income, sp.max_age, sp.slots_available, sp.is_active AS program_open
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Application with everything the screening/decision pages show. */
    public static function withDetails(int $id): ?array
    {
        $app = self::find($id);
        if (!$app) return null;
        $applicantId = (int)$app['applicant_id'];
        $app['applicant']  = Applicant::withUser($applicantId);
        $app['personal']   = Applicant::personalInfo($applicantId);
        $app['education']  = Applicant::education($applicantId);
        $app['family']     = Applicant::familyBackground($applicantId);
        $app['documents']  = Document::byApplication($id);
        $app['interview']  = Interview::assignmentByApplication($id);
        $app['evaluation'] = $app['interview'] ? Interview::evaluation((int)$app['interview']['id']) : null;
        return $app;
    }

    /** User id of the applicant who owns the application (for notifications). */
    public static function userId(int $applicationId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT ap.user_id FROM applications a JOIN applicants ap ON ap.id = a.applicant_id WHERE a.id = ?"
        );
        $stmt->execute([$applicationId]);
        return (int)$stmt->fetchColumn();
    }

    /** In-app + e-mail notification to the applicant (every status change they see). */
    public static function notify(int $applicationId, string $title, string $message, string $type, string $route = 'applicant/dashboard'): void
    {
        $uid = self::userId($applicationId);
        if ($uid) Notification::send($uid, $title, $message, $type, url($route), sendEmail: true);
    }

    /* ---------------------------------------------------------------------------------------------
     *  Applicant side: which application is "current", completeness, submission
     * --------------------------------------------------------------------------------------------- */

    /**
     * The applicant's CURRENT application (docs/PROCESS.md A2):
     *  - an Active scholar: the application that made them a scholar (they can't apply elsewhere meanwhile);
     *  - otherwise, while a program is open: the application for that program, or null (a Draft is created on first save);
     *  - between cycles: the most recent application.
     */
    public static function currentForApplicant(int $applicantId): ?array
    {
        $pdo = Database::conn();
        $base = "SELECT a.*, sp.program_name, sp.academic_year, sp.application_start, sp.application_end,
                        sp.is_active AS program_open
                 FROM applications a JOIN scholarship_programs sp ON sp.id = a.program_id";

        $stmt = $pdo->prepare("$base JOIN scholars s ON s.application_id = a.id
                               WHERE a.applicant_id = ? AND s.status = 'Active' ORDER BY s.id DESC LIMIT 1");
        $stmt->execute([$applicantId]);
        if ($row = $stmt->fetch()) return $row;

        $open = Program::open();
        if ($open) {
            $stmt = $pdo->prepare("$base WHERE a.applicant_id = ? AND a.program_id = ? LIMIT 1");
            $stmt->execute([$applicantId, (int)$open['id']]);
            return $stmt->fetch() ?: null;
        }
        $stmt = $pdo->prepare("$base WHERE a.applicant_id = ? ORDER BY a.id DESC LIMIT 1");
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    /** Every application except the current one, newest first (read-only history). */
    public static function pastForApplicant(int $applicantId, ?array $current): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.submitted_at, a.decided_at, a.decision_remarks,
                    sp.program_name, sp.academic_year, s.scholar_code, s.status AS scholar_status, s.end_reason
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             LEFT JOIN scholars s ON s.application_id = a.id
             WHERE a.applicant_id = ? AND a.id <> ?
             ORDER BY a.id DESC"
        );
        $stmt->execute([$applicantId, (int)($current['id'] ?? 0)]);
        return $stmt->fetchAll();
    }

    /** Applicants may edit only a Draft or a Returned application. */
    public static function isEditable(?array $app): bool
    {
        return $app === null || in_array($app['status'], ['Draft', 'Returned'], true);
    }

    /**
     * What is still missing before the application can be submitted (server-side authority for Submit and
     * the same list the Review step shows). Empty 'missing' = complete.
     */
    public static function completeness(int $applicationId): array
    {
        $app = self::find($applicationId);
        if (!$app) return ['complete' => false, 'missing' => ['Application not found']];

        $applicantId = (int)$app['applicant_id'];
        $p = Applicant::personalInfo($applicantId);
        $e = Applicant::education($applicantId);
        $f = Applicant::familyBackground($applicantId);
        $phone = '/^0[0-9]{10}$/';
        $missing = [];

        if (!$p) {
            $missing[] = 'Personal Information';
        } else {
            if (trim((string)$p['first_name']) === '') $missing[] = 'First name';
            if (trim((string)$p['last_name']) === '')  $missing[] = 'Last name';
            if (empty($p['date_of_birth']))            $missing[] = 'Date of birth';
            if (!preg_match($phone, (string)$p['contact_number'])) $missing[] = 'Contact number (11 digits)';
            if (empty($p['purok_id']))                 $missing[] = 'Purok / subdivision';
        }
        if (!$e) {
            $missing[] = 'Education';
        } else {
            if (trim((string)$e['school_name']) === '') $missing[] = 'School';
            if (trim((string)$e['course_name']) === '') $missing[] = 'Course';
        }
        if (!$f) {
            $missing[] = 'Family Background';
        } else {
            foreach (['father_contact' => "Father's contact number", 'mother_contact' => "Mother's contact number"] as $k => $label) {
                if ((string)$f[$k] !== '' && !preg_match($phone, (string)$f[$k])) $missing[] = "$label (11 digits or blank)";
            }
        }
        foreach (Document::missingTypes($applicationId) as $type) $missing[] = "Document: $type";

        return ['complete' => !$missing, 'missing' => $missing];
    }

    /** A2 Submit / A3 resubmit. Returns ['ok'=>true,'resubmitted'=>bool] or ['ok'=>false,'error'=>string]. */
    public static function submit(int $applicationId, int $userId): array
    {
        $app = self::find($applicationId);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        if (!self::can('submit', $app['status'])) {
            return ['ok' => false, 'error' => 'This application has already been submitted.'];
        }

        // Window: only an open program inside its dates. A Returned application may be resubmitted after the
        // end date (decision D2) — it was submitted on time and the SK office asked for the correction.
        $resubmission = $app['status'] === 'Returned';
        if (!$resubmission && (!$app['program_open'] || !Program::windowOpen($app))) {
            return ['ok' => false, 'error' => "Applications for {$app['program_name']} are accepted from "
                . date('F j, Y', strtotime($app['application_start'])) . ' to '
                . date('F j, Y', strtotime($app['application_end'])) . ' while the program is open.'];
        }

        $check = self::completeness($applicationId);
        if (!$check['complete']) {
            return ['ok' => false, 'error' => 'Please complete these first: ' . implode('; ', $check['missing']) . '.'];
        }

        try {
            self::changeStatus($app, 'submit', $userId, ['submitted_at' => date('Y-m-d H:i:s')],
                $resubmission ? 'resubmitted after correction' : 'submitted');
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return ['ok' => true, 'resubmitted' => $resubmission];
    }

    /* ---------------------------------------------------------------------------------------------
     *  Admin decisions: B2 screening and B5 final decision. Each returns ['ok'=>bool,'error'=>string].
     * --------------------------------------------------------------------------------------------- */

    /** Runs $fn in a transaction; DomainException => a plain error message. */
    private static function decide(callable $fn): array
    {
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $fn($pdo);
            $pdo->commit();
            return ['ok' => true];
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** B2 Qualify for interview: documents count as verified. */
    public static function qualify(int $id, int $adminId): array
    {
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $r = self::decide(function () use ($app, $adminId) {
            $now = date('Y-m-d H:i:s');
            self::changeStatus($app, 'qualify', $adminId, ['reviewed_at' => $now, 'reviewed_by' => $adminId, 'qualified_at' => $now]);
            Document::markAllVerified((int)$app['id']);
        });
        if ($r['ok']) self::notify($id, 'Qualified for interview',
            "Your application to {$app['program_name']} passed screening. We will e-mail you the interview date, time and venue.", 'success');
        return $r;
    }

    /** B2 Return for correction (remarks required). */
    public static function returnForCorrection(int $id, int $adminId, string $remarks): array
    {
        $remarks = trim($remarks);
        if ($remarks === '') return ['ok' => false, 'error' => 'Write the items the applicant must correct.'];
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $r = self::decide(function () use ($app, $adminId, $remarks) {
            $now = date('Y-m-d H:i:s');
            self::changeStatus($app, 'return', $adminId, [
                'reviewed_at' => $now, 'reviewed_by' => $adminId,
                'review_remarks' => $remarks, 'review_remarks_at' => $now, 'review_remarks_by' => $adminId,
            ], $remarks);
        });
        if ($r['ok']) self::notify($id, 'Returned for correction',
            "Your application needs corrections before it can be screened:\n$remarks\n\nPlease fix these items and submit again.",
            'warning', 'applicant/application');
        return $r;
    }

    /** B2 / B5 Reject (reason required). */
    public static function reject(int $id, int $adminId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') return ['ok' => false, 'error' => 'A reason is required to reject an application.'];
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $r = self::decide(function () use ($app, $adminId, $reason) {
            $now = date('Y-m-d H:i:s');
            $set = ['decided_at' => $now, 'decided_by' => $adminId, 'decision_remarks' => $reason];
            if ($app['status'] === 'Submitted') $set += ['reviewed_at' => $now, 'reviewed_by' => $adminId];
            self::changeStatus($app, 'reject', $adminId, $set, $reason);
        });
        if ($r['ok']) self::notify($id, 'Application not approved',
            "We're sorry — your application to {$app['program_name']} was not approved.\nReason: $reason", 'danger');
        return $r;
    }

    /** B5 Waitlist. */
    public static function waitlist(int $id, int $adminId): array
    {
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $r = self::decide(function () use ($app, $adminId) {
            $now = date('Y-m-d H:i:s');
            self::changeStatus($app, 'waitlist', $adminId, ['decided_at' => $now, 'decided_by' => $adminId, 'waitlisted_at' => $now]);
        });
        if ($r['ok']) self::notify($id, 'Waitlisted',
            "You are on the waitlist for {$app['program_name']}. We will e-mail you if a slot opens.", 'warning');
        return $r;
    }

    /**
     * B5 Approve: blocked when the program's slots are used up (the program row is locked so two approvals
     * can't take the last slot). Creates the scholar and opens Semester 1.
     */
    public static function approve(int $id, int $adminId): array
    {
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $r = self::decide(function (PDO $pdo) use ($app, $adminId) {
            $lock = $pdo->prepare("SELECT slots_available FROM scholarship_programs WHERE id = ? FOR UPDATE");
            $lock->execute([(int)$app['program_id']]);
            $slots = (int)$lock->fetchColumn();
            $used = Program::slotsUsed((int)$app['program_id']);
            if ($used >= $slots) {
                throw new DomainException("All $slots slots are filled ($used approved). Nobody else can be approved unless a slot opens.");
            }
            $now = date('Y-m-d H:i:s');
            self::changeStatus($app, 'approve', $adminId, ['decided_at' => $now, 'decided_by' => $adminId]);
            Scholar::createFromApplication($app, $adminId);
        });
        if ($r['ok']) self::notify($id, 'Approved — welcome, scholar!',
            "Congratulations! Your application to {$app['program_name']} was approved. Your first semester is open: "
            . 'go to My Scholarship and upload your School ID and Grades to claim your cash.', 'success', 'applicant/scholarship');
        return $r;
    }

    /* ---------------------------------------------------------------------------------------------
     *  Eligibility (B2, also shown in B5): pass/fail rows computed from the submitted data
     * --------------------------------------------------------------------------------------------- */

    /**
     * Rows: ['label','rule','value','result' => 'Pass'|'Fail'|'Not provided'|'No limit'].
     * Nothing is decided automatically — staff see the rows next to the buttons.
     */
    public static function eligibility(array $app): array
    {
        $personal  = $app['personal']  ?? Applicant::personalInfo((int)$app['applicant_id']);
        $education = $app['education'] ?? Applicant::education((int)$app['applicant_id']);
        $family    = $app['family']    ?? Applicant::familyBackground((int)$app['applicant_id']);
        $documents = $app['documents'] ?? Document::byApplication((int)$app['id']);
        $rows = [];

        // 1. Age on the submission date.
        $maxAge = (int)($app['max_age'] ?? 0);
        $onDate = $app['submitted_at'] ?: date('Y-m-d');
        $age = !empty($personal['date_of_birth'])
            ? (new DateTime($personal['date_of_birth']))->diff(new DateTime(substr((string)$onDate, 0, 10)))->y : null;
        $rows[] = [
            'label' => 'Age', 'rule' => $maxAge > 0 ? "at most $maxAge on the submission date" : 'no limit',
            'value' => $age === null ? '—' : (string)$age,
            'result' => $maxAge <= 0 ? 'No limit' : ($age === null ? 'Not provided' : ($age <= $maxAge ? 'Pass' : 'Fail')),
        ];

        // 2. Total family income.
        $maxIncome = (float)($app['max_family_income'] ?? 0);
        $income = $family ? (float)$family['total_family_income'] : null;
        $rows[] = [
            'label' => 'Total family income (monthly)', 'rule' => $maxIncome > 0 ? 'at most ₱' . number_format($maxIncome, 2) : 'no limit',
            'value' => $income === null ? '—' : '₱' . number_format($income, 2),
            'result' => $maxIncome <= 0 ? 'No limit' : ($income === null ? 'Not provided' : ($income <= $maxIncome ? 'Pass' : 'Fail')),
        ];

        // 3. GWA (Philippine scale: 1.00 highest, so a LOWER number is better).
        $minGwa = (float)($app['min_gpa'] ?? 0);
        $gwa = isset($education['general_average']) ? (float)$education['general_average'] : null;
        $rows[] = [
            'label' => 'GWA', 'rule' => $minGwa > 0 ? number_format($minGwa, 2) . ' or better (1.00 is highest)' : 'no limit',
            'value' => $gwa === null ? '—' : number_format($gwa, 2),
            'result' => $minGwa <= 0 ? 'No limit' : ($gwa === null ? 'Not provided' : ($gwa <= $minGwa ? 'Pass' : 'Fail')),
        ];

        // 4. Residency document present.
        $hasResidency = false;
        foreach ($documents as $d) {
            if ($d['document_type'] === 'Certificate of Residency' && $d['file_path'] !== '') $hasResidency = true;
        }
        $rows[] = [
            'label' => 'Certificate of Residency', 'rule' => 'uploaded',
            'value' => $hasResidency ? 'Uploaded' : 'Missing', 'result' => $hasResidency ? 'Pass' : 'Fail',
        ];

        $failed = count(array_filter($rows, static fn($r) => $r['result'] === 'Fail' || $r['result'] === 'Not provided'));
        return ['rows' => $rows, 'failed' => $failed, 'summary' => $failed === 0 ? 'Meets all rules' : "$failed rule(s) not met"];
    }

    /* ---------------------------------------------------------------------------------------------
     *  Lists
     * --------------------------------------------------------------------------------------------- */

    /** Records list / queues: filters status, program_id, q (name, code, e-mail). */
    public static function search(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = self::where($filters);
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.submitted_at, a.updated_at,
                    u.full_name, u.email, e.school_name, e.course_name, e.general_average,
                    f.total_family_income, sp.program_name
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             JOIN scholarship_programs sp ON sp.id = a.program_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             LEFT JOIN family_background f ON f.applicant_id = ap.id
             WHERE 1=1 $where
             ORDER BY COALESCE(a.submitted_at, a.created_at) ASC, a.id ASC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters): int
    {
        [$where, $params] = self::where($filters);
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE 1=1 $where"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Shared WHERE for search()/count(). Placeholder names are unique (native prepares). */
    private static function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], APP_STATUS, true)) {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['program_id'])) {
            $sql .= ' AND a.program_id = :pid';
            $params['pid'] = (int)$filters['program_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (u.full_name LIKE :q1 OR a.application_code LIKE :q2 OR u.email LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        return [$sql, $params];
    }
}
