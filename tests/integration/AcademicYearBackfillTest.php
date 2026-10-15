<?php
declare(strict_types=1);

/**
 * database/migrations/2026_10_16_academic_years.sql run for real: the test database is put back in its old shape
 * (programs with an academic_year, no academic_years table), then the migration file is executed statement by statement.
 */
final class AcademicYearBackfillTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    /** The old shape: no academic_years table, and every program with an academic year. */
    private static function oldShape(string $programYear): void
    {
        $pdo = Database::conn();
        $pdo->exec('DROP TABLE academic_years');
        $hasColumn = (bool)$pdo->query("SHOW COLUMNS FROM scholarship_programs LIKE 'academic_year'")->fetch();
        if (!$hasColumn) {
            $pdo->exec("ALTER TABLE scholarship_programs ADD COLUMN academic_year VARCHAR(20) NOT NULL DEFAULT '' AFTER program_name, ADD INDEX idx_year (academic_year)");
        }
        $pdo->prepare('UPDATE scholarship_programs SET academic_year = ?')->execute([$programYear]);
    }

    private static function migrate(): void
    {
        $sql = (string)file_get_contents(BASE_PATH . '/database/migrations/2026_10_16_academic_years.sql');
        foreach (TestDatabase::statements($sql) as $statement) Database::conn()->exec($statement);
    }

    /** The academic year containing today by the June rule. */
    private static function todayYear(): string
    {
        $y = (int)date('Y') - ((int)date('n') < 6 ? 1 : 0);
        return $y . '-' . ($y + 1);
    }

    public function testEveryYearInUseIsCreatedAndTodaysYearIsCurrent(): void
    {
        self::oldShape('2027-2028');
        Database::conn()->prepare("INSERT INTO budgets (program_id, academic_year, total_budget) VALUES (2, ?, 1000)")->execute([self::todayYear()]);
        Database::conn()->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                                VALUES (1, '2029-2030', '1st Semester', 'renewal', NOW(), NOW() + INTERVAL 1 DAY, 'Open', 1)");
        self::migrate();

        $labels = Database::conn()->query('SELECT label FROM academic_years ORDER BY label')->fetchAll(PDO::FETCH_COLUMN);
        $expected = array_values(array_unique(['2026-2027', '2027-2028', '2029-2030', self::todayYear()]));   // seed semesters, program, window, budget
        sort($expected);
        $this->assertSame($expected, $labels);
        $this->assertSame([self::todayYear()], Database::conn()->query('SELECT label FROM academic_years WHERE is_current = 1')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM academic_years WHERE created_by IS NOT NULL'), 'backfilled, not created by anyone');
    }

    public function testWithoutTodaysYearTheLatestIsCurrent(): void
    {
        // Every year in use is far in the future, so today's year is not among them.
        self::oldShape('2091-2092');
        foreach (['scholar_terms', 'renewal_requests', 'budgets', 'term_schedules'] as $table) Database::conn()->exec("UPDATE $table SET academic_year = '2090-2091'");
        self::migrate();
        $this->assertSame(['2091-2092'], Database::conn()->query('SELECT label FROM academic_years WHERE is_current = 1')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testTheDatabaseRefusesASecondCurrentYear(): void
    {
        Database::conn()->exec("INSERT INTO academic_years (label) VALUES ('2027-2028')");
        $this->expectException(PDOException::class);
        Database::conn()->exec("UPDATE academic_years SET is_current = 1 WHERE label = '2027-2028'");
    }
}
