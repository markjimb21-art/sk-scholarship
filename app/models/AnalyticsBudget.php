<?php
declare(strict_types=1);

/** Analytics (2) Possible budget — committed, projected need, surplus, extra fundable — and (7) Fund utilization (releases). */
final class AnalyticsBudget
{
    /** Committed — see Budget::committed(). */
    public static function committed(int $programId, string $academicYear): float
    {
        return Budget::committed($programId, $academicYear);
    }

    /** Projected need = Committed + (projected scholars − current scholars) × amount per semester × 2. */
    public static function projectedNeed(int $programId, string $academicYear): float
    {
        $program = Program::find($programId);
        $newScholars = AnalyticsFunnel::projectedScholars($programId)['expected_new'];
        return self::committed($programId, $academicYear) + $newScholars * (float)$program['amount_per_semester'] * SEMESTERS_PER_YEAR;
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
        $perScholar = (float)$program['amount_per_semester'] * SEMESTERS_PER_YEAR;
        if ($surplus === null || $surplus <= 0 || $perScholar <= 0) return 0;
        return (int)floor($surplus / $perScholar);
    }

    /** Released per semester for the program: [semester label => amount]. */
    public static function releasedBySemester(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT CONCAT(t.academic_year, ' ', t.semester) AS sem, SUM(r.amount) FROM scholarship_releases r
             JOIN scholar_terms t ON t.id = r.term_id JOIN scholars s ON s.id = t.scholar_id
             WHERE s.program_id = ? GROUP BY t.academic_year, t.semester ORDER BY t.academic_year, t.semester"
        );
        $stmt->execute([$programId]);
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Released per calendar month (release_date) for the program's semesters of one A.Y. (null = all): ['YYYY-MM' => amount]. */
    public static function releasedByMonth(int $programId, ?string $academicYear = null): array
    {
        $sql = "SELECT DATE_FORMAT(r.release_date, '%Y-%m') AS ym, SUM(r.amount) FROM scholarship_releases r
                JOIN scholar_terms t ON t.id = r.term_id JOIN scholars s ON s.id = t.scholar_id WHERE s.program_id = ?";
        $params = [$programId];
        if ($academicYear) { $sql .= " AND t.academic_year = ?"; $params[] = $academicYear; }
        $stmt = Database::conn()->prepare($sql . " GROUP BY ym ORDER BY ym");
        $stmt->execute($params);
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Release rate = released ÷ total budget for the program + A.Y. (null without a budget). */
    public static function releaseRate(int $programId, string $academicYear): ?float
    {
        $b = Budget::forProgramYear($programId, $academicYear);
        if (!$b || (float)$b['total_budget'] <= 0) return null;
        return Budget::actualReleased($programId, $academicYear) / (float)$b['total_budget'];
    }

    /** Average released per scholar = released ÷ scholars who received at least one release (program + A.Y.). */
    public static function avgReleasedPerScholar(int $programId, string $academicYear): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT COALESCE(SUM(r.amount), 0) AS total, COUNT(DISTINCT r.scholar_id) AS n FROM scholarship_releases r
             JOIN scholar_terms t ON t.id = r.term_id JOIN scholars s ON s.id = t.scholar_id
             WHERE s.program_id = ? AND t.academic_year = ?"
        );
        $stmt->execute([$programId, $academicYear]);
        $r = $stmt->fetch();
        return ['avg' => (int)$r['n'] ? (float)$r['total'] / (int)$r['n'] : null, 'total' => (float)$r['total'], 'scholars' => (int)$r['n']];
    }
}
