<?php
declare(strict_types=1);

/**
 * Applicant interview slot requests (docs/PROCESS.md §3a). A Qualified applicant (also after a first no-show) picks one of
 * the current top suggestions (InterviewSlots::suggestSlots). The request stays Pending until an admin confirms it — the
 * ONLY way it moves the application to Interview Scheduled, through the existing assign transition (capacity and the
 * one-reschedule rule are enforced there) — or declines it. The applicant is notified either way.
 */
final class SlotRequest
{
    /** How many suggestions an applicant is offered. */
    public const APPLICANT_CHOICES = 3;

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT r.*, a.application_code, a.program_id, a.status AS app_status, u.full_name, s.venue AS schedule_venue
             FROM slot_requests r
             JOIN applications a ON a.id = r.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN interview_schedules s ON s.id = r.schedule_id
             WHERE r.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function pendingFor(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT id FROM slot_requests WHERE application_id = ? AND status = 'Pending' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    /** The latest request of an application (any status), for showing the outcome to the applicant. */
    public static function latestFor(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT id FROM slot_requests WHERE application_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    /** Pending requests (oldest first), optionally of one program. */
    public static function pending(?int $programId = null): array
    {
        $sql = "SELECT r.*, a.application_code, a.program_id, u.full_name, s.venue AS schedule_venue,
                       (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.application_id = a.id AND ia.attendance = 'No-show') AS missed_before
                FROM slot_requests r
                JOIN applications a ON a.id = r.application_id
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                LEFT JOIN interview_schedules s ON s.id = r.schedule_id
                WHERE r.status = 'Pending'";
        $params = [];
        if ($programId) { $sql .= " AND a.program_id = ?"; $params[] = $programId; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY r.created_at, r.id");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** "Mon, Oct 5 · 8:00–10:00 AM at SK Hall" / "… (new schedule)". */
    public static function describe(array $r): string
    {
        return date('D, M j, Y', strtotime($r['proposed_date'])) . ' · ' . date('g:i', strtotime($r['proposed_start'])) . '–'
            . date('g:i A', strtotime($r['proposed_end'])) . ($r['schedule_id'] ? ' at ' . ($r['schedule_venue'] ?? 'the scheduled venue') : ' (new schedule)');
    }

    /**
     * Applicant picks a slot. $pick = ['kind','schedule_id','date','start'] from the form; it must still be one of the
     * applicant's current top APPLICANT_CHOICES suggestions (server-side — a crafted request can't pick anything else).
     */
    public static function create(array $app, array $pick, int $userId): array
    {
        if ($app['status'] !== 'Qualified') return ['ok' => false, 'error' => 'You can pick an interview slot only while you are waiting for a schedule.'];
        if (self::pendingFor((int)$app['id'])) return ['ok' => false, 'error' => 'You already asked for a slot. Wait for the SK office, or cancel that request first.'];
        $item = [
            'kind' => ($pick['kind'] ?? '') === 'schedule' ? 'schedule' : 'block',
            'schedule_id' => (int)($pick['schedule_id'] ?? 0) ?: null,
            'date' => (string)($pick['date'] ?? ''), 'start' => (string)($pick['start'] ?? ''),
        ];
        $slot = InterviewSlots::isCurrentSuggestion((int)$app['program_id'], $item, self::APPLICANT_CHOICES);
        if (!$slot) return ['ok' => false, 'error' => 'That slot is no longer available. Please pick one of the slots shown now.'];

        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO slot_requests (application_id, schedule_id, proposed_date, proposed_start, proposed_end) VALUES (?, ?, ?, ?, ?)"
        )->execute([(int)$app['id'], $slot['schedule_id'], $slot['date'], $slot['start'], $slot['end']]);
        $id = (int)$pdo->lastInsertId();
        $req = self::find($id);
        AuditLog::write('slot_requested', 'slot_requests', $id, "{$app['application_code']} asked for " . self::describe($req), null, ['by' => $userId]);
        Notification::adminEvent('Interview slot requested', "{$req['full_name']} ({$app['application_code']}) asked for " . self::describe($req) . '. Confirm or decline it.',
            'admin/interview-reschedule&application_id=' . (int)$app['id']);
        return ['ok' => true, 'request' => $req];
    }

    /** Applicant withdraws their pending request. */
    public static function cancel(int $applicationId, int $userId): array
    {
        $req = self::pendingFor($applicationId);
        if (!$req) return ['ok' => false, 'error' => 'There is no pending slot request.'];
        Database::conn()->prepare("UPDATE slot_requests SET status = 'Cancelled', decision_note = 'Withdrawn by the applicant', decided_at = NOW()
                                   WHERE id = ? AND status = 'Pending'")->execute([(int)$req['id']]);
        AuditLog::write('slot_request_cancelled', 'slot_requests', (int)$req['id'], "{$req['application_code']} withdrew the slot request", null, ['by' => $userId]);
        return ['ok' => true];
    }

    /**
     * Admin confirms (one click): the picked schedule — or, for a free block, the program's Open schedule at that time or a
     * new one at $venue with $capacity — and the application is assigned through InterviewAssignment::scheduleApplication().
     * If the slot can't be used any more, the request is declined with the reason and the applicant is told.
     */
    public static function confirm(int $id, string $venue, int $capacity, int $adminId): array
    {
        $req = self::find($id);
        if (!$req || $req['status'] !== 'Pending') return ['ok' => false, 'error' => 'This request is no longer pending.'];
        $r = $req['schedule_id']
            ? InterviewAssignment::scheduleApplication((int)$req['application_id'], (int)$req['schedule_id'], $adminId)
            : InterviewAssignment::scheduleApplicationAt((int)$req['application_id'], [
                'interview_date' => $req['proposed_date'], 'start_time' => substr($req['proposed_start'], 0, 5),
                'end_time' => substr($req['proposed_end'], 0, 5), 'venue' => $venue, 'max_slots' => $capacity,
            ], $adminId);
        if (!$r['ok']) {
            self::decline($id, 'The slot could not be confirmed: ' . $r['error'], $adminId);
            return ['ok' => false, 'error' => $r['error'] . ' The request was declined and the applicant asked to pick again.'];
        }
        // assign() already closed pending requests as "Scheduled by the SK office"; record that THIS one was confirmed.
        Database::conn()->prepare("UPDATE slot_requests SET status = 'Confirmed', decision_note = NULL, decided_by = ?, decided_at = NOW() WHERE id = ?")
            ->execute([$adminId, $id]);
        AuditLog::write('slot_request_confirmed', 'slot_requests', $id, "Confirmed {$req['application_code']}: " . self::describe($req));
        return ['ok' => true];
    }

    public static function decline(int $id, string $note, int $adminId): array
    {
        $req = self::find($id);
        if (!$req || $req['status'] !== 'Pending') return ['ok' => false, 'error' => 'This request is no longer pending.'];
        $note = trim($note) !== '' ? mb_substr(trim($note), 0, 255) : 'The SK office could not use this slot.';
        Database::conn()->prepare("UPDATE slot_requests SET status = 'Declined', decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND status = 'Pending'")
            ->execute([$note, $adminId, $id]);
        AuditLog::write('slot_request_declined', 'slot_requests', $id, "Declined {$req['application_code']}: $note");
        Application::notify((int)$req['application_id'], 'Interview slot not confirmed',
            'Your requested interview slot (' . self::describe($req) . ") was not confirmed.\nReason: $note\nPlease pick another slot on your dashboard.", 'warning');
        return ['ok' => true];
    }
}
