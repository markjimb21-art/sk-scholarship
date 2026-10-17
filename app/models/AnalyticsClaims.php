<?php
declare(strict_types=1);

/** Analytics (4) On-time claim submissions, and the overdue list shared with the calendar. */
final class AnalyticsClaims
{
    /**
     * Scholars who missed a claim/renewal deadline: TermSchedule::missedDeadline() — the same list as Claims/Renewals →
     * Missed deadline — filtered by program, academic year and purpose (null = all). One row per scholar per window,
     * with the window in ['schedule']. The calendar colours a window red when it has at least one row here.
     */
    public static function overdue(?int $programId = null, ?string $academicYear = null, ?string $purpose = null): array
    {
        $rows = [];
        foreach ($purpose ? [$purpose] : TermSchedule::PURPOSES as $p) {
            foreach (TermSchedule::missedDeadline($p) as $m) {
                if ($programId && (int)$m['schedule']['program_id'] !== $programId) continue;
                if ($academicYear !== null && $m['schedule']['academic_year'] !== $academicYear) continue;
                $rows[] = $m;
            }
        }
        return $rows;
    }

    /**
     * Submitted claims of the program's semesters of an A.Y. joined to their release window: claim_status other than
     * Waiting for Requirements, submitted_at set, a term_schedules row (purpose release) for the same program + A.Y. + semester.
     */
    private const SUBMITTED_WITH_WINDOW_SQL = "FROM scholar_terms t
        JOIN term_schedules ts ON ts.program_id = t.program_id AND ts.academic_year = t.academic_year
                              AND ts.semester = t.semester AND ts.purpose = 'release'
        WHERE t.program_id = ? AND t.academic_year = ?
          AND t.claim_status <> 'Waiting for Requirements' AND t.submitted_at IS NOT NULL";

    /**
     * On-time rate = submitted on or before the window's deadline ÷ submitted claims with a window.
     * submitted_at is the LAST submission (a re-send after a Resubmit counts). Claims submitted with no window (history
     * from before claim windows existed) are not counted; 'without_window' says how many.
     * Returns ['rate' => float|null, 'on_time', 'total', 'without_window'].
     */
    public static function onTimeRate(int $programId, string $academicYear): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(t.submitted_at <= ts.submission_deadline), 0) AS on_time " . self::SUBMITTED_WITH_WINDOW_SQL);
        $stmt->execute([$programId, $academicYear]);
        $r = $stmt->fetch();
        $none = $pdo->prepare(
            "SELECT COUNT(*) FROM scholar_terms t
             WHERE t.program_id = ? AND t.academic_year = ? AND t.claim_status <> 'Waiting for Requirements' AND t.submitted_at IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM term_schedules ts WHERE ts.program_id = t.program_id AND ts.academic_year = t.academic_year
                                  AND ts.semester = t.semester AND ts.purpose = 'release')"
        );
        $none->execute([$programId, $academicYear]);
        $total = (int)$r['total'];
        return ['rate' => $total > 0 ? (int)$r['on_time'] / $total : null, 'on_time' => (int)$r['on_time'], 'total' => $total,
                'without_window' => (int)$none->fetchColumn()];
    }

    /** Currently overdue claims = scholars in AnalyticsClaims::overdue(program, A.Y., 'release') — the calendar's red deadlines. */
    public static function overdueCount(int $programId, string $academicYear): int
    {
        return count(self::overdue($programId, $academicYear, 'release'));
    }

    /** Average days from the window opening to the claim's submission (same base as onTimeRate). Null with no data. */
    public static function avgDaysToSubmit(int $programId, string $academicYear): ?float
    {
        $stmt = Database::conn()->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, ts.opens_at, t.submitted_at)) " . self::SUBMITTED_WITH_WINDOW_SQL);
        $stmt->execute([$programId, $academicYear]);
        $minutes = $stmt->fetchColumn();
        return $minutes === null ? null : (float)$minutes / 1440;
    }
}
