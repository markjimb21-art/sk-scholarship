<?php
declare(strict_types=1);

/** Analytics (3) Interview effectiveness: attendance, no-shows per attempt, score distribution. */
final class AnalyticsInterviews
{
    /** Score bands for the interview-score chart: label => [from (inclusive), to (exclusive)]. Total score is 0–100. */
    public const SCORE_BANDS = ['Below 50' => [0, 50], '50–69.99' => [50, 70], '70–100' => [70, 100.01]];

    /** Interview attempts of the program that happened (Attended or No-show), on schedules that weren't cancelled. */
    private const ATTEMPTS_SQL = "FROM interview_assignments ia JOIN interview_schedules s ON s.id = ia.schedule_id
                                  WHERE s.program_id = ? AND s.status <> 'Cancelled' AND ia.attendance IN ('Attended','No-show')";

    /** Attendance rate = Attended ÷ (Attended + No-show). Returns ['rate' => float|null, 'attended', 'total']. */
    public static function attendanceRate(int $programId): array
    {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(ia.attendance = 'Attended'), 0) AS attended " . self::ATTEMPTS_SQL);
        $stmt->execute([$programId]);
        $r = $stmt->fetch();
        $total = (int)$r['total'];
        return ['rate' => $total > 0 ? (int)$r['attended'] / $total : null, 'attended' => (int)$r['attended'], 'total' => $total];
    }

    /**
     * No-show rate = No-show ÷ (Attended + No-show), on the first attempt (reschedule_count 0) and after the one
     * reschedule (reschedule_count 1). Returns [0 => ['rate','noshow','total'], 1 => [...]].
     */
    public static function noShowByAttempt(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT LEAST(ia.reschedule_count, 1) AS attempt, COUNT(*) AS total, SUM(ia.attendance = 'No-show') AS noshow "
            . self::ATTEMPTS_SQL . " GROUP BY LEAST(ia.reschedule_count, 1)"
        );
        $stmt->execute([$programId]);
        $out = [0 => ['rate' => null, 'noshow' => 0, 'total' => 0], 1 => ['rate' => null, 'noshow' => 0, 'total' => 0]];
        foreach ($stmt->fetchAll() as $r) {
            $total = (int)$r['total'];
            $out[(int)$r['attempt']] = ['rate' => $total > 0 ? (int)$r['noshow'] / $total : null, 'noshow' => (int)$r['noshow'], 'total' => $total];
        }
        return $out;
    }

    /** Average total score of the program's scored interviews + count per SCORE_BANDS band. Returns ['average','count','bands']. */
    public static function scoreSummary(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ev.total_score FROM interview_evaluations ev
             JOIN interview_assignments ia ON ia.id = ev.assignment_id
             JOIN interview_schedules s ON s.id = ia.schedule_id
             WHERE s.program_id = ? AND ia.attendance = 'Attended'"
        );
        $stmt->execute([$programId]);
        $scores = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $bands = array_fill_keys(array_keys(self::SCORE_BANDS), 0);
        foreach ($scores as $s) {
            foreach (self::SCORE_BANDS as $label => [$from, $to]) {
                if ($s >= $from && $s < $to) { $bands[$label]++; break; }
            }
        }
        return ['average' => $scores ? array_sum($scores) / count($scores) : null, 'count' => count($scores), 'bands' => $bands];
    }
}
