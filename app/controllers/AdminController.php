<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Program.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Term.php';
require_once BASE_PATH . '/app/models/Renewal.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/TermSchedule.php';
require_once BASE_PATH . '/app/models/Analytics.php';
require_once BASE_PATH . '/app/models/SlotRequest.php';

/**
 * Admin area router + the shared admin pages. Only the admin role reaches this (public/index.php).
 * Each process step has its own controller; see docs/PROCESS.md for the step ↔ route map.
 */
final class AdminController
{
    /** route => [controller, method]. DEFAULT DENY: anything not listed here is a 404. */
    private const ROUTES = [
        // Dashboard, records, account pages
        'dashboard'                 => ['AdminController', 'dashboard'],
        'notifications'             => ['AdminController', 'notifications'],
        'applications'              => ['AdminController', 'applications'],
        // Calendar: interviews + claim/renewal windows (read-only)
        'calendar'                  => ['CalendarController', 'index'],
        'calendar-feed'             => ['CalendarController', 'feed'],
        'application'               => ['ScreeningController', 'view'],
        'documents/view'            => ['ScreeningController', 'document'],
        // B1 programs
        'programs'                  => ['AdminController', 'programs'],
        'program-save'              => ['AdminController', 'programSave'],
        'program-toggle'            => ['AdminController', 'programToggle'],
        // B2 screening
        'screening'                 => ['ScreeningController', 'queue'],
        'screening/decide'          => ['ScreeningController', 'decide'],
        // B3–B4 interviews
        'interviews'                => ['InterviewController', 'index'],
        'interview-schedule'        => ['InterviewController', 'schedule'],
        'interview-schedule-save'   => ['InterviewController', 'scheduleSave'],
        'interview-schedule-update' => ['InterviewController', 'scheduleUpdate'],
        'interview-schedule-cancel' => ['InterviewController', 'scheduleCancel'],
        'interview-schedule-delete' => ['InterviewController', 'scheduleDelete'],
        'interview-assign'          => ['InterviewController', 'assign'],
        'interview-unassign'        => ['InterviewController', 'unassign'],
        'interview-no-show'         => ['InterviewController', 'noShow'],
        'interview-reschedule'      => ['InterviewController', 'reschedule'],
        'interview-reschedule-save' => ['InterviewController', 'rescheduleSave'],
        'slot-request-decide'       => ['InterviewController', 'slotDecide'],
        'interview-score'           => ['InterviewController', 'score'],
        'interview-score-save'      => ['InterviewController', 'scoreSave'],
        // B5 decisions
        'decisions'                 => ['InterviewController', 'decisions'],
        'decisions/decide'          => ['InterviewController', 'decide'],
        // B6 budget
        'budget'                    => ['FundsController', 'budget'],
        'budget-save'               => ['FundsController', 'budgetSave'],
        'budget-delete'             => ['FundsController', 'budgetDelete'],
        // B7 claims
        'claims'                    => ['FundsController', 'claims'],
        'claims/view'               => ['FundsController', 'claimView'],
        'claims/decide'             => ['FundsController', 'claimDecide'],
        'term-documents/view'       => ['FundsController', 'termDocument'],
        // B8 releases
        'releases'                  => ['FundsController', 'releases'],
        'release-save'              => ['FundsController', 'releaseSave'],
        // B9 renewals
        'renewals'                  => ['FundsController', 'renewals'],
        'renewals/view'             => ['FundsController', 'renewalView'],
        'renewals/decide'           => ['FundsController', 'renewalDecide'],
        // Claim (release) and renewal submission windows
        'release-schedule'          => ['FundsController', 'releaseSchedule'],
        'renewal-schedule'          => ['FundsController', 'renewalSchedule'],
        'term-schedule-save'        => ['FundsController', 'termScheduleSave'],
        // B10 scholars
        'scholars'                  => ['FundsController', 'scholars'],
        'scholar'                   => ['FundsController', 'scholar'],
        'scholar-end'               => ['FundsController', 'scholarEnd'],
        // Reports and analytics
        'reports'                   => ['ReportController', 'reports'],
        'reports/generate'          => ['ReportController', 'generate'],
        'analytics'                 => ['ReportController', 'analytics'],
        // Accounts, settings, audit
        'users'                     => ['AdminController', 'users'],
        'user-save'                 => ['AdminController', 'userSave'],
        'user-toggle'               => ['AdminController', 'userToggle'],
        'user-reset'                => ['AdminController', 'userReset'],
        'settings'                  => ['AdminController', 'settings'],
        'settings/save'             => ['AdminController', 'settingsSave'],
        'settings/test-email'       => ['AdminController', 'settingsTestEmail'],
        'settings/holiday-save'     => ['AdminController', 'holidaySave'],
        'settings/holiday-delete'   => ['AdminController', 'holidayDelete'],
        'audit-logs'                => ['AdminController', 'auditLogs'],
    ];

