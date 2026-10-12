<?php
declare(strict_types=1);

/** Budget::suggestedTotal = slots × amount per semester × 2 semesters. */
final class BudgetTest extends DatabaseTestCase
{
    public function testSuggestedTotal(): void
    {
        // Program 1: 3 slots × ₱5,000 × 2 = ₱30,000. Program 2: 5 × ₱4,000 × 2 = ₱40,000.
        $this->assertSame(['total' => 30000.0, 'slots' => 3, 'amount' => 5000.0], Budget::suggestedTotal(1));
        $this->assertSame(['total' => 40000.0, 'slots' => 5, 'amount' => 4000.0], Budget::suggestedTotal(2));
    }

    public function testNoSuggestionWithoutSlotsOrAmountOrProgram(): void
    {
        $this->assertNull(Budget::suggestedTotal(999));
        Database::conn()->exec('UPDATE scholarship_programs SET slots_available = 0 WHERE id = 2');
        try {
            $this->assertNull(Budget::suggestedTotal(2));
        } finally {
            Database::conn()->exec('UPDATE scholarship_programs SET slots_available = 5 WHERE id = 2');
        }
    }

    public function testActualReleasedComesFromTheReleases(): void
    {
        $this->assertEqualsWithDelta(5000.0, Budget::actualReleased(1, '2026-2027'), 0.001);
        $this->assertEqualsWithDelta(0.0, Budget::actualReleased(1, '2027-2028'), 0.001);
    }
}
