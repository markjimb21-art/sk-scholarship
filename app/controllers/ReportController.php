<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Report.php';
require_once BASE_PATH . '/app/models/Analytics.php';

/** Reports (PDF/CSV, docs/PROCESS.md §5) and Data Analytics (§6). */
final class ReportController
{
    public function reports(): void
    {
        $reports = Report::CATALOG;
        $programs = Program::options();
        render('admin/reports', compact('reports', 'programs'), 'Reports');
    }

    public function generate(): void
    {
        require_post('admin/reports');
        $type = (string)($_POST['report_type'] ?? '');
        if (!isset(Report::CATALOG[$type])) { flash('danger', 'Unknown report.'); redirect('admin/reports'); }
        $isDate = static fn($d): bool => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
        $from = $isDate($_POST['date_from'] ?? null) ? $_POST['date_from'] : null;
        $to   = $isDate($_POST['date_to'] ?? null) ? $_POST['date_to'] : null;
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $format = ($_POST['format'] ?? 'pdf') === 'csv' ? 'csv' : 'pdf';

        try {
            $report = Report::build($type, $programId, $from, $to, (string)($_POST['status'] ?? ''));
        } catch (InvalidArgumentException $e) {
            flash('danger', $e->getMessage());
            redirect('admin/reports');
        }
        AuditLog::write('report_generated', 'reports', null, "Generated \"{$report['title']}\" as $format", null,
            ['program_id' => $programId, 'from' => $from, 'to' => $to]);
        $format === 'csv' ? self::streamCsv($report) : self::streamPdf($report);
        exit;
    }

    /** Possible scholars + possible budget for one program (and one academic year). */
    public function analytics(): void
    {
        $program = Program::selected(!empty($_GET['program_id']) ? (int)$_GET['program_id'] : null);
        $programs = Program::options();
        $years = Budget::academicYears();
        $data = null;
        if ($program) {
            $pid = (int)$program['id'];
            $ay = in_array($_GET['academic_year'] ?? '', $years, true) ? $_GET['academic_year'] : $program['academic_year'];
            $counts = Analytics::stageCounts($pid);
            $conversion = Analytics::conversionRates($pid);
            $projected = Analytics::projectedScholars($pid);
            $budget = Budget::forProgramYear($pid, $ay);
            $data = [
                'ay' => $ay,
                'counts' => $counts,
                'conversion' => $conversion,
                'projected' => $projected,
                'slotsRemaining' => Analytics::slotsRemaining($pid),
                'waitlisted' => Analytics::waitlistedAvailable($pid),
                'budget' => $budget,
                'committed' => Analytics::committed($pid, $ay),
                'projectedNeed' => Analytics::projectedNeed($pid, $ay),
                'remaining' => Analytics::remaining($pid, $ay),
                'surplus' => Analytics::surplus($pid, $ay),
                'extraFundable' => Analytics::extraFundable($pid, $ay),
                'attendance' => Analytics::attendanceRate($pid),
                'noShow' => Analytics::noShowByAttempt($pid),
                'scores' => Analytics::scoreSummary($pid),
                'onTime' => Analytics::onTimeRate($pid, $ay),
                'overdue' => Analytics::overdueCount($pid, $ay),
                'avgDays' => Analytics::avgDaysToSubmit($pid, $ay),
                // (5) profiles, (6) academic performance, (7) fund utilization
                'profiles' => array_combine(array_keys(Analytics::PROFILE_DIMENSIONS),
                    array_map(static fn($d) => Analytics::profileBreakdown($pid, $d), array_keys(Analytics::PROFILE_DIMENSIONS))),
                'gwaVs' => Analytics::gwaApplicantsVsScholars($pid),
                'gwaSemesters' => Analytics::gwaSemesters($pid, $ay),
                'gwaPerScholar' => Analytics::gwaPerScholar($pid, $ay),
                'gwaBySemester' => Analytics::avgGwaBySemester($pid),
                'atRisk' => Analytics::atRiskScholars($pid),
                'gwaTrend' => Analytics::gwaTrend($pid),
                'releasedBySemester' => Analytics::releasedBySemester($pid),
                'releasedByMonth' => Analytics::releasedByMonth($pid, $ay),
                'releaseRate' => Analytics::releaseRate($pid, $ay),
                'avgPerScholar' => Analytics::avgReleasedPerScholar($pid, $ay),
            ];
        }
        render('admin/analytics', compact('program', 'programs', 'years', 'data') + ['pageScripts' => [asset('js/sk-charts.js')]], 'Data Analytics');
    }

