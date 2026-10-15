<?php
declare(strict_types=1);

/**
 * A9: the renewal asks for the scholar's year level for the next semester; it is kept on the request and updates the
 * application form's year level (education_records), with the old value in record_history.
 * Seed: Scott (applicant 8, 3rd Year), semester 2 = 2026-2027 2nd Semester.
 */
final class RenewalYearLevelTest extends DatabaseTestCase
{
    private array $term;
    private int $userId;

    protected function setUp(): void
    {
        $pdo = Database::conn();
        // Semester 2 released, a renewal window open for 2027-2028 1st Semester, and the renewal documents uploaded.
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Released' WHERE id = 2");
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (1, '2027-2028', '1st Semester', 'renewal', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 3 DAY, 'Open', 3)");
        $pdo->exec("INSERT INTO term_documents (term_id, purpose, type, side, file_path, original_filename, uploaded_at, is_current) VALUES
                    (2, 'renewal', 'Enrollment Form', NULL, 'storage/term-documents/2/ef.pdf', 'ef.pdf', NOW(), 1),
                    (2, 'renewal', 'School ID', 'Front', 'storage/term-documents/2/f.pdf', 'f.pdf', NOW(), 1),
                    (2, 'renewal', 'School ID', 'Back', 'storage/term-documents/2/b.pdf', 'b.pdf', NOW(), 1)");
        $this->term = Term::find(2);
        $this->userId = (int)self::value('SELECT user_id FROM applicants WHERE id = 8');
    }

    protected function tearDown(): void
    {
        self::rebuild();
    }

    public function testTheYearLevelIsRequired(): void
    {
        $this->assertSame('Choose your year level for the next semester.', Renewal::submit($this->term, $this->userId, '', '')['error']);
        $this->assertSame('Write your year level (you chose Other).', Renewal::submit($this->term, $this->userId, 'Other', ' ')['error']);
        $this->assertFalse((bool)self::value('SELECT COUNT(*) FROM renewal_requests WHERE from_term_id = 2'), 'nothing was saved');
    }

    public function testTheRenewalUpdatesTheApplicationFormYearLevel(): void
    {
        $this->assertSame('3rd Year', Applicant::education(8)['year_level']);
        $r = Renewal::submit($this->term, $this->userId, '4th Year', 'ignored');
        $this->assertTrue($r['ok'], json_encode($r));

        $req = self::row('SELECT year_level, year_level_other FROM renewal_requests WHERE from_term_id = 2');
        $this->assertSame(['year_level' => '4th Year', 'year_level_other' => null], $req, 'kept on the renewal');
        $this->assertSame('4th Year', Applicant::education(8)['year_level'], 'the application form shows the new year level');
        $this->assertSame(['3rd Year', '4th Year'], array_values(self::row(
            "SELECT old_value, new_value FROM record_history WHERE applicant_id = 8 AND field = 'year_level' ORDER BY id DESC LIMIT 1")));
    }

    public function testApprovalNeedsTheNextSemestersAcademicYear(): void
    {
        // The renewal is into 2027-2028 1st Semester; the seed only has 2026-2027.
        $this->assertTrue(Renewal::submit($this->term, $this->userId, '4th Year', '')['ok']);
        $id = (int)self::value('SELECT id FROM renewal_requests WHERE from_term_id = 2');
        $r = Renewal::approve($id, 1);
        $this->assertSame(['ok' => false, 'error' => "Academic year 2027-2028 doesn't exist yet — create it under Releasing of budget → Academic years."], $r);
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM scholar_terms WHERE academic_year = '2027-2028'"), 'no semester opened');

        AcademicYear::create('2027-2028', false, false, 1);
        $this->assertTrue(Renewal::approve($id, 1)['ok']);
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM scholar_terms WHERE academic_year = '2027-2028' AND semester = '1st Semester'"));
    }
}
