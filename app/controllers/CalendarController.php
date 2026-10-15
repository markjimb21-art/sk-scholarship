<?php
declare(strict_types=1);

/**
 * Admin calendar (docs/CHANGE_PLAN_3.md §1): interview schedules and claim/renewal windows on one read-only calendar.
 * Every colour and state comes from the same functions the list pages use: Interview::displayStatus() (Interviews list)
 * and AnalyticsClaims::overdue() → TermSchedule::missedDeadline() (Claims/Renewals → Missed deadline).
 */
final class CalendarController
{
    /** Hex colours: interview statuses mirror the Bootstrap badge colours of Interview::STATUS_COLORS. */
    public const COLORS = [
        'Open' => '#198754', 'Full' => '#ffc107', 'Closed' => '#6c757d', 'Cancelled' => '#dc3545',
        'release' => '#0d6efd', 'renewal' => '#6f42c1',
        'due_soon' => '#fd7e14', 'overdue' => '#dc3545', 'past' => '#6c757d',
    ];
    public const LEGEND = [
        ['Interview — open', 'Open', false], ['Interview — full', 'Full', false], ['Interview — closed', 'Closed', false],
        ['Interview — cancelled', 'Cancelled', false], ['Claim deadline', 'release', false], ['Renewal deadline', 'renewal', false],
        ['Deadline within ' . TERM_SCHEDULE_REMINDER_HOURS . ' hours', 'due_soon', false], ['Overdue (someone missed it)', 'overdue', false],
        ['Planned release / semester start', 'release', true],
    ];

    private function programId(): ?int
    {
        $id = get_int('program_id');
        return $id > 0 && Program::find($id) ? $id : null;
    }

    public function index(): void
    {
        $programs = Program::options();
        $programId = $this->programId();
        $today = date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        render('admin/calendar', compact('programs', 'programId', 'today', 'weekEnd') + [
            'pageScripts' => [asset('vendor/fullcalendar/core.global.min.js'), asset('vendor/fullcalendar/daygrid.global.min.js'),
                            asset('js/pages/calendar.js')],
        ], 'Calendar');
    }

    /** GET start/end (from FullCalendar) + optional program_id → JSON events. */
    public function feed(): void
    {
        $from = substr(get_str('start'), 0, 10);
        $to = substr(get_str('end'), 0, 10);
        foreach ([$from, $to] as $d) {
            $dt = DateTime::createFromFormat('!Y-m-d', $d);
            if (!$dt || $dt->format('Y-m-d') !== $d) json_response(['error' => 'Invalid range.'], 400);
        }
        if ($to < $from || (strtotime($to) - strtotime($from)) > 100 * 86400) json_response(['error' => 'Invalid range.'], 400);
        $programId = $this->programId();

        $events = [];
        // Each staff role sees only what it may open: interviews (reviewer) and claim/renewal windows (treasurer).
        if (AdminController::canAccess('interviews')) {
            foreach (Interview::schedulesInRange($from, $to, $programId) as $s) $events[] = $this->interviewEvent($s);
        }
        if (!AdminController::canAccess('release-schedule')) { header('Cache-Control: private, no-store'); json_response($events); }

        // Windows with at least one scholar in the Missed-deadline list are overdue (one shared function).
        $overdue = [];
        foreach (AnalyticsClaims::overdue($programId) as $m) $overdue[(int)$m['schedule']['id']] = ($overdue[(int)$m['schedule']['id']] ?? 0) + 1;
        foreach (TermSchedule::inRange($from, $to, $programId) as $w) {
            $events[] = $this->deadlineEvent($w, $overdue[(int)$w['id']] ?? 0);
            if ($w['planned_date']) $events[] = $this->plannedEvent($w);
        }
        header('Cache-Control: private, no-store');
        json_response($events);
    }

