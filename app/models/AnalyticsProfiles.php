<?php
declare(strict_types=1);

/** Analytics (5) Applicant and scholar profiles (purok, school, course, age, income) and (6) Academic performance (GWA). */
final class AnalyticsProfiles
{
    /** Profile dimensions: key => label. */
    public const PROFILE_DIMENSIONS = [
        'purok' => 'Purok / subdivision', 'school' => 'School', 'course' => 'Course', 'year_level' => 'Year level',
        'sex' => 'Sex', 'age_band' => 'Age on the submission date', 'income_band' => 'Monthly family income',
    ];

    /** Submitted applications of a program with the applicant's profile (current profile — the form is per applicant). */
    private const PROFILE_FROM = "FROM applications a
        LEFT JOIN applicants_personal_information pi ON pi.applicant_id = a.applicant_id
        LEFT JOIN puroks pu ON pu.id = pi.purok_id
        LEFT JOIN education_records e ON e.id = (SELECT MAX(e2.id) FROM education_records e2 WHERE e2.applicant_id = a.applicant_id)
        LEFT JOIN family_background f ON f.applicant_id = a.applicant_id
        WHERE a.program_id = ? AND a.status <> 'Draft'";

    /** SQL CASE turning $expr into the label of its band (bands: label => [from, to) ), 'Not given' when NULL. */
    private static function bandCase(string $expr, array $bands): string
    {
        $sql = "CASE WHEN $expr IS NULL THEN 'Not given'";
        foreach ($bands as $label => [$from, $to]) {
            $sql .= ' WHEN ' . $expr . ' >= ' . (float)$from . ' AND ' . $expr . ' < ' . (float)$to . ' THEN ' . Database::conn()->quote($label);
        }
        return $sql . " ELSE 'Not given' END";
    }

    /**
     * Applicants vs approved scholars of a program, grouped by one profile dimension (PROFILE_DIMENSIONS).
     * Applicants = submitted applications; Approved = status Approved; approval rate = approved ÷ applicants.
     * Returns [['group','applicants','approved','rate' => float|null]], bands in band order, others by applicants desc.
     */
    public static function profileBreakdown(int $programId, string $dimension): array
    {
        $group = match ($dimension) {
            'purok'       => "COALESCE(pu.purok_name, 'Not given')",
            'school'      => "COALESCE(NULLIF(TRIM(e.school_name), ''), 'Not given')",
            'course'      => "COALESCE(NULLIF(TRIM(e.course_name), ''), 'Not given')",
            'year_level'  => "COALESCE(CAST(e.year_level AS CHAR), 'Not given')",
            'sex'         => "COALESCE(CAST(pi.sex AS CHAR), 'Not given')",
            'age_band'    => self::bandCase('TIMESTAMPDIFF(YEAR, pi.date_of_birth, DATE(COALESCE(a.submitted_at, a.created_at)))', AGE_BANDS),
            'income_band' => self::bandCase('f.total_family_income', INCOME_BANDS),
            default       => throw new InvalidArgumentException('Unknown profile dimension.'),
        };
        $stmt = Database::conn()->prepare(
            "SELECT $group AS grp, COUNT(*) AS applicants, SUM(a.status = 'Approved') AS approved " . self::PROFILE_FROM . " GROUP BY grp"
        );
        $stmt->execute([$programId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $n = (int)$r['applicants'];
            $rows[$r['grp']] = ['group' => $r['grp'], 'applicants' => $n, 'approved' => (int)$r['approved'], 'rate' => $n ? (int)$r['approved'] / $n : null];
        }
        $order = match ($dimension) { 'age_band' => array_keys(AGE_BANDS), 'income_band' => array_keys(INCOME_BANDS), 'year_level' => YEAR_LEVELS, default => null };
        if ($order) {
            $sorted = [];
            foreach (array_merge($order, ['Not given']) as $k) if (isset($rows[$k])) $sorted[] = $rows[$k];
            return $sorted;
        }
        $rows = array_values($rows);
        usort($rows, static fn($x, $y) => [$y['applicants'], $x['group']] <=> [$x['applicants'], $y['group']]);
        return $rows;
    }

    /**
     * Average GWA (Philippine scale, lower = better) of applicants vs approved scholars of a program, from the GWA on the
     * application form. Returns ['applicants' => ['avg','n'], 'scholars' => ['avg','n']].
     */
    public static function gwaApplicantsVsScholars(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT AVG(e.general_average) AS avg_all, COUNT(e.general_average) AS n_all,
                    AVG(IF(a.status = 'Approved', e.general_average, NULL)) AS avg_sch, COUNT(IF(a.status = 'Approved', e.general_average, NULL)) AS n_sch "
            . self::PROFILE_FROM
        );
        $stmt->execute([$programId]);
        $r = $stmt->fetch();
        return ['applicants' => ['avg' => $r['avg_all'] !== null ? (float)$r['avg_all'] : null, 'n' => (int)$r['n_all']],
                'scholars'   => ['avg' => $r['avg_sch'] !== null ? (float)$r['avg_sch'] : null, 'n' => (int)$r['n_sch']]];
    }

