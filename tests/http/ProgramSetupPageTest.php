<?php
declare(strict_types=1);

/**
 * Programs → New program through the real page (docs/CHANGE_PLAN_5.md §7): a refused part saves nothing and reopens
 * the form with what was typed; a good form saves the program, budget and renewal window; editing a program never
 * touches its window or budget (decision D9).
 */
final class ProgramSetupPageTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function form(array $over = []): array
    {
        return $over + [
            'program_name' => 'EduKabataan 2028', 'academic_year' => '2028-2029',
            'application_start' => date('Y-m-d', strtotime('+1 day')), 'application_end' => date('Y-m-d', strtotime('+30 days')),
            'slots_available' => '300', 'amount_per_semester' => '5000',
            'with_budget' => '1', 'budget_total' => '3000000',
            'with_renewal' => '1', 'renewal_opens_at' => date('Y-m-d\T00:00', strtotime('+1 day')), 'renewal_deadline' => date('Y-m-d\T23:59', strtotime('+30 days')),
        ];
    }

    public function testARefusedPartSavesNothingAndReopensTheForm(): void
    {
        $c = self::signedIn('admin@test.local');
        $c->post('/admin/program-save', self::form(['budget_total' => '0']), [], [], '/admin/programs');
        $html = $c->get('/admin/programs')->body;
        $this->assertStringContainsString('Budget: Total budget must be a number greater than 0. Nothing was saved.', html_entity_decode($html, ENT_QUOTES));
        $this->assertMatchesRegularExpression('~data-reopen="[^"]*EduKabataan 2028[^"]*"~', $html, 'the form opens again with what was typed');
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM scholarship_programs WHERE academic_year = '2028-2029'"));
        $this->assertStringNotContainsString('data-reopen', $c->get('/admin/programs')->body, 'only once');
    }

    public function testAGoodFormSavesEverythingAndEditingLeavesTheWindowAndBudgetAlone(): void
    {
        $c = self::signedIn('admin@test.local');
        $c->post('/admin/program-save', self::form(), [], [], '/admin/programs');
        $this->assertSame('Program created for A.Y. 2028-2029. Budget of ₱3,000,000.00 set. Renewal window into 2028-2029 1st Semester created; 0 scholar(s) were notified.',
            self::flashAfter($c));
        $id = (int)self::value("SELECT id FROM scholarship_programs WHERE academic_year = '2028-2029'");
        $this->assertSame([1, 1], [(int)self::value('SELECT COUNT(*) FROM budgets WHERE program_id = ?', [$id]),
                                   (int)self::value("SELECT COUNT(*) FROM term_schedules WHERE program_id = ? AND purpose = 'renewal'", [$id])]);

        $c->post('/admin/program-save', ['id' => $id] + self::form(['program_name' => 'EduKabataan 2028 (renamed)', 'budget_total' => '1']), [], [], '/admin/programs');
        $this->assertSame('Program updated.', self::flashAfter($c));
        $this->assertSame([1, 1, 3000000.0], [(int)self::value('SELECT COUNT(*) FROM budgets WHERE program_id = ?', [$id]),
                                              (int)self::value("SELECT COUNT(*) FROM term_schedules WHERE program_id = ?", [$id]),
                                              (float)self::value('SELECT total_budget FROM budgets WHERE program_id = ?', [$id])]);
    }
}
