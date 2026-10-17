<?php
declare(strict_types=1);

/**
 * database/migrations/2026_10_18_term_program.sql run for real (docs/CHANGE_PLAN_5.md §3): the test database is put
 * back in its old shape — no program on semesters or renewals, no 'continuation', no one-program-per-year key — and the
 * migration file is executed statement by statement.
 *
 * Extra scholars for the 2nd-Semester step (D11), all with a Released 1st Semester:
 *   2 — Active, nothing after it, origin program 2  → gets its 2nd Semester (program 2)
 *   3 — Ended, nothing after it                      → nothing
 *   4 — Active, a renewal request made from it        → nothing (the request still decides)
 * Scott (1) already has his 2nd Semester → nothing.
 */
final class TermProgramMigrationTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function oldShape(): void
    {
        $pdo = Database::conn();
        foreach ([
            'ALTER TABLE scholar_terms DROP FOREIGN KEY fk_terms_program',
            'ALTER TABLE scholar_terms DROP INDEX idx_terms_program_year, DROP COLUMN program_id',
            "ALTER TABLE scholar_terms MODIFY opened_via ENUM('approval','renewal') NOT NULL",
            'ALTER TABLE renewal_requests DROP FOREIGN KEY fk_renewals_program',
            'ALTER TABLE renewal_requests DROP INDEX idx_renewals_program, DROP COLUMN program_id',
            'ALTER TABLE scholarship_programs DROP INDEX uniq_program_year',
        ] as $sql) $pdo->exec($sql);

        // Applications 6, 7 and 9 belong to applicants 6, 7 and 9 in the seed.
        $pdo->exec("INSERT INTO scholars (id, scholar_code, applicant_id, application_id, program_id, approval_date, status, end_reason) VALUES
                    (2, 'SCH-MIG-2', 6, 6, 2, CURDATE(), 'Active', NULL),
                    (3, 'SCH-MIG-3', 7, 7, 1, CURDATE(), 'Ended', 'Dropped out'),
                    (4, 'SCH-MIG-4', 9, 9, 1, CURDATE(), 'Active', NULL)");
        $pdo->exec("INSERT INTO scholar_terms (id, scholar_id, academic_year, semester, opened_via, claim_status) VALUES
                    (21, 2, '2026-2027', '1st Semester', 'approval', 'Released'),
                    (31, 3, '2026-2027', '1st Semester', 'approval', 'Released'),
                    (41, 4, '2026-2027', '1st Semester', 'approval', 'Released')");
        $pdo->exec("INSERT INTO renewal_requests (scholar_id, from_term_id, academic_year, semester, status, submitted_at)
                    VALUES (4, 41, '2026-2027', '2nd Semester', 'Submitted', NOW())");
    }

    private static function migrate(): void
    {
        $sql = (string)file_get_contents(BASE_PATH . '/database/migrations/2026_10_18_term_program.sql');
        foreach (TestDatabase::statements($sql) as $statement) Database::conn()->exec($statement);
    }

    private static function column(string $table, string $column): array
    {
        return Database::conn()->query("SHOW FULL COLUMNS FROM `$table` LIKE '$column'")->fetch();
    }

    private static function referencedTable(string $table, string $column): ?string
    {
        $v = self::value("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                         [$table, $column]);
        return $v === false ? null : (string)$v;
    }

    public function testSemestersAndRenewalsGetTheScholarsProgram(): void
    {
        self::oldShape();
        self::migrate();

        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id WHERE t.program_id <> s.program_id'));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM renewal_requests rr JOIN scholars s ON s.id = rr.scholar_id WHERE rr.program_id <> s.program_id'));
        $this->assertSame(2, (int)self::value('SELECT program_id FROM scholar_terms WHERE id = 21'), 'a scholar of program 2 keeps program 2');

        foreach (['scholar_terms', 'renewal_requests'] as $table) {
            $col = self::column($table, 'program_id');
            $this->assertSame('NO', $col['Null'], "$table.program_id is NOT NULL");
            $this->assertSame('scholarship_programs', self::referencedTable($table, 'program_id'), "$table.program_id has its foreign key");
        }
        $this->assertStringContainsString("'continuation'", self::column('scholar_terms', 'opened_via')['Type']);
        $this->assertStringContainsString('Origin program', self::column('scholars', 'program_id')['Comment']);
    }

    public function testOnlyAnActiveScholarStuckAfterAReleased1stSemesterGetsThe2ndSemester(): void
    {
        self::oldShape();
        self::migrate();

        $opened = Database::conn()->query(
            "SELECT scholar_id, program_id, academic_year, semester, claim_status FROM scholar_terms WHERE opened_via = 'continuation'"
        )->fetchAll();
        $this->assertSame([['scholar_id' => 2, 'program_id' => 2, 'academic_year' => '2026-2027', 'semester' => '2nd Semester',
                            'claim_status' => 'Waiting for Requirements']], $opened);
    }

    public function testASecondProgramInTheSameAcademicYearIsRefused(): void
    {
        self::oldShape();
        self::migrate();
        $this->expectException(PDOException::class);
        Database::conn()->exec("INSERT INTO scholarship_programs (program_name, academic_year, application_start, application_end)
                                VALUES ('Duplicate', '2026-2027', CURDATE(), CURDATE())");
    }

    public function testTheMigrationStopsBeforeTheKeyWhenAYearAlreadyHasTwoPrograms(): void
    {
        self::oldShape();
        Database::conn()->exec("UPDATE scholarship_programs SET academic_year = '2026-2027'");   // the check query would list it
        try {
            self::migrate();
            $this->fail('The unique key should be refused while two programs share an academic year.');
        } catch (PDOException $e) {
            $this->assertSame(1062, (int)($e->errorInfo[1] ?? 0));
        }
        // Everything before the last statement is in place, so fixing the year and adding the key finishes the job.
        $this->assertSame('NO', self::column('scholar_terms', 'program_id')['Null']);
    }
}
