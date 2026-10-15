<?php
declare(strict_types=1);

/** Budget::suggestedTotal = number of Active scholars × (amount per semester × 2 semesters). */
final class BudgetTest extends DatabaseTestCase
{
    public function testSuggestedTotal(): void
    {
        // Program 1: 1 Active scholar (Scott) × (₱5,000 × 2) = ₱10,000.
        $this->assertSame(['total' => 10000.0, 'scholars' => 1, 'amount' => 5000.0], Budget::suggestedTotal(1));
    }

    public function testOnlyActiveScholarsCount(): void
    {
        Database::conn()->exec("INSERT INTO scholars (scholar_code, applicant_id, application_id, program_id, approval_date, status, end_reason, ended_at) VALUES
            ('SCH-TEST-A2', 7, 7, 1, CURDATE(), 'Active', NULL, NULL),
            ('SCH-TEST-GR', 6, 6, 1, CURDATE(), 'Ended', 'Graduated', NOW())");
        try {
            $this->assertSame(['total' => 20000.0, 'scholars' => 2, 'amount' => 5000.0], Budget::suggestedTotal(1), 'the graduate is not counted');
        } finally {
            self::rebuild();
        }
    }

    public function testNoSuggestionWithoutScholarsOrAmountOrProgram(): void
    {
        $this->assertNull(Budget::suggestedTotal(999));
        $this->assertNull(Budget::suggestedTotal(2), 'program 2 has no scholars yet');
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
