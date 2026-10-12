<?php
declare(strict_types=1);

/**
 * Putting applicants on interview schedules (B3–B4): assign, unassign, reschedule, no-shows, the attempts of one
 * application, and the interview reminders sent by bin/send_reminders.php.
 *
 * One interview_assignments row per scheduling attempt; an application's latest row is its current one.
 * A first no-show keeps its row and sends the application back to Qualified; the next row then has
 * reschedule_count = 1, and a no-show on it rejects the application.
 * Reminders: a row is CLAIMED (reminder_sent_at) before its reminder is sent, so it is sent once.
 */
final class InterviewAssignment
{
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

    /** How many interviews the application has missed (No-show attempts). */
    public static function missedCount(int $applicationId): int
    {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM interview_assignments WHERE application_id = ? AND attendance = 'No-show'");
        $stmt->execute([$applicationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Bulk-assign Qualified applications of the schedule's program, up to its free capacity.
     * Returns ['assigned' => n, 'errors' => [code => message]].
     */
    public static function assign(int $scheduleId, array $applicationIds, int $adminId, bool $moved = false): array
    {
        $sched = Interview::schedule($scheduleId);
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
                    "Assigned {$app['application_code']} to " . Interview::describe($sched) . ($rescheduleCount ? ' (reschedule)' : ''));
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
                ($moved ? 'Your interview was changed. New schedule: ' : 'Your interview is on ') . Interview::describe($sched)
                . '. Bring a valid ID and arrive 15 minutes early.'
                . ($rescheduleCount ? "\nThis is your one rescheduled interview — missing it ends your application." : ''), $moved ? 'warning' : 'success');
        }
        return ['assigned' => $assigned, 'errors' => $errors];
    }

    /** Remove an attempt that is still waiting (attendance Scheduled); the application goes back to Qualified. */
    public static function removeWaitingAssignment(array $a, int $adminId, string $why): void
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
            'Your interview on ' . Interview::describe($a) . ' was cancelled. We will e-mail you a new schedule.', 'danger');
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
            $target = Interview::schedule($scheduleId);
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
            $r = Interview::createSchedule((int)$app['program_id'], $data, $adminId);
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
                'You missed your interview on ' . Interview::describe($a) . '. You get ONE new schedule — we will e-mail it to you. Missing it ends your application.', 'warning');
            Notification::adminEvent('First no-show — reschedule needed',
                "{$a['full_name']} ({$a['application_code']}) missed the interview on " . Interview::describe($a) . ' and needs their one new schedule.',
                'admin/interview-reschedule&application_id=' . (int)$a['application_id']);
        }
        return ['ok' => true, 'final' => $final];
    }

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
}
