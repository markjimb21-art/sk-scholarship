<?php
declare(strict_types=1);

/**
 * Interview::suggestSlots — existing Open schedules with room first, then free 2-hour blocks: Monday–Saturday,
 * business hours, from the day after application_end, never on holidays or another program's interview day, never
 * overlapping an Open schedule. Seed week (@mon = first Monday after application_end): Monday 08–10 taken by
 * schedule 4, Tuesday holiday, Wednesday program 2's day, Thursday 13–15 a CANCELLED schedule (blocks nothing).
 */
final class InterviewSuggestionsTest extends DatabaseTestCase
{
    private static array $all;
    private static string $mon;

    public static function setUpBeforeClass(): void
    {
        self::$all = Interview::suggestSlots(1, 60);
        self::$mon = (string)self::value('SELECT interview_date FROM interview_schedules WHERE id = 4');
    }

    private static function day(int $offset): string
    {
        return (new DateTimeImmutable(self::$mon))->modify("+$offset day")->format('Y-m-d');
    }

    private static function blocksOn(string $date): array
    {
        return array_values(array_map(static fn($s) => $s['start'],
            array_filter(self::$all, static fn($s) => $s['kind'] === 'block' && $s['date'] === $date)));
    }

    public function testExistingScheduleWithRoomComesFirst(): void
    {
        $first = self::$all[0];
        $this->assertSame('schedule', $first['kind']);
        $this->assertSame(4, $first['schedule_id']);
        $this->assertSame(9, $first['free']);
        $this->assertCount(1, array_filter(self::$all, static fn($s) => $s['kind'] === 'schedule'), 'the cancelled schedule is not offered');
    }

    public function testEveryBlockFollowsTheRules(): void
    {
        $end = (string)self::value('SELECT application_end FROM scholarship_programs WHERE id = 1');
        $holidays = Interview::holidays(date('Y-m-d'), '2100-01-01');
        $otherDay = (string)self::value('SELECT interview_date FROM interview_schedules WHERE id = 5');
        $open = Database::conn()->query("SELECT interview_date, start_time, end_time FROM interview_schedules WHERE status = 'Open'")->fetchAll();
        $blocks = array_filter(self::$all, static fn($s) => $s['kind'] === 'block');
        $this->assertNotEmpty($blocks);

        foreach ($blocks as $b) {
            $this->assertGreaterThan($end, $b['date'], 'starts after application_end');
            $this->assertNotSame('7', date('N', strtotime($b['date'])), 'never on Sunday');
            $this->assertContains([$b['start'], $b['end']], Interview::SUGGESTION_BLOCKS, 'a business-hours block');
            $this->assertArrayNotHasKey($b['date'], $holidays, 'never on a holiday');
            $this->assertNotSame($otherDay, $b['date'], "never on another program's interview day");
            foreach ($open as $s) {
                if ($s['interview_date'] !== $b['date']) continue;
                $overlap = $b['start'] < substr($s['end_time'], 0, 5) && $b['end'] > substr($s['start_time'], 0, 5);
                $this->assertFalse($overlap, "{$b['date']} {$b['start']} overlaps an Open schedule");
            }
        }
    }

    public function testTheSeedWeek(): void
    {
        $this->assertSame(['10:00', '13:00', '15:00'], self::blocksOn(self::$mon), 'Monday: 08:00 is taken by schedule 4');
        $this->assertSame([], self::blocksOn(self::day(1)), 'Tuesday is a holiday');
        $this->assertSame([], self::blocksOn(self::day(2)), "Wednesday is program 2's interview day");
        $this->assertSame(['08:00', '10:00', '13:00', '15:00'], self::blocksOn(self::day(3)), 'Thursday: the cancelled schedule blocks nothing');
    }

    public function testLimitAndWithoutSchedules(): void
    {
        $five = Interview::suggestSlots(1, 5);
        $this->assertCount(5, $five);
        $this->assertSame('schedule', $five[0]['kind']);
        foreach (Interview::suggestSlots(1, 10, $why, false) as $s) $this->assertSame('block', $s['kind']);
    }

    public function testUnknownProgram(): void
    {
        $this->assertSame([], Interview::suggestSlots(999, 5, $why));
        $this->assertSame('Program not found.', $why);
    }
}
