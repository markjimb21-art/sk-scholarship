<?php
declare(strict_types=1);

/**
 * Claim/renewal windows: program, A.Y. and semester can be changed — and the window deleted — only while nobody has
 * submitted under it. Seed: all three windows of program 1 already have Scott's submission.
 * A renewal window always goes into the 1st Semester of its program's own A.Y. (plan 5, decisions D1/D3): program 2 is
 * 2027-2028, so its renewal window is the one Scott (in 2026-2027 2nd Semester) renews into.
 */
final class TermScheduleEditDeleteTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function dates(): array
    {
        return ['opens_at' => date('Y-m-d\TH:i', strtotime('-1 day')), 'submission_deadline' => date('Y-m-d\TH:i', strtotime('+5 days'))];
    }

    /** The renewal window into 2027-2028 1st Semester (program 2): Scott hasn't renewed — 0 submitted, 1 not yet. */
    private static function newWindow(): int
    {
        $r = TermSchedule::save(['purpose' => 'renewal', 'program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '1st Semester'] + self::dates(), null, 1);
        return $r['id'];
    }

    private static function seededWindow(): int
    {
        return (int)self::value("SELECT id FROM term_schedules WHERE purpose = 'release' AND semester = '2nd Semester'");
    }

    public function testARenewalWindowGoesIntoThe1stSemesterOfItsProgramsYear(): void
    {
        $r = TermSchedule::save(['purpose' => 'renewal', 'program_id' => 2, 'academic_year' => '2030-2031', 'semester' => '2nd Semester'] + self::dates(), null, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $row = TermSchedule::find($r['id']);
        $this->assertSame(['2027-2028', '1st Semester'], [$row['academic_year'], $row['semester']], 'the year and semester sent are ignored');
        $this->assertSame(1, $r['notified'], 'Scott is told the renewal window is open');
    }

    public function testTheSemesterOfAClaimWindowNobodySubmittedUnderCanBeChanged(): void
    {
        $id = TermSchedule::save(['purpose' => 'release', 'program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '1st Semester'] + self::dates(), null, 1)['id'];
        $r = TermSchedule::save(['program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '2nd Semester', 'status' => 'Open'] + self::dates(), $id, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('2nd Semester', TermSchedule::find($id)['semester']);
    }

    public function testTheProgramOfARenewalWindowNobodySubmittedUnderCanBeChanged(): void
    {
        $id = self::newWindow();
        $r = TermSchedule::save(['program_id' => 1, 'academic_year' => '2027-2028', 'semester' => '1st Semester', 'status' => 'Open'] + self::dates(), $id, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(1, $r['notified'], 'Scott is told the 2027-2028 window was cancelled');
        $row = TermSchedule::find($id);
        $this->assertSame([1, '2026-2027', '1st Semester'], [(int)$row['program_id'], $row['academic_year'], $row['semester']],
            'it follows program 1 into its own A.Y.');
    }

    public function testAWindowWithSubmissionsKeepsItsSemester(): void
    {
        $id = self::seededWindow();
        $r = TermSchedule::save(['program_id' => 1, 'academic_year' => '2027-2028', 'semester' => '1st Semester', 'status' => 'Open'] + self::dates(), $id, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $row = TermSchedule::find($id);
        $this->assertSame(['2026-2027', '2nd Semester'], [$row['academic_year'], $row['semester']], 'only the dates changed');
    }

    public function testChangingIntoAnExistingWindowIsRefused(): void
    {
        $this->assertTrue(TermSchedule::save(['purpose' => 'renewal', 'program_id' => 1] + self::dates(), null, 1)['ok']);   // 2026-2027 1st Semester
        $id = self::newWindow();
        $r = TermSchedule::save(['program_id' => 1, 'status' => 'Open'] + self::dates(), $id, 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('already exists', $r['error']);
    }

    public function testDelete(): void
    {
        $r = TermSchedule::delete(self::seededWindow(), 1);
        $this->assertFalse($r['ok'], 'Scott submitted under it');
        $this->assertStringContainsString('already submitted', $r['error']);

        $id = self::newWindow();
        $this->assertSame(['ok' => true, 'notified' => 1, 'purpose' => 'renewal'], TermSchedule::delete($id, 1), 'Scott is told it was cancelled');
        $this->assertNull(TermSchedule::find($id));
        $this->assertSame('term_schedule_deleted', self::value("SELECT action FROM audit_logs WHERE entity_type = 'term_schedules' AND entity_id = ? ORDER BY id DESC LIMIT 1", [$id]));
    }
}
