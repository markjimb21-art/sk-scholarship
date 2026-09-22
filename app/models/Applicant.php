<?php
declare(strict_types=1);

final class Applicant
{
    /** Shared by search() and count() so paging totals always match the rows. Placeholder names must be unique (native prepares). */
    private static function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q1 OR u.email LIKE :q2 OR a.application_code LIKE :q3)";
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['school'])) {
            $sql .= " AND e.school_name = :school";
            $params['school'] = $filters['school'];
        }
        if (!empty($filters['year_level'])) {
            $sql .= " AND e.year_level = :yl";
            $params['yl'] = $filters['year_level'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM applications ap WHERE ap.applicant_id = a.id AND ap.status = :st)";
            $params['st'] = $filters['status'];
        }
        return [$sql, $params];
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM applicants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM applicants WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function personalInfo(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT p.*, pr.purok_name
             FROM applicants_personal_information p
             LEFT JOIN puroks pr ON pr.id = p.purok_id
             WHERE p.applicant_id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function education(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM education_records WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function familyBackground(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM family_background WHERE applicant_id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function withUser(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, u.full_name, u.email, u.contact_number, u.profile_picture
             FROM applicants a
             JOIN users u ON u.id = a.user_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function search(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT a.id, a.application_code, u.full_name, u.email, u.contact_number,
                       p.complete_address, e.school_name, e.course_name, e.year_level,
                       (SELECT status FROM applications ap WHERE ap.applicant_id = a.id ORDER BY id DESC LIMIT 1) AS app_status
                FROM applicants a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN applicants_personal_information p ON p.applicant_id = a.id
                LEFT JOIN education_records e ON e.applicant_id = a.id
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
        $sql = "SELECT COUNT(DISTINCT a.id)
                FROM applicants a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN education_records e ON e.applicant_id = a.id
                WHERE 1=1";
        [$w, $params] = self::where($filters);
        $sql .= $w;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}