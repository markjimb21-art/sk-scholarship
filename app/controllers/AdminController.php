<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/ApplicationEligibility.php';
require_once BASE_PATH . '/app/models/ApplicationSearch.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/DocumentStorage.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/InterviewSlots.php';
require_once BASE_PATH . '/app/models/InterviewAssignment.php';
require_once BASE_PATH . '/app/models/InterviewScore.php';
require_once BASE_PATH . '/app/models/Program.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Term.php';
require_once BASE_PATH . '/app/models/TermDocument.php';
require_once BASE_PATH . '/app/models/Renewal.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/TermSchedule.php';
require_once BASE_PATH . '/app/models/WorkQueues.php';
require_once BASE_PATH . '/app/models/AnalyticsFunnel.php';
require_once BASE_PATH . '/app/models/AnalyticsBudget.php';
require_once BASE_PATH . '/app/models/AnalyticsInterviews.php';
require_once BASE_PATH . '/app/models/AnalyticsClaims.php';
require_once BASE_PATH . '/app/models/AnalyticsProfiles.php';
require_once BASE_PATH . '/app/models/SlotRequest.php';
require_once BASE_PATH . '/app/models/RecordHistory.php';
require_once BASE_PATH . '/app/models/User.php';
require_once BASE_PATH . '/app/models/Setting.php';
require_once BASE_PATH . '/app/models/Holiday.php';

/**
 * Admin area router + the shared admin pages. Only the admin role reaches this (public/index.php).
 * Each process step has its own controller; see docs/PROCESS.md for the step ↔ route map.
 */
final class AdminController
{
    /** Who may open what (docs/PROCESS.md "Roles"). The admin role can open everything. */
    private const STAFF  = ['admin', 'reviewer', 'treasurer'];   // every staff role
    private const REVIEW = ['admin', 'reviewer'];                // applications: screening, interviews, decisions
    private const FUNDS  = ['admin', 'treasurer'];               // budget, claims, releases, renewals, scholars
    private const ADMIN  = ['admin'];                            // programs, users, settings, reports, analytics, audit

