<?php
declare(strict_types=1);

/** B10 Scholars list: graduated scholars are shown with Active ones by default, with the status "Graduated". */
final class ScholarListTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        // Seed scholar 1 (Scott) is Active; add one graduated and one dropped-out scholar from the Waitlisted/Interviewed applicants.
        self::rebuild();
        Database::conn()->exec("INSERT INTO scholars (scholar_code, applicant_id, application_id, program_id, approval_date, status, end_reason, ended_at) VALUES
            ('SCH-TEST-GRAD', 7, 7, 1, CURDATE() - INTERVAL 400 DAY, 'Ended', 'Graduated', NOW()),
            ('SCH-TEST-DROP', 6, 6, 1, CURDATE() - INTERVAL 400 DAY, 'Ended', 'Dropped out', NOW())");
    }

    public static function tearDownAfterClass(): void
    {
        self::rebuild();
    }

    private static function codes(string $status): array
    {
        $codes = array_column(Scholar::search(['status' => $status]), 'scholar_code');
        sort($codes);
        return $codes;
    }

    public function testTheListFilters(): void
    {
        $this->assertSame(['SCH-TEST-0001', 'SCH-TEST-GRAD'], self::codes('current'), 'default: active and graduated');
        $this->assertSame(['SCH-TEST-0001'], self::codes('Active'));
        $this->assertSame(['SCH-TEST-GRAD'], self::codes('Graduated'));
        $this->assertSame(['SCH-TEST-DROP'], self::codes('Ended'), 'ended for other reasons');
        $this->assertSame(['SCH-TEST-0001', 'SCH-TEST-DROP', 'SCH-TEST-GRAD'], self::codes('all'));
    }

    public function testTheStatusShown(): void
    {
        $this->assertSame(['Active', 'success'], Scholar::statusBadge(['status' => 'Active', 'end_reason' => null]));
        $this->assertSame(['Graduated', 'primary'], Scholar::statusBadge(['status' => 'Ended', 'end_reason' => 'Graduated']));
        $this->assertSame(['Ended — Did not renew', 'secondary'], Scholar::statusBadge(['status' => 'Ended', 'end_reason' => 'Did not renew']));
    }
}
