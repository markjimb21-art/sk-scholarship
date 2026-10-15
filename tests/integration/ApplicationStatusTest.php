<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Depends;

/**
 * The status machine against the database: refused moves, stale updates, approval in the current academic year, and
 * approval blocked when the slots are full or there is no current academic year.
 */
final class ApplicationStatusTest extends DatabaseTestCase
{
    public static function tearDownAfterClass(): void
    {
        self::rebuild();   // these tests change statuses and create a scholar
    }

    public function testChangeStatusRefusesAMoveOutsideTheMap(): void
    {
        $draft = Application::find(1);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This action is not possible while the application is "Draft — not submitted".');
        Application::changeStatus($draft, 'approve', 1);
    }

    public function testChangeStatusRefusesAStaleRow(): void
    {
        $stale = Application::find(2);
        $stale['status'] = 'Submitted';
        Database::conn()->exec("UPDATE applications SET status = 'Qualified' WHERE id = 2");   // someone else got there first
        try {
            Application::changeStatus($stale, 'qualify', 1);
            $this->fail('A stale update must be refused.');
        } catch (DomainException $e) {
            $this->assertSame('This application was changed by someone else. Please reload the page.', $e->getMessage());
        } finally {
            Database::conn()->exec("UPDATE applications SET status = 'Submitted' WHERE id = 2");
        }
    }

    public function testApproveWithAFreeSlotCreatesTheScholar(): void
    {
        AcademicYear::create('2027-2028', true, false, 1);   // the current academic year, not the seed's 2026-2027
        $r = Application::approve(6, 1);                     // Ivy (Interviewed); 1 of 3 slots used
        $this->assertSame(['ok' => true], array_intersect_key($r, ['ok' => 0]));
        $this->assertSame('Approved', self::value('SELECT status FROM applications WHERE id = 6'));
        $this->assertSame(2, Program::slotsUsed(1));
        $term = self::row('SELECT t.academic_year, t.semester, t.claim_status FROM scholars s JOIN scholar_terms t ON t.scholar_id = s.id WHERE s.application_id = 6');
        $this->assertSame(['academic_year' => '2027-2028', 'semester' => '1st Semester', 'claim_status' => 'Waiting for Requirements'], $term,
            'Semester 1 is the 1st Semester of the CURRENT academic year');
    }

    #[Depends('testApproveWithAFreeSlotCreatesTheScholar')]
    public function testApproveBeyondTheSlotsIsBlocked(): void
    {
        Database::conn()->exec('UPDATE scholarship_programs SET slots_available = 2 WHERE id = 1');   // now full: 2 of 2
        $r = Application::approve(7, 1);                     // Wes (Waitlisted)
        $this->assertFalse($r['ok']);
        $this->assertSame('All 2 slots are filled (2 approved). Nobody else can be approved unless a slot opens.', $r['error']);
        $this->assertSame('Waitlisted', self::value('SELECT status FROM applications WHERE id = 7'), 'status unchanged');
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM scholars WHERE application_id = 7'), 'no scholar created');
    }

    #[Depends('testApproveBeyondTheSlotsIsBlocked')]
    public function testApprovalNeedsACurrentAcademicYear(): void
    {
        Database::conn()->exec('UPDATE academic_years SET is_current = 0');
        Database::conn()->exec('UPDATE scholarship_programs SET slots_available = 5 WHERE id = 1');   // slots are not the reason
        $r = Application::approve(7, 1);
        $this->assertSame(['ok' => false, 'error' => 'No current academic year — an admin or treasurer must create one under Releasing of budget → Academic years.'], $r);
        $this->assertSame('Waitlisted', self::value('SELECT status FROM applications WHERE id = 7'));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM scholars WHERE application_id = 7'));
    }
}
