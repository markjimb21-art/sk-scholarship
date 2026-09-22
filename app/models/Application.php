<?php
declare(strict_types=1);

final class Application
{
    /** Shared by search() and count() so paging totals always match the rows. Placeholder names must be unique (native prepares). */
    private static function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q1 OR a.application_code LIKE :q2 OR u.email LIKE :q3)";
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status']))     { $sql .= " AND a.status = :st";              $params['st'] = $filters['status']; }
        if (!empty($filters['school']))     { $sql .= " AND e.school_name = :school";     $params['school'] = $filters['school']; }
        if (!empty($filters['course']))     { $sql .= " AND e.course_name LIKE :course";  $params['course'] = '%' . $filters['course'] . '%'; }
        if (!empty($filters['year_level'])) { $sql .= " AND e.year_level = :yl";          $params['yl'] = $filters['year_level']; }
        if (!empty($filters['program_id'])) { $sql .= " AND a.program_id = :pid";         $params['pid'] = (int)$filters['program_id']; }
        if (isset($filters['max_income']) && $filters['max_income'] !== '') {
            $sql .= " AND f.total_family_income <= :mi";
            $params['mi'] = (float)$filters['max_income'];
        }
        return [$sql, $params];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, sp.program_name, sp.academic_year, sp.semester
             FROM applications a
             JOIN scholarship_programs sp ON sp.id = a.program_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByApplicant(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM applications WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function withDetails(int $id): ?array
    {
        $app = self::find($id);
        if (!$app) return null;

        $app['applicant']   = Applicant::withUser((int)$app['applicant_id']);
        $app['personal']    = Applicant::personalInfo((int)$app['applicant_id']);
        $app['education']   = Applicant::education((int)$app['applicant_id']);
        $app['family']      = Applicant::familyBackground((int)$app['applicant_id']);
        $app['documents']   = Document::byApplication($id);
        $app['interview']   = Interview::assignmentByApplication($id);
        $app['evaluation']  = $app['interview'] ? Interview::evaluation((int)$app['interview']['id']) : null;
        return $app;
    }

    public static function search(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT a.id, a.application_code, a.status, a.submitted_at, a.decided_at,
                       u.full_name, u.email, u.contact_number,
                       e.school_name, e.course_name, e.year_level,
                       f.total_family_income,
                       sp.program_name,
                       (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id AND d.status = 'Verified') AS docs_verified,
                       (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id) AS docs_total
                FROM applications a
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                JOIN scholarship_programs sp ON sp.id = a.program_id
                LEFT JOIN education_records e ON e.applicant_id = ap.id
                LEFT JOIN family_background f ON f.applicant_id = ap.id
                WHERE 1=1";
        [$w, $params] = self::where($filters);
        $sql .= $w;

        $sql .= " ORDER BY a.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM applications a
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                LEFT JOIN education_records e ON e.applicant_id = ap.id
                LEFT JOIN family_background f ON f.applicant_id = ap.id
                WHERE 1=1";
        [$w, $params] = self::where($filters);
        $sql .= $w;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function updateStatus(int $id, string $status, ?int $actorId = null, ?string $remarks = null): void
    {
        $pdo = Database::conn();
        $old = self::find($id);
        $pdo->prepare(
            "UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$status, $id]);

        AuditLog::write('application_status_changed', 'applications', $id,
            "Status changed to $status",
            ['status' => $old['status'] ?? null],
            ['status' => $status]
        );
    }

    public static function stats(): array
    {
        $pdo = Database::conn();
        $row = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'Submitted' OR status = 'Under Initial Review') AS pending,
                SUM(status = 'For Interview' OR status = 'Interview Scheduled') AS for_interview,
                SUM(status = 'Interview Completed' OR status = 'For Final Evaluation') AS interviewed,
                SUM(status = 'Approved') AS approved,
                SUM(status = 'Rejected') AS rejected,
                SUM(status = 'Waitlisted') AS waitlisted
             FROM applications"
        )->fetch();
        return $row ?: [];
    }
}