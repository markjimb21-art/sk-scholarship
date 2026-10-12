<?php
declare(strict_types=1);

/** Lists of applications (the Applications records page and the Screening queue), filtered and paged. */
final class ApplicationSearch
{
    /** Records list / queues: filters status, program_id, q (name, code, e-mail). */
    public static function search(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = self::where($filters);
        $stmt = Database::conn()->prepare(
            "SELECT a.id, a.application_code, a.status, a.submitted_at, a.updated_at,
                    u.full_name, u.email, e.school_name, e.course_name, e.general_average,
                    f.total_family_income, sp.program_name
             FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             JOIN scholarship_programs sp ON sp.id = a.program_id
             LEFT JOIN education_records e ON e.applicant_id = ap.id
             LEFT JOIN family_background f ON f.applicant_id = ap.id
             WHERE 1=1 $where
             ORDER BY COALESCE(a.submitted_at, a.created_at) ASC, a.id ASC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters): int
    {
        [$where, $params] = self::where($filters);
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM applications a
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE 1=1 $where"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Shared WHERE for search()/count(). Placeholder names are unique (native prepares). */
    private static function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], APP_STATUS, true)) {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['program_id'])) {
            $sql .= ' AND a.program_id = :pid';
            $params['pid'] = (int)$filters['program_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (u.full_name LIKE :q1 OR a.application_code LIKE :q2 OR u.email LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        return [$sql, $params];
    }
}
