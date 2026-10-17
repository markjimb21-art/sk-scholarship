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

    /**
     * Scott (2026-2027, program 1) renews into 2027-2028 (program 2): each semester's money, window and lists follow its
     * own program. His 2026-2027 release (₱5,000) stays in program 1's budget; his 2027-2028 release goes to program 2's.
     */
    public function testARenewedScholarCountsInTheProgramOfEachSemester(): void
    {
        $pdo = Database::conn();
        $pdo->exec("INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at)
                    VALUES (1, 2, 2, '2027-2028', '1st Semester', 'Submitted', NOW())");
        $this->assertTrue(Renewal::approve((int)$pdo->lastInsertId(), 3)['ok']);
        $newTerm = (int)self::value("SELECT id FROM scholar_terms WHERE scholar_id = 1 AND academic_year = '2027-2028'");

        // The new semester's claim window is program 2's.
        $window = ['purpose' => 'release', 'program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '1st Semester'];
        $this->assertSame(['SCH-TEST-0001'], array_column(TermSchedule::affectedScholars($window), 'scholar_code'));
        $this->assertSame([], TermSchedule::affectedScholars(['program_id' => 1] + $window), 'not program 1\'s');

        // Released against program 2's 2027-2028 budget.
        $this->assertTrue(Budget::save(['program_id' => 2, 'academic_year' => '2027-2028', 'total_budget' => '20000'], null)['ok']);
        $this->assertSame(2 * 4000.0, Budget::committed(2, '2027-2028'), 'both 2027-2028 semesters of program 2, at its ₱4,000');
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Verified' WHERE id = $newTerm");
        $r = Term::release($newTerm, ['amount' => '4000', 'release_date' => date('Y-m-d'), 'payment_method' => 'Cash'], 3);
        $this->assertTrue($r['ok'], json_encode($r));

        $this->assertSame(4000.0, Budget::actualReleased(2, '2027-2028'));
        $this->assertSame(4000.0, (float)self::value("SELECT released FROM budgets WHERE program_id = 2 AND academic_year = '2027-2028'"));
        $this->assertSame(5000.0, Budget::actualReleased(1, '2026-2027'), 'the old release stays in the 2026-2027 budget');
        $this->assertSame(0.0, Budget::actualReleased(1, '2027-2028'));
        $this->assertSame(4000.0, Budget::committed(2, '2027-2028'), 'one 2027-2028 semester left to release');
        $this->assertSame(5000.0, Budget::committed(1, '2026-2027'), 'his 2026-2027 2nd Semester is still unreleased in program 1');

        // Lists: current program 2, origin program 1; he is a scholar of both programs.
        $scholar = Scholar::find(1);
        $this->assertSame(['Other Test Program', 'EduKabataan Test Program'], [$scholar['program_name'], $scholar['origin_program_name']]);
        foreach ([1, 2] as $pid) {
            $report = Report::build('scholars', $pid, null, null);
            $this->assertSame(['SCH-TEST-0001'], array_column($report['rows'], 0), "masterlist of program $pid");
        }
        $this->assertSame(['Other Test Program', 'EduKabataan Test Program'], array_slice(Report::build('scholars', 2, null, null)['rows'][0], 3, 2));
        $this->assertSame(['SCH-TEST-0001'], array_column(Scholar::search(['program_id' => '2', 'status' => 'all']), 'scholar_code'));

        // Program 2 now holds his records, so it can't be deleted.
        $this->assertStringStartsWith('Scholars renewed into', Program::delete(2, 1)['error']);
    }
}
