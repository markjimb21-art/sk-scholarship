<?php
declare(strict_types=1);

/**
 * A semester belongs to a program (docs/CHANGE_PLAN_5.md §3): approving an applicant opens Semester 1 in the
 * application's program; approving a renewal opens the new semester in the program the renewal went INTO.
 */
final class TermProgramTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    public function testApprovingAnApplicantOpensSemester1InTheApplicationsProgram(): void
    {
        $r = Application::approve(6, 1);   // Ivy, Interviewed, program 1
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(1, (int)self::value(
            "SELECT t.program_id FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id WHERE s.application_id = 6"));
    }

    public function testApprovingARenewalOpensTheSemesterInTheRenewalsProgram(): void
    {
        // Scott (origin program 1) renews from his 2nd Semester 2026-2027 into 2027-2028, the year of program 2.
        Database::conn()->exec("INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at)
                                VALUES (1, 2, 2, '2027-2028', '1st Semester', 'Submitted', NOW())");
        $id = (int)Database::conn()->lastInsertId();
        $this->assertSame(2, Renewal::find($id)['program_id'], 'the renewal keeps its own program (not the scholar\'s origin)');

        $r = Renewal::approve($id, 3);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(2, (int)self::value(
            "SELECT program_id FROM scholar_terms WHERE scholar_id = 1 AND academic_year = '2027-2028' AND semester = '1st Semester'"));
        $this->assertSame(1, (int)self::value('SELECT program_id FROM scholars WHERE id = 1'), 'the origin program stays');
    }
}
