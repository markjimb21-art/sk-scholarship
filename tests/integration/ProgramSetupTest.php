<?php
declare(strict_types=1);

/**
 * Programs → New program opens the academic year in one form (docs/CHANGE_PLAN_5.md §7): program + budget + renewal
 * window in one transaction — all saved, or nothing when any part is refused; the scholars are told only after the
 * commit. Seed: programs 1 (2026-2027) and 2 (2027-2028); Scott's latest semester is moved to 2027-2028 here, so he is
 * the one a 2028-2029 renewal window goes to.
 */
final class ProgramSetupTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        // Scott renewed into 2027-2028 (program 2).
        Database::conn()->exec("INSERT INTO scholar_terms (scholar_id, program_id, academic_year, semester, opened_via, claim_status)
                                VALUES (1, 2, '2027-2028', '1st Semester', 'renewal', 'Released'),
                                       (1, 2, '2027-2028', '2nd Semester', 'continuation', 'Verified')");
    }

    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function form(array $over = []): array
    {
        return $over + [
            'program_name' => 'EduKabataan 2028', 'academic_year' => '2028-2029',
            'application_start' => date('Y-m-d', strtotime('+1 day')), 'application_end' => date('Y-m-d', strtotime('+30 days')),
            'slots_available' => '300', 'amount_per_semester' => '5000', 'min_gpa' => '', 'max_family_income' => '', 'max_age' => '',
            'with_budget' => '1', 'budget_total' => '3000000',
            'with_renewal' => '1', 'renewal_opens_at' => date('Y-m-d\T00:00', strtotime('+1 day')), 'renewal_deadline' => date('Y-m-d\T23:59', strtotime('+30 days')),
        ];
    }

    /** What exists of a 2028-2029 setup: [programs, budgets, renewal windows, audit entries, notifications to Scott]. */
    private static function saved(): array
    {
        return [
            (int)self::value("SELECT COUNT(*) FROM scholarship_programs WHERE academic_year = '2028-2029'"),
            (int)self::value("SELECT COUNT(*) FROM budgets WHERE academic_year = '2028-2029'"),
            (int)self::value("SELECT COUNT(*) FROM term_schedules WHERE academic_year = '2028-2029'"),
            (int)self::value("SELECT COUNT(*) FROM audit_logs WHERE action IN ('program_created', 'budget_created', 'term_schedule_created')"),
            (int)self::value("SELECT COUNT(*) FROM notifications WHERE user_id = 11 AND title LIKE 'Renewal open%'"),
        ];
    }

    public function testTheProgramItsBudgetAndItsRenewalWindowAreSavedTogether(): void
    {
        $before = (int)self::value("SELECT COUNT(*) FROM audit_logs WHERE action IN ('program_created', 'budget_created', 'term_schedule_created')");
        $r = ProgramSetup::create(self::form(), 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(1, $r['notified'], 'Scott (latest semester in 2027-2028) is told the renewal is open');
        $this->assertSame([1, 1, 1, $before + 3, 1], self::saved(), 'each part wrote its own audit entry');

        $this->assertSame(3000000.0, (float)Budget::find($r['budget_id'])['total_budget']);
        $w = TermSchedule::find($r['renewal_id']);
        $this->assertSame([$r['id'], 'renewal', '2028-2029', '1st Semester', 'Open'],
            [(int)$w['program_id'], $w['purpose'], $w['academic_year'], $w['semester'], $w['status']]);
        $this->assertSame(substr(self::form()['renewal_deadline'], 0, 10) . ' 23:59:00', $w['submission_deadline']);
        $this->assertSame($r['id'], (int)Renewal::window(Term::current(1))['schedule']['program_id'],
            'the 2027-2028 scholars renew into it (one program per A.Y.)');
    }

    public function testNothingIsSavedWhenTheRenewalWindowIsRefused(): void
    {
        $r = ProgramSetup::create(self::form(['renewal_opens_at' => date('Y-m-d\TH:i', strtotime('-2 days')),
                                               'renewal_deadline' => date('Y-m-d\TH:i', strtotime('-1 day'))]), 1);
        $this->assertSame(['ok' => false, 'part' => 'renewal', 'error' => 'Renewal window: The deadline must be in the future.'], $r);
        $before = self::saved();
        $this->assertSame([0, 0, 0], array_slice($before, 0, 3), 'no program, no budget, no window');
        $this->assertSame(0, $before[4], 'nobody was e-mailed');
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM audit_logs WHERE description LIKE '%EduKabataan 2028%'"), 'no audit entry left behind');
    }

    public function testNothingIsSavedWhenTheBudgetIsRefused(): void
    {
        $r = ProgramSetup::create(self::form(['budget_total' => '0']), 1);
        $this->assertSame(['ok' => false, 'part' => 'budget', 'error' => 'Budget: Total budget must be a number greater than 0.'], $r);
        $this->assertSame([0, 0, 0], array_slice(self::saved(), 0, 3));
    }

    public function testNothingIsSavedWhenTheProgramIsRefused(): void
    {
        $r = ProgramSetup::create(self::form(['academic_year' => '2027-2028']), 1);
        $this->assertSame('program', $r['part']);
        $this->assertStringStartsWith('Program: A.Y. 2027-2028 already has a program ("Other Test Program")', $r['error']);
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM scholarship_programs WHERE program_name = 'EduKabataan 2028'"));
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM budgets WHERE program_id NOT IN (1)"));
    }

    public function testUntickedSectionsAreNotCreated(): void
    {
        $data = self::form();
        unset($data['with_budget'], $data['with_renewal']);
        $r = ProgramSetup::create($data, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame([null, null, 0], [$r['budget_id'], $r['renewal_id'], $r['notified']]);
        $this->assertSame([1, 0, 0], array_slice(self::saved(), 0, 3));
    }
}