    /** route => [controller, method, roles]. DEFAULT DENY: a route not listed, or not allowed for the role, is a 404. */
    private const ROUTES = [
        // Dashboard, records, account pages
        'dashboard'                 => ['AdminController', 'dashboard', self::STAFF],
        'notifications'             => ['AdminController', 'notifications', self::STAFF],
        'applications'              => ['AdminController', 'applications', self::REVIEW],
        // Calendar: interviews + claim/renewal windows (read-only)
        'calendar'                  => ['CalendarController', 'index', self::STAFF],
        'calendar-feed'             => ['CalendarController', 'feed', self::STAFF],
        'application'               => ['ScreeningController', 'view', self::REVIEW],
        'documents/view'            => ['ScreeningController', 'document', self::REVIEW],
        'documents/invalidate'      => ['ScreeningController', 'invalidateDocument', self::STAFF],
        // B1 programs
        'programs'                  => ['AdminController', 'programs', self::ADMIN],
        'program-save'              => ['AdminController', 'programSave', self::ADMIN],
        'program-toggle'            => ['AdminController', 'programToggle', self::ADMIN],
        // B2 screening
        'screening'                 => ['ScreeningController', 'queue', self::REVIEW],
        'screening/decide'          => ['ScreeningController', 'decide', self::REVIEW],
        // B3–B4 interviews
        'interviews'                => ['InterviewController', 'index', self::REVIEW],
        'interview-schedule'        => ['InterviewController', 'schedule', self::REVIEW],
        'interview-schedule-save'   => ['InterviewController', 'scheduleSave', self::REVIEW],
        'interview-schedule-update' => ['InterviewController', 'scheduleUpdate', self::REVIEW],
        'interview-schedule-cancel' => ['InterviewController', 'scheduleCancel', self::REVIEW],
        'interview-schedule-delete' => ['InterviewController', 'scheduleDelete', self::REVIEW],
        'interview-assign'          => ['InterviewController', 'assign', self::REVIEW],
        'interview-unassign'        => ['InterviewController', 'unassign', self::REVIEW],
        'interview-no-show'         => ['InterviewController', 'noShow', self::REVIEW],
        'interview-reschedule'      => ['InterviewController', 'reschedule', self::REVIEW],
        'interview-reschedule-save' => ['InterviewController', 'rescheduleSave', self::REVIEW],
        'slot-request-decide'       => ['InterviewController', 'slotDecide', self::REVIEW],
        'interview-score'           => ['InterviewController', 'score', self::REVIEW],
        'interview-score-save'      => ['InterviewController', 'scoreSave', self::REVIEW],
        // B5 decisions
        'decisions'                 => ['InterviewController', 'decisions', self::REVIEW],
        'decisions/decide'          => ['InterviewController', 'decide', self::REVIEW],
        // B6 budget
        'budget'                    => ['FundsController', 'budget', self::FUNDS],
        'budget-save'               => ['FundsController', 'budgetSave', self::FUNDS],
        'budget-delete'             => ['FundsController', 'budgetDelete', self::FUNDS],
        // B7 claims
        'claims'                    => ['FundsController', 'claims', self::FUNDS],
        'claims/view'               => ['FundsController', 'claimView', self::FUNDS],
        'claims/decide'             => ['FundsController', 'claimDecide', self::FUNDS],
        'term-documents/view'       => ['FundsController', 'termDocument', self::FUNDS],
        // B8 releases
        'releases'                  => ['FundsController', 'releases', self::FUNDS],
        'release-save'              => ['FundsController', 'releaseSave', self::FUNDS],
        // B9 renewals
        'renewals'                  => ['FundsController', 'renewals', self::FUNDS],
        'renewals/view'             => ['FundsController', 'renewalView', self::FUNDS],
        'renewals/decide'           => ['FundsController', 'renewalDecide', self::FUNDS],
        // Claim (release) and renewal submission windows
        'release-schedule'          => ['FundsController', 'releaseSchedule', self::FUNDS],
        'renewal-schedule'          => ['FundsController', 'renewalSchedule', self::FUNDS],
        'term-schedule-save'        => ['FundsController', 'termScheduleSave', self::FUNDS],
        // B10 scholars
        'scholars'                  => ['FundsController', 'scholars', self::FUNDS],
        'scholar'                   => ['FundsController', 'scholar', self::FUNDS],
        'scholar-end'               => ['FundsController', 'scholarEnd', self::FUNDS],
        // Reports and analytics
        'reports'                   => ['ReportController', 'reports', self::ADMIN],
        'reports/generate'          => ['ReportController', 'generate', self::ADMIN],
        'analytics'                 => ['ReportController', 'analytics', self::ADMIN],
        // Accounts, settings, audit
        'users'                     => ['AdminController', 'users', self::ADMIN],
        'user-save'                 => ['AdminController', 'userSave', self::ADMIN],
        'user-toggle'               => ['AdminController', 'userToggle', self::ADMIN],
        'user-reset'                => ['AdminController', 'userReset', self::ADMIN],
        'settings'                  => ['AdminController', 'settings', self::ADMIN],
        'settings/save'             => ['AdminController', 'settingsSave', self::ADMIN],
        'settings/test-email'       => ['AdminController', 'settingsTestEmail', self::ADMIN],
        'settings/holiday-save'     => ['AdminController', 'holidaySave', self::ADMIN],
        'settings/holiday-delete'   => ['AdminController', 'holidayDelete', self::ADMIN],
        'audit-logs'                => ['AdminController', 'auditLogs', self::ADMIN],
    ];

    /** Staff roles that reach the admin area at all (each route then checks its own list). */
    public const STAFF_ROLES = self::STAFF;

    /** May the logged-in user (or $role) open this admin route (without the "admin/" prefix)? Used by handle(), the sidebar and pages. */
    public static function canAccess(string $route, ?string $role = null): bool
    {
        $role ??= Auth::role();
        $entry = self::ROUTES[trim($route, '/')] ?? null;
        return $entry !== null && in_array($role, $entry[2], true);
    }

    public function handle(string $action): void
    {
        Auth::requireRole(...self::STAFF);
        $route = self::ROUTES[trim($action, '/')] ?? null;
        if ($route === null || !in_array(Auth::role(), $route[2], true)) {
            http_response_code(404);   // same answer for "no such page" and "not for your role"
            require VIEW_PATH . '/errors/404.php';
            return;
        }
        [$class, $method] = $route;
        if ($class !== self::class) require_once BASE_PATH . "/app/controllers/$class.php";
        $controller = $class === self::class ? $this : new $class();
        $controller->$method();
    }

    /* ================= DASHBOARD ================= */

