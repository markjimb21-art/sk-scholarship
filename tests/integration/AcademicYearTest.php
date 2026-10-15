<?php
declare(strict_types=1);

/**
 * Academic years (Releasing of budget → Academic years). Seed: one year, 2026-2027, current; program 1 has one Active
 * scholar (Scott, ₱5,000 per semester), program 2 has none.
 */
final class AcademicYearTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function currentLabels(): array
    {
        return Database::conn()->query('SELECT label FROM academic_years WHERE is_current = 1')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testLabelsMustBeTwoYearsInARow(): void
    {
        foreach (['2027', '2027-2029', '2027/2028', '1999-2000', ' '] as $bad) {
            $this->assertFalse(AcademicYear::create($bad, false, false, 1)['ok'], $bad);
        }
        $this->assertTrue(AcademicYear::create('2027-2028', false, false, 1)['ok']);
        $r = AcademicYear::create('2027-2028', false, false, 1);
        $this->assertSame('Academic year 2027-2028 already exists.', $r['error']);
    }

    public function testExactlyOneYearIsCurrent(): void
    {
        $r = AcademicYear::create('2027-2028', true, false, 1);
        $this->assertTrue($r['current']);
        $this->assertSame(['2027-2028'], self::currentLabels());
        $old = (int)self::value("SELECT id FROM academic_years WHERE label = '2026-2027'");
        $this->assertTrue(AcademicYear::setCurrent($old, 1)['ok']);
        $this->assertSame(['2026-2027'], self::currentLabels());
        $this->assertSame('academic_year_current_changed', self::value("SELECT action FROM audit_logs WHERE entity_type = 'academic_years' ORDER BY id DESC LIMIT 1"));
    }

    public function testTheFirstYearIsMadeCurrentEvenUnticked(): void
    {
        Database::conn()->exec('DELETE FROM academic_years');
        $this->assertNull(AcademicYear::current());
        $this->assertSame(AcademicYear::labelFor(date('Y-m-d')), AcademicYear::suggestNext(), 'no year yet: today\'s');
        $this->assertTrue(AcademicYear::create('2030-2031', false, false, 1)['current']);
        $this->assertSame(['2030-2031'], self::currentLabels());
    }

    public function testSuggestNextAndTheJuneRule(): void
    {
        $this->assertSame('2027-2028', AcademicYear::suggestNext());
        $this->assertSame('2026-2027', AcademicYear::labelFor('2026-10-16'));
        $this->assertSame('2026-2027', AcademicYear::labelFor('2026-06-01'));
        $this->assertSame('2025-2026', AcademicYear::labelFor('2026-05-31'));
    }

    public function testDeleteOnlyAnUnusedYearThatIsNotCurrent(): void
    {
        $current = AcademicYear::current();
        $r = AcademicYear::delete((int)$current['id'], 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('current academic year', $r['error']);

        $next = AcademicYear::create('2027-2028', true, false, 1);   // 2026-2027 is no longer current, but still used
        $r = AcademicYear::delete((int)$current['id'], 1);
        $this->assertStringContainsString('semester(s)', $r['error'], 'the seed semesters use 2026-2027');

        $unused = AcademicYear::create('2028-2029', false, false, 1);
        $this->assertTrue(AcademicYear::delete($unused['id'], 1)['ok']);
        $this->assertFalse(AcademicYear::exists('2028-2029'));
        $this->assertTrue(AcademicYear::exists($next['label']));
    }

    public function testCreatingAYearCreatesBudgetsForProgramsWithActiveScholars(): void
    {
        $r = AcademicYear::create('2027-2028', false, true, 1);
        $this->assertSame([['program_name' => 'EduKabataan Test Program', 'total' => 10000.0]], $r['budgets'], 'program 2 has no scholars');
        $this->assertSame(10000.0, (float)self::value("SELECT total_budget FROM budgets WHERE program_id = 1 AND academic_year = '2027-2028'"));
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM budgets WHERE program_id = 2 AND academic_year = '2027-2028'"));
    }

    public function testAnExistingBudgetIsKept(): void
    {
        Database::conn()->exec("INSERT INTO budgets (program_id, academic_year, total_budget) VALUES (1, '2028-2029', 123)");
        $r = AcademicYear::create('2028-2029', false, true, 1);
        $this->assertSame([], $r['budgets']);
        $this->assertSame(123.0, (float)self::value("SELECT total_budget FROM budgets WHERE program_id = 1 AND academic_year = '2028-2029'"));
    }

    public function testTheOverviewCountsScholarsPerProgram(): void
    {
        $byProgram = [];
        foreach (AcademicYear::overview('2026-2027') as $row) $byProgram[(int)$row['program']['id']] = $row;
        $this->assertSame(1, $byProgram[1]['scholars']);
        $this->assertSame(0, $byProgram[2]['scholars']);
        $this->assertNotNull($byProgram[1]['windows']['release']['2nd Semester'], 'seed: the claim window of the 2nd semester');
        $this->assertNull($byProgram[2]['windows']['release']['1st Semester']);
    }
}
