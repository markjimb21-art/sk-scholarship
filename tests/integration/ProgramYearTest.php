<?php
declare(strict_types=1);

/**
 * B1: a program's academic year is typed in the form (pre-filled from the application start date); left blank it follows
 * the start date. Fixed once the program has scholars. One program per academic year (D1, plan 5).
 */
final class ProgramYearTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function form(string $start, string $end): array
    {
        return ['program_name' => 'Year Test', 'application_start' => $start, 'application_end' => $end,
                'slots_available' => '2', 'amount_per_semester' => '3000'];
    }

    public function testTheSuggestedYearFollowsTheStartDate(): void
    {
        $this->assertSame('2026-2027', Program::academicYearFor('2026-10-14'), 'June–December: that year');
        $this->assertSame('2026-2027', Program::academicYearFor('2026-06-01'));
        $this->assertSame('2025-2026', Program::academicYearFor('2026-05-31'), 'January–May: the A.Y. that began the year before');
        $this->assertSame('2026-2027', Program::academicYearFor('2027-01-15'));
    }

    public function testATypedYearIsStored(): void
    {
        $r = Program::save(self::form('2027-07-01', '2027-07-31') + ['academic_year' => '2028-2029'], null, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('2028-2029', Program::find($r['id'])['academic_year']);
    }

    public function testABlankYearFollowsTheDates(): void
    {
        // 2028: the seed's program 2 holds 2027-2028 (one program per A.Y.).
        $r = Program::save(self::form('2028-07-01', '2028-07-31') + ['academic_year' => ''], null, 1);
        $this->assertSame('2028-2029', Program::find($r['id'])['academic_year']);
    }

    public function testASecondProgramForAnAcademicYearIsRefused(): void
    {
        $r = Program::save(self::form('2027-07-01', '2027-07-31') + ['academic_year' => '2027-2028'], null, 1);
        $this->assertFalse($r['ok']);
        $this->assertStringStartsWith('A.Y. 2027-2028 already has a program ("Other Test Program")', $r['error']);
        $this->assertSame(2, (int)self::value("SELECT COUNT(*) FROM scholarship_programs"), 'nothing was added');
        // Saving the program that holds the year again is fine.
        $this->assertTrue(Program::save(self::form('2027-07-01', '2027-07-31') + ['academic_year' => '2027-2028'], 2, 1)['ok']);
    }

    public function testABadlyWrittenYearIsRefused(): void
    {
        foreach (['2026', '2026-2028', '26-27', 'abcd-efgh'] as $bad) {
            $r = Program::save(self::form('2027-07-01', '2027-07-31') + ['academic_year' => $bad], null, 1);
            $this->assertFalse($r['ok'], $bad);
            $this->assertStringStartsWith('Academic year must look like 2026-2027', $r['error']);
        }
    }

    public function testAProgramWithScholarsKeepsItsYear(): void
    {
        // Program 1 (2026-2027) has Scott as a scholar: a different typed A.Y. is ignored.
        $r = Program::save(self::form('2028-07-01', '2028-07-31') + ['slots_available' => '3', 'academic_year' => '2028-2029'], 1, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('2026-2027', Program::find(1)['academic_year']);
        // Program 2 has no scholars: its A.Y. can be changed.
        $this->assertTrue(Program::save(self::form('2028-07-01', '2028-07-31') + ['academic_year' => '2029-2030'], 2, 1)['ok']);
        $this->assertSame('2029-2030', Program::find(2)['academic_year']);
    }
}
