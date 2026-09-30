<?php
declare(strict_types=1);

/**
 * The two analyses in docs/PROCESS.md §6 — one small function per number, so each figure on the
 * analytics page can be explained by the formula printed under it.
 */
final class Analytics
{
    /** Stages shown in the funnel, in process order. */
    public const STAGES = ['Submitted', 'Returned', 'Qualified', 'Interview Scheduled', 'Interviewed', 'Approved', 'Waitlisted', 'Rejected'];

    /** Stages that can still turn into Approved. */
    public const OPEN_STAGES = ['Submitted', 'Returned', 'Qualified', 'Interview Scheduled', 'Interviewed', 'Waitlisted'];

    /**
     * SQL condition "application a reached stage S at some point" (not just "is in S now").
     * Returned/Qualified/Waitlisted are timestamped; the interview stages come from the interview tables.
     */
    private static function reachedCondition(string $stage): string
    {
        return match ($stage) {
            'Submitted'           => 'a.submitted_at IS NOT NULL',
            'Returned'            => 'a.review_remarks_at IS NOT NULL',
            'Qualified'           => 'a.qualified_at IS NOT NULL',
            'Interview Scheduled' => 'EXISTS (SELECT 1 FROM interview_assignments ia WHERE ia.application_id = a.id)',
            'Interviewed'         => 'EXISTS (SELECT 1 FROM interview_assignments ia JOIN interview_evaluations ev ON ev.assignment_id = ia.id WHERE ia.application_id = a.id)',
            'Waitlisted'          => 'a.waitlisted_at IS NOT NULL',
            default               => "a.status = '" . ($stage === 'Approved' ? 'Approved' : 'Rejected') . "'",
        };
    }

    /* ---------------- Overdue (shared by the calendar and section 4) ---------------- */

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

    /* ---------------- (1) Possible scholars ---------------- */

