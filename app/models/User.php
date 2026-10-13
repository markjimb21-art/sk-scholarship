<?php
declare(strict_types=1);

/** User accounts (the users table): staff accounts managed by the admin, and every account's own details. */
final class User
{
    private const SEARCH_LIMIT = 200;   // accounts page

    /** Name and e-mail of one account, or null. */
    public static function contact(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function email(int $id): string
    {
        $stmt = Database::conn()->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /** Id of the ACTIVE account with this e-mail (forgot password), or null. */
    public static function activeIdByEmail(string $email): ?int
    {
        $stmt = Database::conn()->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    /** Accounts page: search by name/e-mail and filter by role; at most SEARCH_LIMIT rows. */
    public static function search(string $q, string $role): array
    {
        $sql = "SELECT u.id, u.full_name, u.email, u.contact_number, u.is_active, u.email_verified, u.last_login, u.created_at, r.role_name
                FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
        $params = [];
        if ($q !== '')  { $sql .= " AND (u.full_name LIKE :q1 OR u.email LIKE :q2)"; $params['q1'] = $params['q2'] = "%$q%"; }
        if ($role !== '') { $sql .= " AND r.role_name = :role"; $params['role'] = $role; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY r.role_name, u.full_name LIMIT " . self::SEARCH_LIMIT);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** id + name of every account, for the audit-log filter. */
    public static function options(): array
    {
        return Database::conn()->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
    }

    /** Active admins other than $exceptId (an admin can never remove the last one). */
    public static function otherActiveAdmins(int $exceptId): int
    {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'admin' AND u.is_active = 1 AND u.id <> ?");
        $stmt->execute([$exceptId]);
        return (int)$stmt->fetchColumn();
    }

    public static function emailTaken(string $email, int $exceptId): bool
    {
        $stmt = Database::conn()->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $exceptId]);
        return (bool)$stmt->fetch();
    }

    /** Role name of an account, or null. */
    public static function roleName(int $id): ?string
    {
        $stmt = Database::conn()->prepare("SELECT r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetchColumn();
        return $role === false ? null : (string)$role;
    }

    /** is_active, e-mail and role of an account (activate/deactivate), or null. */
    public static function status(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT u.is_active, u.email, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function updateStaff(int $id, string $name, string $email, string $contact, string $roleName): void
    {
        Database::conn()->prepare("UPDATE users SET full_name = ?, email = ?, contact_number = ?, role_id = (SELECT id FROM roles WHERE role_name = ?) WHERE id = ?")
            ->execute([$name, $email, $contact, $roleName, $id]);
    }

    /** New staff account, active and verified. Returns its id. */
    public static function createStaff(string $roleName, string $name, string $email, string $password, string $contact): int
    {
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO users (role_id, full_name, email, password_hash, contact_number, is_active, email_verified)
             VALUES ((SELECT id FROM roles WHERE role_name = ?), ?, ?, ?, ?, 1, 1)"
        )->execute([$roleName, $name, $email, password_hash($password, PASSWORD_DEFAULT), $contact]);
        return (int)$pdo->lastInsertId();
    }

    public static function setActive(int $id, int $active): void
    {
        Database::conn()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$active, $id]);
    }

    /** A reset by the admin or by e-mail code: new password AND the login lockout cleared. */
    public static function resetPassword(int $id, string $password): void
    {
        Database::conn()->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /** The account owner changes their own password (My Profile); the lockout counters are left as they are. */
    public static function changePassword(int $id, string $password): void
    {
        Database::conn()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function updateProfile(int $id, string $name, string $contact): void
    {
        Database::conn()->prepare("UPDATE users SET full_name = ?, contact_number = ? WHERE id = ?")->execute([$name, $contact, $id]);
    }

    public static function markEmailVerified(int $id): void
    {
        Database::conn()->prepare("UPDATE users SET email_verified = 1 WHERE id = ?")->execute([$id]);
    }
}
