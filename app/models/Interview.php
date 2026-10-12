<?php
declare(strict_types=1);

/**
 * Interview schedules, assignments (attendance) and rubric scores (B3–B4), plus the B5 ranking.
 * One interview_assignments row per scheduling attempt; an application's latest row is its current one.
 * A first no-show keeps its row and sends the application back to Qualified; the next row then has
 * reschedule_count = 1, and a no-show on it rejects the application.
 */
final class Interview
{
    /* ---------------- reading ---------------- */

    /* ---- Interview reminders (bin/send_reminders.php): a row is CLAIMED before its reminder is sent, so it is sent once ---- */

    /** Still-waiting interview attempts starting after $from and no later than $until, not yet reminded. */
    public static function remindersDue(string $from, string $until): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.id, ia.application_id, ia.reschedule_count, s.interview_date, s.start_time, s.end_time, s.venue,
                    a.application_code, ap.user_id
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             WHERE ia.attendance = 'Scheduled'
               AND a.status = 'Interview Scheduled'
               AND s.status <> 'Cancelled'
               AND ia.reminder_sent_at IS NULL
               AND TIMESTAMP(s.interview_date, s.start_time) > ?
               AND TIMESTAMP(s.interview_date, s.start_time) <= ?
             ORDER BY s.interview_date, s.start_time"
        );
        $stmt->execute([$from, $until]);
        return $stmt->fetchAll();
    }

    /** Claim one reminder; false when another run already took it. */
    public static function claimReminder(int $assignmentId, string $now): bool
    {
        $stmt = Database::conn()->prepare("UPDATE interview_assignments SET reminder_sent_at = ? WHERE id = ? AND reminder_sent_at IS NULL");
        $stmt->execute([$now, $assignmentId]);
        return $stmt->rowCount() === 1;
    }

    /** Give a claimed reminder back after a failure, so the next run retries. */
    public static function releaseReminder(int $assignmentId): void
    {
        Database::conn()->prepare("UPDATE interview_assignments SET reminder_sent_at = NULL WHERE id = ?")->execute([$assignmentId]);
    }

    /** How many interviews the application has missed (No-show attempts). */
    public static function missedCount(int $applicationId): int
    {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM interview_assignments WHERE application_id = ? AND attendance = 'No-show'");
        $stmt->execute([$applicationId]);
        return (int)$stmt->fetchColumn();
    }

    /** The venue of the program's most recent schedule ('' when none), to pre-fill a new schedule. */
    public static function lastVenue(int $programId): string
    {
        $stmt = Database::conn()->prepare("SELECT venue FROM interview_schedules WHERE program_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$programId]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /** Every interview attempt of an application with its schedule and score, oldest first (application page). */
    public static function attemptsForApplication(int $applicationId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.status AS schedule_status, ev.total_score
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             WHERE ia.application_id = ? ORDER BY ia.id"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    /** The application's current (latest) interview assignment with its schedule, if any. */
    public static function assignmentByApplication(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.status AS schedule_status
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             WHERE ia.application_id = ? ORDER BY ia.id DESC LIMIT 1"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetch() ?: null;
    }

    public static function assignment(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.program_id, s.status AS schedule_status,
                    a.status AS app_status, a.application_code, u.full_name
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE ia.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function evaluation(int $assignmentId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT e.*, u.full_name AS interviewer_name
             FROM interview_evaluations e
             LEFT JOIN users u ON u.id = e.interviewer_id
             WHERE e.assignment_id = ? LIMIT 1"
        );
        $stmt->execute([$assignmentId]);
        return $stmt->fetch() ?: null;
    }

    /** "Monday, October 5, 2026, 9:00 AM – 11:00 AM at <venue>" — used in notifications and the dashboard. */
    public static function describe(array $row): string
    {
        return date('l, F j, Y', strtotime($row['interview_date'])) . ', '
            . date('g:i A', strtotime($row['start_time'])) . ' – ' . date('g:i A', strtotime($row['end_time']))
            . ' at ' . $row['venue'];
    }

    public static function schedule(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*, sp.program_name, sp.academic_year,
                    (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id = s.id) AS assigned_count
             FROM interview_schedules s
             JOIN scholarship_programs sp ON sp.id = s.program_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Schedules of a program with counts (assigned = every attempt placed on it; it uses capacity). */
    public static function schedulesByProgram(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*,
                    COUNT(ia.id) AS assigned_count,
                    SUM(ia.attendance = 'Scheduled') AS waiting_count,
                    SUM(ia.attendance = 'Attended')  AS attended_count,
                    SUM(ia.attendance = 'No-show')   AS noshow_count
             FROM interview_schedules s
             LEFT JOIN interview_assignments ia ON ia.schedule_id = s.id
             WHERE s.program_id = ?
             GROUP BY s.id
             ORDER BY s.interview_date DESC, s.start_time DESC"
        );
        $stmt->execute([$programId]);
        return $stmt->fetchAll();
    }

    public static function assignmentsBySchedule(int $scheduleId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, a.id AS application_id, a.application_code, a.status AS app_status,
                    u.full_name, u.email, u.contact_number, ev.total_score
             FROM interview_assignments ia
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             WHERE ia.schedule_id = ?
             ORDER BY u.full_name"
        );
        $stmt->execute([$scheduleId]);
        return $stmt->fetchAll();
    }

    /** Qualified applications of a program waiting for a schedule (missed_before = 1 after a first no-show). */
    public static function qualifiedWithoutSchedule(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.qualified_at, u.full_name, u.email,
                    (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.application_id = a.id AND ia.attendance = 'No-show') AS missed_before
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE a.program_id = ? AND a.status = 'Qualified'
             ORDER BY a.qualified_at, a.id"
        );
        $stmt->execute([$programId]);
        return $stmt->fetchAll();
    }

    /* ---------------- schedules (B3) ---------------- */

    /** Bootstrap colour per displayed schedule status — shared by the Interviews list and the calendar. */
    public const STATUS_COLORS = ['Open' => 'success', 'Full' => 'warning', 'Done' => 'secondary', 'Cancelled' => 'danger'];

    /**
     * What a schedule shows as on the Interviews list and the calendar: Cancelled, Done (date passed), Full (assigned
     * reached capacity) or Open. Needs a row with status, interview_date, assigned_count and max_slots.
     */
    public static function displayStatus(array $schedule): string
    {
        if ($schedule['status'] === 'Cancelled') return 'Cancelled';
        if ($schedule['interview_date'] < date('Y-m-d')) return 'Done';
        return (int)$schedule['assigned_count'] >= (int)$schedule['max_slots'] ? 'Full' : 'Open';
    }

    /**
     * Interviews on one day (Open schedules), earliest first, each with its attempts:
     * [schedule fields + program_name, 'attempts' => [[id, attendance, reschedule_count, application_id, application_code,
     * app_status, user_id, full_name]], 'unmarked' => attempts still Scheduled]. Used by the dashboard and the daily digest.
     */
    public static function day(string $date): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare(
            "SELECT s.*, sp.program_name FROM interview_schedules s JOIN scholarship_programs sp ON sp.id = s.program_id
             WHERE s.interview_date = ? AND s.status = 'Open' ORDER BY s.start_time, s.id"
        );
        $stmt->execute([$date]);
        $schedules = $stmt->fetchAll();
        $attempts = $pdo->prepare(
            "SELECT ia.id, ia.attendance, ia.reschedule_count, a.id AS application_id, a.application_code, a.status AS app_status,
                    u.id AS user_id, u.full_name
             FROM interview_assignments ia
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE ia.schedule_id = ? ORDER BY u.full_name"
        );
        foreach ($schedules as &$s) {
            $attempts->execute([(int)$s['id']]);
            $s['attempts'] = $attempts->fetchAll();
            $s['unmarked'] = count(array_filter($s['attempts'], static fn($x) => $x['attendance'] === 'Scheduled'));
        }
        unset($s);
        return $schedules;
    }

    /** Schedules dated in [$from, $to] with program name and assigned count (calendar), optionally of one program. */
    public static function schedulesInRange(string $from, string $to, ?int $programId = null): array
    {
        $sql = "SELECT s.*, sp.program_name, COUNT(ia.id) AS assigned_count
                FROM interview_schedules s
                JOIN scholarship_programs sp ON sp.id = s.program_id
                LEFT JOIN interview_assignments ia ON ia.schedule_id = s.id
                WHERE s.interview_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($programId) { $sql .= " AND s.program_id = ?"; $params[] = $programId; }
        $stmt = Database::conn()->prepare($sql . " GROUP BY s.id ORDER BY s.interview_date, s.start_time");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Interview blocks offered as suggestions (Mon–Sat, lunch hour skipped). */
    public const SUGGESTION_BLOCKS = [['08:00', '10:00'], ['10:00', '12:00'], ['13:00', '15:00'], ['15:00', '17:00']];
    /** Days the suggestions may reach (Settings → interview_suggestion_window_days, 7–180, default 60). */
    public static function suggestionWindowDays(): int
    {
        return max(7, min(180, (int)setting('interview_suggestion_window_days', '60')));
    }

    /** Holidays in [$from, $to] as [Y-m-d => name]. */
    public static function holidays(string $from, string $to): array
    {
        $stmt = Database::conn()->prepare("SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Ranked interview slots for a program — a suggestion only; the admin can still pick any time. ONE function for the
     * New-schedule form, the reschedule page and the applicant's slot picker. In order:
     *   1. existing Open schedules of THIS program, from tomorrow, that still have free capacity (soonest first);
     *   2. new free 2-hour blocks (SUGGESTION_BLOCKS), Monday–Saturday, from the day after application_end (or tomorrow),
     *      skipping holidays, dates on which ANOTHER program already has an interview day, and blocks that overlap any
     *      Open schedule.
     * Window end = the later of application_end and today + suggestionWindowDays() (so reschedules late in the cycle still
     * get suggestions). $withSchedules = false leaves out group 1 (e.g. for a form that creates a new schedule).
     * Each item: ['kind' => 'schedule'|'block', 'schedule_id' => int|null, 'date','start','end' (H:i), 'venue' => ?string,
     * 'free' => ?int, 'label']. Returns [] with $why set when there is nothing to suggest.
     */
    public static function suggestSlots(int $programId, int $limit = 5, ?string &$why = null, bool $withSchedules = true): array
    {
        $why = null;
        $program = Program::find($programId);
        if (!$program) { $why = 'Program not found.'; return []; }
        $days = self::suggestionWindowDays();
        $tomorrow = new DateTimeImmutable('tomorrow');
        $end = new DateTimeImmutable($program['application_end']);
        $last = max($end, new DateTimeImmutable('today'))->modify("+$days days");
        $firstBlock = max($end->modify('+1 day'), $tomorrow);
        $range = [$tomorrow->format('Y-m-d'), $last->format('Y-m-d')];
        $pdo = Database::conn();
        $out = [];

        if ($withSchedules) {
            $stmt = $pdo->prepare(
                "SELECT s.id, s.interview_date, s.start_time, s.end_time, s.venue, s.max_slots, COUNT(ia.id) AS assigned
                 FROM interview_schedules s LEFT JOIN interview_assignments ia ON ia.schedule_id = s.id
                 WHERE s.program_id = ? AND s.status = 'Open' AND s.interview_date BETWEEN ? AND ?
                 GROUP BY s.id HAVING assigned < s.max_slots ORDER BY s.interview_date, s.start_time"
            );
            $stmt->execute([$programId, ...$range]);
            foreach ($stmt->fetchAll() as $s) {
                $free = (int)$s['max_slots'] - (int)$s['assigned'];
                $out[] = [
                    'kind' => 'schedule', 'schedule_id' => (int)$s['id'], 'date' => $s['interview_date'],
                    'start' => substr($s['start_time'], 0, 5), 'end' => substr($s['end_time'], 0, 5), 'venue' => $s['venue'], 'free' => $free,
                    'label' => date('D, M j', strtotime($s['interview_date'])) . ' · ' . date('g:i', strtotime($s['start_time']))
                        . '–' . date('g:i A', strtotime($s['end_time'])) . " · {$s['venue']} ($free open)",
                ];
                if (count($out) >= $limit) return $out;
            }
        }

        $stmt = $pdo->prepare("SELECT interview_date, start_time, end_time, program_id FROM interview_schedules
                               WHERE status = 'Open' AND interview_date BETWEEN ? AND ?");
        $stmt->execute($range);
        $busy = [];
        $otherProgramDays = [];
        foreach ($stmt->fetchAll() as $s) {
            $busy[$s['interview_date']][] = [substr($s['start_time'], 0, 5), substr($s['end_time'], 0, 5)];
            if ((int)$s['program_id'] !== $programId) $otherProgramDays[$s['interview_date']] = true;
        }
        $holidays = self::holidays(...$range);

        for ($d = $firstBlock; $d <= $last && count($out) < $limit; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if ((int)$d->format('N') === 7 || isset($holidays[$date]) || isset($otherProgramDays[$date])) continue;
            foreach (self::SUGGESTION_BLOCKS as [$from, $to]) {
                foreach ($busy[$date] ?? [] as [$bFrom, $bTo]) {
                    if ($bFrom < $to && $bTo > $from) continue 2;   // overlaps an existing schedule
                }
                $out[] = [
                    'kind' => 'block', 'schedule_id' => null, 'date' => $date, 'start' => $from, 'end' => $to, 'venue' => null, 'free' => null,
                    'label' => $d->format('D, M j') . ' · ' . date('g:i', strtotime($from)) . '–' . date('g:i A', strtotime($to)) . ' (new)',
                ];
                if (count($out) >= $limit) break;
            }
        }
        if (!$out) {
            $why = $firstBlock > $last
                ? "No suggestions: nothing free up to {$last->format('M j, Y')} ($days-day window, Settings)."
                : "No open schedule or free 2-hour block up to {$last->format('M j, Y')} ($days-day window, Settings).";
        }
        return $out;
    }

    /** Is $item (from suggestSlots) still one of the current top suggestions for the program? (server-side check of a pick) */
    public static function isCurrentSuggestion(int $programId, array $item, int $limit): ?array
    {
        foreach (self::suggestSlots($programId, $limit) as $s) {
            if ($s['kind'] === $item['kind'] && $s['date'] === $item['date'] && $s['start'] === $item['start']
                && (int)$s['schedule_id'] === (int)($item['schedule_id'] ?? 0)) return $s;
        }
        return null;
    }

    /** Validates schedule input. Returns [clean data, error|null]. */
    private static function scheduleInput(array $data): array
    {
        $date  = trim((string)($data['interview_date'] ?? ''));
        $start = trim((string)($data['start_time'] ?? ''));
        $end   = trim((string)($data['end_time'] ?? ''));
        $venue = trim((string)($data['venue'] ?? ''));
        $cap   = (int)($data['max_slots'] ?? 0);
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) return [null, 'Choose a valid date.'];
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) return [null, 'Choose valid start and end times.'];
        if (strtotime($end) <= strtotime($start)) return [null, 'End time must be after start time.'];
        if ($venue === '') return [null, 'Venue is required.'];
        if ($cap < 1) return [null, 'Capacity must be at least 1.'];
        return [['interview_date' => $date, 'start_time' => $start, 'end_time' => $end, 'venue' => $venue, 'max_slots' => $cap], null];
    }

    public static function createSchedule(int $programId, array $data, int $adminId): array
    {
        [$in, $err] = self::scheduleInput($data);
        if ($err) return ['ok' => false, 'error' => $err];
        if ($in['interview_date'] < date('Y-m-d')) return ['ok' => false, 'error' => 'The date is in the past.'];
        if (!Program::find($programId)) return ['ok' => false, 'error' => 'Program not found.'];
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO interview_schedules (program_id, interview_date, start_time, end_time, venue, max_slots, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'Open', ?)"
        )->execute([$programId, $in['interview_date'], $in['start_time'], $in['end_time'], $in['venue'], $in['max_slots'], $adminId]);
        $id = (int)$pdo->lastInsertId();
        AuditLog::write('schedule_created', 'interview_schedules', $id, 'Interview schedule created: ' . self::describe($in), null, $in);
        return ['ok' => true, 'id' => $id];
    }

    /** Edit; if date, time or venue changed, applicants still waiting are notified and their reminder is reset. */
    public static function updateSchedule(int $id, array $data, int $adminId): array
    {
        $old = self::schedule($id);
        if (!$old) return ['ok' => false, 'error' => 'Schedule not found.'];
        if ($old['status'] === 'Cancelled') return ['ok' => false, 'error' => 'A cancelled schedule can’t be edited.'];
        [$in, $err] = self::scheduleInput($data);
        if ($err) return ['ok' => false, 'error' => $err];
        if ($in['max_slots'] < (int)$old['assigned_count']) {
            return ['ok' => false, 'error' => "Capacity can’t be lower than the {$old['assigned_count']} applicants already assigned."];
        }

        $pdo = Database::conn();
        $pdo->prepare("UPDATE interview_schedules SET interview_date = ?, start_time = ?, end_time = ?, venue = ?, max_slots = ? WHERE id = ?")
            ->execute([$in['interview_date'], $in['start_time'], $in['end_time'], $in['venue'], $in['max_slots'], $id]);
        AuditLog::write('schedule_updated', 'interview_schedules', $id, 'Interview schedule updated: ' . self::describe($in), $old, $in);

        $hm = static fn(string $t): string => date('H:i', strtotime($t));
        $changed = $old['interview_date'] !== $in['interview_date'] || $hm($old['start_time']) !== $hm($in['start_time'])
                || $hm($old['end_time']) !== $hm($in['end_time']) || $old['venue'] !== $in['venue'];
        if ($changed) {
            $pdo->prepare("UPDATE interview_assignments SET reminder_sent_at = NULL WHERE schedule_id = ? AND attendance = 'Scheduled'")->execute([$id]);
            foreach (self::assignmentsBySchedule($id) as $a) {
                if ($a['attendance'] !== 'Scheduled') continue;
                Application::notify((int)$a['application_id'], 'Interview changed',
                    'Your interview was changed. New schedule: ' . self::describe($in) . '.', 'warning');
            }
        }
        return ['ok' => true];
    }

    /** Cancel: every applicant still waiting goes back to Qualified (their attempt is removed) and is notified. */
    public static function cancelSchedule(int $id, int $adminId): array
    {
        $sched = self::schedule($id);
        if (!$sched) return ['ok' => false, 'error' => 'Schedule not found.'];
        if ($sched['status'] === 'Cancelled') return ['ok' => false, 'error' => 'Already cancelled.'];
        if (array_filter(self::assignmentsBySchedule($id), static fn($a) => $a['attendance'] !== 'Scheduled')) {
            return ['ok' => false, 'error' => 'This schedule already has attendance recorded, so it can’t be cancelled.'];
        }
        $pdo = Database::conn();
        $notify = [];
        try {
            $pdo->beginTransaction();
            foreach (self::assignmentsBySchedule($id) as $a) {
                self::removeWaitingAssignment($a, $adminId, 'schedule cancelled');
                $notify[] = (int)$a['application_id'];
            }
            $pdo->prepare("UPDATE interview_schedules SET status = 'Cancelled' WHERE id = ?")->execute([$id]);
            AuditLog::write('schedule_cancelled', 'interview_schedules', $id, 'Cancelled: ' . self::describe($sched));
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        foreach ($notify as $appId) {
            Application::notify($appId, 'Interview cancelled',
                'Your interview on ' . self::describe($sched) . ' was cancelled. We will e-mail you a new schedule.', 'danger');
        }
        return ['ok' => true, 'count' => count($notify)];
    }

    /** Delete an EMPTY schedule (created by mistake). One with applicants must be cancelled instead. */
    public static function deleteSchedule(int $id): array
    {
        $sched = self::schedule($id);
        if (!$sched) return ['ok' => false, 'error' => 'Schedule not found.'];
        if ((int)$sched['assigned_count'] > 0) return ['ok' => false, 'error' => 'This schedule has applicants. Cancel it instead.'];
        Database::conn()->prepare("DELETE FROM interview_schedules WHERE id = ?")->execute([$id]);
        AuditLog::write('schedule_deleted', 'interview_schedules', $id, 'Deleted empty schedule: ' . self::describe($sched), $sched);
        return ['ok' => true];
    }

    /* ---------------- assignments and attendance (B3–B4) ---------------- */

    /**
     * Bulk-assign Qualified applications of the schedule's program, up to its free capacity.
     * Returns ['assigned' => n, 'errors' => [code => message]].
     */
    public static function assign(int $scheduleId, array $applicationIds, int $adminId, bool $moved = false): array
    {
        $sched = self::schedule($scheduleId);
        if (!$sched) return ['assigned' => 0, 'errors' => ['' => 'Schedule not found.']];
        if ($sched['status'] === 'Cancelled') return ['assigned' => 0, 'errors' => ['' => 'This schedule is cancelled.']];
        if ($sched['interview_date'] < date('Y-m-d')) return ['assigned' => 0, 'errors' => ['' => 'This schedule is in the past.']];

        $pdo = Database::conn();
        $assigned = 0;
        $errors = [];
        foreach (array_unique(array_map('intval', $applicationIds)) as $appId) {
            $app = Application::find($appId);
            $code = $app['application_code'] ?? "#$appId";
            if (!$app || (int)$app['program_id'] !== (int)$sched['program_id']) { $errors[$code] = 'not in this program'; continue; }
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare("SELECT max_slots FROM interview_schedules WHERE id = ? FOR UPDATE");
                $lock->execute([$scheduleId]);
                $cap = (int)$lock->fetchColumn();
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM interview_assignments WHERE schedule_id = ?");
                $cnt->execute([$scheduleId]);
                if ((int)$cnt->fetchColumn() >= $cap) throw new DomainException('schedule is full');

                $missed = $pdo->prepare("SELECT COUNT(*) FROM interview_assignments WHERE application_id = ? AND attendance = 'No-show'");
                $missed->execute([$appId]);
                $rescheduleCount = (int)$missed->fetchColumn();

                Application::changeStatus($app, 'assign', $adminId, [], 'schedule #' . $scheduleId);
                $pdo->prepare("INSERT INTO interview_assignments (schedule_id, application_id, attendance, reschedule_count) VALUES (?, ?, 'Scheduled', ?)")
                    ->execute([$scheduleId, $appId, $rescheduleCount]);
                AuditLog::write('interview_assigned', 'interview_assignments', (int)$pdo->lastInsertId(),
                    "Assigned {$app['application_code']} to " . self::describe($sched) . ($rescheduleCount ? ' (reschedule)' : ''));
                // Scheduled now, so any slot the applicant asked for is settled.
                $pdo->prepare("UPDATE slot_requests SET status = 'Cancelled', decision_note = 'Scheduled by the SK office', decided_by = ?, decided_at = NOW()
                               WHERE application_id = ? AND status = 'Pending'")->execute([$adminId, $appId]);
                $pdo->commit();
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[$code] = $e->getMessage();
                continue;
            }
            $assigned++;
            Application::notify($appId, $moved ? 'Interview changed' : 'Interview scheduled',
                ($moved ? 'Your interview was changed. New schedule: ' : 'Your interview is on ') . self::describe($sched)
                . '. Bring a valid ID and arrive 15 minutes early.'
                . ($rescheduleCount ? "\nThis is your one rescheduled interview — missing it ends your application." : ''), $moved ? 'warning' : 'success');
        }
        return ['assigned' => $assigned, 'errors' => $errors];
    }

    /** Remove an attempt that is still waiting (attendance Scheduled); the application goes back to Qualified. */
    private static function removeWaitingAssignment(array $a, int $adminId, string $why): void
    {
        if ($a['attendance'] !== 'Scheduled') throw new DomainException('Attendance is already recorded for this applicant.');
        $app = Application::find((int)$a['application_id']);
        Application::changeStatus($app, 'unassign', $adminId, [], $why);
        Database::conn()->prepare("DELETE FROM interview_assignments WHERE id = ?")->execute([(int)$a['id']]);
        AuditLog::write('interview_unassigned', 'interview_assignments', (int)$a['id'], "Removed {$app['application_code']} from schedule: $why");
    }

    public static function unassign(int $assignmentId, int $adminId): array
    {
        $a = self::assignment($assignmentId);
        if (!$a) return ['ok' => false, 'error' => 'Assignment not found.'];
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            self::removeWaitingAssignment($a, $adminId, 'removed by admin');
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        Application::notify((int)$a['application_id'], 'Interview cancelled',
            'Your interview on ' . self::describe($a) . ' was cancelled. We will e-mail you a new schedule.', 'danger');
        return ['ok' => true];
    }

    /**
     * Put an application on $scheduleId from the reschedule page:
     *   - Qualified (waiting, incl. after a first no-show): a normal assign ("Interview scheduled");
     *   - Interview Scheduled on an upcoming attempt: the waiting attempt is removed and the new one created, and the
     *     applicant gets ONE "Interview changed" notification (no extra no-show is counted — nobody missed anything).
     * Capacity and the one-reschedule rule are checked by assign(). Returns ['ok'=>true] or ['ok'=>false,'error'].
     */
    public static function scheduleApplication(int $applicationId, int $scheduleId, int $adminId): array
    {
        $app = Application::find($applicationId);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $current = self::assignmentByApplication($applicationId);
        $moving = $app['status'] === 'Interview Scheduled';
        if ($moving) {
            if (!$current || $current['attendance'] !== 'Scheduled') return ['ok' => false, 'error' => 'This interview already has attendance recorded.'];
            if ((int)$current['schedule_id'] === $scheduleId) return ['ok' => false, 'error' => 'The applicant is already on that schedule.'];
            $target = self::schedule($scheduleId);
            if (!$target || $target['status'] === 'Cancelled') return ['ok' => false, 'error' => 'Choose an open schedule.'];
            if ((int)$target['assigned_count'] >= (int)$target['max_slots']) return ['ok' => false, 'error' => 'That schedule is full.'];
            $pdo = Database::conn();
            try {
                $pdo->beginTransaction();
                $full = self::assignment((int)$current['id']);
                self::removeWaitingAssignment($full, $adminId, 'moved to schedule #' . $scheduleId);
                $pdo->commit();
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        } elseif ($app['status'] !== 'Qualified') {
            return ['ok' => false, 'error' => 'Only a Qualified application (or one already scheduled) can be given a schedule.'];
        }
        $r = self::assign($scheduleId, [$applicationId], $adminId, $moving);
        if ($r['assigned'] === 1) return ['ok' => true];
        if ($moving) self::assign((int)$current['schedule_id'], [$applicationId], $adminId);   // put them back where they were
        return ['ok' => false, 'error' => 'Not scheduled: ' . implode('; ', $r['errors'] ?: ['unknown error'])];
    }

    /**
     * Create a schedule from a date/time (a suggested block or the admin's own pick) and put the application on it.
     * Reuses an Open schedule of the program with the same date and times if there is one.
     */
    public static function scheduleApplicationAt(int $applicationId, array $data, int $adminId): array
    {
        $app = Application::find($applicationId);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];
        $stmt = Database::conn()->prepare(
            "SELECT id FROM interview_schedules WHERE program_id = ? AND status = 'Open' AND interview_date = ?
               AND TIME_FORMAT(start_time, '%H:%i') = ? AND TIME_FORMAT(end_time, '%H:%i') = ? LIMIT 1"
        );
        $stmt->execute([(int)$app['program_id'], (string)($data['interview_date'] ?? ''), substr((string)($data['start_time'] ?? ''), 0, 5), substr((string)($data['end_time'] ?? ''), 0, 5)]);
        $scheduleId = (int)$stmt->fetchColumn();
        if (!$scheduleId) {
            $r = self::createSchedule((int)$app['program_id'], $data, $adminId);
            if (!$r['ok']) return $r;
            $scheduleId = (int)$r['id'];
        }
        return self::scheduleApplication($applicationId, $scheduleId, $adminId) + ['schedule_id' => $scheduleId];
    }

    /** B4 No-show: the first one sends the application back to Qualified (one reschedule); a second one rejects it. */
    public static function markNoShow(int $assignmentId, int $adminId): array
    {
        $a = self::assignment($assignmentId);
        if (!$a) return ['ok' => false, 'error' => 'Assignment not found.'];
        if ($a['attendance'] !== 'Scheduled') return ['ok' => false, 'error' => 'Attendance is already recorded for this applicant.'];
        if ($a['interview_date'] > date('Y-m-d')) return ['ok' => false, 'error' => 'You can mark a no-show only on or after the interview date.'];
        $final = (int)$a['reschedule_count'] >= 1;
        $app = Application::find((int)$a['application_id']);
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE interview_assignments SET attendance = 'No-show' WHERE id = ? AND attendance = 'Scheduled'")->execute([$assignmentId]);
            if ($final) {
                Application::changeStatus($app, 'no_show_final', $adminId, [
                    'decided_at' => date('Y-m-d H:i:s'), 'decided_by' => $adminId,
                    'decision_remarks' => 'Did not attend the rescheduled interview.',
                ], 'second no-show');
            } else {
                Application::changeStatus($app, 'no_show', $adminId, [], 'first no-show — one reschedule allowed');
            }
            AuditLog::write('interview_no_show', 'interview_assignments', $assignmentId, "No-show: {$a['application_code']}" . ($final ? ' (second — rejected)' : ''));
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($final) {
            Application::notify((int)$a['application_id'], 'Application not approved',
                'You did not attend your rescheduled interview, so your application has ended. Reason: Did not attend the rescheduled interview.', 'danger');
        } else {
            Application::notify((int)$a['application_id'], 'You missed your interview',
                'You missed your interview on ' . self::describe($a) . '. You get ONE new schedule — we will e-mail it to you. Missing it ends your application.', 'warning');
            Notification::adminEvent('First no-show — reschedule needed',
                "{$a['full_name']} ({$a['application_code']}) missed the interview on " . self::describe($a) . ' and needs their one new schedule.',
                'admin/interview-reschedule&application_id=' . (int)$a['application_id']);
        }
        return ['ok' => true, 'final' => $final];
    }

    /**
     * B4 Score an attendee with the rubric (INTERVIEW_RUBRIC). Marks the attempt Attended and the application
     * Interviewed. Scores can be corrected while the application is still Interviewed (no decision yet).
     */
    public static function saveScore(int $assignmentId, array $input, string $remarks, int $adminId): array
    {
        $a = self::assignment($assignmentId);
        if (!$a) return ['ok' => false, 'error' => 'Assignment not found.'];
        $firstScore = $a['attendance'] === 'Scheduled';
        if (!$firstScore && !($a['attendance'] === 'Attended' && $a['app_status'] === 'Interviewed')) {
            return ['ok' => false, 'error' => 'This interview can no longer be scored.'];
        }
        if ($a['interview_date'] > date('Y-m-d')) return ['ok' => false, 'error' => 'You can score only on or after the interview date.'];

        $scores = [];
        foreach (INTERVIEW_RUBRIC as $field => $c) {
            $v = trim((string)($input[$field] ?? ''));
            if ($v === '' || !is_numeric($v) || (float)$v < 0 || (float)$v > $c['max']) {
                return ['ok' => false, 'error' => "{$c['label']} must be a number from 0 to {$c['max']}."];
            }
            $scores[$field] = round((float)$v, 2);
        }
        $total = array_sum($scores);

        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $cols = array_keys($scores);
            $pdo->prepare(
                "INSERT INTO interview_evaluations (assignment_id, " . implode(', ', $cols) . ", total_score, remarks, interviewer_id, evaluated_at)
                 VALUES (:assignment_id, :" . implode(', :', $cols) . ", :total_score, :remarks, :interviewer_id, NOW())
                 ON DUPLICATE KEY UPDATE " . implode(', ', array_map(static fn($c) => "$c = VALUES($c)", $cols)) . ",
                    total_score = VALUES(total_score), remarks = VALUES(remarks), interviewer_id = VALUES(interviewer_id), evaluated_at = NOW()"
            )->execute($scores + ['assignment_id' => $assignmentId, 'total_score' => $total,
                                  'remarks' => trim($remarks) ?: null, 'interviewer_id' => $adminId]);
            if ($firstScore) {
                $pdo->prepare("UPDATE interview_assignments SET attendance = 'Attended' WHERE id = ?")->execute([$assignmentId]);
                Application::changeStatus(Application::find((int)$a['application_id']), 'score', $adminId, [], "interview total $total");
            }
            AuditLog::write($firstScore ? 'interview_scored' : 'interview_score_corrected', 'interview_evaluations', $assignmentId,
                "Interview score for {$a['application_code']}: $total / 100", null, $scores + ['total' => $total]);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return ['ok' => true, 'total' => $total];
    }

    /* ---------------- ranking (B5) ---------------- */

    /**
     * Interviewed and Waitlisted applications of a program, ranked by interview total (high first), then
     * total family income (low first), then GWA (lower number = better, first). Each row has its eligibility.
     */
    public static function ranking(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.applicant_id, a.submitted_at, a.program_id,
                    sp.max_age, sp.max_family_income, sp.min_gpa,
                    u.full_name, ev.total_score, e.general_average, f.total_family_income
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN interview_assignments ia ON ia.id = (SELECT MAX(ia2.id) FROM interview_assignments ia2 WHERE ia2.application_id = a.id)
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             LEFT JOIN education_records e ON e.id = (SELECT MAX(e2.id) FROM education_records e2 WHERE e2.applicant_id = a.applicant_id)
             LEFT JOIN family_background f ON f.applicant_id = a.applicant_id
             WHERE a.program_id = ? AND a.status IN ('Interviewed', 'Waitlisted')
             ORDER BY ev.total_score DESC, f.total_family_income ASC, (e.general_average IS NULL) ASC, e.general_average ASC, a.id ASC"
        );
        $stmt->execute([$programId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $i => &$r) {
            $r['rank'] = $i + 1;
            $r['eligibility'] = Application::eligibility($r);
        }
        unset($r);
        return $rows;
    }
}
