<?php
declare(strict_types=1);

/**
 * Scholar semesters (scholar_terms) and their cash claims (A8 / B7 / B8).
 * One row per scholar per semester; claim_status follows CLAIM_TRANSITIONS only. A semester belongs to a program
 * (program_id): its amount, budget, claim window and seat come from that program, which can differ from the scholar's
 * origin program (origin_program_id) once they renewed into a later academic year's program.
 */
final class Term
{
    private const RELEASE_HISTORY_LIMIT = 300;   // newest releases on the releases page

    /** Claim status map (docs/PROCESS.md §1). */
    public const CLAIM_TRANSITIONS = [
        'submit'   => ['from' => ['Waiting for Requirements', 'Resubmit'], 'to' => 'Submitted'],
        'verify'   => ['from' => ['Submitted'],                            'to' => 'Verified'],
        'resubmit' => ['from' => ['Submitted'],                            'to' => 'Resubmit'],
        'release'  => ['from' => ['Verified'],                             'to' => 'Released'],
    ];

    public static function canClaim(string $action, string $claimStatus): bool
    {
        return in_array($claimStatus, self::CLAIM_TRANSITIONS[$action]['from'] ?? [], true);
    }

    /** Next semester: 1st -> 2nd (same A.Y.), 2nd -> 1st of the next A.Y. */
    public static function nextSemester(string $academicYear, string $semester): array
    {
        if ($semester === '1st Semester') return ['academic_year' => $academicYear, 'semester' => '2nd Semester'];
        [$a, $b] = array_map('intval', explode('-', $academicYear) + [0, 0]);
        return ['academic_year' => ($a + 1) . '-' . ($b + 1), 'semester' => '1st Semester'];
    }

    /** Previous semester: 2nd -> 1st (same A.Y.), 1st -> 2nd of the previous A.Y. */
    public static function previousSemester(string $academicYear, string $semester): array
    {
        if ($semester === '2nd Semester') return ['academic_year' => $academicYear, 'semester' => '1st Semester'];
        [$a, $b] = array_map('intval', explode('-', $academicYear) + [0, 0]);
        return ['academic_year' => ($a - 1) . '-' . ($b - 1), 'semester' => '2nd Semester'];
    }

