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
        $app['interview']  = InterviewAssignment::assignmentByApplication($id);
        $app['evaluation'] = $app['interview'] ? InterviewScore::evaluation((int)$app['interview']['id']) : null;
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

    /**
     * The current application if it is editable, creating a Draft for the OPEN program when there is none.
     * Returns [app|null, error|null].
     */
    public static function editableFor(array $applicant): array
    {
        $app = Application::currentForApplicant((int)$applicant['id']);
        if ($app) {
            return Application::isEditable($app) ? [$app, null] : [null, 'This application can no longer be edited.'];
        }
        if (Scholar::hasActive((int)$applicant['id'])) return [null, 'You already have an active scholarship.'];
        $program = Program::open();
        if (!$program) return [null, 'No scholarship program is accepting applications right now.'];

        return [Application::find(Application::createDraft((int)$applicant['id'], $program)), null];
    }

    /** A new Draft for $program with its 5 empty document rows (the applicant's first save). Returns the new id. */
    public static function createDraft(int $applicantId, array $program): int
    {
        $pdo = Database::conn();
        $code = generate_code('APP');
        $pdo->prepare("INSERT INTO applications (application_code, applicant_id, program_id, status) VALUES (?, ?, ?, 'Draft')")
            ->execute([$code, $applicantId, (int)$program['id']]);
        $appId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, status) VALUES (?, ?, '', '', 'Not Submitted')");
        foreach (DOCUMENT_TYPES as $type) $ins->execute([$appId, $type]);
        AuditLog::write('application_draft_created', 'applications', $appId, "Draft started for {$program['program_name']}");
        return $appId;
    }

    /** Does application $applicationId belong to applicant $applicantId? */
    public static function isOwnedBy(int $applicationId, int $applicantId): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM applications WHERE id = ? AND applicant_id = ?");
        $stmt->execute([$applicationId, $applicantId]);
        return (bool)$stmt->fetchColumn();
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

    /** Every key an admin may flag on Return: "<step>.<field>" (RETURN_FLAG_FIELDS) and "documents.<type>" => label. */
    public static function flagOptions(): array
    {
        $out = [];
        foreach (RETURN_FLAG_FIELDS as $step => $fields) foreach ($fields as $f => $label) $out["$step.$f"] = $label;
        foreach (DOCUMENT_TYPES as $t) $out["documents.$t"] = $t;
        return $out;
    }

    /** The fields/documents flagged when the application was last returned (empty = none flagged). */
    public static function flags(?array $app): array
    {
        $list = json_decode((string)($app['flagged_fields'] ?? ''), true);
        return is_array($list) ? array_values(array_intersect($list, array_keys(self::flagOptions()))) : [];
    }

    /**
     * May the applicant change this field/document right now? A Draft: everything. Returned WITH flags: only the flagged
     * ones — everything else stays exactly as submitted. Returned without flags (older returns): everything.
     */
    public static function fieldEditable(array $app, string $key): bool
    {
        if ($app['status'] === 'Draft') return true;
        if ($app['status'] !== 'Returned') return false;
        $flags = self::flags($app);
        return !$flags || in_array($key, $flags, true);
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
                . fmt_long_date($app['application_start']) . ' to '
                . fmt_long_date($app['application_end']) . ' while the program is open.'];
        }

        $check = self::completeness($applicationId);
        if (!$check['complete']) {
            return ['ok' => false, 'error' => 'Please complete these first: ' . implode('; ', $check['missing']) . '.'];
        }
        // Returned with flagged documents: each must have been uploaded again since the return.
        if ($resubmission) {
            $notRedone = [];
            foreach (Document::byApplication($applicationId) as $d) {
                if (in_array('documents.' . $d['document_type'], self::flags($app), true)
                    && ($d['file_path'] === '' || $d['status'] === 'Invalid' || (string)$d['uploaded_at'] < (string)$app['review_remarks_at'])) {
                    $notRedone[] = $d['document_type'];
                }
            }
            if ($notRedone) return ['ok' => false, 'error' => 'Upload a new ' . implode(' and ', $notRedone) . ' first — the SK office flagged it.'];
        }

        try {
            self::changeStatus($app, 'submit', $userId, ['submitted_at' => date('Y-m-d H:i:s')],
                $resubmission ? 'resubmitted after correction' : 'submitted');
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        Notification::adminEvent($resubmission ? 'Application resubmitted' : 'New application submitted',
            "{$app['application_code']} — {$app['program_name']}" . ($resubmission ? ' (after correction)' : '') . ' is waiting for screening.',
            'admin/application&id=' . $applicationId);
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
    /**
     * B2 Return for correction. $flags = the "<step>.<field>" / "documents.<type>" keys the admin ticked as wrong: only
     * those can be changed by the applicant; the rest stays as submitted. $invalidDocs (document types, each also flagged)
     * are marked INVALID with the remark as the reason and their file removed (the rows are kept).
     */
    public static function returnForCorrection(int $id, int $adminId, string $remarks, array $flags = [], array $invalidDocs = []): array
    {
        $remarks = trim($remarks);
        if ($remarks === '') return ['ok' => false, 'error' => 'Write the items the applicant must correct.'];
        $app = self::find($id);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $invalidDocs = array_values(array_intersect($invalidDocs, DOCUMENT_TYPES));
        $flags = array_values(array_unique(array_intersect(
            array_merge($flags, array_map(static fn($t) => "documents.$t", $invalidDocs)), array_keys(self::flagOptions()))));
        $r = self::decide(function () use ($app, $adminId, $remarks, $flags, $invalidDocs) {
            $now = date('Y-m-d H:i:s');
            self::changeStatus($app, 'return', $adminId, [
                'reviewed_at' => $now, 'reviewed_by' => $adminId,
                'review_remarks' => $remarks, 'review_remarks_at' => $now, 'review_remarks_by' => $adminId,
                'flagged_fields' => $flags ? json_encode($flags) : null,
            ], $remarks . ($flags ? ' — flagged: ' . implode(', ', $flags) : ''));
            foreach (Document::byApplication((int)$app['id']) as $d) {
                if (in_array($d['document_type'], $invalidDocs, true) && $d['file_path'] !== '' && $d['status'] !== 'Invalid') {
                    Document::markInvalid('documents', (int)$d['id'], $remarks, $adminId);
                }
            }
        });
        if ($r['ok']) {
            $labels = self::flagOptions();
            $list = $flags ? "\n\nPlease correct only: " . implode(', ', array_map(static fn($k) => $labels[$k], $flags)) . '. Everything else stays as you submitted it.' : '';
            self::notify($id, 'Returned for correction',
                "Your application needs corrections before it can be screened:\n$remarks$list\n\nPlease fix these items and submit again.",
                'warning', 'applicant/application');
        }
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
        if ($r['ok']) {
            $window = TermSchedule::forSemester((int)$app['program_id'], $app['academic_year'], '1st Semester', 'release');
            self::notify($id, 'Approved — welcome, scholar!',
                "Congratulations! Your application to {$app['program_name']} was approved. Your first semester "
                . "({$app['academic_year']} 1st Semester) is open. To claim your cash on My Scholarship: "
                . TermSchedule::describeForScholar($window, 'release'), 'success', 'applicant/scholarship');
        }
        return $r;
    }
}
