<?php
declare(strict_types=1);

/**
 * The separate test database. Every test and the smoke script go through here, and it refuses to touch anything
 * except TestDatabase::NAME: the real sk_scholarship database can never be dropped or written by a test.
 */
final class TestDatabase
{
    public const NAME = 'sk_scholarship_test';

    /** Stop unless DB_NAME is the test database (call before config.php connects to anything). */
    public static function requireTestEnvironment(): void
    {
        if (getenv('DB_NAME') !== self::NAME) {
            fwrite(STDERR, "Refusing to run: DB_NAME must be '" . self::NAME . "' (it is '" . (getenv('DB_NAME') ?: 'unset') . "').\n");
            exit(1);
        }
    }

    /** Drop and re-create the test database from database/schema.sql + tests/seed.sql. */
    public static function rebuild(): void
    {
        self::requireTestEnvironment();
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $server = new PDO("mysql:host=$host;charset=utf8mb4", getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . self::NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // From here on, the application's own connection does the work, so it is the one guaranteed to point at the test DB.
        $pdo = Database::conn();
        self::assertConnectedToTestDatabase($pdo);
        $pdo->exec('DROP DATABASE `' . self::NAME . '`');
        $pdo->exec('CREATE DATABASE `' . self::NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . self::NAME . '`');
        foreach ([BASE_PATH . '/database/schema.sql', BASE_PATH . '/tests/seed.sql'] as $file) {
            foreach (self::statements((string)file_get_contents($file)) as $sql) $pdo->exec($sql);
        }
        self::assertConnectedToTestDatabase($pdo);
    }

    public static function assertConnectedToTestDatabase(PDO $pdo): void
    {
        $current = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($current !== self::NAME && $current !== null) {
            fwrite(STDERR, "Refusing to run: connected to '$current', not '" . self::NAME . "'.\n");
            exit(1);
        }
    }

    /** Split an SQL file into statements on ';' outside quotes and comments (schema.sql has no DELIMITER blocks). */
    public static function statements(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`') { $buf .= $sql[++$i] ?? ''; continue; }
                if ($c === $quote) $quote = null;
                continue;
            }
            if ($c === '-' && ($sql[$i + 1] ?? '') === '-') {           // -- comment to end of line
                $i = (int)(strpos($sql, "\n", $i) ?: $len);
                continue;
            }
            if ($c === '/' && ($sql[$i + 1] ?? '') === '*') {           // /* comment */
                $i = (int)(strpos($sql, '*/', $i + 2) ?: $len) + 1;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') $quote = $c;
            if ($c === ';') {
                if (trim($buf) !== '') $out[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') $out[] = trim($buf);
        return $out;
    }
}
