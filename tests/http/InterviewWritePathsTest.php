<?php
declare(strict_types=1);

/**
 * B3–B5 through the real pages, checked only by HTTP answers and database rows (no class names), so the same test
 * proves the interview code behaves the same before and after it was split into several classes.
 */
final class InterviewWritePathsTest extends HttpTestCase
{
    private static function admin(): HttpClient { return self::signedIn('admin@test.local'); }

    private static function newSchedule(HttpClient $c, string $start = '13:00', string $end = '15:00', int $days = 25): int
    {
        $c->post('/admin/interview-schedule-save', ['program_id' => 1, 'interview_date' => date('Y-m-d', strtotime("+$days days")),
            'start_time' => $start, 'end_time' => $end, 'venue' => 'Covered Court', 'max_slots' => 5], [], [], '/admin/interviews');
        return (int)self::value('SELECT MAX(id) FROM interview_schedules');
    }

    public function testDeclineASlotRequest(): void
    {
        $c = self::admin();
        $req = (int)self::value("SELECT id FROM slot_requests WHERE status = 'Pending' LIMIT 1");
        if ($req === 0) $this->markTestSkipped('no pending request left');
        $c->post('/admin/slot-request-decide', ['request_id' => $req, 'decision' => 'decline', 'note' => 'Please pick another day.'], [], [], '/admin/interviews');
        $this->assertSame('Request declined. The applicant was notified.', self::flashAfter($c));
        $this->assertSame('Declined', self::value('SELECT status FROM slot_requests WHERE id = ?', [$req]));
    }

    public function testCreateUpdateAndDeleteASchedule(): void
    {
        $c = self::admin();
        $id = self::newSchedule($c);
        $this->assertStringEndsWith("/admin/interview-schedule&id=$id", $c->location);
        $this->assertSame('Schedule created. Now assign Qualified applicants to it.', self::flashAfter($c));

        $c->post('/admin/interview-schedule-update', ['id' => $id, 'interview_date' => date('Y-m-d', strtotime('+25 days')), 'start_time' => '13:00',
            'end_time' => '15:00', 'venue' => 'Barangay Hall Annex', 'max_slots' => 6], [], [], "/admin/interview-schedule&id=$id");
        $this->assertSame('Schedule updated. Affected applicants were notified of any date, time or venue change.', self::flashAfter($c));
        $this->assertSame(['venue' => 'Barangay Hall Annex', 'max_slots' => 6], self::row('SELECT venue, max_slots FROM interview_schedules WHERE id = ?', [$id]));

        $c->post('/admin/interview-schedule-delete', ['id' => $id], [], [], "/admin/interview-schedule&id=$id");
        $this->assertSame('Empty schedule deleted.', self::flashAfter($c));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM interview_schedules WHERE id = ?', [$id]));
    }

    public function testAssignUnassignNoShowRescheduleAndSecondNoShow(): void
    {
        $c = self::admin();
        $s = self::newSchedule($c, '08:00', '10:00', 26);
        $s2 = self::newSchedule($c, '10:00', '12:00', 27);

        $c->post('/admin/interview-assign', ['schedule_id' => $s, 'application_ids' => [4]], [], [], "/admin/interview-schedule&id=$s");
        $this->assertSame('1 applicant(s) assigned and notified.', self::flashAfter($c));
        $this->assertSame('Interview Scheduled', self::value('SELECT status FROM applications WHERE id = 4'));
        $a1 = (int)self::value('SELECT MAX(id) FROM interview_assignments WHERE application_id = 4');

        $c->post('/admin/interview-unassign', ['schedule_id' => $s, 'assignment_id' => $a1], [], [], "/admin/interview-schedule&id=$s");
        $this->assertSame('Applicant removed from this schedule and notified.', self::flashAfter($c));
        $this->assertSame('Qualified', self::value('SELECT status FROM applications WHERE id = 4'));

        $c->post('/admin/interview-assign', ['schedule_id' => $s, 'application_ids' => [4]], [], [], "/admin/interview-schedule&id=$s");
        $a2 = (int)self::value('SELECT MAX(id) FROM interview_assignments WHERE application_id = 4');
        Database::conn()->exec("UPDATE interview_schedules SET interview_date = CURDATE() WHERE id = $s");   // a no-show is marked on/after the date
        $c->post('/admin/interview-no-show', ['schedule_id' => $s, 'assignment_id' => $a2], [], [], "/admin/interview-schedule&id=$s");
        $this->assertSame('No-show recorded. The applicant is back in the waiting list for their one reschedule.', self::flashAfter($c));
        $this->assertSame('Qualified', self::value('SELECT status FROM applications WHERE id = 4'));

        $c->post('/admin/interview-reschedule-save', ['application_id' => 4, 'mode' => 'schedule', 'schedule_id' => $s2], [], [], '/admin/interview-reschedule&application_id=4');
        $this->assertSame('Interview scheduled. The applicant was notified.', self::flashAfter($c));
        $a3 = self::row('SELECT id, schedule_id, reschedule_count FROM interview_assignments WHERE application_id = 4 ORDER BY id DESC LIMIT 1');
        $this->assertSame([$s2, 1], [$a3['schedule_id'], $a3['reschedule_count']], 'the one reschedule');

        Database::conn()->exec("UPDATE interview_schedules SET interview_date = CURDATE() WHERE id = $s2");
        $c->post('/admin/interview-no-show', ['schedule_id' => $s2, 'assignment_id' => $a3['id']], [], [], "/admin/interview-schedule&id=$s2");
        $this->assertSame('Second no-show: the application was rejected and the applicant notified.', self::flashAfter($c));
        $this->assertSame('Rejected', self::value('SELECT status FROM applications WHERE id = 4'));
    }

