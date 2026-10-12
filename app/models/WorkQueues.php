<?php
declare(strict_types=1);

/** The dashboard's "needs your action" counters: how many items wait in each staff work queue. */
final class WorkQueues
{
    /** How many items wait in each work queue (all programs). $today = Y-m-d for "interviews today". */
    public static function queueCounts(string $today): array
    {
        $pdo = Database::conn();
        $count = static fn(string $sql, array $p = []): int => (int)(($s = $pdo->prepare($sql)) && $s->execute($p) ? $s->fetchColumn() : 0);
        return [
            'to_screen'        => $count("SELECT COUNT(*) FROM applications WHERE status = 'Submitted'"),
            'qualified'        => $count("SELECT COUNT(*) FROM applications WHERE status = 'Qualified'"),
            'interviews_today' => $count("SELECT COUNT(*) FROM interview_assignments ia JOIN interview_schedules s ON s.id = ia.schedule_id
                                          WHERE ia.attendance = 'Scheduled' AND s.status = 'Open' AND s.interview_date = ?", [$today]),
            'slot_requests'    => $count("SELECT COUNT(*) FROM slot_requests WHERE status = 'Pending'"),
            'to_decide'        => $count("SELECT COUNT(*) FROM applications WHERE status = 'Interviewed'"),
            'claims_to_verify' => $count("SELECT COUNT(*) FROM scholar_terms WHERE claim_status = 'Submitted'"),
            'to_release'       => $count("SELECT COUNT(*) FROM scholar_terms WHERE claim_status = 'Verified'"),
            'renewals'         => $count("SELECT COUNT(*) FROM renewal_requests WHERE status = 'Submitted'"),
        ];
    }
}
