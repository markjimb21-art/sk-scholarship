<?php
declare(strict_types=1);

/**
 * Scholars: Active or Ended (with a reason). An Ended scholar whose reason is Graduated is shown as "Graduated".
 * Semesters, claims and renewals live in Term / Renewal.
 */
final class Scholar
{
    private const LIST_LIMIT = 500;   // scholars page

    /**
     * SQL for scholar s's CURRENT program: the program of their latest semester (it changes when they renew into a later
     * academic year's program). scholars.program_id is the ORIGIN program, the one that first approved them.
     */
    private const CURRENT_PROGRAM_SQL = "COALESCE((SELECT ct.program_id FROM scholar_terms ct WHERE ct.scholar_id = s.id
                                                   ORDER BY ct.academic_year DESC, ct.semester DESC LIMIT 1), s.program_id)";

    /** Scholars page status filter => label. 'current' (the default) = Active and graduated scholars. */
    public const LIST_FILTERS = [
        'current' => 'Active and graduated', 'Active' => 'Active', 'Graduated' => 'Graduated',
        'Ended' => 'Ended (other reasons)', 'all' => 'All',
    ];

    /** The status a scholar is shown with: [label, Bootstrap colour] — Active, Graduated, or "Ended — <reason>". */
    public static function statusBadge(array $scholar): array
    {
        if ($scholar['status'] === 'Active') return ['Active', 'success'];
        if ($scholar['end_reason'] === 'Graduated') return ['Graduated', 'primary'];
        return ['Ended — ' . $scholar['end_reason'], 'secondary'];
    }

    /** program_id = the origin program (origin_program_name); program_name etc. = the current program (current_program_id). */
    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*, sp.id AS current_program_id, sp.program_name, sp.academic_year AS program_year, sp.amount_per_semester, sp.min_gpa,
                    op.program_name AS origin_program_name,
                    u.id AS user_id, u.full_name, u.email, u.contact_number, a.application_code,
                    pi.date_of_birth, pi.height_cm, pi.weight_kg, pi.blood_type, pi.religion, pi.citizenship
             FROM scholars s
             JOIN scholarship_programs sp ON sp.id = " . self::CURRENT_PROGRAM_SQL . "
             JOIN scholarship_programs op ON op.id = s.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN applications a ON a.id = s.application_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN applicants_personal_information pi ON pi.applicant_id = s.applicant_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** The applicant's scholarship: the Active one if any, otherwise the most recent. */
    public static function forApplicant(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id FROM scholars WHERE applicant_id = ? ORDER BY (status = 'Active') DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    public static function forApplication(int $applicationId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT id FROM scholars WHERE application_id = ? LIMIT 1");
        $stmt->execute([$applicationId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    /** Does the applicant hold an Active scholarship right now? (They can't apply to another program meanwhile.) */
    public static function hasActive(int $applicantId): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM scholars WHERE applicant_id = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$applicantId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Scholars list (filters: q, status, program_id = has a semester in that program) with their current program and
     * semester, origin program and total released.
     */
    /**
     * Active scholars per academic year of their latest semester: [A.Y. => n]. A renewal window into Y's 1st Semester
     * goes to those of the year before Y (TermSchedule::affectedScholars); the new-program form shows that number.
     */
    public static function activeByLatestYear(): array
    {
        $rows = Database::conn()->query(
            "SELECT lt.academic_year, COUNT(*) FROM scholars s
             JOIN scholar_terms lt ON lt.id = (SELECT x.id FROM scholar_terms x WHERE x.scholar_id = s.id
                                               ORDER BY x.academic_year DESC, x.semester DESC LIMIT 1)
             WHERE s.status = 'Active' GROUP BY lt.academic_year"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map('intval', $rows);
    }

    public static function search(array $filters): array
    {
        $sql = "SELECT s.id, s.scholar_code, s.status, s.end_reason, s.approval_date, sp.program_name, op.program_name AS origin_program_name,
                       u.full_name, u.email,
                       (SELECT CONCAT(t.academic_year, ' ', t.semester, '|', t.claim_status) FROM scholar_terms t
                         WHERE t.scholar_id = s.id ORDER BY t.academic_year DESC, t.semester DESC LIMIT 1) AS current_term,
                       (SELECT COALESCE(SUM(r.amount), 0) FROM scholarship_releases r WHERE r.scholar_id = s.id) AS total_released
                FROM scholars s
                JOIN scholarship_programs sp ON sp.id = " . self::CURRENT_PROGRAM_SQL . "
                JOIN scholarship_programs op ON op.id = s.program_id
                JOIN applicants ap ON ap.id = s.applicant_id
                JOIN users u ON u.id = ap.user_id
                WHERE 1=1";
        $params = [];
        $sql .= match ($filters['status'] ?? 'current') {
            'current'   => " AND (s.status = 'Active' OR s.end_reason = 'Graduated')",
            'Active'    => " AND s.status = 'Active'",
            'Graduated' => " AND s.status = 'Ended' AND s.end_reason = 'Graduated'",
            'Ended'     => " AND s.status = 'Ended' AND NOT (s.end_reason <=> 'Graduated')",
            default     => '',   // 'all'
        };
        if (!empty($filters['program_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM scholar_terms pt WHERE pt.scholar_id = s.id AND pt.program_id = :pid)";
            $params['pid'] = (int)$filters['program_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= " AND (u.full_name LIKE :q1 OR s.scholar_code LIKE :q2 OR u.email LIKE :q3)";
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        $stmt = Database::conn()->prepare($sql . " ORDER BY s.status, u.full_name LIMIT " . self::LIST_LIMIT);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * B10 End the scholarship (Active -> Ended with a reason). An ended scholar can't claim or renew.
     * Returns ['ok'=>true,'slot_freed'=>bool,'program_id'] — the scholar's current program (of their latest semester);
     * a slot of it is freed when they had only one semester in it (Program::seats).
     */
    public static function end(int $id, string $reason, int $adminId): array
    {
        if (!in_array($reason, SCHOLAR_END_REASONS, true)) return ['ok' => false, 'error' => 'Choose a reason.'];
        $scholar = self::find($id);
        if (!$scholar) return ['ok' => false, 'error' => 'Scholar not found.'];
        $stmt = Database::conn()->prepare(
            "UPDATE scholars SET status = 'Ended', end_reason = ?, ended_at = NOW() WHERE id = ? AND status = 'Active'"
        );
        $stmt->execute([$reason, $id]);
        if ($stmt->rowCount() !== 1) return ['ok' => false, 'error' => 'This scholarship has already ended.'];
        AuditLog::write('scholarship_ended', 'scholars', $id, "Scholarship ended: $reason", ['status' => 'Active'], ['status' => 'Ended', 'end_reason' => $reason, 'by' => $adminId]);

        $terms = Database::conn()->prepare("SELECT COUNT(*) FROM scholar_terms WHERE scholar_id = ? AND program_id = ?");
        $terms->execute([$id, (int)$scholar['current_program_id']]);
        Notification::send((int)$scholar['user_id'], 'Scholarship ended',
            "Your SK scholarship ({$scholar['program_name']}) has ended. Reason: $reason. Thank you for being an SK scholar.",
            'info', url('applicant/scholarship'), sendEmail: true);
        return ['ok' => true, 'slot_freed' => (int)$terms->fetchColumn() <= 1, 'program_id' => (int)$scholar['current_program_id']];
    }

    /**
     * A7: create the scholar for an approved application and open Semester 1 (1st Semester of the program's
     * academic year, decision D3). Call inside the approval transaction. Returns the new scholar id.
     */
    public static function createFromApplication(array $app, int $actorId): int
    {
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO scholars (scholar_code, applicant_id, application_id, program_id, approval_date, status)
             VALUES (?, ?, ?, ?, CURDATE(), 'Active')"
        )->execute([generate_code('SCH'), (int)$app['applicant_id'], (int)$app['id'], (int)$app['program_id']]);
        $scholarId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO scholar_terms (scholar_id, program_id, academic_year, semester, opened_via, claim_status)
             VALUES (?, ?, ?, '1st Semester', 'approval', 'Waiting for Requirements')"
        )->execute([$scholarId, (int)$app['program_id'], $app['academic_year']]);
        $termId = (int)$pdo->lastInsertId();

        AuditLog::write('scholar_created', 'scholars', $scholarId,
            "Scholar created from application {$app['application_code']}; opened {$app['academic_year']} 1st Semester",
            null, ['application_id' => (int)$app['id'], 'term_id' => $termId, 'by' => $actorId]);
        return $scholarId;
    }
}
