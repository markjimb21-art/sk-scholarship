<?php
declare(strict_types=1);

/** B5 slots used: a graduated scholar frees the slot (2 / 2 − 1 graduated = 1 / 2). Seed: Scott, 2nd semester of program 1. */
final class SlotsUsedTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    public function testAGraduatedScholarFreesTheSlot(): void
    {
        $this->assertSame(1, Program::slotsUsed(1));
        $r = Scholar::end(1, 'Graduated', 1);
        $this->assertTrue($r['slot_freed'], 'the Decisions page offers the next waitlisted applicant');
        $this->assertSame(0, Program::slotsUsed(1));
    }

    public function testAScholarWhoDroppedOutAfterTheFirstSemesterKeepsTheSlot(): void
    {
        $r = Scholar::end(1, 'Dropped out', 1);
        $this->assertFalse($r['slot_freed']);
        $this->assertSame(1, Program::slotsUsed(1));
    }
}