    /** Recorded semester GWAs (scholar_terms.gwa, set when a claim is verified) of the program's semesters, oldest first. */
    private static function termGwas(int $programId, ?string $academicYear = null): array
    {
        $sql = "SELECT s.id AS scholar_id, s.scholar_code, s.status, u.full_name, t.academic_year, t.semester, t.gwa
                FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id
                JOIN applicants ap ON ap.id = s.applicant_id JOIN users u ON u.id = ap.user_id
                WHERE t.program_id = ? AND t.gwa IS NOT NULL";
        $params = [$programId];
        if ($academicYear) { $sql .= " AND t.academic_year = ?"; $params[] = $academicYear; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY u.full_name, s.id, t.academic_year, t.semester");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Semester labels ("2026-2027 1st Semester") that have a recorded GWA, in order. */
    public static function gwaSemesters(int $programId, ?string $academicYear = null): array
    {
        $labels = [];
        foreach (self::termGwas($programId, $academicYear) as $t) $labels["{$t['academic_year']} {$t['semester']}"] = true;
        $labels = array_keys($labels);
        sort($labels);
        return $labels;
    }

    /**
     * GWA per semester per scholar: [['scholar_id','scholar_code','full_name','status','gwas' => [semester label => gwa]]].
     */
    public static function gwaPerScholar(int $programId, ?string $academicYear = null): array
    {
        $out = [];
        foreach (self::termGwas($programId, $academicYear) as $t) {
            $id = (int)$t['scholar_id'];
            $out[$id] ??= ['scholar_id' => $id, 'scholar_code' => $t['scholar_code'], 'full_name' => $t['full_name'], 'status' => $t['status'], 'gwas' => []];
            $out[$id]['gwas']["{$t['academic_year']} {$t['semester']}"] = (float)$t['gwa'];
        }
        return array_values($out);
    }

    /** Average recorded GWA per semester for the program: [semester label => ['avg','n']] (lower = better). */
    public static function avgGwaBySemester(int $programId, ?string $academicYear = null): array
    {
        $sum = [];
        foreach (self::termGwas($programId, $academicYear) as $t) {
            $k = "{$t['academic_year']} {$t['semester']}";
            $sum[$k] ??= ['total' => 0.0, 'n' => 0];
            $sum[$k]['total'] += (float)$t['gwa'];
            $sum[$k]['n']++;
        }
        ksort($sum);
        return array_map(static fn($s) => ['avg' => $s['total'] / $s['n'], 'n' => $s['n']], $sum);
    }

    /**
     * At-risk scholars: ACTIVE scholars whose latest recorded semester GWA is worse (a higher number) than the program's
     * minimum GWA. Returns [['scholar_id','scholar_code','full_name','semester','gwa','min_gpa']]; [] when no minimum is set.
     */
    public static function atRiskScholars(int $programId): array
    {
        $program = Program::find($programId);
        if (!$program || $program['min_gpa'] === null) return [];
        $min = (float)$program['min_gpa'];
        $out = [];
        foreach (self::gwaPerScholar($programId) as $s) {
            if ($s['status'] !== 'Active' || !$s['gwas']) continue;
            $last = array_key_last($s['gwas']);
            if ($s['gwas'][$last] > $min) $out[] = $s + ['semester' => $last, 'gwa' => $s['gwas'][$last], 'min_gpa' => $min];
        }
        return $out;
    }

    /**
     * GWA trend per scholar with at least two recorded semesters: change = last − first; Improving when the GWA went
     * DOWN (better), Declining when it went up, Steady within ±0.05. Returns [scholar rows + 'first','last','change','trend'].
     */
    public static function gwaTrend(int $programId, ?string $academicYear = null): array
    {
        $out = [];
        foreach (self::gwaPerScholar($programId, $academicYear) as $s) {
            if (count($s['gwas']) < 2) continue;
            $first = reset($s['gwas']);
            $last = end($s['gwas']);
            $change = round($last - $first, 2);
            $out[] = $s + ['first' => $first, 'last' => $last, 'change' => $change,
                           'trend' => abs($change) <= 0.05 ? 'Steady' : ($change < 0 ? 'Improving' : 'Declining')];
        }
        return $out;
    }

    /**
     * Active scholars whose CURRENT program (their latest semester's) is this one, per GWA band (GWA_BANDS), using each
     * scholar's latest recorded semester GWA, else the application GWA.
     */
    public static function scholarsByGwaBand(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT COALESCE((SELECT t.gwa FROM scholar_terms t WHERE t.scholar_id = s.id AND t.gwa IS NOT NULL
                              ORDER BY t.academic_year DESC, t.semester DESC LIMIT 1),
                             (SELECT e.general_average FROM education_records e WHERE e.applicant_id = s.applicant_id ORDER BY e.id DESC LIMIT 1)) AS gwa
             FROM scholars s
             WHERE s.status = 'Active'
               AND (SELECT ct.program_id FROM scholar_terms ct WHERE ct.scholar_id = s.id ORDER BY ct.academic_year DESC, ct.semester DESC LIMIT 1) = ?"
        );
        $stmt->execute([$programId]);
        $bands = array_fill_keys(array_merge(array_keys(GWA_BANDS), ['Not recorded']), 0);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $g) {
            if ($g === null) { $bands['Not recorded']++; continue; }
            foreach (GWA_BANDS as $label => [$from, $to]) if ((float)$g >= $from && (float)$g <= $to + 0.004) { $bands[$label]++; break; }
        }
        return $bands;
    }
}
