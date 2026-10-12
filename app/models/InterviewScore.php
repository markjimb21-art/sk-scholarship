<?php
declare(strict_types=1);

/** Interview scores (B4, rubric in INTERVIEW_RUBRIC) and the ranking used for the final decision (B5). */
final class InterviewScore
{
    public static function evaluation(int $assignmentId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT e.*, u.full_name AS interviewer_name
             FROM interview_evaluations e
             LEFT JOIN users u ON u.id = e.interviewer_id
             WHERE e.assignment_id = ? LIMIT 1"
        );
        $stmt->execute([$assignmentId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * B4 Score an attendee with the rubric (INTERVIEW_RUBRIC). Marks the attempt Attended and the application
     * Interviewed. Scores can be corrected while the application is still Interviewed (no decision yet).
     */
    public static function saveScore(int $assignmentId, array $input, string $remarks, int $adminId): array
    {
        $a = InterviewAssignment::assignment($assignmentId);
        if (!$a) return ['ok' => false, 'error' => 'Assignment not found.'];
        $firstScore = $a['attendance'] === 'Scheduled';
        if (!$firstScore && !($a['attendance'] === 'Attended' && $a['app_status'] === 'Interviewed')) {
            return ['ok' => false, 'error' => 'This interview can no longer be scored.'];
        }
        if ($a['interview_date'] > date('Y-m-d')) return ['ok' => false, 'error' => 'You can score only on or after the interview date.'];

        $scores = [];
        foreach (INTERVIEW_RUBRIC as $field => $c) {
            $v = trim((string)($input[$field] ?? ''));
            if ($v === '' || !is_numeric($v) || (float)$v < 0 || (float)$v > $c['max']) {
                return ['ok' => false, 'error' => "{$c['label']} must be a number from 0 to {$c['max']}."];
            }
            $scores[$field] = round((float)$v, 2);
        }
        $total = array_sum($scores);

        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $cols = array_keys($scores);
            $pdo->prepare(
                "INSERT INTO interview_evaluations (assignment_id, " . implode(', ', $cols) . ", total_score, remarks, interviewer_id, evaluated_at)
                 VALUES (:assignment_id, :" . implode(', :', $cols) . ", :total_score, :remarks, :interviewer_id, NOW())
                 ON DUPLICATE KEY UPDATE " . implode(', ', array_map(static fn($c) => "$c = VALUES($c)", $cols)) . ",
                    total_score = VALUES(total_score), remarks = VALUES(remarks), interviewer_id = VALUES(interviewer_id), evaluated_at = NOW()"
            )->execute($scores + ['assignment_id' => $assignmentId, 'total_score' => $total,
                                  'remarks' => trim($remarks) ?: null, 'interviewer_id' => $adminId]);
            if ($firstScore) {
                $pdo->prepare("UPDATE interview_assignments SET attendance = 'Attended' WHERE id = ?")->execute([$assignmentId]);
                Application::changeStatus(Application::find((int)$a['application_id']), 'score', $adminId, [], "interview total $total");
            }
            AuditLog::write($firstScore ? 'interview_scored' : 'interview_score_corrected', 'interview_evaluations', $assignmentId,
                "Interview score for {$a['application_code']}: $total / 100", null, $scores + ['total' => $total]);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return ['ok' => true, 'total' => $total];
    }

    /**
     * Interviewed and Waitlisted applications of a program, ranked by interview total (high first), then
     * total family income (low first), then GWA (lower number = better, first). Each row has its eligibility.
     */
    public static function ranking(int $programId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.applicant_id, a.submitted_at, a.program_id,
                    sp.max_age, sp.max_family_income, sp.min_gpa,
                    u.full_name, ev.total_score, e.general_average, f.total_family_income
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN interview_assignments ia ON ia.id = (SELECT MAX(ia2.id) FROM interview_assignments ia2 WHERE ia2.application_id = a.id)
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             LEFT JOIN education_records e ON e.id = (SELECT MAX(e2.id) FROM education_records e2 WHERE e2.applicant_id = a.applicant_id)
             LEFT JOIN family_background f ON f.applicant_id = a.applicant_id
             WHERE a.program_id = ? AND a.status IN ('Interviewed', 'Waitlisted')
             ORDER BY ev.total_score DESC, f.total_family_income ASC, (e.general_average IS NULL) ASC, e.general_average ASC, a.id ASC"
        );
        $stmt->execute([$programId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $i => &$r) {
            $r['rank'] = $i + 1;
            $r['eligibility'] = ApplicationEligibility::evaluate($r);
        }
        unset($r);
        return $rows;
    }
}
