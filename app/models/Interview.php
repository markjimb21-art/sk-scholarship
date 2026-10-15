<?php
declare(strict_types=1);

/** Interview schedules (B3): create, edit, cancel, delete, and the lists and day views built from them. */
final class Interview
{
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
            "SELECT s.*, sp.program_name,
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

    /** Bootstrap colour per displayed schedule status — shared by the Interviews list and the calendar. */
    public const STATUS_COLORS = ['Open' => 'success', 'Full' => 'warning', 'Closed' => 'secondary', 'Cancelled' => 'danger'];

    /**
     * What a schedule shows as on the Interviews list and the calendar: Cancelled, Done (date passed), Full (assigned
     * reached capacity) or Open. Needs a row with status, interview_date, assigned_count and max_slots.
     */
    public static function displayStatus(array $schedule): string
    {
        if ($schedule['status'] === 'Cancelled') return 'Cancelled';
        if (self::isClosed($schedule)) return 'Closed';
        return (int)$schedule['assigned_count'] >= (int)$schedule['max_slots'] ? 'Full' : 'Open';
    }

    /**
     * Closed = the admin closed it (Edit schedule), or its end time has passed (closes by itself; the row is unchanged).
     * Nobody can be assigned or moved onto a closed schedule; attendance and scores can still be recorded.
     */
    public static function isClosed(array $schedule, ?string $now = null): bool
    {
        if ($schedule['status'] === 'Closed') return true;
        $end = $schedule['interview_date'] . ' ' . substr((string)$schedule['end_time'], 0, 8);
        return strtotime($end) <= strtotime($now ?? date('Y-m-d H:i:s'));
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
             WHERE s.interview_date = ? AND s.status <> 'Cancelled' ORDER BY s.start_time, s.id"
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

    /** The venue of the program's most recent schedule ('' when none), to pre-fill a new schedule. */
    public static function lastVenue(int $programId): string
    {
        $stmt = Database::conn()->prepare("SELECT venue FROM interview_schedules WHERE program_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$programId]);
        return (string)($stmt->fetchColumn() ?: '');
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

    /**
     * Edit; status Open or Closed (cancelling is its own action, cancelSchedule). If date, time or venue changed,
     * applicants still waiting are notified and their reminder is reset.
     */
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
        $in['status'] = (string)($data['status'] ?? $old['status']);
        if (!in_array($in['status'], ['Open', 'Closed'], true)) return ['ok' => false, 'error' => 'Choose Open or Closed. To cancel, use Cancel schedule.'];

        $pdo = Database::conn();
        $pdo->prepare("UPDATE interview_schedules SET interview_date = ?, start_time = ?, end_time = ?, venue = ?, max_slots = ?, status = ? WHERE id = ?")
            ->execute([$in['interview_date'], $in['start_time'], $in['end_time'], $in['venue'], $in['max_slots'], $in['status'], $id]);
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
                InterviewAssignment::removeWaitingAssignment($a, $adminId, 'schedule cancelled');
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
}