    /** "Needs your action" counters (each links to its queue) + slots and budget of the selected program. */
    public function dashboard(): void
    {
        $today = date('Y-m-d');
        $n = WorkQueues::queueCounts($today);
        $counters = [
            ['Applications to screen', $n['to_screen'], 'admin/screening', 'bi-inbox', 'Applications with status Submitted'],
            ['Qualified, no schedule', $n['qualified'], 'admin/interviews', 'bi-calendar-plus', 'Applications with status Qualified'],
            ['Interviews today', $n['interviews_today'], 'admin/interviews', 'bi-people', 'Applicants still marked Scheduled on a schedule dated today'],
            ['Slot requests to confirm', $n['slot_requests'], 'admin/interviews', 'bi-hand-index', 'Interview slots applicants picked, waiting for your confirmation'],
            ['Awaiting a decision', $n['to_decide'], 'admin/decisions', 'bi-clipboard-check', 'Applications with status Interviewed'],
            ['Claims to verify', $n['claims_to_verify'], 'admin/claims', 'bi-file-earmark-check', 'Semester claims with status Submitted'],
            ['Verified, to release', $n['to_release'], 'admin/releases', 'bi-cash-coin', 'Semester claims with status Verified'],
            ['Renewals to review', $n['renewals'], 'admin/renewals', 'bi-arrow-repeat', 'Renewal requests with status Submitted'],
        ];

        // Only the queues this staff role may open.
        $counters = array_values(array_filter($counters, static fn($c) => self::canAccess(substr($c[2], 6))));

        $program = Program::selected(!empty($_GET['program_id']) ? (int)$_GET['program_id'] : null);
        $programs = Program::options();
        $slotsUsed = $program ? Program::slotsUsed((int)$program['id']) : 0;
        $budget = $program ? Budget::forProgramYear((int)$program['id'], $program['academic_year']) : null;
        $todayInterviews = self::canAccess('interviews') ? Interview::day($today) : null;
        $showBudget = self::canAccess('budget');
        if (!$showBudget) $budget = null;

        // Charts for the selected program (numbers shown in a table next to each chart).
        $charts = $program ? [
            'status'  => AnalyticsFunnel::stageCounts((int)$program['id']),
            'months'  => AnalyticsBudget::releasedByMonth((int)$program['id']),
            'gwa'     => AnalyticsProfiles::scholarsByGwaBand((int)$program['id']),
        ] : null;

        render('admin/dashboard', compact('counters', 'program', 'programs', 'slotsUsed', 'budget', 'todayInterviews', 'charts', 'showBudget')
            + ['pageScripts' => [asset('js/sk-charts.js')]], 'Dashboard');
    }

    public function notifications(): void
    {
        $userId = admin_id();
        if (!empty($_GET['mark_read'])) Notification::markAllRead($userId);
        render('applicant/notifications', ['notifications' => Notification::forUser($userId)], 'Notifications');
    }

