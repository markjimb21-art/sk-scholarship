<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Base class for tests that read the seed data. Tests that WRITE call rebuild() so the next test sees the seed again. */
abstract class DatabaseTestCase extends TestCase
{
    protected static function rebuild(): void
    {
        TestDatabase::rebuild();
    }

    protected static function value(string $sql, array $params = []): mixed
    {
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected static function row(string $sql, array $params = []): array
    {
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }
}
