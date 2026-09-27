<?php
declare(strict_types=1);

/** Interview schedules, assignments (attendance) and rubric scores (B3–B4). */
final class Interview
{
    /** The application's interview assignment with its schedule, if any. */
    public static function assignmentByApplication(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.status AS schedule_status
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             WHERE ia.application_id = ? LIMIT 1"
        );
        $stmt->execute([$applicationId]);
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
}