    public function handle(string $action): void
    {
        Auth::requireRole('admin');
        $route = self::ROUTES[trim($action, '/')] ?? null;
        if ($route === null) {
            http_response_code(404);
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
        $pdo = Database::conn();
        $count = static fn(string $sql, array $p = []): int => (int)(($s = $pdo->prepare($sql)) && $s->execute($p) ? $s->fetchColumn() : 0);
        $today = date('Y-m-d');
        $counters = [
            ['Applications to screen', $count("SELECT COUNT(*) FROM applications WHERE status = 'Submitted'"), 'admin/screening', 'bi-inbox', 'Applications with status Submitted'],
            ['Qualified, no schedule', $count("SELECT COUNT(*) FROM applications WHERE status = 'Qualified'"), 'admin/interviews', 'bi-calendar-plus', 'Applications with status Qualified'],
            ['Interviews today', $count("SELECT COUNT(*) FROM interview_assignments ia JOIN interview_schedules s ON s.id = ia.schedule_id
                                         WHERE ia.attendance = 'Scheduled' AND s.status = 'Open' AND s.interview_date = ?", [$today]),
                'admin/interviews', 'bi-people', 'Applicants still marked Scheduled on a schedule dated today'],
            ['Slot requests to confirm', $count("SELECT COUNT(*) FROM slot_requests WHERE status = 'Pending'"), 'admin/interviews', 'bi-hand-index', 'Interview slots applicants picked, waiting for your confirmation'],
            ['Awaiting a decision', $count("SELECT COUNT(*) FROM applications WHERE status = 'Interviewed'"), 'admin/decisions', 'bi-clipboard-check', 'Applications with status Interviewed'],
            ['Claims to verify', $count("SELECT COUNT(*) FROM scholar_terms WHERE claim_status = 'Submitted'"), 'admin/claims', 'bi-file-earmark-check', 'Semester claims with status Submitted'],
            ['Verified, to release', $count("SELECT COUNT(*) FROM scholar_terms WHERE claim_status = 'Verified'"), 'admin/releases', 'bi-cash-coin', 'Semester claims with status Verified'],
            ['Renewals to review', $count("SELECT COUNT(*) FROM renewal_requests WHERE status = 'Submitted'"), 'admin/renewals', 'bi-arrow-repeat', 'Renewal requests with status Submitted'],
        ];

        $program = Program::selected(!empty($_GET['program_id']) ? (int)$_GET['program_id'] : null);
        $programs = Program::options();
        $slotsUsed = $program ? Program::slotsUsed((int)$program['id']) : 0;
        $budget = $program ? Budget::forProgramYear((int)$program['id'], $program['academic_year']) : null;
        $todayInterviews = Interview::day($today);

        render('admin/dashboard', compact('counters', 'program', 'programs', 'slotsUsed', 'budget', 'todayInterviews'), 'Dashboard');
    }

    public function notifications(): void
    {
        $userId = admin_id();
        if (!empty($_GET['mark_read'])) {
            Database::conn()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        }
        $stmt = Database::conn()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        render('applicant/notifications', ['notifications' => $stmt->fetchAll()], 'Notifications');
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
        $rows = Application::search($filters, $limit, ($page - 1) * $limit);
        $total = Application::count($filters);
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
        $pdo = Database::conn();
        $q = trim((string)($_GET['q'] ?? ''));
        $role = in_array($_GET['role'] ?? '', ['admin', 'applicant'], true) ? $_GET['role'] : '';
        $sql = "SELECT u.id, u.full_name, u.email, u.contact_number, u.is_active, u.email_verified, u.last_login, u.created_at, r.role_name
                FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
        $params = [];
        if ($q !== '')  { $sql .= " AND (u.full_name LIKE :q1 OR u.email LIKE :q2)"; $params['q1'] = $params['q2'] = "%$q%"; }
        if ($role !== '') { $sql .= " AND r.role_name = :role"; $params['role'] = $role; }
        $stmt = $pdo->prepare($sql . " ORDER BY r.role_name, u.full_name LIMIT 200");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        render('admin/users', compact('users', 'q', 'role'), 'Users');
    }

    /** Create or edit an ADMIN account (applicants register themselves). */
    public function userSave(): void
    {
        require_post('admin/users');
        $pdo = Database::conn();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $contact = digits_only($_POST['contact_number'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Name and a valid e-mail are required.'); redirect('admin/users'); }
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) { flash('danger', 'That e-mail is already used.'); redirect('admin/users'); }

        if ($id > 0) {
            $pdo->prepare("UPDATE users SET full_name = ?, email = ?, contact_number = ? WHERE id = ?")->execute([$name, $email, $contact, $id]);
            AuditLog::write('user_updated', 'users', $id, "Updated account $email");
            flash('success', 'Account updated.');
        } else {
            if (strlen($password) < PASSWORD_MIN_LENGTH) { flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'); redirect('admin/users'); }
            $pdo->prepare(
                "INSERT INTO users (role_id, full_name, email, password_hash, contact_number, is_active, email_verified)
                 VALUES ((SELECT id FROM roles WHERE role_name = 'admin'), ?, ?, ?, ?, 1, 1)"
            )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $contact]);
            $newId = (int)$pdo->lastInsertId();
            AuditLog::write('user_created', 'users', $newId, "Created admin account $email");
            flash('success', 'Admin account created.');
        }
        redirect('admin/users');
    }

    public function userToggle(): void
    {
        require_post('admin/users');
        $id = (int)($_POST['id'] ?? 0);
        if ($id === admin_id()) { flash('danger', 'You cannot deactivate your own account.'); redirect('admin/users'); }
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT is_active, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'User not found.'); redirect('admin/users'); }
        $new = $row['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new, $id]);
        AuditLog::write('user_status_changed', 'users', $id, ($new ? 'Activated' : 'Deactivated') . " {$row['email']}");
        flash('success', $new ? 'Account activated.' : 'Account deactivated.');
        redirect('admin/users');
    }

    public function userReset(): void
    {
        require_post('admin/users');
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['new_password'] ?? '');
        if (strlen($password) < PASSWORD_MIN_LENGTH) { flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'); redirect('admin/users'); }
        Database::conn()->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        AuditLog::write('user_password_reset', 'users', $id, 'Admin reset the password');
        flash('success', 'Password reset.');
        redirect('admin/users');
    }

    /* ================= SETTINGS ================= */

    /** Only settings that do something: e-mail on/off, reminder hours, Send Test Email. */
    public function settings(): void
    {
        $rows = Database::conn()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = [
            'email_notifications'      => (string)($rows['email_notifications'] ?? '1'),
            'interview_reminder_hours' => (string)($rows['interview_reminder_hours'] ?? '24'),
            'daily_digest_enabled'     => (string)($rows['daily_digest_enabled'] ?? '1'),
            'daily_digest_sent_on'     => (string)($rows['daily_digest_sent_on'] ?? ''),
            'admin_event_emails'       => (string)($rows['admin_event_emails'] ?? '0'),
            'interview_suggestion_window_days' => (string)Interview::suggestionWindowDays(),
        ];
        $mailStatus = Notification::mailConfigStatus();
        $holidays = Database::conn()->query(
            "SELECT * FROM holidays WHERE holiday_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY holiday_date"
        )->fetchAll();
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
        $stmt = Database::conn()->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($values as $k => $v) $stmt->execute([$k, $v]);
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
        $pdo = Database::conn();
        try {
            $pdo->prepare("INSERT INTO holidays (holiday_date, name, created_by) VALUES (?, ?, ?)")->execute([$date, mb_substr($name, 0, 120), admin_id()]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            flash('danger', 'That date is already a holiday.');
            redirect('admin/settings');
        }
        AuditLog::write('holiday_added', 'holidays', (int)$pdo->lastInsertId(), "Holiday $date: $name");
        flash('success', 'Holiday added. Interview suggestions skip it.');
        redirect('admin/settings');
    }

    /** Remove a holiday (a setting, not a record — see "Data retention" in docs/PROCESS.md). */
    public function holidayDelete(): void
    {
        require_post('admin/settings');
        $id = (int)($_POST['id'] ?? 0);
        $stmt = Database::conn()->prepare("SELECT * FROM holidays WHERE id = ?");
        $stmt->execute([$id]);
        $h = $stmt->fetch();
        if ($h) {
            Database::conn()->prepare("DELETE FROM holidays WHERE id = ?")->execute([$id]);
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
        $pdo = Database::conn();
        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'action'      => (string)($_GET['action'] ?? ''),
            'entity_type' => (string)($_GET['entity_type'] ?? ''),
            'user_id'     => (string)($_GET['user_id'] ?? ''),
            'date_from'   => (string)($_GET['date_from'] ?? ''),
            'date_to'     => (string)($_GET['date_to'] ?? ''),
        ];
        $where = '';
        $params = [];
        if ($filters['q'] !== '')           { $where .= " AND (al.description LIKE :q1 OR al.action LIKE :q2)"; $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%'; }
        if ($filters['action'] !== '')      { $where .= " AND al.action = :a"; $params['a'] = $filters['action']; }
        if ($filters['entity_type'] !== '') { $where .= " AND al.entity_type = :et"; $params['et'] = $filters['entity_type']; }
        if ($filters['user_id'] !== '')     { $where .= " AND al.user_id = :uid"; $params['uid'] = (int)$filters['user_id']; }
        if ($filters['date_from'] !== '')   { $where .= " AND DATE(al.created_at) >= :df"; $params['df'] = $filters['date_from']; }
        if ($filters['date_to'] !== '')     { $where .= " AND DATE(al.created_at) <= :dt"; $params['dt'] = $filters['date_to']; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $stmt = $pdo->prepare("SELECT al.*, u.full_name AS actor_name, u.email AS actor_email
                               FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
                               WHERE 1=1 $where ORDER BY al.created_at DESC, al.id DESC LIMIT $limit OFFSET " . (($page - 1) * $limit));
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE 1=1 $where");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        $actions     = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
        $entityTypes = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll();
        $users       = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
        render('admin/audit_Logs', compact('filters', 'logs', 'total', 'page', 'pages', 'actions', 'entityTypes', 'users'), 'Audit Logs');
    }
}
