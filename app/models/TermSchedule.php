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
    /** Documents a scholar submits in each window (wording used in notifications). */
    public const DOCUMENTS = ['release' => 'Grades and School ID', 'renewal' => 'Enrollment Form and School ID'];
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
            $r['state'] = self::state($r);
        }
        unset($r);
        return $rows;
    }

    /**
     * Active scholars a window applies to, with 'pending' = hasn't submitted yet.
     *   release: scholars of the program with a semester row for this A.Y. + semester (pending while the claim is
     *            Waiting for Requirements or Resubmit);
     *   renewal: scholars of the program whose semester BEFORE the target exists (pending while there is no renewal
     *            request from it, or it is in Resubmit).
     */
    public static function affectedScholars(array $row): array
    {
        $pdo = Database::conn();
        if ($row['purpose'] === 'release') {
            $stmt = $pdo->prepare(
                "SELECT s.id AS scholar_id, s.scholar_code, u.id AS user_id, u.full_name, u.email, t.id AS term_id,
                        t.claim_status AS progress, t.claim_status IN ('Waiting for Requirements','Resubmit') AS pending
                 FROM scholars s
                 JOIN scholar_terms t ON t.scholar_id = s.id AND t.academic_year = ? AND t.semester = ?
                 JOIN applicants ap ON ap.id = s.applicant_id
                 JOIN users u ON u.id = ap.user_id
                 WHERE s.program_id = ? AND s.status = 'Active'
                 ORDER BY u.full_name"
            );
            $stmt->execute([$row['academic_year'], $row['semester'], (int)$row['program_id']]);
        } else {
            $prev = Term::previousSemester($row['academic_year'], $row['semester']);
            $stmt = $pdo->prepare(
                "SELECT s.id AS scholar_id, s.scholar_code, u.id AS user_id, u.full_name, u.email, t.id AS term_id,
                        COALESCE(rr.status, CONCAT('Claim: ', t.claim_status)) AS progress,
                        (rr.id IS NULL OR rr.status = 'Resubmit') AS pending
                 FROM scholars s
                 JOIN scholar_terms t ON t.scholar_id = s.id AND t.academic_year = ? AND t.semester = ?
                 LEFT JOIN renewal_requests rr ON rr.from_term_id = t.id
                 JOIN applicants ap ON ap.id = s.applicant_id
                 JOIN users u ON u.id = ap.user_id
                 WHERE s.program_id = ? AND s.status = 'Active'
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
     * Semesters that need a window and don't have one yet: [['program_id','program_name','academic_year','semester','count']].
     *   release: Active scholars' semesters still Waiting for Requirements / Resubmit;
     *   renewal: the semester after an Active scholar's current, Released semester with no renewal request yet.
     */
    public static function needed(string $purpose): array
    {
        $pdo = Database::conn();
        $need = [];
        if ($purpose === 'release') {
            $rows = $pdo->query(
                "SELECT s.program_id, sp.program_name, t.academic_year, t.semester, COUNT(*) AS n
                 FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id JOIN scholarship_programs sp ON sp.id = s.program_id
                 WHERE s.status = 'Active' AND t.claim_status IN ('Waiting for Requirements','Resubmit')
                 GROUP BY s.program_id, sp.program_name, t.academic_year, t.semester"
            )->fetchAll();
            foreach ($rows as $r) $need[] = $r;
        } else {
            $rows = $pdo->query(
                "SELECT s.program_id, sp.program_name, t.academic_year, t.semester
                 FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id JOIN scholarship_programs sp ON sp.id = s.program_id
                 LEFT JOIN renewal_requests rr ON rr.from_term_id = t.id
                 WHERE s.status = 'Active' AND t.claim_status = 'Released' AND rr.id IS NULL
                   AND NOT EXISTS (SELECT 1 FROM scholar_terms later WHERE later.scholar_id = t.scholar_id
                                   AND (later.academic_year > t.academic_year
                                        OR (later.academic_year = t.academic_year AND later.semester > t.semester)))"
            )->fetchAll();
            $grouped = [];
            foreach ($rows as $r) {
                $next = Term::nextSemester($r['academic_year'], $r['semester']);
                $key = $r['program_id'] . '|' . $next['academic_year'] . '|' . $next['semester'];
                $grouped[$key] ??= ['program_id' => $r['program_id'], 'program_name' => $r['program_name'],
                                    'academic_year' => $next['academic_year'], 'semester' => $next['semester'], 'n' => 0];
                $grouped[$key]['n']++;
            }
            $need = array_values($grouped);
        }
        return array_values(array_filter($need, static fn($n) =>
            !self::forSemester((int)$n['program_id'], $n['academic_year'], $n['semester'], $purpose)));
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
     * Creating notifies every affected scholar who hasn't submitted; a changed deadline resets the 48 h reminder and
     * notifies them again; cancelling notifies them too. Returns ['ok'=>true,'id','notified'] or ['ok'=>false,'error'].
     */
    public static function save(array $data, ?int $id, int $adminId): array
    {
        $old = $id ? self::find($id) : null;
        if ($id && !$old) return ['ok' => false, 'error' => 'Schedule not found.'];

        if ($old) {
            $purpose = $old['purpose'];
            $programId = (int)$old['program_id'];
            $year = $old['academic_year'];
            $semester = $old['semester'];
        } else {
            $purpose = (string)($data['purpose'] ?? '');
            $programId = (int)($data['program_id'] ?? 0);
            $year = trim((string)($data['academic_year'] ?? ''));
            $semester = (string)($data['semester'] ?? '');
            if (!in_array($purpose, self::PURPOSES, true)) return ['ok' => false, 'error' => 'Invalid schedule type.'];
            if (!Program::find($programId)) return ['ok' => false, 'error' => 'Choose a scholarship program.'];
            if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int)$m[2] !== (int)$m[1] + 1) {
                return ['ok' => false, 'error' => 'Academic year must look like 2026-2027.'];
            }
            if (!in_array($semester, SEMESTERS, true)) return ['ok' => false, 'error' => 'Choose a semester.'];
        }

        $opens = self::parseDateTime((string)($data['opens_at'] ?? ''));
        $deadline = self::parseDateTime((string)($data['submission_deadline'] ?? ''));
        if (!$opens) return ['ok' => false, 'error' => 'Enter when submission opens.'];
        if (!$deadline) return ['ok' => false, 'error' => 'Enter the submission deadline.'];
        if ($deadline <= $opens) return ['ok' => false, 'error' => 'The deadline must be after the opening date.'];
        $deadlineChanged = !$old || $old['submission_deadline'] !== $deadline;
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
            $pdo->prepare(
                "UPDATE term_schedules SET opens_at = :opens_at, submission_deadline = :submission_deadline,
                        planned_date = :planned_date, status = :status, notes = :notes$reset WHERE id = :id"
            )->execute($values + ['id' => $id]);
            AuditLog::write('term_schedule_updated', 'term_schedules', $id, "$label updated",
                array_intersect_key($old, $values), $values);
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
        $kind = null;
        if (!$old) $kind = 'created';
        elseif ($status === 'Cancelled' && $old['status'] !== 'Cancelled') $kind = 'cancelled';
        elseif ($status === 'Open' && ($deadlineChanged || $old['status'] !== 'Open')) $kind = 'changed';
        $notified = $kind ? self::notifyAffected($row, $kind) : 0;
        return ['ok' => true, 'id' => $id, 'notified' => $notified];
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
                . date('F j, Y', strtotime($row['planned_date'])) . '.';
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
