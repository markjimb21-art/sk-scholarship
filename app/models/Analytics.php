<?php
declare(strict_types=1);

final class Analytics
{
    /* ============ KPIs ============ */
    public static function kpis(?int $programId = null): array
    {
        $pdo = Database::conn();
        $where = $programId ? " WHERE program_id = " . (int)$programId : "";

        $apps = $pdo->query(
            "SELECT
                COUNT(*) AS total_applicants,
                SUM(status='Approved') AS approved,
                SUM(status='Rejected') AS rejected,
                SUM(status='Waitlisted') AS waitlisted,
                SUM(status IN ('Interview Completed','For Final Evaluation')) AS interviewed,
                SUM(status IN ('For Interview','Interview Scheduled')) AS scheduled_interviews,
                SUM(status IN ('Submitted','Under Initial Review','Incomplete','Documents Under Review')) AS pending
             FROM applications $where"
        )->fetch();

        $scholars = $pdo->query(
            "SELECT
                COUNT(*) AS total_scholars,
                SUM(status='Active') AS active_scholars,
                SUM(status='Graduated') AS graduated,
                SUM(status='On Probation') AS on_probation,
                SUM(status='Withdrawn') AS withdrawn,
                SUM(status='Suspended') AS suspended
             FROM scholars"
        )->fetch();

        $funds = $pdo->query(
            "SELECT COALESCE(SUM(amount),0) AS total_released FROM scholarship_releases"
        )->fetch();

        $budget = $pdo->query(
            "SELECT COALESCE(SUM(total_budget),0) AS total_budget FROM budgets"
        )->fetch();

        $gpa = $pdo->query("SELECT AVG(gpa) AS avg_gpa FROM academic_records")->fetch();

        $interview = $pdo->query(
            "SELECT
                COUNT(*) AS total_assigned,
                SUM(assignment_status='Completed') AS completed,
                SUM(assignment_status='No Show') AS no_shows
             FROM interview_assignments"
        )->fetch();

        $attendanceRate = ((int)($interview['total_assigned'] ?? 0)) > 0
            ? round(((int)$interview['completed'] / (int)$interview['total_assigned']) * 100, 1)
            : 0.0;

        $approvalRate = ((int)($apps['total_applicants'] ?? 0)) > 0
            ? round(((int)$apps['approved'] / (int)$apps['total_applicants']) * 100, 1)
            : 0.0;

        return [
            'total_applicants'   => (int)($apps['total_applicants'] ?? 0),
            'approved'           => (int)($apps['approved'] ?? 0),
            'rejected'           => (int)($apps['rejected'] ?? 0),
            'waitlisted'         => (int)($apps['waitlisted'] ?? 0),
            'interviewed'        => (int)($apps['interviewed'] ?? 0),
            'scheduled'          => (int)($apps['scheduled_interviews'] ?? 0),
            'pending'            => (int)($apps['pending'] ?? 0),
            'active_scholars'    => (int)($scholars['active_scholars'] ?? 0),
            'total_scholars'     => (int)($scholars['total_scholars'] ?? 0),
            'graduated'          => (int)($scholars['graduated'] ?? 0),
            'on_probation'       => (int)($scholars['on_probation'] ?? 0),
            'withdrawn'          => (int)($scholars['withdrawn'] ?? 0),
            'suspended'          => (int)($scholars['suspended'] ?? 0),
            'total_released'     => (float)($funds['total_released'] ?? 0),
            'total_budget'       => (float)($budget['total_budget'] ?? 0),
            'remaining_budget'   => (float)$budget['total_budget'] - (float)$funds['total_released'],
            'avg_gpa'            => $gpa['avg_gpa'] !== null ? round((float)$gpa['avg_gpa'], 2) : null,
            'attendance_rate'    => $attendanceRate,
            'approval_rate'      => $approvalRate,
        ];
    }