    /** Count of the program's applications currently in each stage. */
    public static function stageCounts(int $programId): array
    {
        $stmt = Database::conn()->prepare("SELECT status, COUNT(*) FROM applications WHERE program_id = ? GROUP BY status");
        $stmt->execute([$programId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach (self::STAGES as $s) $out[$s] = (int)($rows[$s] ?? 0);
        return $out;
    }

    /** Programs that are closed (admin closed them) and whose application period has ended — excluding $exceptId. */
    public static function closedProgramIds(int $exceptId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM scholarship_programs WHERE is_active = 0 AND application_end < CURDATE() AND id <> ?"
        );
        $stmt->execute([$exceptId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Conversion rate of each open stage to Approved:
     *   rate(S) = applications that reached S and ended Approved ÷ applications that reached S.
     * Base = applications of CLOSED past programs; with no history, this program's applications that
     * already have a final result (Approved or Rejected) — labelled "based on current data only".
     * Returns ['basis' => text, 'current_only' => bool, 'rates' => [stage => ['rate'=>float|null,'reached'=>n,'approved'=>n]]].
     */
    public static function conversionRates(int $programId): array
    {
        $closed = self::closedProgramIds($programId);
        if ($closed) {
            $scope = 'a.program_id IN (' . implode(',', array_fill(0, count($closed), '?')) . ')';
            $params = $closed;
            $basis = count($closed) . ' closed past program(s)';
            $currentOnly = false;
        } else {
            $scope = "a.program_id = ? AND a.status IN ('Approved','Rejected')";
            $params = [$programId];
            $basis = 'this program’s decided applications (no closed past program yet)';
            $currentOnly = true;
        }
        $rates = [];
        foreach (self::OPEN_STAGES as $stage) {
            $cond = self::reachedCondition($stage);
            $stmt = Database::conn()->prepare(
                "SELECT COUNT(*) AS reached, SUM(a.status = 'Approved') AS approved
                 FROM applications a WHERE $scope AND $cond"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();
            $reached = (int)$row['reached'];
            $approved = (int)$row['approved'];
            $rates[$stage] = ['reached' => $reached, 'approved' => $approved, 'rate' => $reached > 0 ? $approved / $reached : null];
        }
        return ['basis' => $basis, 'current_only' => $currentOnly, 'rates' => $rates];
    }

    /**
     * Projected scholars = slots used + Σ over open stages (count in stage × conversion rate), capped at the slots.
     * A stage with no data (rate null) adds 0. Returns ['value','uncapped','expected_new','terms' => [stage => contribution]].
     */
    public static function projectedScholars(int $programId): array
    {
        $program = Program::find($programId);
        $counts = self::stageCounts($programId);
        $rates = self::conversionRates($programId)['rates'];
        $used = Program::slotsUsed($programId);
        $terms = [];
        foreach (self::OPEN_STAGES as $s) $terms[$s] = $counts[$s] * (float)($rates[$s]['rate'] ?? 0);
        $uncapped = $used + array_sum($terms);
        $value = min((float)$program['slots_available'], $uncapped);
        return ['value' => $value, 'uncapped' => $uncapped, 'expected_new' => max(0.0, $value - $used), 'terms' => $terms, 'slots_used' => $used];
    }

    /** Slots remaining = slots − slots used. */
    public static function slotsRemaining(int $programId): int
    {
        $program = Program::find($programId);
        return max(0, (int)$program['slots_available'] - Program::slotsUsed($programId));
    }

    /** Waitlisted applicants available to fill open slots. */
    public static function waitlistedAvailable(int $programId): int
    {
        return self::stageCounts($programId)['Waitlisted'];
    }

    /* ---------------- (2) Possible budget ---------------- */

    /** Committed — see Budget::committed(). */
    public static function committed(int $programId, string $academicYear): float
    {
        return Budget::committed($programId, $academicYear);
    }

    /** Projected need = Committed + (projected scholars − current scholars) × amount per semester × 2. */
    public static function projectedNeed(int $programId, string $academicYear): float
    {
        $program = Program::find($programId);
        $newScholars = self::projectedScholars($programId)['expected_new'];
        return self::committed($programId, $academicYear) + $newScholars * (float)$program['amount_per_semester'] * 2;
    }

    /** Remaining = total budget − released (null when there is no budget for the program + A.Y.). */
    public static function remaining(int $programId, string $academicYear): ?float
    {
        $b = Budget::forProgramYear($programId, $academicYear);
        return $b ? (float)$b['total_budget'] - Budget::actualReleased($programId, $academicYear) : null;
    }

    /** Surplus (+) or shortfall (−) = remaining − projected need. */
    public static function surplus(int $programId, string $academicYear): ?float
    {
        $remaining = self::remaining($programId, $academicYear);
        return $remaining === null ? null : $remaining - self::projectedNeed($programId, $academicYear);
    }

    /* ---------------- (3) Interview effectiveness ---------------- */

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

    /* ---------------- (4) On-time submissions (claims) ---------------- */

    /**
     * Submitted claims of a program + A.Y. joined to their release window: claim_status other than Waiting for
     * Requirements, submitted_at set, a term_schedules row (purpose release) for the same program + A.Y. + semester.
     */
    private const SUBMITTED_WITH_WINDOW_SQL = "FROM scholar_terms t
        JOIN scholars sc ON sc.id = t.scholar_id
        JOIN term_schedules ts ON ts.program_id = sc.program_id AND ts.academic_year = t.academic_year
                              AND ts.semester = t.semester AND ts.purpose = 'release'
        WHERE sc.program_id = ? AND t.academic_year = ?
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
            "SELECT COUNT(*) FROM scholar_terms t JOIN scholars sc ON sc.id = t.scholar_id
             WHERE sc.program_id = ? AND t.academic_year = ? AND t.claim_status <> 'Waiting for Requirements' AND t.submitted_at IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM term_schedules ts WHERE ts.program_id = sc.program_id AND ts.academic_year = t.academic_year
                                  AND ts.semester = t.semester AND ts.purpose = 'release')"
        );
        $none->execute([$programId, $academicYear]);
        $total = (int)$r['total'];
        return ['rate' => $total > 0 ? (int)$r['on_time'] / $total : null, 'on_time' => (int)$r['on_time'], 'total' => $total,
                'without_window' => (int)$none->fetchColumn()];
    }

    /** Currently overdue claims = scholars in Analytics::overdue(program, A.Y., 'release') — the calendar's red deadlines. */
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

    /** Extra scholars fundable = floor(surplus ÷ (amount per semester × 2)); 0 when there is a shortfall. */
    public static function extraFundable(int $programId, string $academicYear): int
    {
        $program = Program::find($programId);
        $surplus = self::surplus($programId, $academicYear);
        $perScholar = (float)$program['amount_per_semester'] * 2;
        if ($surplus === null || $surplus <= 0 || $perScholar <= 0) return 0;
        return (int)floor($surplus / $perScholar);
    }
}