    /** Read-only list of every application (the database of applications), filterable. */
    public function applications(): void
    {
        $filters = [
            'q'          => trim((string)($_GET['q'] ?? '')),
            'status'     => (string)($_GET['status'] ?? ''),
            'program_id' => (string)($_GET['program_id'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $rows = ApplicationSearch::search($filters, $limit, ($page - 1) * $limit);
        $total = ApplicationSearch::count($filters);
        $pages = max(1, (int)ceil($total / $limit));
        $programs = Program::options();
        render('admin/applications', compact('filters', 'page', 'rows', 'total', 'pages', 'programs'), 'Applications');
    }

    /* ================= B1 PROGRAMS ================= */

    public function programs(): void
    {
        $programs = Program::all();
        foreach ($programs as &$p) $p['slots_used'] = Program::slotsUsed((int)$p['id']);
        unset($p);
        $openProgram = Program::open();
        render('admin/programs', compact('programs', 'openProgram'), 'Scholarship Programs');
    }

    public function programSave(): void
    {
        require_post('admin/programs');
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $result = Program::save($_POST, $id, admin_id());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? ($id ? 'Program updated.' : 'Program created.') : $result['error']);
        redirect('admin/programs');
    }

    public function programToggle(): void
    {
        require_post('admin/programs');
        $state = Program::toggle((int)($_POST['id'] ?? 0));
        if ($state === null) flash('danger', 'Program not found.');
        else flash('success', $state ? 'Program opened. Applicants can apply while today is inside its dates.' : 'Program closed.');
        redirect('admin/programs');
    }

    /* ================= ACCOUNTS ================= */

    public function users(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $role = in_array($_GET['role'] ?? '', [...self::STAFF, 'applicant'], true) ? $_GET['role'] : '';
        $users = User::search($q, $role);
        render('admin/users', compact('users', 'q', 'role'), 'Users');
    }

    /** Create or edit a STAFF account (admin, reviewer, treasurer); applicants register themselves. Role changes are audit-logged. */
    public function userSave(): void
    {
        require_post('admin/users');
        $id = (int)($_POST['id'] ?? 0);
        $roleName = (string)($_POST['role'] ?? '');
        if (!in_array($roleName, self::STAFF, true)) { flash('danger', 'Choose a role: admin, reviewer or treasurer.'); redirect('admin/users'); }
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $contact = digits_only($_POST['contact_number'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Name and a valid e-mail are required.'); redirect('admin/users'); }
        if (User::emailTaken($email, $id)) { flash('danger', 'That e-mail is already used.'); redirect('admin/users'); }

        if ($id > 0) {
            $oldRole = User::roleName($id);
            if (!in_array($oldRole, self::STAFF, true)) { flash('danger', 'Only staff accounts are managed here.'); redirect('admin/users'); }
            if ($oldRole !== $roleName) {
                if ($id === admin_id()) { flash('danger', 'You cannot change your own role.'); redirect('admin/users'); }
                if ($oldRole === 'admin' && User::otherActiveAdmins($id) === 0) { flash('danger', 'This is the last active admin — make someone else admin first.'); redirect('admin/users'); }
            }
            User::updateStaff($id, $name, $email, $contact, $roleName);
            AuditLog::write('user_updated', 'users', $id, "Updated account $email");
            if ($oldRole !== $roleName) {
                AuditLog::write('user_role_changed', 'users', $id, "Role of $email changed from $oldRole to $roleName", ['role' => $oldRole], ['role' => $roleName, 'by' => admin_id()]);
            }
            flash('success', $oldRole !== $roleName ? "Account updated; role changed to $roleName." : 'Account updated.');
        } else {
            if ($problems = password_problems($password)) { flash('danger', password_rule_message($problems)); redirect('admin/users'); }
            if ($password !== (string)($_POST['password_confirm'] ?? '')) { flash('danger', 'The two passwords do not match.'); redirect('admin/users'); }
            $newId = User::createStaff($roleName, $name, $email, $password, $contact);
            AuditLog::write('user_created', 'users', $newId, "Created $roleName account $email", null, ['role' => $roleName, 'by' => admin_id()]);
            flash('success', ucfirst($roleName) . ' account created.');
        }
        redirect('admin/users');
    }

    public function userToggle(): void
    {
        require_post('admin/users');
        $id = (int)($_POST['id'] ?? 0);
        if ($id === admin_id()) { flash('danger', 'You cannot deactivate your own account.'); redirect('admin/users'); }
        $row = User::status($id);
        if (!$row) { flash('danger', 'User not found.'); redirect('admin/users'); }
        if ($row['is_active'] && $row['role_name'] === 'admin' && User::otherActiveAdmins($id) === 0) {
            flash('danger', 'This is the last active admin, so it can’t be deactivated.'); redirect('admin/users');
        }
        $new = $row['is_active'] ? 0 : 1;
        User::setActive($id, $new);
        AuditLog::write('user_status_changed', 'users', $id, ($new ? 'Activated' : 'Deactivated') . " {$row['email']}");
        flash('success', $new ? 'Account activated.' : 'Account deactivated.');
        redirect('admin/users');
    }

    public function userReset(): void
    {
        require_post('admin/users');
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['new_password'] ?? '');
        if ($problems = password_problems($password)) { flash('danger', password_rule_message($problems)); redirect('admin/users'); }
        if ($password !== (string)($_POST['new_password_confirm'] ?? '')) { flash('danger', 'The two passwords do not match.'); redirect('admin/users'); }
        User::resetPassword($id, $password);
        AuditLog::write('user_password_reset', 'users', $id, 'Admin reset the password');
        flash('success', 'Password reset.');
        redirect('admin/users');
    }

    /* ================= SETTINGS ================= */

    /** Only settings that do something: e-mail on/off, reminder hours, Send Test Email. */
    public function settings(): void
    {
        $rows = Setting::all();
        $settings = [
            'email_notifications'      => (string)($rows['email_notifications'] ?? '1'),
            'interview_reminder_hours' => (string)($rows['interview_reminder_hours'] ?? '24'),
            'daily_digest_enabled'     => (string)($rows['daily_digest_enabled'] ?? '1'),
            'daily_digest_sent_on'     => (string)($rows['daily_digest_sent_on'] ?? ''),
            'admin_event_emails'       => (string)($rows['admin_event_emails'] ?? '0'),
            'interview_suggestion_window_days' => (string)InterviewSlots::suggestionWindowDays(),
        ];
        $mailStatus = Notification::mailConfigStatus();
        $holidays = Holiday::recent();
        render('admin/settings', compact('settings', 'mailStatus', 'holidays'), 'Settings');
    }

    public function settingsSave(): void
    {
        require_post('admin/settings');
        $values = [
            'email_notifications'      => !empty($_POST['email_notifications']) ? '1' : '0',
            'interview_reminder_hours' => (string)max(0, min(168, (int)($_POST['interview_reminder_hours'] ?? 24))),
            'daily_digest_enabled'     => !empty($_POST['daily_digest_enabled']) ? '1' : '0',
            'admin_event_emails'       => !empty($_POST['admin_event_emails']) ? '1' : '0',
            'interview_suggestion_window_days' => (string)max(7, min(180, (int)($_POST['interview_suggestion_window_days'] ?? 60))),
        ];
        Setting::saveMany($values);
        AuditLog::write('settings_updated', 'system_settings', null, 'Settings saved', null, $values);
        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }

    /** Add a holiday (never suggested for interviews). */
    public function holidaySave(): void
    {
        require_post('admin/settings');
        $date = trim((string)($_POST['holiday_date'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) { flash('danger', 'Choose a valid date.'); redirect('admin/settings'); }
        if ($name === '') { flash('danger', 'Give the holiday a name.'); redirect('admin/settings'); }
        $holidayId = Holiday::add($date, $name, admin_id());
        if ($holidayId === null) { flash('danger', 'That date is already a holiday.'); redirect('admin/settings'); }
        AuditLog::write('holiday_added', 'holidays', $holidayId, "Holiday $date: $name");
        flash('success', 'Holiday added. Interview suggestions skip it.');
        redirect('admin/settings');
    }

    /** Remove a holiday (a setting, not a record — see "Data retention" in docs/PROCESS.md). */
    public function holidayDelete(): void
    {
        require_post('admin/settings');
        $id = (int)($_POST['id'] ?? 0);
        $h = Holiday::find($id);
        if ($h) {
            Holiday::delete($id);
            AuditLog::write('holiday_removed', 'holidays', $id, "Removed holiday {$h['holiday_date']}: {$h['name']}", $h);
        }
        flash('success', 'Holiday removed.');
        redirect('admin/settings');
    }

    /** Sends a fixed test message. Never echoes SMTP credentials. */
    public function settingsTestEmail(): void
    {
        require_post('admin/settings');
        $to = strtolower(trim((string)($_POST['test_email'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Enter a valid e-mail address to send the test to.'); redirect('admin/settings'); }
        $result = Notification::sendTestEmail($to);
        AuditLog::write($result['ok'] ? 'test_email_sent' : 'test_email_failed', 'system_settings', null,
            $result['ok'] ? 'Test e-mail sent' : 'Test e-mail failed');
        flash($result['ok'] ? 'success' : 'danger', $result['ok']
            ? 'Test e-mail sent.'
            : 'The test e-mail could not be sent.' . (APP_DEBUG ? ' Check storage/logs/app.log for the SMTP error.' : ''));
        redirect('admin/settings');
    }

    /* ================= AUDIT LOGS ================= */

    public function auditLogs(): void
    {
        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'action'      => (string)($_GET['action'] ?? ''),
            'entity_type' => (string)($_GET['entity_type'] ?? ''),
            'user_id'     => (string)($_GET['user_id'] ?? ''),
            'date_from'   => (string)($_GET['date_from'] ?? ''),
            'date_to'     => (string)($_GET['date_to'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $logs = AuditLog::search($filters, $limit, ($page - 1) * $limit);
        $total = AuditLog::count($filters);
        $pages = max(1, (int)ceil($total / $limit));

        [$actions, $entityTypes] = AuditLog::filterOptions();
        $users = User::options();
        render('admin/audit_Logs', compact('filters', 'logs', 'total', 'page', 'pages', 'actions', 'entityTypes', 'users'), 'Audit Logs');
    }
}
