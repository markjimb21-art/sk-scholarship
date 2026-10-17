<?php
declare(strict_types=1);

/**
 * Admin-opened submission windows (docs/CHANGE_PLAN_2.md §4). One row per program + academic year + semester + purpose:
 *   release — the cash claim (A8) of that semester: Grades + School ID;
 *   renewal — the renewal (A9) INTO that semester: Enrollment Form + School ID.
 * A scholar can submit only while the window is Open and opens_at <= now <= submission_deadline. A re-upload the admin
 * asked for (Resubmit) is allowed while the window is not Cancelled, even after the deadline (decision E2).
 * Missed deadlines are never acted on automatically — the admin extends, follows up, or ends the scholarship.
 */
final class TermSchedule
{
    public const PURPOSES = ['release', 'renewal'];
    public const STATUSES = ['Open', 'Closed', 'Cancelled'];
    /**
     * Documents a scholar submits in each window (wording used in notifications). A claim asks for the School ID only
     * when this A.Y.'s is not on file (decision D4), so the claim wording stays general; My Scholarship lists them.
     */
    public const DOCUMENTS = ['release' => 'claim documents', 'renewal' => 'Enrollment Form and School ID'];
    public const TITLES = ['release' => 'Claim', 'renewal' => 'Renewal'];
    /** Window states shown to scholars and admins. */
    public const STATE_LABELS = [
        'not_created'     => 'Not open yet',
        'not_open_yet'    => 'Opens later',
        'open'            => 'Open',
        'deadline_passed' => 'Deadline passed',
        'closed'          => 'Closed',
        'cancelled'       => 'Cancelled',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ts.*, sp.program_name, u.full_name AS created_by_name
             FROM term_schedules ts
             JOIN scholarship_programs sp ON sp.id = ts.program_id
             LEFT JOIN users u ON u.id = ts.created_by
             WHERE ts.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** The window for a program + semester + purpose, if the admin created one. */
    public static function forSemester(int $programId, string $academicYear, string $semester, string $purpose): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM term_schedules WHERE program_id = ? AND academic_year = ? AND semester = ? AND purpose = ? LIMIT 1"
        );
        $stmt->execute([$programId, $academicYear, $semester, $purpose]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    /** not_created | cancelled | closed | not_open_yet | open | deadline_passed */
    public static function state(?array $row, ?string $now = null): string
    {
        if (!$row) return 'not_created';
        if ($row['status'] === 'Cancelled') return 'cancelled';
        if ($row['status'] === 'Closed') return 'closed';
        $now ??= date('Y-m-d H:i:s');
        if ($now < $row['opens_at']) return 'not_open_yet';
        if ($now > $row['submission_deadline']) return 'deadline_passed';
        return 'open';
    }

    /**
     * Can a scholar upload/submit in this window? First submissions need the window open; a re-upload the admin
     * asked for ($isResubmit) only needs a window that exists and isn't Cancelled.
     */
    public static function allowsUpload(?array $row, bool $isResubmit): bool
    {
        $state = self::state($row);
        return $state === 'open' || ($isResubmit && $row !== null && $state !== 'cancelled');
    }

    /** One plain sentence for scholars about a window (used on My Scholarship, the dashboard and approval e-mails). */
    public static function describeForScholar(?array $row, string $purpose, bool $isResubmit = false): string
    {
        $docs = self::DOCUMENTS[$purpose];
        $when = static fn(string $dt): string => date('F j, Y g:i A', strtotime($dt));
        return match (self::state($row)) {
            'not_created'     => "Not open yet — the SK office will e-mail you when you can submit your $docs.",
            'not_open_yet'    => "Submission opens on {$when($row['opens_at'])}. Submit your $docs by {$when($row['submission_deadline'])}.",
            'open'            => "Submit your $docs by {$when($row['submission_deadline'])}.",
            'deadline_passed' => $isResubmit
                ? "Re-upload what the SK office asked for (the deadline was {$when($row['submission_deadline'])})."
                : "The deadline ({$when($row['submission_deadline'])}) has passed — contact the SK office.",
            'closed'          => $isResubmit
                ? 'Re-upload what the SK office asked for.'
                : 'Submission for this semester is closed — contact the SK office.',
            'cancelled'       => 'Submission for this semester was cancelled — contact the SK office.',
        };
    }

    /** Windows of one purpose (newest first) with affected / submitted / pending counts. */
    public static function all(string $purpose): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ts.*, sp.program_name FROM term_schedules ts JOIN scholarship_programs sp ON sp.id = ts.program_id
             WHERE ts.purpose = ? ORDER BY ts.academic_year DESC, ts.semester DESC, sp.program_name"
        );
        $stmt->execute([$purpose]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $affected = self::affectedScholars($r);
            $r['affected_count'] = count($affected);
            $r['pending_count'] = count(array_filter($affected, static fn($a) => $a['pending']));
            $r['submitted_count'] = $r['affected_count'] - $r['pending_count'];
            $r['state'] = self::state($r);
        }
        unset($r);
        return $rows;
    }

    /**
     * Active scholars a window applies to, with 'pending' = hasn't submitted yet.
     *   release: the program's semesters of this A.Y. + semester (pending while the claim is Waiting for Requirements
     *            or Resubmit);
     *   renewal into a 1st Semester (once a year, decisions D1–D3): everyone who renewed into it through this program,
     *            plus the Active scholars without a request whose latest semester is in the previous A.Y. (pending while
     *            there is no request, or it is in Resubmit). On a window made the old way on another year's program
     *            (D8), only that program's scholars. Renewal rows also carry has_request and the latest semester
     *            (latest_year, latest_semester, latest_claim) for "Did not renew" (Renewal::didNotRenew);
     *   renewal into a 2nd Semester (only windows made before D3): scholars whose 1st Semester is in the program and
     *            who sent a renewal from it (pending while it is in Resubmit).
     */
    public static function affectedScholars(array $row): array
    {
        $pdo = Database::conn();
        if ($row['purpose'] === 'renewal' && $row['semester'] === '1st Semester') {
            $program = Program::find((int)$row['program_id']);
            $legacy = !$program || $program['academic_year'] !== $row['academic_year'];
            $stmt = $pdo->prepare(
                "SELECT s.id AS scholar_id, s.scholar_code, u.id AS user_id, u.full_name, u.email,
                        COALESCE(rr.from_term_id, lt.id) AS term_id,
                        COALESCE(rr.status, CONCAT('Claim: ', lt.claim_status)) AS progress,
                        (rr.id IS NULL OR rr.status = 'Resubmit') AS pending, (rr.id IS NOT NULL) AS has_request,
                        lt.academic_year AS latest_year, lt.semester AS latest_semester, lt.claim_status AS latest_claim
                 FROM scholars s
                 JOIN scholar_terms lt ON lt.id = (SELECT x.id FROM scholar_terms x WHERE x.scholar_id = s.id
                                                   ORDER BY x.academic_year DESC, x.semester DESC LIMIT 1)
                 LEFT JOIN renewal_requests rr ON rr.scholar_id = s.id AND rr.program_id = :pid
                                              AND rr.academic_year = :ay AND rr.semester = :sem
                 JOIN applicants ap ON ap.id = s.applicant_id
                 JOIN users u ON u.id = ap.user_id
                 WHERE s.status = 'Active'
                   AND (rr.id IS NOT NULL OR (lt.academic_year = :prev_ay" . ($legacy ? ' AND lt.program_id = :own_pid' : '') . "))
                 ORDER BY u.full_name"
            );
            $params = ['pid' => (int)$row['program_id'], 'ay' => $row['academic_year'], 'sem' => $row['semester'],
                       'prev_ay' => Term::previousSemester($row['academic_year'], '1st Semester')['academic_year']];
            if ($legacy) $params['own_pid'] = (int)$row['program_id'];
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['pending'] = (bool)$r['pending']; $r['has_request'] = (bool)$r['has_request']; }
            unset($r);
            return $rows;
        }
        if ($row['purpose'] === 'release') {
            $stmt = $pdo->prepare(
                "SELECT s.id AS scholar_id, s.scholar_code, u.id AS user_id, u.full_name, u.email, t.id AS term_id,
                        t.claim_status AS progress, t.claim_status IN ('Waiting for Requirements','Resubmit') AS pending
                 FROM scholar_terms t
                 JOIN scholars s ON s.id = t.scholar_id
                 JOIN applicants ap ON ap.id = s.applicant_id
                 JOIN users u ON u.id = ap.user_id
                 WHERE t.academic_year = ? AND t.semester = ? AND t.program_id = ? AND s.status = 'Active'
                 ORDER BY u.full_name"
            );
            $stmt->execute([$row['academic_year'], $row['semester'], (int)$row['program_id']]);
        } else {
            // A renewal window into a 2nd Semester (made before plan 5): since the 1st Semester release opens the 2nd by
            // itself (decision D3), it only concerns the scholars who sent a renewal into it.
            $prev = Term::previousSemester($row['academic_year'], $row['semester']);
            $stmt = $pdo->prepare(
                "SELECT s.id AS scholar_id, s.scholar_code, u.id AS user_id, u.full_name, u.email, t.id AS term_id,
                        rr.status AS progress, rr.status = 'Resubmit' AS pending
                 FROM scholar_terms t
                 JOIN scholars s ON s.id = t.scholar_id
                 JOIN renewal_requests rr ON rr.from_term_id = t.id
                 JOIN applicants ap ON ap.id = s.applicant_id
                 JOIN users u ON u.id = ap.user_id
                 WHERE t.academic_year = ? AND t.semester = ? AND t.program_id = ? AND s.status = 'Active'
                 ORDER BY u.full_name"
            );
            $stmt->execute([$prev['academic_year'], $prev['semester'], (int)$row['program_id']]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r['pending'] = (bool)$r['pending'];
        unset($r);
        return $rows;
    }

    /**
     * Windows (not Cancelled) whose deadline or planned date falls in [$from, $to] (dates), for the calendar;
     * optionally of one program.
     */
    public static function inRange(string $from, string $to, ?int $programId = null): array
    {
        $sql = "SELECT ts.*, sp.program_name FROM term_schedules ts JOIN scholarship_programs sp ON sp.id = ts.program_id
                WHERE ts.status <> 'Cancelled'
                  AND (DATE(ts.submission_deadline) BETWEEN ? AND ? OR ts.planned_date BETWEEN ? AND ?)";
        $params = [$from, $to, $from, $to];
        if ($programId) { $sql .= " AND ts.program_id = ?"; $params[] = $programId; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY ts.submission_deadline");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Scholars who hadn't submitted when a (not cancelled) window's deadline passed — for the admin to decide. */
    public static function missedDeadline(string $purpose): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ts.*, sp.program_name FROM term_schedules ts JOIN scholarship_programs sp ON sp.id = ts.program_id
             WHERE ts.purpose = ? AND ts.status <> 'Cancelled' AND ts.submission_deadline < ?
             ORDER BY ts.submission_deadline DESC"
        );
        $stmt->execute([$purpose, date('Y-m-d H:i:s')]);
        $out = [];
        foreach ($stmt->fetchAll() as $sched) {
            foreach (self::affectedScholars($sched) as $a) {
                if ($a['pending']) $out[] = $a + ['schedule' => $sched];
            }
        }
        return $out;
    }

    /**
     * Semesters that need a window and don't have one yet: [['program_id','program_name','academic_year','semester','n']].
     *   release: Active scholars' semesters still Waiting for Requirements / Resubmit;
     *   renewal: the 1st Semester of the next A.Y. for Active scholars whose current semester is a 2nd Semester with the
     *            claim Verified or Released and no renewal request yet, on the program of that A.Y. (program_id null =
     *            that A.Y. has no program yet — create it first). Renewal::window's lookup decides "has a window".
     */
    public static function needed(string $purpose): array
    {
        $pdo = Database::conn();
        if ($purpose === 'release') {
            $rows = $pdo->query(
                "SELECT t.program_id, sp.program_name, t.academic_year, t.semester, COUNT(*) AS n
                 FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id JOIN scholarship_programs sp ON sp.id = t.program_id
                 WHERE s.status = 'Active' AND t.claim_status IN ('Waiting for Requirements','Resubmit')
                 GROUP BY t.program_id, sp.program_name, t.academic_year, t.semester"
            )->fetchAll();
            return array_values(array_filter($rows, static fn($n) =>
                !self::forSemester((int)$n['program_id'], $n['academic_year'], $n['semester'], 'release')));
        }
        $terms = $pdo->query(
            "SELECT t.* FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id
             LEFT JOIN renewal_requests rr ON rr.from_term_id = t.id
             WHERE s.status = 'Active' AND t.semester = '2nd Semester' AND t.claim_status IN ('Verified','Released') AND rr.id IS NULL
               AND NOT EXISTS (SELECT 1 FROM scholar_terms later WHERE later.scholar_id = t.scholar_id
                               AND (later.academic_year > t.academic_year
                                    OR (later.academic_year = t.academic_year AND later.semester > t.semester)))"
        )->fetchAll();
        $grouped = [];
        foreach ($terms as $t) {
            if (Renewal::window($t)['schedule']) continue;
            $next = Term::nextSemester($t['academic_year'], $t['semester']);
            $program = Program::forAcademicYear($next['academic_year']);
            $grouped[$next['academic_year']] ??= ['program_id' => $program['id'] ?? null, 'program_name' => $program['program_name'] ?? null,
                                                 'academic_year' => $next['academic_year'], 'semester' => $next['semester'], 'n' => 0];
            $grouped[$next['academic_year']]['n']++;
        }
        return array_values($grouped);
    }

    /** Accepts the datetime-local format (Y-m-d\TH:i) and Y-m-d H:i[:s]. Returns 'Y-m-d H:i:s' or null. */
    private static function parseDateTime(string $v): ?string
    {
        $v = trim(str_replace('T', ' ', $v));
        foreach (['Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
            $d = DateTime::createFromFormat('!' . $fmt, $v);
            if ($d && $d->format($fmt) === $v) return $d->format('Y-m-d H:i:s');
        }
        return null;
    }

    /**
     * Create (program, A.Y., semester and purpose fixed from then on) or edit a window.
     * Creating notifies every affected scholar who hasn't submitted; a changed deadline resets the TERM_SCHEDULE_REMINDER_HOURS reminder and
     * notifies them again; cancelling notifies them too. $notify = false: nobody is notified — for a caller inside a
     * transaction (ProgramSetup), which notifies after its commit. Returns ['ok'=>true,'id','notified'] or ['ok'=>false,'error'].
     */
    public static function save(array $data, ?int $id, int $adminId, bool $notify = true): array
    {
        $old = $id ? self::find($id) : null;
        if ($id && !$old) return ['ok' => false, 'error' => 'Schedule not found.'];

        $purpose = $old ? $old['purpose'] : (string)($data['purpose'] ?? '');
        if (!in_array($purpose, self::PURPOSES, true)) return ['ok' => false, 'error' => 'Invalid schedule type.'];
        // Program + A.Y. + semester: chosen for a new window, and changeable on an existing one only while nobody has
        // submitted under it (after that it belongs to their record — close or cancel it instead).
        if ($old && self::submittedCount($old) > 0) {
            [$programId, $year, $semester] = [(int)$old['program_id'], $old['academic_year'], $old['semester']];
        } else {
            $programId = (int)($data['program_id'] ?? $old['program_id'] ?? 0);
            $year = trim((string)($data['academic_year'] ?? $old['academic_year'] ?? ''));
            $semester = (string)($data['semester'] ?? $old['semester'] ?? '');
            $program = Program::find($programId);
            if (!$program) return ['ok' => false, 'error' => 'Choose a scholarship program.'];
            if ($purpose === 'renewal') {
                // Renewal is once a year, into the 1st Semester of the program's own academic year (decisions D1, D3).
                // A window made the old way keeps its target while its program stays the same (D8).
                [$year, $semester] = $old && (int)$old['program_id'] === $programId
                    ? [$old['academic_year'], $old['semester']] : [$program['academic_year'], '1st Semester'];
            }
            if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int)$m[2] !== (int)$m[1] + 1) {
                return ['ok' => false, 'error' => 'Academic year must look like 2026-2027.'];
            }
            if (!in_array($semester, SEMESTERS, true)) return ['ok' => false, 'error' => 'Choose a semester.'];
        }
        $targetChanged = $old && [(int)$old['program_id'], $old['academic_year'], $old['semester']] !== [$programId, $year, $semester];

        $opens = self::parseDateTime((string)($data['opens_at'] ?? ''));
        $deadline = self::parseDateTime((string)($data['submission_deadline'] ?? ''));
        if (!$opens) return ['ok' => false, 'error' => 'Enter when submission opens.'];
        if (!$deadline) return ['ok' => false, 'error' => 'Enter the submission deadline.'];
        if ($deadline <= $opens) return ['ok' => false, 'error' => 'The deadline must be after the opening date.'];
        $deadlineChanged = !$old || $targetChanged || $old['submission_deadline'] !== $deadline;
        if ($deadlineChanged && $deadline <= date('Y-m-d H:i:s')) return ['ok' => false, 'error' => 'The deadline must be in the future.'];

        $planned = trim((string)($data['planned_date'] ?? ''));
        if ($planned !== '') {
            $pd = DateTime::createFromFormat('!Y-m-d', $planned);
            if (!$pd || $pd->format('Y-m-d') !== $planned) return ['ok' => false, 'error' => 'Choose a valid planned date.'];
            if ($planned < substr($deadline, 0, 10)) {
                return ['ok' => false, 'error' => ($purpose === 'release' ? 'The planned release date' : 'The semester start date')
                    . ' can’t be before the submission deadline.'];
            }
        }
        $status = $old ? (string)($data['status'] ?? $old['status']) : 'Open';
        if (!in_array($status, self::STATUSES, true)) return ['ok' => false, 'error' => 'Invalid status.'];
        $notes = trim((string)($data['notes'] ?? ''));

        $values = [
            'opens_at' => $opens, 'submission_deadline' => $deadline, 'planned_date' => $planned ?: null,
            'status' => $status, 'notes' => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
        ];
        $pdo = Database::conn();
        $label = self::TITLES[$purpose] . " window {$year} {$semester}";
        if ($old) {
            $reset = $deadlineChanged ? ', deadline_reminder_sent_at = NULL' : '';
            $target = ['program_id' => $programId, 'academic_year' => $year, 'semester' => $semester];
            try {
                $pdo->prepare(
                    "UPDATE term_schedules SET program_id = :program_id, academic_year = :academic_year, semester = :semester,
                            opens_at = :opens_at, submission_deadline = :submission_deadline,
                            planned_date = :planned_date, status = :status, notes = :notes$reset WHERE id = :id"
                )->execute($values + $target + ['id' => $id]);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    return ['ok' => false, 'error' => 'A ' . lcfirst($label) . ' already exists for this program — edit that one instead.'];
                }
                throw $e;
            }
            AuditLog::write('term_schedule_updated', 'term_schedules', $id,
                $targetChanged ? "$label updated (was {$old['academic_year']} {$old['semester']}, {$old['program_name']})" : "$label updated",
                array_intersect_key($old, $values + $target), $values + $target);
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline,
                                                 planned_date, status, notes, created_by)
                     VALUES (:program_id, :academic_year, :semester, :purpose, :opens_at, :submission_deadline,
                             :planned_date, :status, :notes, :created_by)"
                )->execute($values + ['program_id' => $programId, 'academic_year' => $year, 'semester' => $semester,
                                      'purpose' => $purpose, 'created_by' => $adminId]);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    return ['ok' => false, 'error' => 'A ' . lcfirst($label) . ' already exists for this program — edit it instead.'];
                }
                throw $e;
            }
            $id = (int)$pdo->lastInsertId();
            AuditLog::write('term_schedule_created', 'term_schedules', $id, "$label created", null, $values);
        }

        $row = self::find($id);
        if (!$notify) return ['ok' => true, 'id' => $id, 'notified' => 0];   // the caller notifies after its commit
        if ($targetChanged) {
            // A different semester (or program): like cancelling the old window and creating the new one.
            $notified = $old['status'] !== 'Cancelled' ? self::notifyAffected($old, 'cancelled') : 0;
            if ($status === 'Open') $notified += self::notifyAffected($row, 'created');
            return ['ok' => true, 'id' => $id, 'notified' => $notified];
        }
        $kind = null;
        if (!$old) $kind = 'created';
        elseif ($status === 'Cancelled' && $old['status'] !== 'Cancelled') $kind = 'cancelled';
        elseif ($status === 'Open' && ($deadlineChanged || $old['status'] !== 'Open')) $kind = 'changed';
        $notified = $kind ? self::notifyAffected($row, $kind) : 0;
        return ['ok' => true, 'id' => $id, 'notified' => $notified];
    }

    /** How many scholars already submitted under a window (affectedScholars minus the pending ones). */
    public static function submittedCount(array $row): int
    {
        return count(array_filter(self::affectedScholars($row), static fn($a) => !$a['pending']));
    }

    /**
     * Delete a window nobody has submitted under (wrong program or semester, or not needed). Scholars it was announced to
     * are told it was cancelled, unless it already was. Audit-logged. After a submission the window belongs to the
     * scholars' record (and the on-time figures), so it can only be closed or cancelled.
     * Returns ['ok'=>true,'notified','purpose'] or ['ok'=>false,'error'].
     */
    public static function delete(int $id, int $adminId): array
    {
        $row = self::find($id);
        if (!$row) return ['ok' => false, 'error' => 'Schedule not found.'];
        $submitted = self::submittedCount($row);
        if ($submitted > 0) {
            return ['ok' => false, 'error' => "$submitted scholar(s) already submitted under this window, so it is kept. Set it to Closed or Cancelled instead."];
        }
        $notified = $row['status'] !== 'Cancelled' ? self::notifyAffected($row, 'cancelled') : 0;
        Database::conn()->prepare('DELETE FROM term_schedules WHERE id = ?')->execute([$id]);
        AuditLog::write('term_schedule_deleted', 'term_schedules', $id,
            self::TITLES[$row['purpose']] . " window {$row['academic_year']} {$row['semester']} ({$row['program_name']}) deleted",
            array_intersect_key($row, array_flip(['program_id', 'academic_year', 'semester', 'purpose', 'opens_at', 'submission_deadline', 'status'])),
            ['by' => $adminId]);
        return ['ok' => true, 'notified' => $notified, 'purpose' => $row['purpose']];
    }

    /**
     * In-app + e-mail to every affected scholar who hasn't submitted yet. $kind: created | changed | cancelled | reminder.
     * Returns how many scholars were notified.
     */
    public static function notifyAffected(array $row, string $kind): int
    {
        $purpose = $row['purpose'];
        $docs = self::DOCUMENTS[$purpose];
        $sem = "{$row['academic_year']} {$row['semester']}";
        $deadline = date('F j, Y g:i A', strtotime($row['submission_deadline']));
        $what = $purpose === 'release' ? "your cash claim for $sem" : "your renewal for $sem";
        $extra = '';
        if ($row['opens_at'] > date('Y-m-d H:i:s')) $extra .= "\nSubmission opens on " . date('F j, Y g:i A', strtotime($row['opens_at'])) . '.';
        if ($row['planned_date']) {
            $extra .= "\n" . ($purpose === 'release' ? 'Planned release date: ' : 'Semester starts: ')
                . fmt_long_date($row['planned_date']) . '.';
        }
        [$title, $message, $type] = match ($kind) {
            'created'   => [self::TITLES[$purpose] . ' open: submit by ' . date('M j', strtotime($row['submission_deadline'])),
                            "Submit your $docs for $what by $deadline on My Scholarship.$extra", 'info'],
            'changed'   => [self::TITLES[$purpose] . ' deadline changed',
                            "The deadline for $what is now $deadline. Submit your $docs on My Scholarship.$extra", 'info'],
            'cancelled' => [self::TITLES[$purpose] . ' window cancelled',
                            "The submission window for $what was cancelled. The SK office will contact you.", 'warning'],
            'reminder'  => [self::TITLES[$purpose] . ' reminder: deadline ' . date('M j', strtotime($row['submission_deadline'])),
                            "Reminder: submit your $docs for $what by $deadline on My Scholarship.", 'warning'],
        };
        $count = 0;
        foreach (self::affectedScholars($row) as $a) {
            if (!$a['pending']) continue;
            Notification::send((int)$a['user_id'], $title, $message, $type, url('applicant/scholarship'), sendEmail: true);
            $count++;
        }
        AuditLog::write('term_schedule_notified', 'term_schedules', (int)$row['id'],
            self::TITLES[$purpose] . " window $sem: '$kind' sent to $count scholar(s)");
        return $count;
    }

    /** Open windows whose deadline is within $hours and still ahead, with no reminder sent yet (bin/send_reminders.php). */
    public static function dueForReminder(string $now, int $hours): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM term_schedules
             WHERE status = 'Open' AND deadline_reminder_sent_at IS NULL
               AND opens_at <= ? AND submission_deadline > ? AND submission_deadline <= ?
             ORDER BY submission_deadline"
        );
        $stmt->execute([$now, $now, date('Y-m-d H:i:s', strtotime($now) + $hours * 3600)]);
        return array_values(array_filter(array_map(static fn($id) => self::find((int)$id), $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    /** Claim a window's deadline reminder before sending it; false when another run already took it. */
    public static function claimDeadlineReminder(int $id, string $now): bool
    {
        $stmt = Database::conn()->prepare("UPDATE term_schedules SET deadline_reminder_sent_at = ? WHERE id = ? AND deadline_reminder_sent_at IS NULL");
        $stmt->execute([$now, $id]);
        return $stmt->rowCount() === 1;
    }

    /** Give a claimed deadline reminder back after a failure, so the next run retries. */
    public static function releaseDeadlineReminder(int $id): void
    {
        Database::conn()->prepare("UPDATE term_schedules SET deadline_reminder_sent_at = NULL WHERE id = ?")->execute([$id]);
    }

    /** Academic years offered in the form: every known year plus the one after the latest (renewals go into it). */
    public static function academicYearOptions(): array
    {
        $years = Budget::academicYears();
        if ($years) {
            [$a, $b] = array_map('intval', explode('-', $years[0]) + [0, 0]);
            if ($a > 0) array_unshift($years, ($a + 1) . '-' . ($b + 1));
        }
        return array_values(array_unique($years));
    }
}
