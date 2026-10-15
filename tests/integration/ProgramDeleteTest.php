<?php
declare(strict_types=1);

/** B1 delete: only a program nobody submitted an application to; its Drafts, budgets, windows and interview schedules go with it. */
final class ProgramDeleteTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    public function testAProgramWithApplicationsIsKept(): void
    {
        $r = Program::delete(1, 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('submitted applications', $r['error']);
        $this->assertSame(1, (int)self::value('SELECT COUNT(*) FROM scholarship_programs WHERE id = 1'));
    }

    public function testAProgramNobodyAppliedToIsDeletedWithItsSetup(): void
    {
        $pdo = Database::conn();
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM applications WHERE program_id = 2'), 'seed: nobody applied to program 2');
        $pdo->exec("INSERT INTO budgets (program_id, academic_year, total_budget) VALUES (2, '2026-2027', 10000)");
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (2, '2026-2027', '1st Semester', 'release', NOW(), NOW() + INTERVAL 5 DAY, 'Open', 1)");
        $pdo->exec("INSERT INTO interview_schedules (program_id, interview_date, start_time, end_time, venue, max_slots, status)
                    VALUES (2, CURDATE() + INTERVAL 7 DAY, '08:00', '10:00', 'Hall', 10, 'Open')");

        $this->assertSame(['ok' => true, 'drafts' => 0], Program::delete(2, 1));
        foreach (['scholarship_programs WHERE id', 'budgets WHERE program_id', 'term_schedules WHERE program_id', 'interview_schedules WHERE program_id'] as $where) {
            $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM $where = 2"), $where);
        }
        $this->assertSame('program_deleted', self::value("SELECT action FROM audit_logs WHERE entity_type = 'scholarship_programs' AND entity_id = 2 ORDER BY id DESC LIMIT 1"));
    }

    public function testDraftsOnTheProgramAreDeletedWithIt(): void
    {
        // The seed Draft (application 1, applicant 1) moved to program 2, which has no submitted application.
        Database::conn()->exec('UPDATE applications SET program_id = 2 WHERE id = 1');
        $user = Application::userId(1);
        $this->assertSame(['ok' => true, 'drafts' => 1], Program::delete(2, 1));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM applications WHERE id = 1'));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM documents WHERE application_id = 1'), 'its documents too');
        $this->assertSame('Application removed', self::value('SELECT title FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$user]));
    }
}
