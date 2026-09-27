<?php
declare(strict_types=1);

/** Scholars: Active or Ended (with a reason). Semesters, claims and renewals live in Term / Renewal. */
final class Scholar
{
    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*, sp.program_name, sp.academic_year AS program_year, sp.amount_per_semester, sp.min_gpa,
                    u.id AS user_id, u.full_name, u.email, u.contact_number, a.application_code
             FROM scholars s
             JOIN scholarship_programs sp ON sp.id = s.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN applications a ON a.id = s.application_id
             JOIN users u ON u.id = ap.user_id
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
            "INSERT INTO scholar_terms (scholar_id, academic_year, semester, opened_via, claim_status)
             VALUES (?, ?, '1st Semester', 'approval', 'Waiting for Requirements')"
        )->execute([$scholarId, $app['academic_year']]);
        $termId = (int)$pdo->lastInsertId();

        AuditLog::write('scholar_created', 'scholars', $scholarId,
            "Scholar created from application {$app['application_code']}; opened {$app['academic_year']} 1st Semester",
            null, ['application_id' => (int)$app['id'], 'term_id' => $termId, 'by' => $actorId]);
        return $scholarId;
    }
}