    /**
     * The release window (term_schedules, purpose 'release') for this semester's claim:
     * ['schedule' => row|null, 'state' => TermSchedule::state(), 'can_upload' => bool, 'message' => text for the scholar].
     */
    public static function claimWindow(array $term): array
    {
        $schedule = TermSchedule::forSemester((int)$term['program_id'], $term['academic_year'], $term['semester'], 'release');
        $resubmit = $term['claim_status'] === 'Resubmit';
        return [
            'schedule'   => $schedule,
            'state'      => TermSchedule::state($schedule),
            'can_upload' => TermSchedule::allowsUpload($schedule, $resubmit),
            'message'    => TermSchedule::describeForScholar($schedule, 'release', $resubmit),
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT t.*, s.status AS scholar_status, s.scholar_code, s.program_id AS origin_program_id, s.applicant_id,
                    sp.program_name, sp.amount_per_semester, sp.min_gpa,
                    u.id AS user_id, u.full_name, u.email,
                    r.id AS release_id, r.amount AS released_amount, r.release_date, r.payment_method, r.reference_number
             FROM scholar_terms t
             JOIN scholars s ON s.id = t.scholar_id
             JOIN scholarship_programs sp ON sp.id = t.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN scholarship_releases r ON r.term_id = t.id
             WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** All semesters of a scholar, newest first, with release details. */
    public static function forScholar(int $scholarId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT t.*, r.amount AS released_amount, r.release_date, r.payment_method, r.reference_number, r.release_code
             FROM scholar_terms t
             LEFT JOIN scholarship_releases r ON r.term_id = t.id
             WHERE t.scholar_id = ?
             ORDER BY t.academic_year DESC, t.semester DESC"
        );
        $stmt->execute([$scholarId]);
        return $stmt->fetchAll();
    }

    /** The scholar's current (latest) semester. */
    public static function current(int $scholarId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM scholar_terms WHERE scholar_id = ? ORDER BY academic_year DESC, semester DESC LIMIT 1"
        );
        $stmt->execute([$scholarId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    public static function isCurrent(array $term): bool
    {
        $cur = self::current((int)$term['scholar_id']);
        return $cur && (int)$cur['id'] === (int)$term['id'];
    }

    /** Can the scholar change the CLAIM documents of this term right now? Needs the admin's release window (§4). */
    public static function claimEditable(array $term): bool
    {
        return $term['scholar_status'] === 'Active'
            && self::canClaim('submit', $term['claim_status'])
            && self::isCurrent($term)
            && self::claimWindow($term)['can_upload'];
    }

    /** When the only thing blocking the scholar is the admin's window, the window's message for them; otherwise null. */
    public static function closedReason(array $term, string $purpose): ?string
    {
        $window = $purpose === 'claim' ? self::claimWindow($term) : Renewal::window($term);
        return $window['can_upload'] ? null : $window['message'];
    }

    /**
     * The documents this claim needs (decision D4: the School ID once per academic year): only the Grades when a School
     * ID for this A.Y. is on file (Document::schoolIdOnFile — from the application, the renewal or an earlier claim of
     * the year), otherwise the School ID and the Grades.
     */
    public static function claimDocumentTypes(array $term): array
    {
        return Document::schoolIdOnFile((int)$term['applicant_id'], $term['academic_year'], (int)$term['id'])
            ? ['Grades'] : TERM_DOCUMENT_TYPES['claim'];
    }

    /**
     * The claim documents to SHOW: what it needs while it can still change; once Verified or Released, what was submitted
     * with it (a later renewal may have replaced the School ID on file since).
     */
    public static function claimDocumentsToShow(array $term, array $docs): array
    {
        if (!in_array($term['claim_status'], ['Verified', 'Released'], true)) return self::claimDocumentTypes($term);
        return array_values(array_filter(TERM_DOCUMENT_TYPES['claim'], static fn($t) => $t === 'Grades' || isset($docs[$t])));
    }

    /** A8: submit the claim once its documents (claimDocumentTypes) are uploaded. */
    public static function submitClaim(array $term, int $userId): array
    {
        if (!self::claimEditable($term)) return ['ok' => false, 'error' => self::closedReason($term, 'claim') ?? 'This claim can’t be submitted right now.'];
        $types = self::claimDocumentTypes($term);
        if (!TermDocument::ready((int)$term['id'], 'claim', $types)) {
            return ['ok' => false, 'error' => $types === ['Grades']
                ? 'Upload your college grades first.'
                : 'Upload your School ID (front and back) and your college grades first.'];
        }
        self::changeClaimStatus($term, 'submit', $userId, ['submitted_at' => date('Y-m-d H:i:s')]);
        Notification::adminEvent('Claim submitted', "{$term['full_name']} ({$term['scholar_code']}) — {$term['academic_year']} {$term['semester']} claim is waiting for verification.",
            'admin/claims/view&id=' . (int)$term['id']);
        return ['ok' => true];
    }

    /* ---------------- admin side: B7 verify, B8 release ---------------- */

    /** Release history (Releases page), newest first, at most 300; filters: program_id, academic_year ('' = all). */
    public static function releaseHistory(array $filters): array
    {
        $sql = "SELECT r.*, t.academic_year, t.semester, s.scholar_code, u.full_name, sp.program_name, st.full_name AS released_by
                FROM scholarship_releases r
                JOIN scholar_terms t ON t.id = r.term_id
                JOIN scholars s ON s.id = r.scholar_id
                JOIN scholarship_programs sp ON sp.id = t.program_id
                JOIN applicants ap ON ap.id = s.applicant_id
                JOIN users u ON u.id = ap.user_id
                LEFT JOIN users st ON st.id = r.staff_id
                WHERE 1=1";
        $params = [];
        if ($filters['program_id'] !== '')    { $sql .= " AND t.program_id = :pid"; $params['pid'] = (int)$filters['program_id']; }
        if ($filters['academic_year'] !== '') { $sql .= " AND t.academic_year = :ay"; $params['ay'] = $filters['academic_year']; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY r.release_date DESC, r.id DESC LIMIT " . self::RELEASE_HISTORY_LIMIT);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Semesters with a given claim status (queues), oldest submission first. */
    public static function byClaimStatus(string $status, ?int $programId = null): array
    {
        $sql = "SELECT t.*, s.scholar_code, s.status AS scholar_status, s.program_id AS origin_program_id, sp.program_name, sp.amount_per_semester, sp.min_gpa,
                       u.full_name, u.email,
                       (SELECT b.total_budget - b.released FROM budgets b WHERE b.program_id = t.program_id AND b.academic_year = t.academic_year) AS budget_remaining
                FROM scholar_terms t
                JOIN scholars s ON s.id = t.scholar_id
                JOIN scholarship_programs sp ON sp.id = t.program_id
                JOIN applicants ap ON ap.id = s.applicant_id
                JOIN users u ON u.id = ap.user_id
                WHERE t.claim_status = ?";
        $params = [$status];
        if ($programId) { $sql .= " AND t.program_id = ?"; $params[] = $programId; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY COALESCE(t.submitted_at, t.reviewed_at, t.created_at), t.id");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function notifyScholar(array $term, string $title, string $message, string $type): void
    {
        Notification::send((int)$term['user_id'], $title, $message, $type, url('applicant/scholarship'), sendEmail: true);
    }

    /** B7 Verified: the GWA read from the Grades is stored on the semester (the academic record). */
    public static function verifyClaim(int $termId, string $gwa, int $adminId): array
    {
        $term = self::find($termId);
        if (!$term) return ['ok' => false, 'error' => 'Semester not found.'];
        $gwa = trim($gwa);
        if (!is_numeric($gwa) || (float)$gwa < 1 || (float)$gwa > 5) {
            return ['ok' => false, 'error' => 'Enter the GWA from the Grades (1.00 to 5.00).'];
        }
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            self::changeClaimStatus($term, 'verify', $adminId, [
                'gwa' => round((float)$gwa, 2), 'remarks' => null,
                'reviewed_by' => $adminId, 'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'GWA ' . number_format((float)$gwa, 2));
            TermDocument::mark($termId, 'claim', 'Verified', null);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        self::notifyScholar($term, 'Claim verified',
            "Your claim documents for {$term['academic_year']} {$term['semester']} were verified. We will e-mail you when your cash is released.", 'success');
        return ['ok' => true];
    }

    /** B7 Resubmit (remarks required): the chosen documents (default: both) must be uploaded again. */
    public static function resubmitClaim(int $termId, string $remarks, array $types, int $adminId, bool $invalid = false): array
    {
        $term = self::find($termId);
        if (!$term) return ['ok' => false, 'error' => 'Semester not found.'];
        $remarks = trim($remarks);
        if ($remarks === '') return ['ok' => false, 'error' => 'Write what the scholar must re-upload.'];
        $required = self::claimDocumentTypes($term);   // a School ID on file isn't part of this claim
        $types = array_values(array_intersect($types, $required)) ?: $required;
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            self::changeClaimStatus($term, 'resubmit', $adminId, [
                'remarks' => $remarks, 'reviewed_by' => $adminId, 'reviewed_at' => date('Y-m-d H:i:s'),
            ], $remarks);
            TermDocument::mark($termId, 'claim', 'Resubmit', $remarks, $types);
            if ($invalid) TermDocument::invalidateNewest($termId, 'claim', $types, $remarks, $adminId);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        self::notifyScholar($term, 'Claim: please re-upload',
            "Please re-upload your " . implode(' and ', $types) . " for {$term['academic_year']} {$term['semester']}.\nRemarks: $remarks", 'warning');
        return ['ok' => true];
    }

    /**
     * B8 Release cash for a Verified semester. Blocked when there is no budget for the program + A.Y. or the
     * amount exceeds its remaining balance. One release per semester (unique term_id).
     */
    public static function release(int $termId, array $data, int $adminId): array
    {
        $term = self::find($termId);
        if (!$term) return ['ok' => false, 'error' => 'Semester not found.'];
        $amount = trim((string)($data['amount'] ?? ''));
        $date = trim((string)($data['release_date'] ?? ''));
        $method = (string)($data['payment_method'] ?? '');
        $reference = trim((string)($data['reference_number'] ?? ''));
        if (!is_numeric($amount) || (float)$amount <= 0) return ['ok' => false, 'error' => 'Amount must be more than 0.'];
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) return ['ok' => false, 'error' => 'Choose a valid release date (today or earlier).'];
        if (!in_array($method, PAYMENT_METHODS, true)) return ['ok' => false, 'error' => 'Choose a payment method.'];
        if ($method !== 'Cash' && $reference === '') return ['ok' => false, 'error' => "A reference number is required for $method."];

        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $blocker = Budget::releaseBlocker((int)$term['program_id'], $term['program_name'], $term['academic_year'], (float)$amount);
            if ($blocker) throw new DomainException($blocker);
            self::changeClaimStatus($term, 'release', $adminId, [], peso($amount) . " via $method");
            $pdo->prepare(
                "INSERT INTO scholarship_releases (release_code, scholar_id, term_id, amount, release_date, payment_method, reference_number, staff_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([generate_code('REL'), (int)$term['scholar_id'], $termId, round((float)$amount, 2), $date, $method, $reference ?: null, $adminId]);
            $releaseId = (int)$pdo->lastInsertId();
            Budget::syncReleased((int)$term['program_id'], $term['academic_year']);
            AuditLog::write('scholarship_released', 'scholarship_releases', $releaseId,
                "Released ₱" . number_format((float)$amount, 2) . " to {$term['scholar_code']} for {$term['academic_year']} {$term['semester']}");
            $secondId = self::openSecondSemester($term);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => 'This semester was already released.'];
            throw $e;
        }
        $next = $secondId
            ? "\nYour {$term['academic_year']} 2nd Semester is now open. To claim it on My Scholarship: "
              . TermSchedule::describeForScholar(TermSchedule::forSemester((int)$term['program_id'], $term['academic_year'], '2nd Semester', 'release'), 'release')
            : ($term['semester'] === '2nd Semester' ? "\nNext: renew for the next academic year on My Scholarship when the SK office opens the renewal." : '');
        self::notifyScholar($term, $secondId ? 'Cash released — 2nd Semester open' : 'Cash released',
            peso($amount) . " for {$term['academic_year']} {$term['semester']} was released on "
            . fmt_long_date($date) . " via $method" . ($reference ? " (reference no. $reference)" : '') . '.' . $next, 'success');
        return ['ok' => true, 'second_semester_opened' => $secondId !== null];
    }

    /**
     * Decision D3: no renewal between the two semesters of an academic year. When a 1st Semester's cash is released, the
     * 2nd Semester of the same program and A.Y. opens by itself (opened_via 'continuation') — for an Active scholar whose
     * latest semester it is. Call inside the release transaction. Returns the new semester's id, or null.
     */
    private static function openSecondSemester(array $term): ?int
    {
        if ($term['semester'] !== '1st Semester' || $term['scholar_status'] !== 'Active' || !self::isCurrent($term)) return null;
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO scholar_terms (scholar_id, program_id, academic_year, semester, opened_via, claim_status)
             VALUES (?, ?, ?, '2nd Semester', 'continuation', 'Waiting for Requirements')"
        )->execute([(int)$term['scholar_id'], (int)$term['program_id'], $term['academic_year']]);
        $id = (int)$pdo->lastInsertId();
        AuditLog::write('term_opened', 'scholar_terms', $id,
            "Opened {$term['academic_year']} 2nd Semester for {$term['scholar_code']} (1st Semester released)");
        return $id;
    }

    /**
     * Apply a claim transition (guarded by CLAIM_TRANSITIONS, conditional on the status the caller saw).
     * Throws DomainException with a plain message. Audit-logged.
     */
    public static function changeClaimStatus(array $term, string $action, int $actorId, array $set = [], string $note = ''): void
    {
        $from = (string)$term['claim_status'];
        if (!self::canClaim($action, $from)) {
            throw new DomainException("This action is not possible while the claim is \"$from\".");
        }
        $to = self::CLAIM_TRANSITIONS[$action]['to'];
        $cols = ['claim_status' => $to] + $set;
        $assign = implode(', ', array_map(static fn($k) => "`$k` = :$k", array_keys($cols)));
        $stmt = Database::conn()->prepare("UPDATE scholar_terms SET $assign WHERE id = :id AND claim_status = :from_status");
        $stmt->execute($cols + ['id' => (int)$term['id'], 'from_status' => $from]);
        if ($stmt->rowCount() !== 1) throw new DomainException('This claim was changed by someone else. Please reload the page.');

        AuditLog::write('claim_' . $action, 'scholar_terms', (int)$term['id'],
            "Claim {$term['academic_year']} {$term['semester']}: $from → $to" . ($note !== '' ? " — $note" : ''),
            ['claim_status' => $from], ['claim_status' => $to] + $set + ['by' => $actorId]);
    }
}
