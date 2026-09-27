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
