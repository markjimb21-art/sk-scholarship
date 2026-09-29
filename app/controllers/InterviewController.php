<?php
declare(strict_types=1);

/** B3 schedules and assignment, B4 attendance and scoring, B5 ranking and decisions. */
final class InterviewController
{
    private function programFromRequest(): ?array
    {
        $id = $_GET['program_id'] ?? $_POST['program_id'] ?? null;
        return Program::selected($id !== null && $id !== '' ? (int)$id : null);
    }

    /** B3: the program's schedules + the Qualified applicants still waiting for one. */
    public function index(): void
    {
        $program = $this->programFromRequest();
        $programs = Program::options();
        $schedules = $program ? Interview::schedulesByProgram((int)$program['id']) : [];
        $waiting = $program ? Interview::qualifiedWithoutSchedule((int)$program['id']) : [];
        $noSuggestionReason = null;
        $suggestions = $program ? Interview::suggestSlots((int)$program['id'], 5, $noSuggestionReason) : [];
        render('admin/interviews', compact('program', 'programs', 'schedules', 'waiting', 'suggestions', 'noSuggestionReason'), 'Interviews');
    }

    /** One schedule: assigned applicants with attendance/score actions, and bulk-assign within capacity. */
    public function schedule(): void
    {
        $schedule = Interview::schedule((int)($_GET['id'] ?? 0));
        if (!$schedule) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $assignments = Interview::assignmentsBySchedule((int)$schedule['id']);
        $waiting = Interview::qualifiedWithoutSchedule((int)$schedule['program_id']);
        $free = max(0, (int)$schedule['max_slots'] - (int)$schedule['assigned_count']);
        render('admin/interview_schedule', compact('schedule', 'assignments', 'waiting', 'free'),
            'Interview · ' . date('M d, Y', strtotime($schedule['interview_date'])));
    }

    public function scheduleSave(): void
    {
        $programId = (int)($_POST['program_id'] ?? 0);
        require_post('admin/interviews&program_id=' . $programId);
        $r = Interview::createSchedule($programId, $_POST, admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/interviews&program_id=' . $programId); }
        flash('success', 'Schedule created. Now assign Qualified applicants to it.');
        redirect('admin/interview-schedule&id=' . $r['id']);
    }

    public function scheduleUpdate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        require_post('admin/interview-schedule&id=' . $id);
        $r = Interview::updateSchedule($id, $_POST, admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Schedule updated. Affected applicants were notified of any date, time or venue change.' : $r['error']);
        redirect('admin/interview-schedule&id=' . $id);
    }

    public function scheduleCancel(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        require_post('admin/interview-schedule&id=' . $id);
        $r = Interview::cancelSchedule($id, admin_id());
        flash($r['ok'] ? 'warning' : 'danger', $r['ok'] ? "Schedule cancelled. {$r['count']} applicant(s) are back in the waiting list and were notified." : $r['error']);
        redirect('admin/interview-schedule&id=' . $id);
    }

    public function scheduleDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $sched = Interview::schedule($id);
        require_post('admin/interviews');
        $r = Interview::deleteSchedule($id);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Empty schedule deleted.' : $r['error']);
        redirect($r['ok'] ? 'admin/interviews&program_id=' . (int)($sched['program_id'] ?? 0) : 'admin/interview-schedule&id=' . $id);
    }

    /** Bulk-assign selected Qualified applicants (respects capacity). */
    public function assign(): void
    {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $ids = array_filter(array_map('intval', (array)($_POST['application_ids'] ?? [])));
        if (!$ids) { flash('warning', 'Select at least one applicant.'); redirect('admin/interview-schedule&id=' . $scheduleId); }
        $r = Interview::assign($scheduleId, $ids, admin_id());
        if ($r['assigned']) flash('success', "{$r['assigned']} applicant(s) assigned and notified.");
        if ($r['errors']) {
            flash('danger', 'Not assigned: ' . implode('; ', array_map(static fn($k, $v) => trim("$k $v"), array_keys($r['errors']), $r['errors'])) . '.');
        }
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    public function unassign(): void
    {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $r = Interview::unassign((int)($_POST['assignment_id'] ?? 0), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Applicant removed from this schedule and notified.' : $r['error']);
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    /** B4 No-show (1st: back to waiting list with one reschedule; 2nd: rejected). */
    public function noShow(): void
    {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $r = Interview::markNoShow((int)($_POST['assignment_id'] ?? 0), admin_id());
        if (!$r['ok']) flash('danger', $r['error']);
        else flash('warning', $r['final'] ? 'Second no-show: the application was rejected and the applicant notified.'
                                          : 'No-show recorded. The applicant is back in the waiting list for their one reschedule.');
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    /** B4 Rubric form for an attendee. */
    public function score(): void
    {
        $assignment = Interview::assignment((int)($_GET['id'] ?? 0));
        if (!$assignment) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $evaluation = Interview::evaluation((int)$assignment['id']);
        $editable = $assignment['attendance'] === 'Scheduled'
            || ($assignment['attendance'] === 'Attended' && $assignment['app_status'] === 'Interviewed');
        render('admin/interview_score', compact('assignment', 'evaluation', 'editable'), 'Interview score · ' . $assignment['full_name']);
    }

    public function scoreSave(): void
    {
        $id = (int)($_POST['assignment_id'] ?? 0);
        require_post('admin/interview-score&id=' . $id);
        $r = Interview::saveScore($id, $_POST, (string)($_POST['remarks'] ?? ''), admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/interview-score&id=' . $id); }
        $a = Interview::assignment($id);
        flash('success', "Score saved: {$r['total']} / 100. The applicant is now waiting for the final decision.");
        redirect('admin/interview-schedule&id=' . (int)$a['schedule_id']);
    }

    /** B5 Ranking + slots counter + promote-next-waitlisted prompt. */
    public function decisions(): void
    {
        $program = $this->programFromRequest();
        $programs = Program::options();
        $ranking = $program ? Interview::ranking((int)$program['id']) : [];
        $slots = (int)($program['slots_available'] ?? 0);
        $slotsUsed = $program ? Program::slotsUsed((int)$program['id']) : 0;
        $nextWaitlisted = null;
        foreach ($ranking as $r) {
            if ($r['status'] === 'Waitlisted') { $nextWaitlisted = $r; break; }
        }
        render('admin/decisions', compact('program', 'programs', 'ranking', 'slots', 'slotsUsed', 'nextWaitlisted'), 'Final Decisions');
    }

    /** Approve | Waitlist | Reject (reason). */
    public function decide(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $app = Application::find($id);
        $back = 'admin/decisions&program_id=' . (int)($app['program_id'] ?? 0);
        require_post($back);
        $result = match ($_POST['decision'] ?? '') {
            'approve'  => Application::approve($id, admin_id()),
            'waitlist' => Application::waitlist($id, admin_id()),
            'reject'   => Application::reject($id, admin_id(), (string)($_POST['reason'] ?? '')),
            default    => ['ok' => false, 'error' => 'Unknown decision.'],
        };
        $done = ['approve' => 'Approved — scholar created and Semester 1 opened.', 'waitlist' => 'Waitlisted.', 'reject' => 'Rejected.'];
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $done[$_POST['decision']] . ' The applicant was notified.' : $result['error']);
        redirect($back);
    }
}
