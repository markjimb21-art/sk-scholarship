<?php
declare(strict_types=1);

/** Holidays (Settings → Holidays): dates never suggested for interviews. */
final class Holiday
{
    /** Holidays from 30 days ago onwards (the Settings list). */
    public static function recent(): array
    {
        return Database::conn()->query(
            "SELECT * FROM holidays WHERE holiday_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY holiday_date"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM holidays WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Add a holiday; returns its id, or null when that date is already a holiday. */
    public static function add(string $date, string $name, int $createdBy): ?int
    {
        $pdo = Database::conn();
        try {
            $pdo->prepare("INSERT INTO holidays (holiday_date, name, created_by) VALUES (?, ?, ?)")->execute([$date, mb_substr($name, 0, 120), $createdBy]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) throw $e;   // 1062 = duplicate date
            return null;
        }
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::conn()->prepare("DELETE FROM holidays WHERE id = ?")->execute([$id]);
    }
}
