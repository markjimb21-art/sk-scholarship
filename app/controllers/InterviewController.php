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
        // The New-schedule form suggests free blocks only; existing schedules with room are listed beside it.
        $suggestions = $program ? InterviewSlots::suggestSlots((int)$program['id'], 5, $noSuggestionReason, false) : [];
        $slotRequests = $program ? SlotRequest::pending((int)$program['id']) : [];
        render('admin/interviews', compact('program', 'programs', 'schedules', 'waiting', 'suggestions', 'noSuggestionReason', 'slotRequests') + ['pageScripts' => [asset('js/pages/interviews.js')]], 'Interviews');
    }

    /** One schedule: assigned applicants with attendance/score actions, and bulk-assign within capacity. */
    public function schedule(): void
    {
        $schedule = Interview::schedule(get_int('id'));
        if (!$schedule) not_found();
        $assignments = Interview::assignmentsBySchedule((int)$schedule['id']);
        $waiting = Interview::qualifiedWithoutSchedule((int)$schedule['program_id']);
        $free = max(0, (int)$schedule['max_slots'] - (int)$schedule['assigned_count']);
        render('admin/interview_schedule', compact('schedule', 'assignments', 'waiting', 'free') + ['pageScripts' => [asset('js/pages/interview-schedule.js')]],
            'Interview · ' . fmt_date($schedule['interview_date']));
    }

    public function scheduleSave(): void
    {
        $programId = post_int('program_id');
        require_post('admin/interviews&program_id=' . $programId);
        $r = Interview::createSchedule($programId, $_POST, admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/interviews&program_id=' . $programId); }
        flash('success', 'Schedule created. Now assign Qualified applicants to it.');
        redirect('admin/interview-schedule&id=' . $r['id']);
    }

    public function scheduleUpdate(): void
    {
        $id = post_int('id');
        require_post('admin/interview-schedule&id=' . $id);
        $r = Interview::updateSchedule($id, $_POST, admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Schedule updated. Affected applicants were notified of any date, time or venue change.' : $r['error']);
        redirect('admin/interview-schedule&id=' . $id);
    }

    public function scheduleCancel(): void
    {
        $id = post_int('id');
        require_post('admin/interview-schedule&id=' . $id);
        $r = Interview::cancelSchedule($id, admin_id());
        flash($r['ok'] ? 'warning' : 'danger', $r['ok'] ? "Schedule cancelled. {$r['count']} applicant(s) are back in the waiting list and were notified." : $r['error']);
        redirect('admin/interview-schedule&id=' . $id);
    }

    public function scheduleDelete(): void
    {
        $id = post_int('id');
        $sched = Interview::schedule($id);
        require_post('admin/interviews');
        $r = Interview::deleteSchedule($id);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Empty schedule deleted.' : $r['error']);
        redirect($r['ok'] ? 'admin/interviews&program_id=' . (int)($sched['program_id'] ?? 0) : 'admin/interview-schedule&id=' . $id);
    }

    /** Bulk-assign selected Qualified applicants (respects capacity). */
    public function assign(): void
    {
        $scheduleId = post_int('schedule_id');
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $ids = array_filter(array_map('intval', (array)($_POST['application_ids'] ?? [])));
        if (!$ids) { flash('warning', 'Select at least one applicant.'); redirect('admin/interview-schedule&id=' . $scheduleId); }
        $r = InterviewAssignment::assign($scheduleId, $ids, admin_id());
        if ($r['assigned']) flash('success', "{$r['assigned']} applicant(s) assigned and notified.");
        if ($r['errors']) {
            flash('danger', 'Not assigned: ' . implode('; ', array_map(static fn($k, $v) => trim("$k $v"), array_keys($r['errors']), $r['errors'])) . '.');
        }
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    public function unassign(): void
    {
        $scheduleId = post_int('schedule_id');
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $r = InterviewAssignment::unassign(post_int('assignment_id'), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Applicant removed from this schedule and notified.' : $r['error']);
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    /* ---------------- Scheduling page with suggestions (§3a) ---------------- */

    /** GET application_id (or id = an interview assignment): suggestions, a free date/time picker, and any slot request. */
    public function reschedule(): void
    {
        $appId = get_int('application_id');
        if (!$appId && !empty($_GET['id'])) $appId = (int)(InterviewAssignment::assignment((int)$_GET['id'])['application_id'] ?? 0);
        $app = $appId ? Application::withDetails($appId) : null;
        if (!$app) not_found();
        $currentAssignment = InterviewAssignment::assignmentByApplication($appId);
        $missed = InterviewAssignment::missedCount($appId);
        $why = null;
        $suggestions = InterviewSlots::suggestSlots((int)$app['program_id'], 8, $why);
        if (($currentAssignment['attendance'] ?? '') === 'Scheduled') {   // don't suggest the schedule they are already on
            $suggestions = array_values(array_filter($suggestions, static fn($s) => (int)$s['schedule_id'] !== (int)$currentAssignment['schedule_id']));
        }
        $pending = SlotRequest::pendingFor($appId);
        $defaultVenue = Interview::lastVenue((int)$app['program_id']);
        $schedulable = in_array($app['status'], ['Qualified', 'Interview Scheduled'], true)
            && !($app['status'] === 'Interview Scheduled' && ($currentAssignment['attendance'] ?? '') !== 'Scheduled');
        render('admin/interview_reschedule', compact('app', 'currentAssignment', 'missed', 'suggestions', 'why', 'pending', 'defaultVenue', 'schedulable') + ['pageScripts' => [asset('js/pages/interview-reschedule.js')]],
            'Schedule interview · ' . ($app['applicant']['full_name'] ?? ''));
    }

    /** POST application_id + mode: 'schedule' (schedule_id) or 'new' (interview_date, start_time, end_time, venue, max_slots). */
    public function rescheduleSave(): void
    {
        $appId = post_int('application_id');
        $back = 'admin/interview-reschedule&application_id=' . $appId;
        require_post($back);
        $r = ($_POST['mode'] ?? '') === 'schedule'
            ? InterviewAssignment::scheduleApplication($appId, post_int('schedule_id'), admin_id())
            : InterviewAssignment::scheduleApplicationAt($appId, $_POST, admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect($back); }
        flash('success', 'Interview scheduled. The applicant was notified.');
        $a = InterviewAssignment::assignmentByApplication($appId);
        redirect('admin/interview-schedule&id=' . (int)($a['schedule_id'] ?? 0));
    }

    /** POST request_id + decision confirm (venue, max_slots for a new block) | decline (note). */
    public function slotDecide(): void
    {
        $req = SlotRequest::find(post_int('request_id'));
        $back = 'admin/interview-reschedule&application_id=' . (int)($req['application_id'] ?? 0);
        require_post($back);
        if (!$req) { flash('danger', 'Request not found.'); redirect('admin/interviews'); }
        $r = ($_POST['decision'] ?? '') === 'confirm'
            ? SlotRequest::confirm((int)$req['id'], trim(post_str('venue')), max(1, (int)($_POST['max_slots'] ?? 10)), admin_id())
            : SlotRequest::decline((int)$req['id'], post_str('note'), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok']
            ? (($_POST['decision'] ?? '') === 'confirm' ? 'Slot confirmed — the applicant is scheduled and was notified.' : 'Request declined. The applicant was notified.')
            : $r['error']);
        redirect($back);
    }

    /** B4 No-show (1st: back to waiting list with one reschedule; 2nd: rejected). */
    public function noShow(): void
    {
        $scheduleId = post_int('schedule_id');
        require_post('admin/interview-schedule&id=' . $scheduleId);
        $r = InterviewAssignment::markNoShow(post_int('assignment_id'), admin_id());
        if (!$r['ok']) flash('danger', $r['error']);
        else flash('warning', $r['final'] ? 'Second no-show: the application was rejected and the applicant notified.'
                                          : 'No-show recorded. The applicant is back in the waiting list for their one reschedule.');
        redirect('admin/interview-schedule&id=' . $scheduleId);
    }

    /** B4 Rubric form for an attendee. */
    public function score(): void
    {
        $assignment = InterviewAssignment::assignment(get_int('id'));
        if (!$assignment) not_found();
        $evaluation = InterviewScore::evaluation((int)$assignment['id']);
        $editable = $assignment['attendance'] === 'Scheduled'
            || ($assignment['attendance'] === 'Attended' && $assignment['app_status'] === 'Interviewed');
        render('admin/interview_score', compact('assignment', 'evaluation', 'editable') + ['pageScripts' => [asset('js/pages/interview-score.js')]], 'Interview score · ' . $assignment['full_name']);
    }

    public function scoreSave(): void
    {
        $id = post_int('assignment_id');
        require_post('admin/interview-score&id=' . $id);
        $r = InterviewScore::saveScore($id, $_POST, post_str('remarks'), admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/interview-score&id=' . $id); }
        $a = InterviewAssignment::assignment($id);
        flash('success', "Score saved: {$r['total']} / 100. The applicant is now waiting for the final decision.");
        redirect('admin/interview-schedule&id=' . (int)$a['schedule_id']);
    }

    /** B5 Ranking + seats (returning, pending renewals, new, free — Program::seats) + promote-next-waitlisted prompt. */
    public function decisions(): void
    {
        $program = $this->programFromRequest();
        $programs = Program::options();
        $ranking = $program ? InterviewScore::ranking((int)$program['id']) : [];
        $seats = $program ? Program::seats((int)$program['id']) : null;
        $nextWaitlisted = null;
        foreach ($ranking as $r) {
            if ($r['status'] === 'Waitlisted') { $nextWaitlisted = $r; break; }
        }
        render('admin/decisions', compact('program', 'programs', 'ranking', 'seats', 'nextWaitlisted') + ['pageScripts' => [asset('js/pages/decisions.js')]], 'Final Decisions');
    }

    /** Approve | Waitlist | Reject (reason). */
    public function decide(): void
    {
        $id = post_int('id');
        $app = Application::find($id);
        $back = 'admin/decisions&program_id=' . (int)($app['program_id'] ?? 0);
        require_post($back);
        $result = match ($_POST['decision'] ?? '') {
            'approve'  => Application::approve($id, admin_id()),
            'waitlist' => Application::waitlist($id, admin_id()),
            'reject'   => Application::reject($id, admin_id(), post_str('reason')),
            default    => ['ok' => false, 'error' => 'Unknown decision.'],
        };
        $done = ['approve' => 'Approved — scholar created and Semester 1 opened.', 'waitlist' => 'Waitlisted.', 'reject' => 'Rejected.'];
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $done[$_POST['decision']] . ' The applicant was notified.' : $result['error']);
        redirect($back);
    }
}
