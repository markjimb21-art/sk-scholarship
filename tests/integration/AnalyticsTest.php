<?php
declare(strict_types=1);

/**
 * The analytics formulas (docs/PROCESS.md §6), checked against numbers worked out BY HAND from tests/seed.sql.
 * Program 1: 3 slots, ₱5,000 per semester; budget ₱50,000 for 2026-2027 with ₱5,000 released; 1 scholar (Scott).
 */
final class AnalyticsTest extends DatabaseTestCase
{
    private const P = 1;
    private const AY = '2026-2027';

    public function testStageCountsHaveOneApplicationInEachStage(): void
    {
        $this->assertSame(array_fill_keys(Analytics::STAGES, 1), Analytics::stageCounts(self::P));
    }

    /**
     * No closed past program → rates come from this program's decided applications: Scott (Approved), Remy (Rejected).
     * Both were submitted, qualified and scheduled → 1 of 2 = 0.5. Only Scott was scored → Interviewed 1 of 1 = 1.0.
     * Neither was returned or waitlisted → no data (null).
     */
    public function testConversionRates(): void
    {
        $c = Analytics::conversionRates(self::P);
        $this->assertTrue($c['current_only']);
        $rate = static fn(string $s) => $c['rates'][$s]['rate'];
        $this->assertSame(0.5, $rate('Submitted'));
        $this->assertNull($rate('Returned'));
        $this->assertSame(0.5, $rate('Qualified'));
        $this->assertSame(0.5, $rate('Interview Scheduled'));
        $this->assertEquals(1, $rate('Interviewed'));   // 1 ÷ 1 is the int 1 in PHP
        $this->assertNull($rate('Waitlisted'));
    }

    /**
     * Projected scholars = slots used + Σ(count in stage × rate), capped at the slots.
     *   = 1 + (1×0.5 + 1×0 + 1×0.5 + 1×0.5 + 1×1.0 + 1×0) = 1 + 2.5 = 3.5 → capped at 3 slots; expected new = 3 − 1 = 2.
     */
    public function testProjectedScholars(): void
    {
        $p = Analytics::projectedScholars(self::P);
        $this->assertSame(1, $p['slots_used']);
        $this->assertEqualsWithDelta(3.5, $p['uncapped'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $p['value'], 1e-9);
        $this->assertEqualsWithDelta(2.0, $p['expected_new'], 1e-9);
        $this->assertSame(2, Analytics::slotsRemaining(self::P));
        $this->assertSame(1, Analytics::waitlistedAvailable(self::P));
    }

    /** Committed = ₱5,000 × (2 semesters − 1 already released to Scott) = ₱5,000. */
    public function testCommitted(): void
    {
        $this->assertEqualsWithDelta(5000.0, Analytics::committed(self::P, self::AY), 0.001);
    }

    /** Projected need = committed + expected new scholars × ₱5,000 × 2 = 5,000 + 2 × 10,000 = ₱25,000. */
    public function testProjectedNeed(): void
    {
        $this->assertEqualsWithDelta(25000.0, Analytics::projectedNeed(self::P, self::AY), 0.001);
    }

    /** Remaining = 50,000 − 5,000 = 45,000; surplus = 45,000 − 25,000 = ₱20,000; no budget for another year → null. */
    public function testRemainingAndSurplus(): void
    {
        $this->assertEqualsWithDelta(45000.0, Analytics::remaining(self::P, self::AY), 0.001);
        $this->assertEqualsWithDelta(20000.0, Analytics::surplus(self::P, self::AY), 0.001);
        $this->assertNull(Analytics::surplus(self::P, '2030-2031'));
    }

    /** Extra fundable = floor(20,000 ÷ (5,000 × 2)) = 2; 0 when there is no budget (no surplus). */
    public function testExtraFundable(): void
    {
        $this->assertSame(2, Analytics::extraFundable(self::P, self::AY));
        $this->assertSame(0, Analytics::extraFundable(self::P, '2030-2031'));
    }

    /** Attendance rate = Attended ÷ (Attended + No-show) = 3 ÷ (3 + 2) = 0.6 (Sky's upcoming interview doesn't count). */
    public function testAttendanceRate(): void
    {
        $this->assertSame(['rate' => 0.6, 'attended' => 3, 'total' => 5], Analytics::attendanceRate(self::P));
    }

    /** No-show rate on the first attempt 1 of 4 (Remy); on the one reschedule 1 of 1 (Remy again). */
    public function testNoShowByAttempt(): void
    {
        $n = Analytics::noShowByAttempt(self::P);
        $this->assertSame(['rate' => 0.25, 'noshow' => 1, 'total' => 4], $n[0]);
        $this->assertEquals(['rate' => 1, 'noshow' => 1, 'total' => 1], $n[1]);
    }

    /** Scores 90, 80, 60 → average 76.67; bands: 60 in 50–69.99, 80 and 90 in 70–100. */
    public function testScoreSummary(): void
    {
        $s = Analytics::scoreSummary(self::P);
        $this->assertSame(3, $s['count']);
        $this->assertEqualsWithDelta(230 / 3, $s['average'], 1e-9);
        $this->assertSame(['Below 50' => 0, '50–69.99' => 1, '70–100' => 2], $s['bands']);
    }

    /**
     * On-time rate = claims submitted by the window's deadline ÷ claims with a window.
     * 1st semester: submitted 50 days ago, deadline 40 days ago → on time. 2nd: submitted 5 days ago, deadline 10 days ago → late.
     * = 1 ÷ 2 = 0.5. Average days from the window opening: (10 + 15) ÷ 2 = 12.5.
     */
    public function testOnTimeRate(): void
    {
        $this->assertSame(['rate' => 0.5, 'on_time' => 1, 'total' => 2, 'without_window' => 0], Analytics::onTimeRate(self::P, self::AY));
        $this->assertEqualsWithDelta(12.5, Analytics::avgDaysToSubmit(self::P, self::AY), 0.01);
    }

    public function testNoDataGivesNullRatesNotErrors(): void
    {
        $this->assertNull(Analytics::attendanceRate(2)['rate']);
        $this->assertNull(Analytics::onTimeRate(2, self::AY)['rate']);
        $this->assertNull(Analytics::avgDaysToSubmit(2, self::AY));
    }
}