    private function interviewEvent(array $s): array
    {
        $status = Interview::displayStatus($s);
        $color = self::COLORS[$status];
        return [
            'id' => 'interview-' . (int)$s['id'],
            'title' => 'Interview — ' . $s['program_name'],
            'start' => $s['interview_date'] . 'T' . substr($s['start_time'], 0, 8),
            'end' => $s['interview_date'] . 'T' . substr($s['end_time'], 0, 8),
            'allDay' => false,
            'backgroundColor' => $color, 'borderColor' => $color, 'textColor' => $status === 'Full' ? '#212529' : '#ffffff',
            'classNames' => $status === 'Cancelled' ? ['fc-ev-cancelled'] : [],
            'extendedProps' => [
                'kind' => 'interview', 'date' => $s['interview_date'], 'color' => $color, 'program' => $s['program_name'],
                'when' => Interview::describe($s), 'state' => "$status · {$s['assigned_count']}/{$s['max_slots']} assigned",
                'overdue' => false, 'dueSoon' => false, 'link' => url('admin/interview-schedule&id=' . (int)$s['id']),
            ],
        ];
    }

    /** Deadline event: all-day (on the day before) when the deadline is exactly midnight, otherwise timed (decision F4). */
    private function deadlineEvent(array $w, int $missed): array
    {
        $purpose = $w['purpose'];
        $deadline = strtotime($w['submission_deadline']);
        $now = time();
        $allDay = date('H:i:s', $deadline) === '00:00:00';
        $date = $allDay ? date('Y-m-d', $deadline - 86400) : date('Y-m-d', $deadline);
        $dueSoon = false;
        if ($missed > 0) {
            $color = self::COLORS['overdue'];
            $state = "Overdue — $missed scholar(s) missed it";
        } elseif ($deadline < $now) {
            $color = self::COLORS['past'];
            $state = 'Deadline passed — everyone submitted';
        } elseif ($deadline - $now <= TERM_SCHEDULE_REMINDER_HOURS * 3600) {
            // Same "close" threshold as the deadline reminder in bin/send_reminders.php.
            $color = self::COLORS['due_soon'];
            $state = 'Due within ' . TERM_SCHEDULE_REMINDER_HOURS . ' hours';
            $dueSoon = true;
        } else {
            $color = self::COLORS[$purpose];
            $state = TermSchedule::STATE_LABELS[TermSchedule::state($w)];
        }
        return [
            'id' => 'deadline-' . (int)$w['id'],
            'title' => $w['program_name'] . ' — ' . TermSchedule::TITLES[$purpose] . ' deadline',
            'start' => $allDay ? $date : date('Y-m-d\TH:i:s', $deadline),
            'allDay' => $allDay,
            'backgroundColor' => $color, 'borderColor' => $color, 'textColor' => '#ffffff',
            'classNames' => array_values(array_filter([$dueSoon ? 'fc-ev-due-soon' : null, $missed > 0 ? 'fc-ev-overdue' : null])),
            'extendedProps' => [
                'kind' => $purpose, 'date' => $date, 'color' => $color, 'program' => $w['program_name'],
                'when' => "{$w['academic_year']} {$w['semester']} · deadline " . date('D, M j, Y g:i A', $deadline),
                'state' => $state, 'overdue' => $missed > 0, 'dueSoon' => $dueSoon,
                'link' => url("admin/{$purpose}-schedule&edit=" . (int)$w['id']),
            ],
        ];
    }

    private function plannedEvent(array $w): array
    {
        $purpose = $w['purpose'];
        $color = self::COLORS[$purpose];
        return [
            'id' => 'planned-' . (int)$w['id'],
            'title' => $w['program_name'] . ' — ' . ($purpose === 'release' ? 'planned release' : 'planned semester start'),
            'start' => $w['planned_date'], 'allDay' => true,
            'backgroundColor' => '#ffffff', 'borderColor' => $color, 'textColor' => $color,
            'classNames' => ['fc-ev-planned'],
            'extendedProps' => [
                'kind' => 'planned', 'date' => $w['planned_date'], 'color' => $color, 'program' => $w['program_name'],
                'when' => "{$w['academic_year']} {$w['semester']} · " . date('D, M j, Y', strtotime($w['planned_date'])),
                'state' => 'Planned date (information only)', 'overdue' => false, 'dueSoon' => false, 'outlined' => true,
                'link' => url("admin/{$purpose}-schedule&edit=" . (int)$w['id']),
            ],
        ];
    }
}