    private static function streamCsv(array $report): void
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($report['title']));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $slug . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
        fputcsv($out, [$report['title']]);
        foreach ($report['meta'] as $k => $v) fputcsv($out, [$k, $v]);
        fputcsv($out, []);
        fputcsv($out, $report['headers']);
        // Neutralise spreadsheet formula injection: text starting with = + - @ TAB CR would execute in Excel/Sheets.
        $safe = static fn($c) => (is_string($c) && $c !== '' && !is_numeric($c) && preg_match('/^[=+\-@\t\r]/', $c)) ? "'" . $c : $c;
        foreach ($report['rows'] as $row) fputcsv($out, array_map($safe, $row));
        fclose($out);
    }

    private static function streamPdf(array $report): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            flash('danger', 'DomPDF not installed. Run: composer require dompdf/dompdf');
            redirect('admin/reports');
        }

        // Both logos are embedded as data URIs: Dompdf runs with remote loading off and no file access outside its folder.
        $logo = static fn(string $file): string => 'data:image/png;base64,' . base64_encode((string)file_get_contents(PUBLIC_PATH . '/assets/img/' . $file));

        // Build HTML
        ob_start();
        ?>
        <!doctype html>
        <html><head><meta charset="utf-8">
        <style>
        @page { margin: 30px 30px 50px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1A1F2B; }
        .header { text-align: center; border-bottom: 3px solid #0B4DB3; padding-bottom: 8px; margin-bottom: 12px; }
        .header .logos img { width: 58px; height: 58px; margin: 0 4px; }
        .header h1 { margin: 4px 0 0; font-size: 16px; color: #083A87; }
        .header .tagline { font-size: 11px; font-weight: bold; color: #1A1F2B; }
        .header h2 { margin: 6px 0 0; font-size: 13px; font-weight: bold; color: #1A1F2B; }
        .header .sub { font-size: 9px; color: #454E5F; margin-top: 2px; }
        .meta { margin-bottom: 10px; font-size: 9px; }
        .meta table { width: 100%; }
        .meta td { padding: 2px 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #0B4DB3; color: #fff; text-align: left;
            padding: 5px 6px; border: 1px solid #083A87; font-size: 9px;
        }
        table.data td { padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 9px; }
        table.data tr:nth-child(even) td { background: #f1f5f9; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center;
                    font-size: 8px; color: #454E5F; border-top: 1px solid #cbd5e1; padding-top: 4px; }
        .sig { margin-top: 30px; width: 100%; }
        .sig td { width: 50%; text-align: center; padding-top: 40px; font-size: 9px; }
        .sig .line { border-top: 1px solid #475569; width: 70%; margin: 0 auto; padding-top: 4px; }
        </style>
        </head><body>

        <div class="header">
        <div class="logos"><img src="<?= $logo('sk-logo.png') ?>" alt="SK Barangay Estefania logo"><img src="<?= $logo('barangay-seal.png') ?>" alt="Barangay Estefania seal"></div>
        <h1><?= htmlspecialchars(APP_NAME_SHORT) ?></h1>
        <div class="tagline">Educational Assistance for College Students</div>
        <div class="sub"><?= htmlspecialchars(APP_SUBTITLE) ?></div>
        <h2><?= htmlspecialchars($report['title']) ?></h2>
        </div>

        <div class="meta">
        <table>
            <tr>
            <td><strong>Program:</strong> <?= htmlspecialchars($report['meta']['Program'] ?? '') ?></td>
            <td><strong>Date Range:</strong> <?= htmlspecialchars(($report['meta']['From'] ?? '') . ' to ' . ($report['meta']['To'] ?? '')) ?></td>
            <td><strong>Generated:</strong> <?= htmlspecialchars($report['meta']['Generated'] ?? '') ?></td>
            </tr>
        </table>
        </div>

        <table class="data">
        <thead>
            <tr><?php foreach ($report['headers'] as $h): ?><th><?= htmlspecialchars((string)$h) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
            <?php if (empty($report['rows'])): ?>
            <tr><td colspan="<?= count($report['headers']) ?>" style="text-align:center;">No records found.</td></tr>
            <?php else: foreach ($report['rows'] as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; endif; ?>
        </tbody>
        </table>

        <table class="sig">
        <tr>
            <td><div class="line">Prepared by</div></td>
            <td><div class="line">Approved by</div></td>
        </tr>
        </table>

        <div class="footer">
        Generated by <?= htmlspecialchars(APP_NAME_SHORT) ?> · Page <span class="pageNumber"></span>
        </div>
        </body></html>
        <?php
        $html = ob_get_clean();

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Stream (I = inline, D = download)
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($report['title']));
        $dompdf->stream($slug . '-' . date('Ymd-His') . '.pdf', ['Attachment' => false]);
    }
}
