<?php
declare(strict_types=1);

/**
 * The 8 reports of docs/PROCESS.md §5. Every report takes Program and Date range; each report names the
 * column each filter applies to, only placeholders present in its SQL are bound, and the report header
 * states when a filter doesn't apply.
 */
final class Report
{
    /** key => [title, description, program column, date column (null = date filter not applicable)] */
    public const CATALOG = [
        'applicant_list' => ['Applicant list by status', 'Every submitted application with its status, GWA and family income', 'a.program_id', 'a.submitted_at'],
        'interviews' => ['Interview schedule & attendance', 'Each schedule with the applicants assigned and their attendance', 's.program_id', 's.interview_date'],
        'ranking'    => ['Interview results / ranking', 'Rubric scores and ranking of interviewed applicants', 'a.program_id', 'ev.evaluated_at'],
        'scholars'   => ['Scholar masterlist', 'Every scholar with status, semesters and total released', 's.program_id', 's.approval_date'],
        'releases'   => ['Releases (disbursement)', 'Every cash release with amount, method and reference', 's.program_id', 'r.release_date'],
        'budget'     => ['Budget utilization', 'Budget, released, remaining and committed per program and academic year', 'b.program_id', 'r.release_date'],
        'claims'     => ['Claims & renewals status', 'Status of every semester claim and renewal request', 's.program_id', 'x.created_at'],
        'summary'    => ['Program summary', 'One page of the key numbers of one program', 'a.program_id', 'a.submitted_at'],
        'profile_academic' => ['Profile and academic summary', 'Applicant profiles, academic performance (GWA, at-risk) and fund utilization of one program', 'a.program_id', null],
    ];