    /* ============ APPLICANT ANALYTICS ============ */
    public static function applicantsByStatus(?int $programId = null): array
    {
        $w = $programId ? " WHERE program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT status AS label, COUNT(*) AS value FROM applications $w GROUP BY status ORDER BY value DESC"
        )->fetchAll();
    }

    public static function applicantsBySchool(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT COALESCE(e.school_name, 'Unknown') AS label, COUNT(*) AS value
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             WHERE 1=1 $w
             GROUP BY e.school_name ORDER BY value DESC LIMIT 15"
        )->fetchAll();
    }

    public static function applicantsByCourse(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT COALESCE(e.course_name, 'Unknown') AS label, COUNT(*) AS value
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             WHERE 1=1 $w
             GROUP BY e.course_name ORDER BY value DESC LIMIT 15"
        )->fetchAll();
    }

    public static function applicantsByYearLevel(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT COALESCE(e.year_level, 'Unknown') AS label, COUNT(*) AS value
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             WHERE 1=1 $w
             GROUP BY e.year_level ORDER BY FIELD(e.year_level, '1st Year','2nd Year','3rd Year','4th Year','5th Year','Other')"
        )->fetchAll();
    }

    public static function applicantsByCivilStatus(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT COALESCE(p.civil_status, 'Unknown') AS label, COUNT(*) AS value
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN applicants_personal_information p ON p.applicant_id = ap.id
             WHERE 1=1 $w
             GROUP BY p.civil_status ORDER BY value DESC"
        )->fetchAll();
    }

    public static function applicantsByPurok(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        return Database::conn()->query(
            "SELECT COALESCE(pr.purok_name, 'Not specified') AS label, COUNT(*) AS value
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN applicants_personal_information p ON p.applicant_id = ap.id
             LEFT JOIN puroks pr ON pr.id = p.purok_id
             WHERE 1=1 $w
             GROUP BY pr.purok_name ORDER BY value DESC LIMIT 20"
        )->fetchAll();
    }

    /* ============ FAMILY / FINANCIAL ============ */
    public static function applicantsByIncomeRange(?int $programId = null): array
    {
        $w = $programId ? " AND a.program_id = " . (int)$programId : "";
        $rows = Database::conn()->query(
            "SELECT f.total_family_income AS income
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             LEFT JOIN family_background f ON f.applicant_id = ap.id
             WHERE 1=1 $w AND f.total_family_income IS NOT NULL"
        )->fetchAll();

        $buckets = [
            'Below ₱5,000'    => 0,
            '₱5,000–₱10,000'  => 0,
            '₱10,001–₱15,000' => 0,
            '₱15,001–₱20,000' => 0,
            '₱20,001–₱30,000' => 0,
            'Above ₱30,000'   => 0,
        ];
        foreach ($rows as $r) {
            $i = (float)$r['income'];
            if      ($i < 5000)  $buckets['Below ₱5,000']++;
            elseif  ($i <= 10000) $buckets['₱5,000–₱10,000']++;
            elseif  ($i <= 15000) $buckets['₱10,001–₱15,000']++;
            elseif  ($i <= 20000) $buckets['₱15,001–₱20,000']++;
            elseif  ($i <= 30000) $buckets['₱20,001–₱30,000']++;
            else                  $buckets['Above ₱30,000']++;
        }
        $out = [];
        foreach ($buckets as $label => $value) $out[] = ['label' => $label, 'value' => $value];
        return $out;
    }

    public static function avgParentalIncome(): array
    {
        return Database::conn()->query(
            "SELECT
                AVG(father_income) AS avg_father,
                AVG(mother_income) AS avg_mother,
                AVG(combined_income) AS avg_combined
             FROM family_background"
        )->fetch() ?: [];
    }

    /* ============ INTERVIEW ANALYTICS ============ */
    public static function interviewResults(): array
    {
        return Database::conn()->query(
            "SELECT COALESCE(result, 'Not Evaluated') AS label, COUNT(*) AS value
             FROM interview_assignments ia
             LEFT JOIN interview_evaluations e ON e.assignment_id = ia.id
             GROUP BY e.result ORDER BY value DESC"
        )->fetchAll();
    }

    public static function interviewAttendanceByMonth(): array
    {
        return Database::conn()->query(
            "SELECT DATE_FORMAT(s.interview_date, '%Y-%m') AS label,
                    SUM(ia.assignment_status = 'Completed') AS completed,
                    SUM(ia.assignment_status = 'No Show')   AS no_shows
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             WHERE s.interview_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY label ORDER BY label"
        )->fetchAll();
    }

    public static function interviewScoresByCriteria(): array
    {
        return Database::conn()->query(
            "SELECT
                AVG(financial_need_score) AS financial_need,
                AVG(academic_score)       AS academic,
                AVG(motivation_score)     AS motivation,
                AVG(community_score)      AS community,
                AVG(purpose_score)        AS purpose,
                AVG(total_score)          AS total
             FROM interview_evaluations"
        )->fetch() ?: [];
    }

    /* ============ ACADEMIC ANALYTICS ============ */
    public static function gpaDistribution(): array
    {
        $buckets = [
            '1.00–1.25' => 0, '1.26–1.50' => 0, '1.51–1.75' => 0,
            '1.76–2.00' => 0, '2.01–2.25' => 0, '2.26–2.50' => 0,
            '2.51–2.75' => 0, '2.76–3.00' => 0, '3.01–4.00' => 0, '4.01–5.00' => 0,
        ];
        $rows = Database::conn()->query("SELECT gpa FROM academic_records WHERE gpa IS NOT NULL")->fetchAll();
        foreach ($rows as $r) {
            $g = (float)$r['gpa'];
            if      ($g <= 1.25) $buckets['1.00–1.25']++;
            elseif  ($g <= 1.50) $buckets['1.26–1.50']++;
            elseif  ($g <= 1.75) $buckets['1.51–1.75']++;
            elseif  ($g <= 2.00) $buckets['1.76–2.00']++;
            elseif  ($g <= 2.25) $buckets['2.01–2.25']++;
            elseif  ($g <= 2.50) $buckets['2.26–2.50']++;
            elseif  ($g <= 2.75) $buckets['2.51–2.75']++;
            elseif  ($g <= 3.00) $buckets['2.76–3.00']++;
            elseif  ($g <= 4.00) $buckets['3.01–4.00']++;
            else                  $buckets['4.01–5.00']++;
        }
        $out = [];
        foreach ($buckets as $l => $v) $out[] = ['label' => $l, 'value' => $v];
        return $out;
    }

    public static function gpaTrendByYear(): array
    {
        return Database::conn()->query(
            "SELECT academic_year AS label, AVG(gpa) AS value, COUNT(*) AS c
             FROM academic_records
             WHERE gpa IS NOT NULL
             GROUP BY academic_year ORDER BY academic_year"
        )->fetchAll();
    }

    public static function gpaBySchool(): array
    {
        return Database::conn()->query(
            "SELECT COALESCE(school, 'Unknown') AS label, AVG(gpa) AS value
             FROM academic_records
             WHERE gpa IS NOT NULL
             GROUP BY school ORDER BY value ASC LIMIT 15"
        )->fetchAll();
    }

    public static function atRiskCount(): int
    {
        return (int)Database::conn()->query(
            "SELECT COUNT(DISTINCT ar.scholar_id)
             FROM academic_records ar
             JOIN scholars s ON s.id = ar.scholar_id
             WHERE s.status IN ('Active','On Probation') AND ar.gpa > 2.50
               AND ar.id = (SELECT MAX(id) FROM academic_records WHERE scholar_id = s.id)"
        )->fetchColumn();
    }

    /* ============ SCHOLARSHIP / BUDGET ANALYTICS ============ */
    public static function releaseByYear(): array
    {
        return Database::conn()->query(
            "SELECT academic_year AS label, SUM(amount) AS value, COUNT(*) AS c
             FROM scholarship_releases
             GROUP BY academic_year ORDER BY academic_year"
        )->fetchAll();
    }

    public static function releaseByMonth(): array
    {
        return Database::conn()->query(
            "SELECT DATE_FORMAT(release_date, '%Y-%m') AS label, SUM(amount) AS value
             FROM scholarship_releases
             WHERE release_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY label ORDER BY label"
        )->fetchAll();
    }

    public static function scholarsByStatus(): array
    {
        return Database::conn()->query(
            "SELECT status AS label, COUNT(*) AS value FROM scholars GROUP BY status ORDER BY value DESC"
        )->fetchAll();
    }

    public static function renewalRate(): array
    {
        return Database::conn()->query(
            "SELECT status AS label, COUNT(*) AS value
             FROM scholarship_renewals GROUP BY status ORDER BY value DESC"
        )->fetchAll();
    }

    public static function budgetUtilization(): array
    {
        return Database::conn()->query(
            "SELECT sp.program_name AS label,
                    COALESCE(b.total_budget, 0) AS budget,
                    COALESCE((SELECT SUM(amount) FROM scholarship_releases r
                              JOIN scholars s ON s.id = r.scholar_id
                              WHERE s.program_id = sp.id), 0) AS released
             FROM scholarship_programs sp
             LEFT JOIN budgets b ON b.program_id = sp.id
             ORDER BY sp.id DESC"
        )->fetchAll();
    }

    /* ============ DASHBOARD TREND (used on admin home) ============ */
    public static function applicationsTrend(int $months = 12): array
    {
        return Database::conn()->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS value
             FROM applications
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
             GROUP BY label ORDER BY label"
        )->fetchAll();
    }
}