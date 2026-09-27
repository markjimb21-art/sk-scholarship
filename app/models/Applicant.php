<?php
declare(strict_types=1);

/** The applicant's profile (one per account): personal information, education and family background. */
final class Applicant
{
    /** The applicants row for a user account, created if it is missing (older accounts). */
    public static function forUser(int $userId): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($row = $stmt->fetch()) return $row;

        $pdo->prepare("INSERT INTO applicants (user_id) VALUES (?)")->execute([$userId]);
        return ['id' => (int)$pdo->lastInsertId(), 'user_id' => $userId];
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
        $stmt = Database::conn()->prepare("SELECT * FROM family_background WHERE applicant_id = ? LIMIT 1");
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function withUser(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, u.full_name, u.email, u.contact_number
             FROM applicants a
             JOIN users u ON u.id = a.user_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }
}
