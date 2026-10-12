<?php
declare(strict_types=1);

/** Reference lists for the application form: puroks (address), schools and courses (autocomplete). */
final class Lookup
{
    public static function puroks(): array
    {
        return Database::conn()->query("SELECT id, purok_name FROM puroks ORDER BY purok_name")->fetchAll();
    }

    public static function schools(): array
    {
        return Database::conn()->query("SELECT school_name FROM schools WHERE is_active = 1 ORDER BY school_name")->fetchAll();
    }

    public static function courses(): array
    {
        return Database::conn()->query("SELECT course_name FROM courses WHERE is_active = 1 ORDER BY course_name")->fetchAll();
    }

    /** Upper-case purok name by id ('' when none), read from the table, never from the browser. */
    public static function purokName(?int $purokId): string
    {
        if (!$purokId) return '';
        $stmt = Database::conn()->prepare("SELECT purok_name FROM puroks WHERE id = ?");
        $stmt->execute([$purokId]);
        return up((string)($stmt->fetchColumn() ?: ''));
    }
}
