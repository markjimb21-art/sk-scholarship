<?php
declare(strict_types=1);

/** B1: a program's academic year comes from its application start date, and is fixed once it has scholars. */
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

    public function testTheYearFollowsTheStartDate(): void
    {
        $this->assertSame('2026-2027', Program::academicYearFor('2026-10-14'), 'June–December: that year');
        $this->assertSame('2026-2027', Program::academicYearFor('2026-06-01'));
        $this->assertSame('2025-2026', Program::academicYearFor('2026-05-31'), 'January–May: the A.Y. that began the year before');
        $this->assertSame('2026-2027', Program::academicYearFor('2027-01-15'));
    }

    public function testANewProgramGetsItsYearFromTheDates(): void
    {
        $r = Program::save(self::form('2027-07-01', '2027-07-31') + ['academic_year' => '1999-2000'], null, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('2027-2028', Program::find($r['id'])['academic_year'], 'a posted A.Y. is ignored');
    }

    public function testAProgramWithScholarsKeepsItsYear(): void
    {
        // Program 1 (2026-2027) has Scott as a scholar: moving its dates to 2028 does not move its A.Y.
        $r = Program::save(self::form('2028-07-01', '2028-07-31') + ['slots_available' => '3'], 1, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('2026-2027', Program::find(1)['academic_year']);
        // Program 2 has no scholars: its A.Y. follows the new dates.
        $this->assertTrue(Program::save(self::form('2028-07-01', '2028-07-31'), 2, 1)['ok']);
        $this->assertSame('2028-2029', Program::find(2)['academic_year']);
    }
}
