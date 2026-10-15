<?php
declare(strict_types=1);

/**
 * B3: an interview schedule is Closed when the admin closes it (Edit schedule) or once its end time has passed. Nobody
 * can be assigned to a closed schedule; it still counts as an interview of the day (attendance can be marked).
 * Seed: application 4 is Qualified (program 1).
 */
final class InterviewClosedTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    protected function setUp(): void
    {
        // Two tests use "today 00:00-00:01" (over) and "today 23:58-23:59" (not over yet): not valid in the first or last minutes of a day.
        if (date('H:i') < '00:02' || date('H:i') > '23:57') $this->markTestSkipped('Too close to midnight for today-based schedules.');
    }

    private static function schedule(string $date, string $start, string $end): int
    {
        $r = Interview::createSchedule(1, ['interview_date' => $date, 'start_time' => $start, 'end_time' => $end, 'venue' => 'Hall', 'max_slots' => 5], 1);
        return $r['id'];
    }

    public function testItClosesByItselfOnceItsEndTimeHasPassed(): void
    {
        $row = ['status' => 'Open', 'interview_date' => '2026-10-15', 'end_time' => '18:47:00', 'assigned_count' => 1, 'max_slots' => 10];
        $this->assertFalse(Interview::isClosed($row, '2026-10-15 18:46:59'));
        $this->assertTrue(Interview::isClosed($row, '2026-10-15 18:47:00'));
        $this->assertTrue(Interview::isClosed($row + [], '2026-10-16 08:00:00'));
        $this->assertSame('Closed', Interview::displayStatus(['interview_date' => '2000-01-01'] + $row));
        $this->assertSame('Open', Interview::displayStatus(['interview_date' => '2999-01-01'] + $row));
    }

    public function testNobodyCanBeAssignedToAScheduleWhoseTimeHasPassed(): void
    {
        $id = self::schedule(date('Y-m-d'), '00:00', '00:01');   // today, already over
        $r = InterviewAssignment::assign($id, [4], 1);
        $this->assertSame(0, $r['assigned']);
        $this->assertStringContainsString('closed', reset($r['errors']));
    }

    public function testTheAdminClosesAndReopensItInEdit(): void
    {
        $date = date('Y-m-d', strtotime('+3 days'));
        $id = self::schedule($date, '08:00', '10:00');
        $edit = ['interview_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'venue' => 'Hall', 'max_slots' => 5];

        $this->assertSame(['ok' => true], Interview::updateSchedule($id, $edit + ['status' => 'Closed'], 1));
        $this->assertSame('Closed', Interview::displayStatus(Interview::schedule($id)));
        $this->assertSame(0, InterviewAssignment::assign($id, [4], 1)['assigned'], 'closed: no assigning');

        $this->assertFalse(Interview::updateSchedule($id, $edit + ['status' => 'Cancelled'], 1)['ok'], 'cancelling is its own action');

        $this->assertSame(['ok' => true], Interview::updateSchedule($id, $edit + ['status' => 'Open'], 1));
        $this->assertSame(1, InterviewAssignment::assign($id, [4], 1)['assigned'], 'open again');
    }

    public function testAClosedScheduleOfTodayStillListsItsInterviews(): void
    {
        $id = self::schedule(date('Y-m-d'), '23:58', '23:59');
        $this->assertSame(1, InterviewAssignment::assign($id, [4], 1)['assigned']);
        $edit = ['interview_date' => date('Y-m-d'), 'start_time' => '23:58', 'end_time' => '23:59', 'venue' => 'Hall', 'max_slots' => 5];
        Interview::updateSchedule($id, $edit + ['status' => 'Closed'], 1);
        $this->assertContains($id, array_map(static fn($s) => (int)$s['id'], Interview::day(date('Y-m-d'))), 'attendance can still be marked');
    }
}
