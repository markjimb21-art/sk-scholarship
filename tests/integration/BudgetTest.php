<?php
declare(strict_types=1);

/**
 * Budget::suggestedTotal = slots × (amount per semester × 2 semesters): every seat of the academic-year intake,
 * returning or new, paid for both semesters (docs/CHANGE_PLAN_5.md §6.3; before plan 5 it counted Active scholars).
 */
final class BudgetTest extends DatabaseTestCase
{
    public function testSuggestedTotal(): void
    {
        // Program 1: 3 slots × (₱5,000 × 2) = ₱30,000.
        $this->assertSame(['total' => 30000.0, 'slots' => 3, 'amount' => 5000.0], Budget::suggestedTotal(1));
    }

    public function testASuggestionForANewProgramWithNoScholars(): void
    {
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM scholar_terms WHERE program_id = 2'), 'program 2 (2027-2028) has no scholars yet');
        // 5 slots × (₱4,000 × 2) = ₱40,000.
        $this->assertSame(['total' => 40000.0, 'slots' => 5, 'amount' => 4000.0], Budget::suggestedTotal(2));
    }

    public function testNoSuggestionWithoutAmountOrProgram(): void
    {
        $this->assertNull(Budget::suggestedTotal(999));
        Database::conn()->exec('UPDATE scholarship_programs SET amount_per_semester = 0 WHERE id = 1');
        try {
            $this->assertNull(Budget::suggestedTotal(1));
        } finally {
            self::rebuild();
        }
    }

    public function testActualReleasedComesFromTheReleases(): void
    {
        $this->assertEqualsWithDelta(5000.0, Budget::actualReleased(1, '2026-2027'), 0.001);
        $this->assertEqualsWithDelta(0.0, Budget::actualReleased(1, '2027-2028'), 0.001);
    }
}
