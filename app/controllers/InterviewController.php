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
        $suggestions = $program ? Interview::suggestSlots((int)$program['id'], 5, $noSuggestionReason, false) : [];
        $slotRequests = $program ? SlotRequest::pending((int)$program['id']) : [];
        render('admin/interviews', compact('program', 'programs', 'schedules', 'waiting', 'suggestions', 'noSuggestionReason', 'slotRequests'), 'Interviews');
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

    /* ---------------- Scheduling page with suggestions (§3a) ---------------- */

    /** GET application_id (or id = an interview assignment): suggestions, a free date/time picker, and any slot request. */
    public function reschedule(): void
    {
        $appId = (int)($_GET['application_id'] ?? 0);
        if (!$appId && !empty($_GET['id'])) $appId = (int)(Interview::assignment((int)$_GET['id'])['application_id'] ?? 0);
        $app = $appId ? Application::withDetails($appId) : null;
        if (!$app) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $currentAssignment = Interview::assignmentByApplication($appId);
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM interview_assignments WHERE application_id = ? AND attendance = 'No-show'");
        $stmt->execute([$appId]);
        $missed = (int)$stmt->fetchColumn();
        $why = null;
        $suggestions = Interview::suggestSlots((int)$app['program_id'], 8, $why);
        if (($currentAssignment['attendance'] ?? '') === 'Scheduled') {   // don't suggest the schedule they are already on
            $suggestions = array_values(array_filter($suggestions, static fn($s) => (int)$s['schedule_id'] !== (int)$currentAssignment['schedule_id']));
        }
        $pending = SlotRequest::pendingFor($appId);
        $stmt = Database::conn()->prepare("SELECT venue FROM interview_schedules WHERE program_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int)$app['program_id']]);
        $defaultVenue = (string)($stmt->fetchColumn() ?: '');
        $schedulable = in_array($app['status'], ['Qualified', 'Interview Scheduled'], true)
            && !($app['status'] === 'Interview Scheduled' && ($currentAssignment['attendance'] ?? '') !== 'Scheduled');
        render('admin/interview_reschedule', compact('app', 'currentAssignment', 'missed', 'suggestions', 'why', 'pending', 'defaultVenue', 'schedulable'),
            'Schedule interview · ' . ($app['applicant']['full_name'] ?? ''));
    }

    /** POST application_id + mode: 'schedule' (schedule_id) or 'new' (interview_date, start_time, end_time, venue, max_slots). */
    public function rescheduleSave(): void
    {
        $appId = (int)($_POST['application_id'] ?? 0);
        $back = 'admin/interview-reschedule&application_id=' . $appId;
        require_post($back);
        $r = ($_POST['mode'] ?? '') === 'schedule'
            ? Interview::scheduleApplication($appId, (int)($_POST['schedule_id'] ?? 0), admin_id())
            : Interview::scheduleApplicationAt($appId, $_POST, admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect($back); }
        flash('success', 'Interview scheduled. The applicant was notified.');
        $a = Interview::assignmentByApplication($appId);
        redirect('admin/interview-schedule&id=' . (int)($a['schedule_id'] ?? 0));
    }

    /** POST request_id + decision confirm (venue, max_slots for a new block) | decline (note). */
    public function slotDecide(): void
    {
        $req = SlotRequest::find((int)($_POST['request_id'] ?? 0));
        $back = 'admin/interview-reschedule&application_id=' . (int)($req['application_id'] ?? 0);
        require_post($back);
        if (!$req) { flash('danger', 'Request not found.'); redirect('admin/interviews'); }
        $r = ($_POST['decision'] ?? '') === 'confirm'
            ? SlotRequest::confirm((int)$req['id'], trim((string)($_POST['venue'] ?? '')), max(1, (int)($_POST['max_slots'] ?? 10)), admin_id())
            : SlotRequest::decline((int)$req['id'], (string)($_POST['note'] ?? ''), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok']
            ? (($_POST['decision'] ?? '') === 'confirm' ? 'Slot confirmed — the applicant is scheduled and was notified.' : 'Request declined. The applicant was notified.')
            : $r['error']);
        redirect($back);
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