    /**
     * Build a report: ['title','headers','rows','meta'] (rows are lists of scalars).
     * $status applies to the applicant list only.
     */
    public static function build(string $type, ?int $programId, ?string $from, ?string $to, string $status = ''): array
    {
        [$title, , $programCol, $dateCol] = self::CATALOG[$type];
        $params = array_filter(['pid' => $programId, 'df' => $from, 'dt' => $to], static fn($v) => $v !== null && $v !== '');
        $w = '';
        if ($programId)          $w .= " AND $programCol = :pid";
        if ($from && $dateCol)   $w .= " AND DATE($dateCol) >= :df";
        if ($to && $dateCol)     $w .= " AND DATE($dateCol) <= :dt";

        $label = static fn(string $s): string => APP_STATUS_LABELS[$s] ?? $s;
        $total = null;
        switch ($type) {
            case 'applicant_list':
                if ($status !== '' && in_array($status, APP_STATUS, true)) { $w .= ' AND a.status = :status'; $params['status'] = $status; }
                $sql = "SELECT a.application_code AS 'Application', u.full_name AS 'Name', u.email AS 'E-mail', sp.program_name AS 'Program',
                               a.status AS 'Status', DATE(a.submitted_at) AS 'Submitted', e.general_average AS 'GWA',
                               f.total_family_income AS 'Family income'
                        FROM applications a
                        JOIN applicants ap ON ap.id = a.applicant_id JOIN users u ON u.id = ap.user_id
                        JOIN scholarship_programs sp ON sp.id = a.program_id
                        LEFT JOIN education_records e ON e.id = (SELECT MAX(e2.id) FROM education_records e2 WHERE e2.applicant_id = a.applicant_id)
                        LEFT JOIN family_background f ON f.applicant_id = a.applicant_id
                        WHERE a.status <> 'Draft' $w
                        ORDER BY FIELD(a.status, 'Submitted','Returned','Qualified','Interview Scheduled','Interviewed','Approved','Waitlisted','Rejected'), u.full_name";
                break;

            case 'interviews':
                $sql = "SELECT DATE(s.interview_date) AS 'Date', CONCAT(TIME_FORMAT(s.start_time, '%h:%i %p'), '–', TIME_FORMAT(s.end_time, '%h:%i %p')) AS 'Time',
                               s.venue AS 'Venue', s.status AS 'Schedule', u.full_name AS 'Applicant', a.application_code AS 'Application',
                               ia.attendance AS 'Attendance', IF(ia.reschedule_count > 0, 'Yes', '') AS 'Reschedule'
                        FROM interview_schedules s
                        LEFT JOIN interview_assignments ia ON ia.schedule_id = s.id
                        LEFT JOIN applications a ON a.id = ia.application_id
                        LEFT JOIN applicants ap ON ap.id = a.applicant_id LEFT JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 $w
                        ORDER BY s.interview_date, s.start_time, u.full_name";
                break;

            case 'ranking':
                $sql = "SELECT a.application_code AS 'Application', u.full_name AS 'Name',
                               ev.financial_need_score AS 'Financial need', ev.academic_score AS 'Academic',
                               ev.communication_score AS 'Communication', ev.community_score AS 'Community', ev.total_score AS 'Total',
                               f.total_family_income AS 'Family income', e.general_average AS 'GWA', a.status AS 'Status'
                        FROM interview_evaluations ev
                        JOIN interview_assignments ia ON ia.id = ev.assignment_id
                        JOIN applications a ON a.id = ia.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id JOIN users u ON u.id = ap.user_id
                        LEFT JOIN education_records e ON e.id = (SELECT MAX(e2.id) FROM education_records e2 WHERE e2.applicant_id = a.applicant_id)
                        LEFT JOIN family_background f ON f.applicant_id = a.applicant_id
                        WHERE 1=1 $w
                        ORDER BY ev.total_score DESC, f.total_family_income ASC, (e.general_average IS NULL), e.general_average ASC";
                break;

            case 'scholars':
                $sql = "SELECT s.scholar_code AS 'Scholar ID', u.full_name AS 'Name', u.email AS 'E-mail', sp.program_name AS 'Program',
                               DATE(s.approval_date) AS 'Approved', s.status AS 'Status', COALESCE(s.end_reason, '') AS 'End reason',
                               (SELECT COUNT(*) FROM scholar_terms t WHERE t.scholar_id = s.id) AS 'Semesters',
                               (SELECT COALESCE(SUM(r.amount), 0) FROM scholarship_releases r WHERE r.scholar_id = s.id) AS 'Total released'
                        FROM scholars s
                        JOIN scholarship_programs sp ON sp.id = s.program_id
                        JOIN applicants ap ON ap.id = s.applicant_id JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 $w
                        ORDER BY s.status, u.full_name";
                break;

            case 'releases':
                $sql = "SELECT DATE(r.release_date) AS 'Date', r.release_code AS 'Release', s.scholar_code AS 'Scholar ID', u.full_name AS 'Name',
                               sp.program_name AS 'Program', CONCAT(t.academic_year, ' ', t.semester) AS 'Semester', r.amount AS 'Amount',
                               r.payment_method AS 'Method', COALESCE(r.reference_number, '') AS 'Reference'
                        FROM scholarship_releases r
                        JOIN scholar_terms t ON t.id = r.term_id
                        JOIN scholars s ON s.id = r.scholar_id
                        JOIN scholarship_programs sp ON sp.id = s.program_id
                        JOIN applicants ap ON ap.id = s.applicant_id JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 $w
                        ORDER BY r.release_date, r.id";
                $total = 6;   // column index to sum
                break;

            case 'budget':
                // Released in range = releases inside the date range (all releases when no range is given).
                $dateJoin = ($from ? ' AND DATE(r.release_date) >= :df' : '') . ($to ? ' AND DATE(r.release_date) <= :dt' : '');
                $sql = "SELECT sp.program_name AS 'Program', b.academic_year AS 'A.Y.', b.total_budget AS 'Total',
                               (SELECT COALESCE(SUM(r.amount), 0) FROM scholarship_releases r JOIN scholar_terms t ON t.id = r.term_id
                                  JOIN scholars s ON s.id = t.scholar_id
                                 WHERE s.program_id = b.program_id AND t.academic_year = b.academic_year $dateJoin) AS 'Released in range',
                               b.released AS 'Released (all)', b.remaining AS 'Remaining',
                               ROUND(b.released / b.total_budget * 100, 1) AS 'Utilization %'
                        FROM budgets b JOIN scholarship_programs sp ON sp.id = b.program_id
                        WHERE 1=1 " . ($programId ? ' AND b.program_id = :pid' : '') . "
                        ORDER BY sp.program_name, b.academic_year";
                break;

            case 'claims':
                $sql = "SELECT x.kind AS 'Type', x.scholar_code AS 'Scholar ID', x.full_name AS 'Name', x.semester AS 'Semester',
                               x.status AS 'Status', DATE(x.submitted_at) AS 'Submitted', DATE(x.reviewed_at) AS 'Reviewed', COALESCE(x.remarks, '') AS 'Remarks'
                        FROM (
                            SELECT 'Claim' AS kind, s.scholar_code, u.full_name, s.program_id, CONCAT(t.academic_year, ' ', t.semester) AS semester,
                                   t.claim_status AS status, t.submitted_at, t.reviewed_at, t.remarks, t.created_at
                            FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id
                            JOIN applicants ap ON ap.id = s.applicant_id JOIN users u ON u.id = ap.user_id
                            UNION ALL
                            SELECT 'Renewal', s.scholar_code, u.full_name, s.program_id, CONCAT(rr.academic_year, ' ', rr.semester),
                                   rr.status, rr.submitted_at, rr.reviewed_at, rr.remarks, rr.created_at
                            FROM renewal_requests rr JOIN scholars s ON s.id = rr.scholar_id
                            JOIN applicants ap ON ap.id = s.applicant_id JOIN users u ON u.id = ap.user_id
                        ) x
                        WHERE 1=1 " . str_replace('s.program_id', 'x.program_id', $w) . "
                        ORDER BY x.kind, x.status, x.full_name";
                break;

            case 'summary':
                return self::summary($programId, $from, $to, $title);

            case 'profile_academic':
                return self::profileAcademic($programId, $from, $to, $title);

            default:
                throw new InvalidArgumentException('Unknown report.');
        }

        // Bind ONLY the placeholders this SQL uses (an unused one throws SQLSTATE[HY093]).
        $uses = static fn(string $name): bool => preg_match('/:' . $name . '\b/', $sql) === 1;
        $stmt = Database::conn()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($uses($k)) $stmt->bindValue(":$k", $v, $k === 'pid' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (isset($rows[0]['Status']) && in_array($type, ['applicant_list', 'ranking'], true)) {
            foreach ($rows as &$r) $r['Status'] = $label($r['Status']);
            unset($r);
        }
        $headers = $rows ? array_keys($rows[0]) : [];
        $rows = array_map('array_values', $rows);
        if ($total !== null && $rows) {
            $sum = array_sum(array_column($rows, $total));
            $totalRow = array_fill(0, count($headers), '');
            $totalRow[0] = 'TOTAL';
            $totalRow[$total] = number_format($sum, 2, '.', '');
            $rows[] = $totalRow;
        }
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'meta' => self::meta($programId, $from, $to, $uses('df'), $status)];
    }

