<?php
declare(strict_types=1);

final class Interview
{
    /* ---------------- SCHEDULES ---------------- */

    public static function schedule(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id = s.id) AS assigned_count,
                    sp.program_name, sp.academic_year
             FROM interview_schedules s
             JOIN scholarship_programs sp ON sp.id = s.program_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function schedulesByProgram(int $programId, ?string $fromDate = null): array
    {
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id = s.id) AS assigned_count
                FROM interview_schedules s
                WHERE s.program_id = ?";
        $params = [$programId];
        if ($fromDate) { $sql .= " AND s.interview_date >= ?"; $params[] = $fromDate; }
        $sql .= " ORDER BY s.interview_date ASC, s.start_time ASC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function allSchedules(?string $from = null, ?string $to = null, ?int $programId = null): array
    {
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id = s.id) AS assigned_count,
                       sp.program_name
                FROM interview_schedules s
                JOIN scholarship_programs sp ON sp.id = s.program_id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND s.interview_date >= ?"; $params[] = $from; }
        if ($to)   { $sql .= " AND s.interview_date <= ?"; $params[] = $to; }
        if ($programId) { $sql .= " AND s.program_id = ?"; $params[] = $programId; }
        $sql .= " ORDER BY s.interview_date ASC, s.start_time ASC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function createSchedule(array $data, int $createdBy): array
    {
        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            return ['ok' => false, 'error' => 'End time must be after start time.'];
        }
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO interview_schedules
             (program_id, interview_date, start_time, end_time, venue, interviewer, max_slots, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $data['program_id'], $data['interview_date'], $data['start_time'], $data['end_time'],
            $data['venue'], $data['interviewer'], (int)$data['max_slots'],
            $data['remarks'] ?? null, $createdBy
        ]);
        $id = (int)$pdo->lastInsertId();
        AuditLog::write('schedule_created', 'interview_schedules', $id, "Created interview schedule for {$data['interview_date']}");
        return ['ok' => true, 'id' => $id];
    }

    public static function updateSchedule(int $id, array $data, int $actorId): array
    {
        $old = self::schedule($id);
        if (!$old) return ['ok' => false, 'error' => 'Schedule not found.'];

        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            return ['ok' => false, 'error' => 'End time must be after start time.'];
        }

        // Don't allow reducing max_slots below current assigned
        if ((int)$data['max_slots'] < (int)$old['assigned_count']) {
            return ['ok' => false, 'error' => 'Cannot reduce slots below currently assigned (' . $old['assigned_count'] . ').'];
        }

        Database::conn()->prepare(
            "UPDATE interview_schedules
             SET interview_date=?, start_time=?, end_time=?, venue=?, interviewer=?, max_slots=?, status=?, remarks=?
             WHERE id=?"
        )->execute([
            $data['interview_date'], $data['start_time'], $data['end_time'],
            $data['venue'], $data['interviewer'], (int)$data['max_slots'],
            $data['status'] ?? 'Open', $data['remarks'] ?? null, $id
        ]);

        AuditLog::write('schedule_updated', 'interview_schedules', $id,
            "Updated schedule", $old, $data);

        // Notify affected applicants if date/time/venue changed
        $notify = ($old['interview_date'] !== $data['interview_date'])
               || ($old['start_time'] !== $data['start_time'])
               || ($old['venue'] !== $data['venue']);

        if ($notify) {
            self::notifyAssignedApplicants($id, 'Interview Schedule Updated',
                "Your interview has been rescheduled to {$data['interview_date']} at {$data['start_time']} ({$data['venue']}).",
                'warning');
        }

        return ['ok' => true];
    }

    public static function cancelSchedule(int $id, int $actorId, string $reason = ''): void
    {
        Database::conn()->prepare("UPDATE interview_schedules SET status='Cancelled', remarks=? WHERE id=?")
            ->execute([$reason, $id]);

        self::notifyAssignedApplicants($id, 'Interview Cancelled',
            "Your interview has been cancelled. Reason: " . ($reason ?: 'To be announced.'),
            'danger');

        AuditLog::write('schedule_cancelled', 'interview_schedules', $id, 'Cancelled schedule');
    }

    /* ---------------- ASSIGNMENTS ---------------- */

    public static function assignmentByApplication(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue,
                    s.interviewer, s.remarks AS sched_remarks, s.status AS sched_status
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             WHERE ia.application_id = ? LIMIT 1"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetch() ?: null;
    }

    public static function assignmentsBySchedule(int $scheduleId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, a.application_code, a.status AS app_status,
                    u.full_name, u.email, u.contact_number,
                    e.school_name, e.course_name, e.year_level,
                    ev.result AS eval_result, ev.total_score AS eval_score
             FROM interview_assignments ia
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             WHERE ia.schedule_id = ?
             ORDER BY u.full_name"
        );
        $stmt->execute([$scheduleId]);
        return $stmt->fetchAll();
    }

    public static function eligibleApplicationsForInterview(int $programId): array
    {
        // Applications with status = 'For Interview' and NO assignment yet
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status,
                    u.full_name, u.email, u.contact_number,
                    e.school_name, e.course_name, e.year_level,
                    (SELECT COUNT(*) FROM documents d
                    WHERE d.application_id = a.id AND d.status = 'Verified') AS docs_verified
            FROM applications a
            JOIN applicants ap ON ap.id = a.applicant_id
            JOIN users u ON u.id = ap.user_id
            LEFT JOIN education_records e
                    ON e.applicant_id = ap.id
                AND e.id = (SELECT MAX(id) FROM education_records WHERE applicant_id = ap.id)
            WHERE a.program_id = ?
            AND a.status = 'For Interview'
            AND NOT EXISTS (
                    SELECT 1 FROM interview_assignments ia WHERE ia.application_id = a.id
            )
            ORDER BY u.full_name"
        );
        $stmt->execute([$programId]);
        return $stmt->fetchAll();
    }

    public static function hasSlot(int $scheduleId): bool
    {
        $s = self::schedule($scheduleId);
        return $s && ((int)$s['assigned_count'] < (int)$s['max_slots']);
    }

    public static function assign(int $scheduleId, int $applicationId, int $assignedBy, ?string $instructions = null): array
    {
        $sched = self::schedule($scheduleId);
        if (!$sched)                       return ['ok' => false, 'error' => 'Schedule not found.'];
        if ($sched['status'] === 'Cancelled') return ['ok' => false, 'error' => 'Schedule is cancelled.'];
        if (!self::hasSlot($scheduleId))   return ['ok' => false, 'error' => 'Schedule is already full.'];

        $existing = self::assignmentByApplication($applicationId);
        if ($existing) {
            return ['ok' => false, 'error' => 'Applicant is already assigned to a schedule.'];
        }

        $pdo = Database::conn();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO interview_assignments
                 (schedule_id, application_id, assignment_status, instructions, assigned_by)
                 VALUES (?, ?, 'Scheduled', ?, ?)"
            )->execute([$scheduleId, $applicationId, $instructions, $assignedBy]);

            $assignmentId = (int)$pdo->lastInsertId();

            Application::updateStatus($applicationId, 'Interview Scheduled', $assignedBy);

            // If full, update schedule status
            $sched = self::schedule($scheduleId);
            if ((int)$sched['assigned_count'] >= (int)$sched['max_slots']) {
                $pdo->prepare("UPDATE interview_schedules SET status='Full' WHERE id=?")->execute([$scheduleId]);
            }

            $pdo->commit();

            // Notify applicant
            self::notifyApplicantForApplication($applicationId,
                'Interview Scheduled 📅',
                "Your interview has been scheduled on {$sched['interview_date']} at " .
                date('g:i A', strtotime($sched['start_time'])) .
                " at {$sched['venue']}. Interviewer: {$sched['interviewer']}.",
                'success');

            AuditLog::write('interview_assigned', 'interview_assignments', $assignmentId,
                "Assigned applicant to schedule #$scheduleId");

            return ['ok' => true, 'assignment_id' => $assignmentId];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Assignment failed.'];
        }
    }

    public static function unassign(int $assignmentId, int $actorId): void
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, a.id AS app_id FROM interview_assignments ia
             JOIN applications a ON a.id = ia.application_id
             WHERE ia.id = ?"
        );
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if (!$row) return;

        Database::conn()->prepare("DELETE FROM interview_assignments WHERE id = ?")->execute([$assignmentId]);

        // If schedule was Full, revert to Open
        Database::conn()->prepare(
            "UPDATE interview_schedules SET status='Open'
             WHERE id=? AND status='Full'
             AND (SELECT COUNT(*) FROM interview_assignments WHERE schedule_id=?) < max_slots"
        )->execute([$row['schedule_id'], $row['schedule_id']]);

        Application::updateStatus((int)$row['app_id'], 'For Interview', $actorId);

        self::notifyApplicantForApplication((int)$row['app_id'],
            'Interview Assignment Removed',
            'Your interview assignment has been removed. You will be notified when a new schedule is set.',
            'warning');

        AuditLog::write('interview_unassigned', 'interview_assignments', $assignmentId, 'Removed assignment');
    }

    public static function updateAssignmentStatus(int $assignmentId, string $status, int $actorId, ?string $notes = null): void
    {
        $allowed = ['Scheduled','Confirmed','Completed','No Show','Rescheduled','Cancelled'];
        if (!in_array($status, $allowed, true)) return;

        $stmt = Database::conn()->prepare("SELECT * FROM interview_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if (!$row) return;

        Database::conn()->prepare(
            "UPDATE interview_assignments SET assignment_status = ?, confirmed_at = IF(?='Confirmed', NOW(), confirmed_at) WHERE id=?"
        )->execute([$status, $status, $assignmentId]);

        // Sync application status
        $newAppStatus = match($status) {
            'Completed' => 'Interview Completed',
            'No Show'   => 'Incomplete',
            'Cancelled' => 'For Interview',
            'Confirmed','Scheduled','Rescheduled' => 'Interview Scheduled',
            default     => null
        };
        if ($newAppStatus) {
            Application::updateStatus((int)$row['application_id'], $newAppStatus, $actorId);
        }

        // Notify applicant
        $msg = match($status) {
            'Confirmed' => 'Your interview attendance is confirmed. See you there!',
            'Completed' => 'Your interview has been marked complete. Please wait for the final evaluation.',
            'No Show'   => 'You were marked as no-show for your scheduled interview. Please contact the SK office.',
            'Rescheduled' => 'Your interview has been rescheduled. Check the new date and time in your dashboard.',
            'Cancelled' => 'Your interview has been cancelled. You will be notified of the new schedule.',
            default     => "Your interview status is now: $status"
        };
        $type = match($status) {
            'Completed' => 'success', 'No Show','Cancelled' => 'danger',
            'Rescheduled' => 'warning', default => 'info'
        };
        self::notifyApplicantForApplication((int)$row['application_id'],
            'Interview ' . $status, $msg, $type);

        AuditLog::write('interview_status_changed', 'interview_assignments', $assignmentId,
            "Status changed to $status", ['status' => $row['assignment_status']], ['status' => $status]);
    }

    /* ---------------- EVALUATION ---------------- */

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

    public static function saveEvaluation(int $assignmentId, array $scores, string $result, int $interviewerId,
                                          ?string $recommendation = null, ?string $remarks = null, ?string $notes = null): array
    {
        // 5 criteria, each 0..20 → total 0..100
        $criteria = ['financial_need_score','academic_score','motivation_score','community_score','purpose_score','overall_score'];
        foreach ($criteria as $c) {
            $scores[$c] = max(0, min(20, (float)($scores[$c] ?? 0)));
        }
        // We'll treat "overall_score" as its own 0..20 criterion; total = sum of 5 (excluding overall) * 1 + overall → but simplest: sum all 6, max = 120; or use 5 * 20 = 100.
        // We use 5 criteria x20 = 100 (overall_score excluded from total, kept for qualitative).
        $total = $scores['financial_need_score'] + $scores['academic_score']
               + $scores['motivation_score'] + $scores['community_score']
               + $scores['purpose_score'];

        $rating = round($total / 20, 2); // 0..5 scale

        $pdo = Database::conn();
        $existing = self::evaluation($assignmentId);

        if ($existing) {
            $pdo->prepare(
                "UPDATE interview_evaluations
                 SET financial_need_score=?, academic_score=?, motivation_score=?, community_score=?,
                     purpose_score=?, overall_score=?, total_score=?, rating=?, result=?,
                     recommendation=?, interview_remarks=?, additional_notes=?, interviewer_id=?, evaluated_at=NOW()
                 WHERE assignment_id=?"
            )->execute([
                $scores['financial_need_score'], $scores['academic_score'], $scores['motivation_score'],
                $scores['community_score'], $scores['purpose_score'], $scores['overall_score'],
                $total, $rating, $result, $recommendation, $remarks, $notes, $interviewerId, $assignmentId
            ]);
            AuditLog::write('evaluation_updated', 'interview_evaluations', (int)$existing['id'],
                "Evaluation updated, total=$total");
        } else {
            $pdo->prepare(
                "INSERT INTO interview_evaluations
                 (assignment_id, financial_need_score, academic_score, motivation_score, community_score,
                  purpose_score, overall_score, total_score, max_possible_score, rating, result,
                  recommendation, interview_remarks, additional_notes, interviewer_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $assignmentId, $scores['financial_need_score'], $scores['academic_score'],
                $scores['motivation_score'], $scores['community_score'], $scores['purpose_score'],
                $scores['overall_score'], $total, $rating, $result, $recommendation, $remarks, $notes, $interviewerId
            ]);
            AuditLog::write('evaluation_saved', 'interview_evaluations', (int)$pdo->lastInsertId(),
                "Evaluation saved, total=$total");
        }

        // If recommended, bump app to For Final Evaluation
        $stmt = $pdo->prepare("SELECT application_id FROM interview_assignments WHERE id=?");
        $stmt->execute([$assignmentId]);
        $appId = (int)$stmt->fetchColumn();
        if ($appId && in_array($result, ['Recommended','Recommended with Conditions','For Further Review'], true)) {
            Application::updateStatus($appId, 'For Final Evaluation', $interviewerId);
        } elseif ($appId && $result === 'Not Recommended') {
            // leave for admin to decide - don't auto-reject
        }

        // Notify applicant
        self::notifyApplicantForApplication($appId, 'Interview Result Recorded',
            'Your interview has been evaluated. Please wait for the final scholarship decision.',
            'info');

        return ['ok' => true, 'total' => $total, 'rating' => $rating];
    }

    /* ---------------- STATS ---------------- */

    public static function stats(): array
    {
        $pdo = Database::conn();
        return $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM interview_schedules) AS total_schedules,
                (SELECT COUNT(*) FROM interview_assignments) AS total_assigned,
                (SELECT COUNT(*) FROM interview_assignments WHERE assignment_status = 'Completed') AS completed,
                (SELECT COUNT(*) FROM interview_assignments WHERE assignment_status = 'No Show') AS no_shows,
                (SELECT AVG(total_score) FROM interview_evaluations) AS avg_score
             FROM DUAL"
        )->fetch() ?: [];
    }

    /* ---------------- INTERNAL HELPERS ---------------- */

    public static function notifyAssignedApplicants(int $scheduleId, string $title, string $msg, string $type): void
    {
        $stmt = Database::conn()->prepare(
            "SELECT DISTINCT a.applicant_id, ap.user_id
             FROM interview_assignments ia
             JOIN applications a ON a.id = ia.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             WHERE ia.schedule_id = ?"
        );
        $stmt->execute([$scheduleId]);
        foreach ($stmt->fetchAll() as $row) {
            Notification::send((int)$row['user_id'], $title, $msg, $type, url('applicant/interview'));
        }
    }

    private static function notifyApplicantForApplication(int $applicationId, string $title, string $msg, string $type): void
    {
        $stmt = Database::conn()->prepare(
            "SELECT ap.user_id FROM applications a JOIN applicants ap ON ap.id = a.applicant_id WHERE a.id = ?"
        );
        $stmt->execute([$applicationId]);
        $uid = (int)$stmt->fetchColumn();
        if ($uid) Notification::send($uid, $title, $msg, $type, url('applicant/interview'));
    }
}