    public function testScoreAnInterview(): void
    {
        $c = self::admin();
        $c->post('/admin/interview-score-save', ['assignment_id' => 6, 'financial_need_score' => 35, 'academic_score' => 25, 'communication_score' => 15,
            'community_score' => 10], [], [], '/admin/interview-score&id=6');
        $this->assertSame('You can score only on or after the interview date.', self::flashAfter($c));
        Database::conn()->exec('UPDATE interview_schedules SET interview_date = CURDATE() WHERE id = 4');
        $c->post('/admin/interview-score-save', ['assignment_id' => 6, 'financial_need_score' => 50, 'academic_score' => 20, 'communication_score' => 15,
            'community_score' => 5], [], [], '/admin/interview-score&id=6');
        $this->assertNotSame('', self::flashAfter($c));
        $this->assertSame('Interview Scheduled', self::value('SELECT status FROM applications WHERE id = 5'), 'over the 40 maximum: refused');

        $c->post('/admin/interview-score-save', ['assignment_id' => 6, 'financial_need_score' => 35, 'academic_score' => 25, 'communication_score' => 15,
            'community_score' => 10, 'remarks' => 'Good'], [], [], '/admin/interview-score&id=6');
        $this->assertSame('Score saved: 85 / 100. The applicant is now waiting for the final decision.', self::flashAfter($c));
        $this->assertSame('Interviewed', self::value('SELECT status FROM applications WHERE id = 5'));
        $this->assertSame('85.00', self::value('SELECT total_score FROM interview_evaluations WHERE assignment_id = 6'));
        $this->assertSame('Attended', self::value('SELECT attendance FROM interview_assignments WHERE id = 6'));
    }

    public function testCancelAScheduleWithApplicants(): void
    {
        $c = self::admin();
        Database::conn()->exec("UPDATE applications SET status = 'Qualified', qualified_at = NOW() WHERE id = 2");
        $s = self::newSchedule($c, '15:00', '17:00', 28);
        $c->post('/admin/interview-assign', ['schedule_id' => $s, 'application_ids' => [2]], [], [], "/admin/interview-schedule&id=$s");
        $this->assertSame('Interview Scheduled', self::value('SELECT status FROM applications WHERE id = 2'));

        $c->post('/admin/interview-schedule-cancel', ['id' => $s], [], [], "/admin/interview-schedule&id=$s");
        $this->assertSame('Schedule cancelled. 1 applicant(s) are back in the waiting list and were notified.', self::flashAfter($c));
        $this->assertSame('Cancelled', self::value('SELECT status FROM interview_schedules WHERE id = ?', [$s]));
        $this->assertSame('Qualified', self::value('SELECT status FROM applications WHERE id = 2'), 'back in the waiting list');
    }

    public function testDecisions(): void
    {
        $c = self::admin();
        $c->post('/admin/decisions/decide', ['id' => 6, 'decision' => 'waitlist'], [], [], '/admin/decisions&program_id=1');
        $this->assertSame('Waitlisted. The applicant was notified.', self::flashAfter($c));
        $this->assertSame('Waitlisted', self::value('SELECT status FROM applications WHERE id = 6'));
        $c->post('/admin/decisions/decide', ['id' => 7, 'decision' => 'reject', 'reason' => 'Below the cut-off.'], [], [], '/admin/decisions&program_id=1');
        $this->assertSame('Rejected. The applicant was notified.', self::flashAfter($c));
        $this->assertSame(['status' => 'Rejected', 'decision_remarks' => 'Below the cut-off.'], self::row('SELECT status, decision_remarks FROM applications WHERE id = 7'));
        $this->assertStringContainsString('Ivy Interviewed', $c->get('/admin/decisions&program_id=1')->body, 'the ranking page lists the waitlisted applicant');
    }

}