    /** Report 8: one page of the key numbers of one program (Analytics functions, same formulas as the analytics page). */
    private static function summary(?int $programId, ?string $from, ?string $to, string $title): array
    {
        $program = $programId ? Program::find($programId) : null;
        if (!$program) throw new InvalidArgumentException('Choose a program for the Program summary.');
        $pid = (int)$program['id'];
        $pdo = Database::conn();

        $submitted = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = :pid AND submitted_at IS NOT NULL"
            . ($from ? ' AND DATE(submitted_at) >= :df' : '') . ($to ? ' AND DATE(submitted_at) <= :dt' : ''));
        $released = $pdo->prepare("SELECT COALESCE(SUM(r.amount), 0) FROM scholarship_releases r JOIN scholars s ON s.id = r.scholar_id WHERE s.program_id = :pid"
            . ($from ? ' AND DATE(r.release_date) >= :df' : '') . ($to ? ' AND DATE(r.release_date) <= :dt' : ''));
        foreach ([$submitted, $released] as $st) {
            $st->bindValue(':pid', $pid, PDO::PARAM_INT);
            if ($from) $st->bindValue(':df', $from);
            if ($to)   $st->bindValue(':dt', $to);
            $st->execute();
        }
        $scholars = $pdo->prepare("SELECT SUM(status = 'Active'), SUM(status = 'Ended') FROM scholars WHERE program_id = ?");
        $scholars->execute([$pid]);
        [$active, $ended] = array_map('intval', $scholars->fetch(PDO::FETCH_NUM));

        $counts = AnalyticsFunnel::stageCounts($pid);
        $ay = AcademicYear::currentLabel() ?? AcademicYear::labelFor(date('Y-m-d'));
        $rows = [
            ['Academic year (current)', $ay],
            ['Application dates', $program['application_start'] . ' to ' . $program['application_end']],
            ['Applications submitted' . ($from || $to ? ' (in date range)' : ''), (int)$submitted->fetchColumn()],
        ];
        foreach ($counts as $stage => $n) $rows[] = ['Now ' . strtolower(APP_STATUS_LABELS[$stage]), $n];
        $rows[] = ['Slots used / total', Program::slotsUsed($pid) . ' / ' . (int)$program['slots_available']];
        $rows[] = ['Scholars active / ended', "$active / $ended"];
        $rows[] = ['Amount per semester', peso($program['amount_per_semester'], 'No budget')];
        $rows[] = ['Cash released' . ($from || $to ? ' (in date range)' : ''), peso($released->fetchColumn(), 'No budget')];
        $rows[] = ["Budget remaining ($ay)", peso(AnalyticsBudget::remaining($pid, $ay), 'No budget')];
        $rows[] = ["Committed ($ay)", peso(AnalyticsBudget::committed($pid, $ay), 'No budget')];
        $rows[] = ['Projected scholars', number_format(AnalyticsFunnel::projectedScholars($pid)['value'], 1)];
        $rows[] = ["Projected need ($ay)", peso(AnalyticsBudget::projectedNeed($pid, $ay), 'No budget')];
        $surplus = AnalyticsBudget::surplus($pid, $ay);
        $rows[] = ['Surplus / shortfall', $surplus === null ? 'No budget' : ($surplus < 0 ? 'Shortfall ' : 'Surplus ') . peso(abs($surplus))];

        return ['title' => $title . ' — ' . $program['program_name'], 'headers' => ['Figure', 'Value'], 'rows' => $rows,
                'meta' => self::meta($pid, $from, $to, true, '')];
    }

    /**
     * Report 9 — the analytics of docs/PROCESS.md §6 (5)–(7) for one program as rows: profile breakdowns (applicants,
     * approved, approval rate), academic performance (GWA, at-risk scholars, trend) and fund utilization for the
     * current academic year. Uses the same Analytics functions as the analytics page. Date filter: not applicable.
     */
    private static function profileAcademic(?int $programId, ?string $from, ?string $to, string $title): array
    {
        $program = $programId ? Program::find($programId) : null;
        if (!$program) throw new InvalidArgumentException('Choose a program for the Profile and academic summary.');
        $pid = (int)$program['id'];
        $ay = AcademicYear::currentLabel() ?? AcademicYear::labelFor(date('Y-m-d'));   // fund utilization: the current academic year
        $pct = static fn($r) => $r === null ? '—' : number_format($r * 100, 1) . '%';
        $gwa = static fn($v) => $v === null ? '—' : number_format((float)$v, 2);
        $rows = [];
        foreach (AnalyticsProfiles::PROFILE_DIMENSIONS as $dim => $label) {
            foreach (AnalyticsProfiles::profileBreakdown($pid, $dim) as $r) {
                $rows[] = ["Profile — $label", $r['group'], $r['applicants'], $r['approved'], $pct($r['rate'])];
            }
        }
        $gv = AnalyticsProfiles::gwaApplicantsVsScholars($pid);
        $rows[] = ['Academic', 'Average GWA of applicants', $gv['applicants']['n'], '', $gwa($gv['applicants']['avg'])];
        $rows[] = ['Academic', 'Average GWA of approved scholars', $gv['scholars']['n'], '', $gwa($gv['scholars']['avg'])];
        foreach (AnalyticsProfiles::avgGwaBySemester($pid) as $sem => $g) $rows[] = ['Academic — average GWA per semester', $sem, $g['n'], '', $gwa($g['avg'])];
        $atRisk = AnalyticsProfiles::atRiskScholars($pid);
        $rows[] = ['Academic', 'Scholars below the minimum GWA (' . $gwa($program['min_gpa']) . ')', count($atRisk), '', ''];
        foreach ($atRisk as $s) $rows[] = ['Academic — at risk', "{$s['full_name']} ({$s['scholar_code']})", $s['semester'], '', $gwa($s['gwa'])];
        foreach (AnalyticsProfiles::gwaTrend($pid) as $s) {
            $rows[] = ['Academic — GWA trend', "{$s['full_name']} ({$s['scholar_code']})", $gwa($s['first']) . ' → ' . $gwa($s['last']), '', $s['trend']];
        }
        $aps = AnalyticsBudget::avgReleasedPerScholar($pid, $ay);
        $rows[] = ["Funds ($ay)", 'Released', '', '', peso(Budget::actualReleased($pid, $ay), '—')];
        $rows[] = ["Funds ($ay)", 'Committed', '', '', peso(AnalyticsBudget::committed($pid, $ay), '—')];
        $rows[] = ["Funds ($ay)", 'Release rate (released ÷ total budget)', '', '', $pct(AnalyticsBudget::releaseRate($pid, $ay))];
        $rows[] = ["Funds ($ay)", 'Average released per scholar', $aps['scholars'], '', peso($aps['avg'], '—')];
        foreach (AnalyticsBudget::releasedByMonth($pid, $ay) as $m => $v) $rows[] = ["Funds ($ay) — released per month", $m, '', '', peso($v, '—')];
        foreach (AnalyticsBudget::releasedBySemester($pid) as $sem => $v) $rows[] = ['Funds — released per semester', $sem, '', '', peso($v, '—')];

        return ['title' => $title . ' — ' . $program['program_name'],
                'headers' => ['Section', 'Item', 'Applicants / count', 'Approved', 'Rate / value'], 'rows' => $rows,
                'meta' => self::meta($pid, $from, $to, false, '') + ['Academic year (funds)' => $ay]];
    }

    private static function meta(?int $programId, ?string $from, ?string $to, bool $dateApplied, string $status): array
    {
        $na = 'Not applicable to this report';
        $program = $programId ? Program::find($programId) : null;
        $meta = [
            'Program'   => $program ? $program['program_name'] : 'All programs',
            'From'      => $from ? ($dateApplied ? $from : $na) : 'Any',
            'To'        => $to ? ($dateApplied ? $to : $na) : 'Any',
            'Generated' => date('F d, Y g:i A'),
        ];
        if ($status !== '') $meta['Status'] = APP_STATUS_LABELS[$status] ?? $status;
        return $meta;
    }
}
