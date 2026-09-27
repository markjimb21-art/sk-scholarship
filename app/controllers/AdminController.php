<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Analytics.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/Program.php';

final class AdminController
{
    private const ROLES_ALL   = ['admin', 'staff', 'official'];
    private const ROLES_STAFF = ['admin', 'staff'];
    private const ROLES_ADMIN = ['admin'];

    /**
     * Authorization policy: action => roles allowed. DEFAULT DENY: an action that is not listed here is a 404.
     * ALL   = dashboard/analytics/reports (read-only views for officials)
     * STAFF = day-to-day scholarship administration (admin + staff)
     * ADMIN = users, system settings, audit logs
     * Per-action exceptions are enforced inside the handler (e.g. applications/update -> admin_override is admin only).
     */
    private const ACCESS = [
        // ALL
        'dashboard' => self::ROLES_ALL,
        'notifications' => self::ROLES_ALL,
        'analytics' => self::ROLES_ALL,
        'budget' => self::ROLES_ALL,             // view: admin full, staff/official read-only (view enforces this)
        'reports' => self::ROLES_ALL,
        'reports/generate' => self::ROLES_ALL,
        // STAFF
        'applications' => self::ROLES_STAFF,
        'applications/view' => self::ROLES_STAFF,
        'applications/update' => self::ROLES_STAFF,
        'applicants' => self::ROLES_STAFF,
        'documents' => self::ROLES_STAFF,
        'documents/view' => self::ROLES_STAFF,
        'documents/review' => self::ROLES_STAFF,
        'documents/verify-all' => self::ROLES_STAFF,
        'interview-scheduling' => self::ROLES_STAFF,
        'interview-schedule-save' => self::ROLES_STAFF,
        'interview-schedule-update' => self::ROLES_STAFF,
        'interview-schedule-cancel' => self::ROLES_STAFF,
        'interview-schedule-delete' => self::ROLES_STAFF,
        'interview-schedule-detail' => self::ROLES_STAFF,
        'interview-assign' => self::ROLES_STAFF,
        'interview-assign-batch' => self::ROLES_STAFF,
        'interview-unassign' => self::ROLES_STAFF,
        'interview-assignment-status' => self::ROLES_STAFF,
        'interview-calendar' => self::ROLES_STAFF,
        'interview-calendar-feed' => self::ROLES_STAFF,
        'interview-evaluation' => self::ROLES_STAFF,
        'interview-evaluate' => self::ROLES_STAFF,
        'interview-evaluation-save' => self::ROLES_STAFF,
        'scholars' => self::ROLES_STAFF,
        'scholar-view' => self::ROLES_STAFF,
        'scholar-status' => self::ROLES_STAFF,
        'academic-records' => self::ROLES_STAFF,
        'academic-record-save' => self::ROLES_STAFF,
        'academic-record-delete' => self::ROLES_STAFF,
        'releases' => self::ROLES_STAFF,
        'release-save' => self::ROLES_STAFF,
        'release-delete' => self::ROLES_STAFF,
        'renewals' => self::ROLES_STAFF,
        'renewal-view' => self::ROLES_STAFF,
        'renewal-save' => self::ROLES_STAFF,
        'renewal-update' => self::ROLES_STAFF,
        'renewal-delete' => self::ROLES_STAFF,
        // ADMIN (budget writes)
        'budget-save' => self::ROLES_ADMIN,
        'budget-update' => self::ROLES_ADMIN,
        'budget-delete' => self::ROLES_ADMIN,
        // ADMIN
        'programs' => self::ROLES_ADMIN,
        'program-save' => self::ROLES_ADMIN,
        'program-toggle' => self::ROLES_ADMIN,
        'users' => self::ROLES_ADMIN,
        'user-save' => self::ROLES_ADMIN,
        'user-toggle' => self::ROLES_ADMIN,
        'user-reset' => self::ROLES_ADMIN,
        'user-delete' => self::ROLES_ADMIN,
        'settings' => self::ROLES_ADMIN,
        'settings/save' => self::ROLES_ADMIN,
        'settings/test-email' => self::ROLES_ADMIN,
        'audit-logs' => self::ROLES_ADMIN,
    ];

    public function handle(string $action): void
    {
        $action = trim($action, '/');

        Auth::requireLogin();
        $allowed = self::ACCESS[$action] ?? null;
        if ($allowed === null) {                    // unknown action => 404 (default deny)
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }
        Auth::requireRole(...$allowed);             // wrong role => 403 (GET and POST alike)

        switch ($action) {
            case 'dashboard':                  $this->dashboard(); break;
            case 'applications':               $this->applications(); break;
            case 'applications/view':          $this->viewApplication(); break;
            case 'applications/update':        $this->updateApplication(); break;
            case 'applicants':                 $this->applicants(); break;
            case 'documents':                  $this->documents(); break;
            case 'documents/view':             $this->viewDocument(); break;
            case 'documents/review':           $this->reviewDocument(); break;
            case 'documents/verify-all':       $this->verifyAllDocuments(); break;
            case 'notifications':              $this->notifications(); break;
            case 'interview-scheduling':       $this->interviewScheduling(); break;
            case 'interview-schedule-save':    $this->saveInterviewSchedule(); break;
            case 'interview-schedule-update':  $this->updateInterviewSchedule(); break;
            case 'interview-schedule-cancel':  $this->cancelInterviewSchedule(); break;
            case 'interview-schedule-delete':  $this->deleteInterviewSchedule(); break;
            case 'interview-schedule-detail':  $this->interviewScheduleDetail(); break;
            case 'interview-assign':           $this->assignInterview(); break;
            case 'interview-assign-batch':     $this->assignInterviewBatch(); break;
            case 'interview-unassign':         $this->unassignInterview(); break;
            case 'interview-assignment-status':$this->updateAssignmentStatus(); break;
            case 'interview-calendar':         $this->interviewCalendar(); break;
            case 'interview-calendar-feed':    $this->interviewCalendarFeed(); break;
            case 'interview-evaluation':       $this->interviewEvaluationList(); break;
            case 'interview-evaluate':         $this->interviewEvaluate(); break;
            case 'interview-evaluation-save':  $this->saveInterviewEvaluation(); break;
            case 'scholars':                   $this->scholars(); break;
            case 'scholar-view':               $this->scholarView(); break;
            case 'scholar-status':             $this->scholarStatus(); break;
            case 'academic-records':           $this->academicRecords(); break;
            case 'academic-record-save':       $this->academicRecordSave(); break;
            case 'academic-record-delete':     $this->academicRecordDelete(); break;
            case 'releases':                   $this->releases(); break;
            case 'release-save':               $this->releaseSave(); break;
            case 'release-delete':             $this->releaseDelete(); break;
            case 'renewals':                   $this->renewals(); break;
            case 'renewal-view':               $this->renewalView(); break;
            case 'renewal-save':               $this->renewalSave(); break;
            case 'renewal-update':             $this->renewalUpdate(); break;
            case 'renewal-delete':             $this->renewalDelete(); break;
            case 'budget':                     $this->budgetIndex(); break;
            case 'budget-save':
            case 'budget-update':              $this->budgetSave(); break;
            case 'budget-delete':              $this->budgetDelete(); break;
            case 'programs':                   $this->programs(); break;
            case 'program-save':               $this->programSave(); break;
            case 'program-toggle':             $this->programToggle(); break;
            case 'analytics':                  $this->analytics(); break;
            case 'reports':                    $this->reports(); break;
            case 'reports/generate':           $this->generateReport(); break;
            case 'users':                      $this->users(); break;
            case 'user-save':                  $this->userSave(); break;
            case 'user-toggle':                $this->userToggle(); break;
            case 'user-reset':                 $this->userReset(); break;
            case 'user-delete':                $this->userDelete(); break;
            case 'settings':                   $this->settings(); break;
            case 'settings/save':              $this->settingsSave(); break;
            case 'settings/test-email':        $this->settingsTestEmail(); break;
            case 'audit-logs':                 $this->auditLogs(); break;
            default:
                http_response_code(404);
                require VIEW_PATH . '/errors/404.php';
        }
    }

    /* ------------------------------------------------------------
     *  DASHBOARD
     * ------------------------------------------------------------ */
    private function dashboard(): void
    {
        $appStats      = Application::stats();
        $scholarStats  = Scholar::stats();
        $interviewStats= Interview::stats();

        // Funds
        $pdo = Database::conn();
        $fundsRow = $pdo->query(
            "SELECT COALESCE(SUM(amount),0) AS total_released FROM scholarship_releases"
        )->fetch();
        $budgetRow = $pdo->query(
            "SELECT COALESCE(SUM(total_budget),0) AS total_budget FROM budgets"
        )->fetch();

        // Application status distribution
        $statusDist = $pdo->query(
            "SELECT status, COUNT(*) AS c FROM applications GROUP BY status"
        )->fetchAll();

        // Applications over last 12 months
        $overTime = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
             FROM applications
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY ym ORDER BY ym"
        )->fetchAll();

        // Upcoming interviews
        $upcomingInterviews = $pdo->query(
            "SELECT s.*, (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id=s.id) AS assigned
             FROM interview_schedules s
             WHERE s.interview_date >= CURDATE() AND s.status != 'Cancelled'
             ORDER BY s.interview_date ASC, s.start_time ASC
             LIMIT 5"
        )->fetchAll();

        $pageTitle = 'Admin Dashboard';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/dashboard.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ------------------------------------------------------------
     *  APPLICATIONS LIST
     * ------------------------------------------------------------ */
    private function applications(): void
    {
        $pdo = Database::conn();

        $filters = [
            'q'          => trim($_GET['q'] ?? ''),
            'status'     => $_GET['status'] ?? '',
            'school'     => $_GET['school'] ?? '',
            'course'     => $_GET['course'] ?? '',
            'year_level' => $_GET['year_level'] ?? '',
            'program_id' => $_GET['program_id'] ?? '',
            'max_income' => $_GET['max_income'] ?? '',
        ];

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $rows  = Application::search($filters, $limit, $offset);
        $total = Application::count($filters);
        $pages = max(1, (int)ceil($total / $limit));

        $schools = $pdo->query("SELECT DISTINCT school_name FROM education_records WHERE school_name <> '' ORDER BY school_name")->fetchAll();
        $programs = $pdo->query("SELECT id, program_name FROM scholarship_programs ORDER BY id DESC")->fetchAll();

        $pageTitle = 'Applications';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/applications.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ------------------------------------------------------------
     *  APPLICATION DETAIL
     * ------------------------------------------------------------ */
    private function viewApplication(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $app = Application::withDetails($id);
        if (!$app) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }

        if (!empty($app['review_remarks_by'])) {
            $stmt = Database::conn()->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$app['review_remarks_by']]);
            $app['review_remarks_by_name'] = $stmt->fetchColumn() ?: null;
        }

        // Eligibility panel: the result stored at submission; for applications submitted before this
        // check existed (score is NULL), compute it now from the current data so staff still see it.
        $program = Program::find((int)$app['program_id']);
        $slotsUsed = Program::slotsUsed((int)$app['program_id']);
        $eligibility = null;
        if ($app['eligibility_score'] !== null) {
            $eligibility = ['score' => (float)$app['eligibility_score'], 'notes' => $app['eligibility_notes'] ?? null, 'live' => false];
        } elseif ($app['status'] !== 'Draft' && $program) {
            $eligibility = Application::evaluateEligibility($program, $app['personal'], $app['education'], $app['family']) + ['live' => true];
        }

        // Audit trail for this application
        $audit = Database::conn()->prepare(
            "SELECT al.*, u.full_name AS actor
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE (al.entity_type='applications' AND al.entity_id=?)
                OR (al.entity_type='documents' AND al.entity_id IN (SELECT id FROM documents WHERE application_id=?))
             ORDER BY al.created_at DESC
             LIMIT 50"
        );
        $audit->execute([$id, $id]);
        $auditTrail = $audit->fetchAll();

        $pageTitle = 'Application · ' . $app['application_code'];
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/application_view.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ------------------------------------------------------------
     *  UPDATE APPLICATION (status change / approval / rejection)
     * ------------------------------------------------------------ */
    private function updateApplication(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/applications');
        verify_csrf();

        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $app    = Application::find($id);
        if (!$app) { flash('danger', 'Application not found.'); redirect('admin/applications'); }

        if ($action === 'admin_override' && !Auth::hasRole('admin')) {
            Auth::deny();
        }

        // Workflow guard: Application::TRANSITIONS is the single source of truth (the view's buttons use it too).
        // The only bypass is an admin acting on an application with admin_override enabled; that is audit-logged below.
        $overrideUsed = false;
        if ($action === 'admin_override') {
            if (in_array($app['status'], Application::FINAL_STATUSES, true)) {
                flash('danger', "An override cannot be enabled on an application that is already {$app['status']}.");
                redirect('admin/applications/view&id=' . $id);
            }
        } elseif (isset(Application::TRANSITIONS[$action]) && !Application::transitionAllowed($action, $app['status'])) {
            if (!Application::canBypassTransitions($app)) {
                flash('danger', "That action is not allowed while the application is \"{$app['status']}\".");
                redirect('admin/applications/view&id=' . $id);
            }
            $overrideUsed = true;
        }

        $userId = (int)Auth::user()['id'];
        $pdo = Database::conn();

        try {
            $pdo->beginTransaction();

            switch ($action) {
                case 'start_review':
                    Application::updateStatus($id, 'Under Initial Review', $userId);
                    // In-app notification only — no email for this event (Initial Review is a routine internal step).
                    Notification::send((int)$app['applicant_id'] ? $this->userIdFromApplicant((int)$app['applicant_id']) : 0,
                        'Application Under Review',
                        'Your application is now being reviewed by the SK office.',
                        'info', url('applicant/dashboard'), false);
                    flash('success', 'Marked as under review.');
                    break;

                case 'mark_incomplete':
                    $incompleteRemarks = trim($_POST['remarks'] ?? '');
                    if ($incompleteRemarks === '') {
                        $pdo->rollBack();
                        flash('danger', 'Please provide remarks explaining what needs to be corrected.');
                        redirect('admin/applications/view&id=' . $id);
                    }
                    Application::markIncomplete($id, $userId, $incompleteRemarks);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Application Marked Incomplete',
                        'Your scholarship application has been marked incomplete. Please review the remarks and update your application. ' .
                        'Remarks: ' . $incompleteRemarks,
                        'warning', url('applicant/application'), true);
                    flash('warning', 'Marked as incomplete. The applicant has been notified.');
                    break;

                case 'verify_documents':
                    // Ensure all documents verified first
                    $ver = Document::verifiedCount($id);
                    if ($ver < count(DOCUMENT_TYPES)) {
                        $pdo->rollBack();
                        flash('danger', 'Cannot proceed — ' . (count(DOCUMENT_TYPES) - $ver) . ' document(s) still unverified.');
                        redirect('admin/applications/view&id=' . $id);
                    }
                    Application::updateStatus($id, 'Documents Verified', $userId);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Documents Verified',
                        'All your submitted documents have been verified by the SK office.',
                        'success', url('applicant/documents'), true);
                    flash('success', 'Documents marked verified.');
                    break;

                case 'for_interview':
                    // Server is the authority here — never trust the frontend checklist alone.
                    $review = Application::reviewCompleteness($id);
                    if (!$review['complete']) {
                        $pdo->rollBack();
                        $parts = [];
                        if ($review['missing_documents']) $parts[] = 'Missing documents: ' . implode(', ', $review['missing_documents']) . '.';
                        if ($review['missing_fields']) $parts[] = 'Missing/invalid information: ' . implode(', ', $review['missing_fields']) . '.';
                        flash('danger', 'This application is incomplete. Please mark it as Incomplete and specify the missing or incorrect information. ' . implode(' ', $parts));
                        redirect('admin/applications/view&id=' . $id);
                    }

                    // All required documents are present and valid — verify them as part of this one review decision.
                    $verifiedTypes = [];
                    foreach (Document::byApplication($id) as $doc) {
                        if ($doc['file_path'] !== '' && $doc['status'] !== 'Verified') {
                            Document::updateStatus((int)$doc['id'], 'Verified', $userId, 'Verified as part of application review.');
                            $verifiedTypes[] = $doc['document_type'];
                        }
                    }

                    Application::updateStatus($id, 'For Interview', $userId);
                    AuditLog::write('application_marked_for_interview', 'applications', $id,
                        'Application marked For Interview after completeness review' .
                        ($verifiedTypes ? '; auto-verified: ' . implode(', ', $verifiedTypes) : ''));
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Eligible for Interview',
                        'You are now eligible for an interview. Please wait for scheduling.',
                        'success', url('applicant/interview'), true);
                    flash('success', 'Marked as For Interview. Required documents were automatically verified.');
                    break;

                case 'for_final':
                    Application::updateStatus($id, 'For Final Evaluation', $userId);
                    flash('success', 'Moved to final evaluation.');
                    break;

                case 'approve':
                    // Slot limit: lock the program row so two approvals cannot both take the last slot.
                    $progStmt = $pdo->prepare("SELECT program_name, slots_available FROM scholarship_programs WHERE id = ? FOR UPDATE");
                    $progStmt->execute([(int)$app['program_id']]);
                    $program = $progStmt->fetch();
                    $slots = (int)($program['slots_available'] ?? 0);   // 0 = no limit
                    $used  = Program::slotsUsed((int)$app['program_id']);
                    if ($slots > 0 && $used >= $slots) {
                        if (!Application::canBypassTransitions($app)) {
                            $pdo->rollBack();
                            flash('danger', "Cannot approve — all $slots slot(s) for {$program['program_name']} are already filled ($used scholar(s)). An admin override is required.");
                            redirect('admin/applications/view&id=' . $id);
                        }
                        AuditLog::write('slot_limit_override', 'applications', $id,
                            "Approved over the slot limit via admin override ($used of $slots slots already used)");
                    }
                    $this->approveApplication($id, $app, $userId, $_POST['remarks'] ?? '');
                    flash('success', 'Application approved and scholar created.');
                    break;

                case 'reject':
                    Application::updateStatus($id, 'Rejected', $userId);
                    $pdo->prepare("UPDATE applications SET decided_at=NOW(), decided_by=?, decision_remarks=? WHERE id=?")
                        ->execute([$userId, $_POST['remarks'] ?? 'Application did not meet requirements.', $id]);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Application Not Approved',
                        'We regret to inform you that your scholarship application was not approved. ' .
                        ($_POST['remarks'] ?? ''),
                        'danger', url('applicant/dashboard'), true);
                    flash('warning', 'Application rejected.');
                    break;

                case 'waitlist':
                    Application::updateStatus($id, 'Waitlisted', $userId);
                    $pdo->prepare("UPDATE applications SET decided_at=NOW(), decided_by=?, decision_remarks=? WHERE id=?")
                        ->execute([$userId, $_POST['remarks'] ?? 'Placed on waitlist.', $id]);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Waitlisted',
                        'You have been placed on the waitlist. We will notify you if a slot opens.',
                        'warning', url('applicant/dashboard'), true);
                    flash('info', 'Application waitlisted.');
                    break;

                case 'admin_override':
                    $pdo->prepare("UPDATE applications SET admin_override=1, override_reason=? WHERE id=?")
                        ->execute([$_POST['reason'] ?? '', $id]);
                    AuditLog::write('admin_override', 'applications', $id, 'Admin override enabled');
                    flash('warning', 'Admin override enabled.');
                    break;

                default:
                    flash('danger', 'Unknown action.');
            }

            if ($overrideUsed) {
                AuditLog::write('transition_override', 'applications', $id,
                    "Admin override: '$action' performed while status was \"{$app['status']}\" (outside the normal workflow)",
                    ['status' => $app['status']], ['action' => $action, 'override_reason' => $app['override_reason'] ?? null]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', APP_DEBUG ? $e->getMessage() : 'Update failed.');
        }

        redirect('admin/applications/view&id=' . $id);
    }

    private function approveApplication(int $appId, array $app, int $userId, string $remarks): void
    {
        $pdo = Database::conn();

        // Create scholar
        $result = Scholar::createFromApplication($appId, $userId);
        if (!$result['ok'] && !str_contains($result['error'], 'already exists')) {
            throw new RuntimeException($result['error']);
        }

        $pdo->prepare("UPDATE applications SET status='Approved', decided_at=NOW(), decided_by=?, decision_remarks=? WHERE id=?")
            ->execute([$userId, $remarks, $appId]);

        AuditLog::write('application_approved', 'applications', $appId, 'Application approved');

        Notification::send(
            $this->userIdFromApplicant((int)$app['applicant_id']),
            'Congratulations — Scholarship Approved! 🎉',
            'Your scholarship application has been approved. Welcome, SK Scholar!',
            'success', url('applicant/scholarship'),
            true // try to email
        );
    }

    private function userIdFromApplicant(int $applicantId): int
    {
        $stmt = Database::conn()->prepare("SELECT user_id FROM applicants WHERE id = ?");
        $stmt->execute([$applicantId]);
        return (int)$stmt->fetchColumn();
    }

    /* ------------------------------------------------------------
     *  APPLICANTS (registry, not per-application)
     * ------------------------------------------------------------ */
    private function applicants(): void
    {
        $filters = [
            'q'          => trim($_GET['q'] ?? ''),
            'school'     => $_GET['school'] ?? '',
            'year_level' => $_GET['year_level'] ?? '',
            'status'     => $_GET['status'] ?? '',
        ];

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $rows  = Applicant::search($filters, $limit, $offset);
        $total = Applicant::count($filters);
        $pages = max(1, (int)ceil($total / $limit));

        $pdo = Database::conn();
        $schools = $pdo->query("SELECT DISTINCT school_name FROM education_records WHERE school_name <> '' ORDER BY school_name")->fetchAll();

        $pageTitle = 'Applicants';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/applicants.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ------------------------------------------------------------
     *  DOCUMENTS WORKBENCH
     * ------------------------------------------------------------ */
    private function documents(): void
    {
        $status   = $_GET['status'] ?? '';
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = 25;
        $offset   = ($page - 1) * $limit;

        $sql = "SELECT d.*, a.application_code, u.full_name AS applicant_name, u.email,
                       ap.id AS applicant_id
                FROM documents d
                JOIN applications a ON a.id = d.application_id
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                WHERE d.status != 'Not Submitted' AND d.file_path <> ''";
        $params = [];
        if ($status) { $sql .= " AND d.status = :st"; $params['st'] = $status; }
        $sql .= " ORDER BY d.uploaded_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll();

        // count
        $countSql = "SELECT COUNT(*) FROM documents d WHERE d.status != 'Not Submitted' AND d.file_path <> ''";
        if ($status) $countSql .= " AND d.status = " . Database::conn()->quote($status);
        $total = (int)Database::conn()->query($countSql)->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        $pageTitle = 'Document Verification';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/documents.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ------------------------------------------------------------
     *  VIEW DOCUMENT (inline / download)
     * ------------------------------------------------------------ */
    private function viewDocument(): void
    {
        $doc = Document::find((int)($_GET['id'] ?? 0));
        if (!$doc || !$doc['file_path']) { http_response_code(404); die('Document not found.'); }
        Document::send($doc, isset($_GET['download']));
    }

    /* ------------------------------------------------------------
     *  REVIEW DOCUMENT (verify/reject/resubmit)
     * ------------------------------------------------------------ */
    private function reviewDocument(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/documents');
        verify_csrf();

        $id      = (int)($_POST['id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $allowed = ['Under Review', 'Verified', 'Rejected', 'Resubmission Required'];

        if (!in_array($status, $allowed, true)) {
            flash('danger', 'Invalid status.');
            redirect('admin/documents');
        }

        $doc = Document::find($id);
        if (!$doc) { flash('danger', 'Document not found.'); redirect('admin/documents'); }

        Document::updateStatus($id, $status, (int)Auth::user()['id'], $remarks);

        // Notify applicant
        $stmt = Database::conn()->prepare(
            "SELECT u.id AS user_id, u.full_name, d.document_type, a.application_code
             FROM documents d
             JOIN applications a ON a.id = d.application_id
             JOIN applicants ap ON ap.id = a.applicant_id
             JOIN users u ON u.id = ap.user_id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $title = match($status) {
                'Verified' => 'Document Verified ✅',
                'Rejected' => 'Document Rejected ❌',
                'Resubmission Required' => 'Resubmission Required ⚠️',
                default => 'Document Update'
            };
            $type = match($status) {
                'Verified' => 'success', 'Rejected' => 'danger',
                'Resubmission Required' => 'warning', default => 'info'
            };
            // Document Verified / Rejected: in-app only, no email (routine per-document review events).
            // Resubmission Required / Under Review: unchanged, still emailed.
            $sendEmail = !in_array($status, ['Verified', 'Rejected'], true);
            Notification::send(
                (int)$row['user_id'], $title,
                "Your {$row['document_type']} has been marked as: $status." .
                ($remarks ? " Remarks: $remarks" : ''),
                $type, url('applicant/documents'), $sendEmail
            );
        }

        flash('success', "Document marked as $status.");
        $back = (string)($_POST['redirect'] ?? '');
        redirect(preg_match('#^admin/[A-Za-z0-9_\-/]+(?:[&?][A-Za-z0-9_\-]+=[A-Za-z0-9_\-%.+]*)*(?:\#[A-Za-z0-9_\-]+)?$#', $back) === 1 ? $back : 'admin/documents');
    }

    /* ------------------------------------------------------------
     *  VERIFY ALL DOCUMENTS for an application (bulk action)
     * ------------------------------------------------------------ */
    private function verifyAllDocuments(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/documents');
        verify_csrf();

        $appId = (int)($_POST['application_id'] ?? 0);
        $app = Application::find($appId);
        if (!$app) { flash('danger', 'Application not found.'); redirect('admin/applications'); }

        $userId = (int)Auth::user()['id'];
        $documents = Document::byApplication($appId);
        $verifiedCount = 0;

        foreach ($documents as $doc) {
            if ($doc['file_path'] === '' || $doc['status'] === 'Verified') continue;
            Document::updateStatus((int)$doc['id'], 'Verified', $userId, null);
            $verifiedCount++;
        }

        if ($verifiedCount > 0) {
            AuditLog::write('documents_verify_all', 'applications', $appId, "Verified $verifiedCount document(s) in bulk");
            $applicantUserId = $this->userIdFromApplicant((int)$app['applicant_id']);
            // Document Verified: in-app only, no email (same rule as the single-document Verify action).
            Notification::send(
                $applicantUserId, 'Documents Verified ✅',
                "$verifiedCount of your submitted document(s) have been verified by the SK office.",
                'success', url('applicant/documents'), false
            );
            flash('success', "Verified $verifiedCount document(s).");
        } else {
            flash('info', 'No submitted documents were pending verification.');
        }

        redirect('admin/applications/view&id=' . $appId . '#tab-docs');
    }

    /* ------------------------------------------------------------
     *  Notifications inbox (admin)
     * ------------------------------------------------------------ */
    private function notifications(): void
    {
        $userId = (int)Auth::user()['id'];
        if (!empty($_GET['mark_read'])) {
            Database::conn()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
        }
        $stmt = Database::conn()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();

        $pageTitle = 'Notifications';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/notifications.php';
        require VIEW_PATH . '/layouts/footer.php';
    }
    /* ------------------------------------------------------------
     * INTERVIEW SCHEDULING 
     * ------------------------------------------------------------ */
    private function interviewScheduling(): void
    {
        $pdo = Database::conn();
        $programs = $pdo->query("SELECT id, program_name, academic_year FROM scholarship_programs ORDER BY id DESC")->fetchAll();
        $programId = (int)($_GET['program_id'] ?? ($programs[0]['id'] ?? 0));

        $schedules = $programId ? Interview::schedulesByProgram($programId) : [];
        $progStmt  = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
        $progStmt->execute([$programId]);
        $program   = $programId ? ($progStmt->fetch() ?: null) : null;
        $eligible  = $programId ? Interview::eligibleApplicationsForInterview($programId) : [];

        $pageTitle = 'Interview Scheduling';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/interview_scheduling.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function saveInterviewSchedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $result = Interview::createSchedule([
            'program_id'     => (int)$_POST['program_id'],
            'title'          => trim($_POST['title'] ?? ''),
            'interview_date' => $_POST['interview_date'],
            'start_time'     => $_POST['start_time'],
            'end_time'       => $_POST['end_time'],
            'venue'          => trim($_POST['venue']),
            'interviewer'    => trim($_POST['interviewer']),
            'max_slots'      => (int)$_POST['max_slots'],
            'remarks'        => trim($_POST['remarks'] ?? ''),
        ], (int)Auth::user()['id']);

        if ($result['ok']) flash('success', 'Interview schedule created.');
        else                flash('danger', $result['error']);

        redirect('admin/interview-scheduling&program_id=' . (int)$_POST['program_id']);
    }

    private function updateInterviewSchedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $id = (int)$_POST['id'];
        $result = Interview::updateSchedule($id, [
            'title'          => trim($_POST['title'] ?? ''),
            'interview_date' => $_POST['interview_date'],
            'start_time'     => $_POST['start_time'],
            'end_time'       => $_POST['end_time'],
            'venue'          => trim($_POST['venue']),
            'interviewer'    => trim($_POST['interviewer']),
            'max_slots'      => (int)$_POST['max_slots'],
            'status'         => $_POST['status'],
            'remarks'        => trim($_POST['remarks'] ?? ''),
        ], (int)Auth::user()['id']);

        if ($result['ok']) flash('success', 'Schedule updated.');
        else                flash('danger', $result['error']);
        redirect('admin/interview-scheduling&program_id=' . (int)$_POST['program_id']);
    }

    private function cancelInterviewSchedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $id = (int)$_POST['id'];
        Interview::cancelSchedule($id, (int)Auth::user()['id'], trim($_POST['reason'] ?? ''));
        flash('warning', 'Schedule cancelled. Assigned applicants were notified.');
        redirect('admin/interview-scheduling&program_id=' . (int)$_POST['program_id']);
    }

    private function deleteInterviewSchedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $id = (int)$_POST['id'];
        $result = Interview::deleteSchedule($id, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', 'Schedule deleted.');
        else                flash('danger', $result['error']);
        redirect('admin/interview-scheduling&program_id=' . (int)$_POST['program_id']);
    }

    private function interviewScheduleDetail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $schedule = Interview::schedule($id);
        if (!$schedule) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }

        $assignments = Interview::assignmentsBySchedule($id);
        $eligible    = Interview::eligibleApplicationsForInterview((int)$schedule['program_id']);

        $pageTitle = 'Schedule · ' . date('M d, Y', strtotime($schedule['interview_date']));
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/interview_schedule_detail.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function assignInterview(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $scheduleId    = (int)$_POST['schedule_id'];
        $applicationId = (int)$_POST['application_id'];
        $instructions  = trim($_POST['instructions'] ?? '') ?: null;

        $result = Interview::assign($scheduleId, $applicationId, (int)Auth::user()['id'], $instructions);
        if ($result['ok']) flash('success', 'Applicant assigned.');
        else                flash('danger', $result['error']);

        redirect('admin/interview-schedule-detail&id=' . $scheduleId);
    }

    private function assignInterviewBatch(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();

        $scheduleId = (int)$_POST['schedule_id'];
        $applicationIds = array_filter(array_map('intval', (array)($_POST['application_ids'] ?? [])));

        if (!$applicationIds) {
            flash('warning', 'No applicants were selected.');
            redirect('admin/interview-schedule-detail&id=' . $scheduleId);
        }

        $result = Interview::assignBatch($scheduleId, $applicationIds, (int)Auth::user()['id']);
        $n = count($result['assigned']);
        if ($n > 0) flash('success', "$n applicant(s) assigned.");
        if ($result['failed']) {
            flash('warning', count($result['failed']) . ' applicant(s) could not be assigned (schedule may now be full).');
        }

        redirect('admin/interview-schedule-detail&id=' . $scheduleId);
    }

    private function unassignInterview(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();
        $assignmentId = (int)$_POST['assignment_id'];
        $scheduleId   = (int)$_POST['schedule_id'];
        Interview::unassign($assignmentId, (int)Auth::user()['id']);
        flash('success', 'Assignment removed.');
        redirect('admin/interview-schedule-detail&id=' . $scheduleId);
    }

    private function updateAssignmentStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-scheduling');
        verify_csrf();
        Interview::updateAssignmentStatus(
            (int)$_POST['assignment_id'],
            $_POST['status'],
            (int)Auth::user()['id'],
            trim($_POST['notes'] ?? '')
        );
        flash('success', 'Status updated.');
        redirect('admin/interview-schedule-detail&id=' . (int)$_POST['schedule_id']);
    }

    /* ---------------- CALENDAR ---------------- */
    private function interviewCalendar(): void
    {
        $pdo = Database::conn();
        $programs = $pdo->query("SELECT id, program_name FROM scholarship_programs ORDER BY id DESC")->fetchAll();

        $pageTitle = 'Interview Calendar';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/interview_calendar.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function interviewCalendarFeed(): void
    {
        $from = $_GET['start'] ?? null;
        $to   = $_GET['end'] ?? null;
        $programId = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;

        $schedules = Interview::allSchedules($from, $to, $programId);

        $events = [];
        foreach ($schedules as $s) {
            // Color by status
            $color = match($s['status']) {
                'Open'      => ((int)$s['assigned_count'] >= (int)$s['max_slots']) ? '#f59e0b' : '#1d4ed8',
                'Full'      => '#f59e0b',
                'Completed' => '#16a34a',
                'Cancelled' => '#dc2626',
                default     => '#64748b'
            };

            $title = trim((string)($s['title'] ?? ''));
            if ($title === '') $title = 'Interview';
            $title .= sprintf(' (%d/%d)', (int)$s['assigned_count'], (int)$s['max_slots']);

            $events[] = [
                'id'    => (int)$s['id'],
                'title' => $title,
                'start' => $s['interview_date'] . 'T' . $s['start_time'],
                'end'   => $s['interview_date'] . 'T' . $s['end_time'],
                'url'   => url('admin/interview-schedule-detail&id=' . (int)$s['id']),
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'extendedProps' => [
                    'venue'       => $s['venue'],
                    'interviewer' => $s['interviewer'],
                    'status'      => $s['status'],
                    'assigned'    => (int)$s['assigned_count'],
                    'max_slots'   => (int)$s['max_slots'],
                ],
            ];
        }

        json_response($events);
    }

    /* ---------------- EVALUATION LIST ---------------- */
    private function interviewEvaluationList(): void
    {
        // Interviews that are Completed (or scheduled for today/past) but not yet evaluated OR already evaluated
        $filter = $_GET['filter'] ?? 'pending';
        $sql = "SELECT ia.id AS assignment_id, ia.assignment_status, ia.schedule_id,
                    a.id AS application_id, a.application_code, a.status AS app_status,
                    u.full_name, u.email,
                    e.school_name, e.course_name, e.year_level,
                    s.interview_date, s.start_time, s.venue, s.interviewer,
                    ev.id AS evaluation_id, ev.total_score, ev.result, ev.rating
                FROM interview_assignments ia
                JOIN applications a ON a.id = ia.application_id
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN users u ON u.id = ap.user_id
                JOIN interview_schedules s ON s.id = ia.schedule_id
                LEFT JOIN education_records e ON e.applicant_id = ap.id
                LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
                WHERE 1=1";
        $params = [];
        if ($filter === 'pending') {
            $sql .= " AND ia.assignment_status IN ('Completed','Confirmed','Scheduled') AND ev.id IS NULL";
        } elseif ($filter === 'done') {
            $sql .= " AND ev.id IS NOT NULL";
        } elseif ($filter === 'noshow') {
            $sql .= " AND ia.assignment_status = 'No Show'";
        }
        $sql .= " ORDER BY s.interview_date DESC, s.start_time DESC LIMIT 200";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $pageTitle = 'Interview Evaluation';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/interview_evaluation_list.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function interviewEvaluate(): void
    {
        $assignmentId = (int)($_GET['id'] ?? 0);
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, a.application_code, a.status AS app_status,
                    u.full_name, u.email, u.contact_number,
                    e.school_name, e.course_name, e.year_level,
                    s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer,
                    f.total_family_income
            FROM interview_assignments ia
            JOIN applications a ON a.id = ia.application_id
            JOIN applicants ap ON ap.id = a.applicant_id
            JOIN users u ON u.id = ap.user_id
            JOIN interview_schedules s ON s.id = ia.schedule_id
            LEFT JOIN education_records e ON e.applicant_id = ap.id
            LEFT JOIN family_background f ON f.applicant_id = ap.id
            WHERE ia.id = ? LIMIT 1"
        );
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();
        if (!$assignment) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }

        $evaluation = Interview::evaluation($assignmentId);

        $pageTitle = 'Evaluate · ' . $assignment['full_name'];
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/interview_evaluate.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function saveInterviewEvaluation(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/interview-evaluation');
        verify_csrf();

        $assignmentId = (int)$_POST['assignment_id'];
        $result = Interview::saveEvaluation(
            $assignmentId,
            [
                'financial_need_score' => $_POST['financial_need_score'] ?? 0,
                'academic_score'       => $_POST['academic_score'] ?? 0,
                'motivation_score'     => $_POST['motivation_score'] ?? 0,
                'community_score'      => $_POST['community_score'] ?? 0,
                'purpose_score'        => $_POST['purpose_score'] ?? 0,
                'overall_score'        => $_POST['overall_score'] ?? 0,
            ],
            $_POST['result'] ?? 'For Further Review',
            (int)Auth::user()['id'],
            trim($_POST['recommendation'] ?? ''),
            trim($_POST['remarks'] ?? ''),
            trim($_POST['notes'] ?? '')
        );

        if ($result['ok']) flash('success', "Evaluation saved. Total score: {$result['total']} (Rating: {$result['rating']}/5)");
        else               flash('danger', 'Failed to save evaluation.');

        redirect('admin/interview-evaluation');
    }

    /* ================= SCHOLARS ================= */
    private function scholars(): void
    {
        $pdo = Database::conn();
        $filters = [
            'q'          => trim($_GET['q'] ?? ''),
            'status'     => $_GET['status'] ?? '',
            'school'     => $_GET['school'] ?? '',
            'course'     => $_GET['course'] ?? '',
            'year_level' => $_GET['year_level'] ?? '',
            'program_id' => $_GET['program_id'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $rows  = Scholar::search($filters, $limit, $offset);
        $total = Scholar::count($filters);
        $pages = max(1, (int)ceil($total / $limit));
        $schools = $pdo->query("SELECT DISTINCT school_name FROM education_records WHERE school_name <> '' ORDER BY school_name")->fetchAll();
        $programs = $pdo->query("SELECT id, program_name FROM scholarship_programs ORDER BY id DESC")->fetchAll();

        $pageTitle = 'Scholars';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/scholars.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function scholarView(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $scholar = Scholar::find($id);
        if (!$scholar) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }

        $applicant = Applicant::withUser((int)$scholar['applicant_id']);
        $personal  = Applicant::personalInfo((int)$scholar['applicant_id']);
        $education = Applicant::education((int)$scholar['applicant_id']);
        $family    = Applicant::familyBackground((int)$scholar['applicant_id']);
        $academics = Scholar::academicRecords($id);
        $releases  = Scholar::releases($id);
        $renewals  = Scholar::renewals($id);
        $totalReleased = Scholar::totalReleased($id);
        $budgetYears = Budget::yearsForProgram((int)$scholar['program_id']); // releases need a budget row for their year

        // Audit trail for this scholar
        $audit = Database::conn()->prepare(
            "SELECT al.*, u.full_name AS actor FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.entity_type IN ('scholars','academic_records','scholarship_releases','scholarship_renewals')
            AND (al.entity_id = ? OR al.description LIKE ?)
            ORDER BY al.created_at DESC LIMIT 60"
        );
        $audit->execute([$id, '%scholar #' . $id . '%']);
        $auditTrail = $audit->fetchAll();

        $pageTitle = 'Scholar · ' . $scholar['scholar_code'];
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/scholar_view.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function scholarStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/scholars');
        verify_csrf();
        Scholar::updateStatus(
            (int)$_POST['id'],
            $_POST['status'],
            (int)Auth::user()['id'],
            trim($_POST['remarks'] ?? '')
        );
        flash('success', 'Scholar status updated.');
        redirect('admin/scholar-view&id=' . (int)$_POST['id']);
    }

    /* ================= ACADEMIC RECORDS ================= */
    private function academicRecords(): void
    {
        $pdo = Database::conn();
        $filters = [
            'q'      => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'school' => $_GET['school'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        // Reuse scholar search, but always show scholars (both active and probation)
        $rows  = Scholar::search($filters, $limit, $offset);
        $total = Scholar::count($filters);
        $pages = max(1, (int)ceil($total / $limit));
        $schools = $pdo->query("SELECT DISTINCT school_name FROM education_records WHERE school_name <> '' ORDER BY school_name")->fetchAll();

        $pageTitle = 'Academic Records';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/academic_records.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function academicRecordSave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/academic-records');
        verify_csrf();
        $result = Scholar::saveAcademicRecord($_POST, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', "Academic record saved. Standing: {$result['standing']}");
        else               flash('danger', $result['error']);
        redirect('admin/scholar-view&id=' . (int)$_POST['scholar_id']);
    }

    private function academicRecordDelete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/academic-records');
        verify_csrf();
        Scholar::deleteAcademicRecord((int)$_POST['id'], (int)Auth::user()['id']);
        flash('success', 'Record deleted.');
        redirect('admin/scholar-view&id=' . (int)$_POST['scholar_id']);
    }

    /* ================= RELEASES ================= */
    private function releases(): void
    {
        $pdo = Database::conn();
        $filters = [
            'q'      => trim($_GET['q'] ?? ''),
            'year'   => $_GET['year'] ?? '',
            'school' => $_GET['school'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT r.*, s.scholar_code, u.full_name AS scholar_name,
                    sp.program_name, st.full_name AS staff_name
                FROM scholarship_releases r
                JOIN scholars s ON s.id = r.scholar_id
                JOIN applicants a ON a.id = s.applicant_id
                JOIN users u ON u.id = a.user_id
                JOIN scholarship_programs sp ON sp.id = s.program_id
                LEFT JOIN users st ON st.id = r.staff_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['q'])) { $sql .= " AND (u.full_name LIKE :q1 OR s.scholar_code LIKE :q2 OR r.release_code LIKE :q3)"; $params['q1'] = $params['q2'] = $params['q3'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['year'])) { $sql .= " AND r.academic_year = :y"; $params['y'] = $filters['year']; }
        $sql .= " ORDER BY r.release_date DESC, r.id DESC LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $releases = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) FROM scholarship_releases r
                    JOIN scholars s ON s.id = r.scholar_id
                    JOIN applicants a ON a.id = s.applicant_id
                    JOIN users u ON u.id = a.user_id WHERE 1=1";
        $cp = [];
        if (!empty($filters['q'])) { $countSql .= " AND (u.full_name LIKE :q1 OR s.scholar_code LIKE :q2 OR r.release_code LIKE :q3)"; $cp['q1'] = $cp['q2'] = $cp['q3'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['year'])) { $countSql .= " AND r.academic_year = :y"; $cp['y'] = $filters['year']; }
        $cStmt = $pdo->prepare($countSql); $cStmt->execute($cp);
        $total = (int)$cStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        // Totals
        $totalAllTime = Scholar::totalReleased();
        $perYear = $pdo->query(
            "SELECT academic_year, SUM(amount) AS total, COUNT(*) AS c
            FROM scholarship_releases
            GROUP BY academic_year ORDER BY academic_year DESC"
        )->fetchAll();

        // Budget info
        $budget = (float)$pdo->query("SELECT COALESCE(SUM(total_budget),0) FROM budgets")->fetchColumn();
        $remaining = $budget - $totalAllTime;
        $years = Budget::academicYears();

        $pageTitle = 'Scholarship Releases';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/releases.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function releaseSave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/releases');
        verify_csrf();
        $result = Scholar::recordRelease($_POST, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', "Release recorded ({$result['release_code']}).");
        else               flash('danger', $result['error']);
        redirect('admin/releases');
    }

    private function releaseDelete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/releases');
        verify_csrf();
        Scholar::deleteRelease((int)$_POST['id'], (int)Auth::user()['id']);
        flash('success', 'Release deleted.');
        redirect('admin/releases');
    }

    /* ================= RENEWALS ================= */
    private function renewals(): void
    {
        $pdo = Database::conn();
        $filter = $_GET['filter'] ?? 'all';

        $sql = "SELECT r.*, s.scholar_code, s.status AS scholar_status,
                    u.full_name AS scholar_name, u.email,
                    ev.full_name AS evaluated_by_name
                FROM scholarship_renewals r
                JOIN scholars s ON s.id = r.scholar_id
                JOIN applicants a ON a.id = s.applicant_id
                JOIN users u ON u.id = a.user_id
                LEFT JOIN users ev ON ev.id = r.evaluated_by
                WHERE 1=1";
        if ($filter !== 'all') { $sql .= " AND r.status = " . $pdo->quote($filter); }
        $sql .= " ORDER BY r.created_at DESC LIMIT 200";
        $renewals = $pdo->query($sql)->fetchAll();

        $pageTitle = 'Renewals';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/renewals.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function renewalView(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $renewal = Scholar::renewal($id);
        if (!$renewal) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $scholar = Scholar::find((int)$renewal['scholar_id']);
        $academics = Scholar::academicRecords((int)$renewal['scholar_id']);

        $pageTitle = 'Renewal';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/renewal_view.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function renewalSave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/renewals');
        verify_csrf();
        $result = Scholar::createRenewal($_POST, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', 'Renewal created.');
        else               flash('danger', $result['error']);
        redirect('admin/renewals');
    }

    private function renewalUpdate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/renewals');
        verify_csrf();
        $result = Scholar::updateRenewal((int)$_POST['id'], $_POST, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', 'Renewal updated.');
        else               flash('danger', $result['error']);
        redirect('admin/renewal-view&id=' . (int)$_POST['id']);
    }

    private function renewalDelete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/renewals');
        verify_csrf();
        Scholar::deleteRenewal((int)$_POST['id'], (int)Auth::user()['id']);
        flash('success', 'Renewal deleted.');
        redirect('admin/renewals');
    }

    /* ================= BUDGET ================= */

    private function budgetIndex(): void
    {
        $budgets  = Budget::all();
        $summary  = Budget::summary();
        $programs = Budget::programs();
        $years    = Budget::academicYears();
        $canEdit  = Auth::hasRole('admin'); // staff/official get read-only per the permission model

        $pageTitle = 'Budget Management';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/budget.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function budgetSave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/budget');
        verify_csrf();
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $result = Budget::save($_POST, $id);
        if ($result['ok']) flash('success', $id ? 'Budget updated.' : 'Budget created.');
        else               flash('danger', $result['error']);
        redirect('admin/budget');
    }

    private function budgetDelete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/budget');
        verify_csrf();
        Budget::delete((int)$_POST['id']);
        flash('success', 'Budget deleted.');
        redirect('admin/budget');
    }

    /* ================= SCHOLARSHIP PROGRAMS ================= */

    private function programs(): void
    {
        $programs    = Program::all();
        $openProgram = Program::open();

        $pageTitle = 'Scholarship Programs';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/programs.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function programSave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/programs');
        verify_csrf();
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $result = Program::save($_POST, $id, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', $id ? 'Program updated.' : 'Program created.');
        else               flash('danger', $result['error']);
        redirect('admin/programs');
    }

    private function programToggle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/programs');
        verify_csrf();
        $state = Program::toggle((int)($_POST['id'] ?? 0));
        if ($state === null) flash('danger', 'Program not found.');
        else                 flash('success', $state ? 'Program activated.' : 'Program deactivated.');
        redirect('admin/programs');
    }

    /* ================= ANALYTICS ================= */
    private function analytics(): void
    {
        $pdo = Database::conn();
        $programs = $pdo->query("SELECT id, program_name FROM scholarship_programs ORDER BY id DESC")->fetchAll();
        $programId = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;

        $kpis                = Analytics::kpis($programId);
        $byStatus            = Analytics::applicantsByStatus($programId);
        $bySchool            = Analytics::applicantsBySchool($programId);
        $byCourse            = Analytics::applicantsByCourse($programId);
        $byYear              = Analytics::applicantsByYearLevel($programId);
        $byCivil             = Analytics::applicantsByCivilStatus($programId);
        $byPurok             = Analytics::applicantsByPurok($programId);
        $byIncome            = Analytics::applicantsByIncomeRange($programId);
        $avgIncome           = Analytics::avgParentalIncome();
        $interviewResults    = Analytics::interviewResults();
        $interviewTrend      = Analytics::interviewAttendanceByMonth();
        $interviewScores     = Analytics::interviewScoresByCriteria();
        $gpaDistribution     = Analytics::gpaDistribution();
        $gpaTrend            = Analytics::gpaTrendByYear();
        $gpaBySchool         = Analytics::gpaBySchool();
        $atRisk              = Analytics::atRiskCount();
        $releaseByYear       = Analytics::releaseByYear();
        $releaseByMonth      = Analytics::releaseByMonth();
        $scholarsByStatus    = Analytics::scholarsByStatus();
        $renewalRate         = Analytics::renewalRate();
        $budgetUtilization   = Analytics::budgetUtilization();
        $trend               = Analytics::applicationsTrend(12);

        $pageTitle = 'Data Analytics';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/analytics.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /* ================= REPORTS ================= */
    private function reports(): void
    {
        $pdo = Database::conn();
        $programs = $pdo->query("SELECT id, program_name, academic_year FROM scholarship_programs ORDER BY id DESC")->fetchAll();
        $reports = self::reportCatalog();

        $pageTitle = 'Reports';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/reports.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private static function reportCatalog(): array
    {
        return [
            'applications'         => ['Scholarship Applicant List',       'All submitted applications with key fields'],
            'approved'             => ['Approved Applicants',               'Applications marked Approved'],
            'rejected'             => ['Rejected Applicants',               'Applications marked Rejected'],
            'waitlisted'           => ['Waitlisted Applicants',             'Applications on waitlist'],
            'documents'            => ['Document Verification Report',      'Status of every uploaded document'],
            'interview_schedule'   => ['Interview Schedule Report',         'All interview schedules with counts'],
            'interview_attendance' => ['Interview Attendance Report',       'Assigned applicants + attendance status'],
            'interview_results'    => ['Interview Evaluation Results',      'Scores + recommendations'],
            'noshow'               => ['No-Show Report',                    'Applicants who did not attend'],
            'scholars'             => ['Scholar Masterlist',                'All scholars with basic info'],
            'scholars_school'      => ['Scholars by School',                'Distribution of scholars per school'],
            'scholars_course'      => ['Scholars by Course',                'Distribution of scholars per course'],
            'scholars_year'        => ['Scholars by Year Level',            'Distribution of scholars per year level'],
            'academic'             => ['Academic Performance Report',       'GPA + standing per record'],
            'releases'             => ['Scholarship Release Report',        'All releases with amounts + totals'],
            'budget'               => ['Budget Utilization Report',         'Budget vs released per program'],
            'renewals'             => ['Renewal Report',                    'Renewal records + statuses'],
            'graduation'           => ['Graduation Report',                 'Scholars with Graduated status'],
            'annual'               => ['Annual Scholarship Accomplishment', 'Summary of key metrics'],
        ];
    }

    private function generateReport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/reports');
        verify_csrf();

        $type      = $_POST['report_type'] ?? '';
        $format    = $_POST['format'] ?? 'pdf';   // pdf | csv
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $isDate    = static fn($d): bool => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
        $dateFrom  = $isDate($_POST['date_from'] ?? null) ? $_POST['date_from'] : null;
        $dateTo    = $isDate($_POST['date_to'] ?? null)   ? $_POST['date_to']   : null;

        $catalog = self::reportCatalog();
        if (!isset($catalog[$type])) { flash('danger', 'Unknown report.'); redirect('admin/reports'); }

        $report = self::buildReport($type, $programId, $dateFrom, $dateTo);
        AuditLog::write('report_generated', 'reports', null,
            "Generated {$catalog[$type][0]} as $format");

        if ($format === 'csv') {
            self::streamCsv($report);
        } else {
            self::streamPdf($report);
        }
        exit;
    }

    private static function programName(PDO $pdo, int $id): string|false
    {
        $s = $pdo->prepare("SELECT program_name FROM scholarship_programs WHERE id = ?");
        $s->execute([$id]);
        return $s->fetchColumn();
    }

    private static function buildReport(string $type, ?int $programId, ?string $from, ?string $to): array
    {
        $pdo = Database::conn();
        $params = array_filter(['pid' => $programId, 'df' => $from, 'dt' => $to], static fn($v) => $v !== null && $v !== '');

        // Program/date filter for a given report: each report names the columns the filters apply to
        // (null = that filter doesn't make sense for this report and is not applied).
        $filter = static function (?string $programCol, ?string $dateCol) use ($programId, $from, $to): string {
            $w = '';
            if ($programId && $programCol) $w .= " AND $programCol = :pid";
            if ($from && $dateCol)         $w .= " AND DATE($dateCol) >= :df";
            if ($to && $dateCol)           $w .= " AND DATE($dateCol) <= :dt";
            return $w;
        };
        $w = $filter('a.program_id', 'a.created_at');   // application-based reports

        switch ($type) {
            case 'applications':
                $sql = "SELECT a.application_code AS 'Application Code', u.full_name AS 'Name',
                            u.email AS 'Email', e.school_name AS 'School', e.course_name AS 'Course',
                            e.year_level AS 'Year Level', f.total_family_income AS 'Family Income',
                            a.status AS 'Status', DATE(a.submitted_at) AS 'Submitted'
                        FROM applications a
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        LEFT JOIN family_background f ON f.applicant_id = ap.id
                        WHERE 1=1 $w
                        ORDER BY a.created_at DESC";
                break;

            case 'approved': case 'rejected': case 'waitlisted':
                $params['status'] = ucfirst($type);
                $sql = "SELECT a.application_code AS 'Application Code', u.full_name AS 'Name',
                            u.email AS 'Email', a.status AS 'Status',
                            DATE(a.decided_at) AS 'Decided', a.decision_remarks AS 'Remarks'
                        FROM applications a
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE a.status = :status $w
                        ORDER BY a.decided_at DESC";
                break;

            case 'documents':
                $sql = "SELECT a.application_code AS 'Application Code', u.full_name AS 'Applicant',
                            d.document_type AS 'Document', d.status AS 'Status',
                            DATE(d.uploaded_at) AS 'Uploaded', DATE(d.reviewed_at) AS 'Reviewed',
                            d.remarks AS 'Remarks'
                        FROM documents d
                        JOIN applications a ON a.id = d.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE d.status <> 'Not Submitted' $w
                        ORDER BY d.uploaded_at DESC";
                break;

            case 'interview_schedule':
                $sql = "SELECT DATE(s.interview_date) AS 'Date', s.start_time AS 'Start', s.end_time AS 'End',
                            s.venue AS 'Venue', s.interviewer AS 'Interviewer',
                            s.max_slots AS 'Max Slots',
                            (SELECT COUNT(*) FROM interview_assignments ia WHERE ia.schedule_id=s.id) AS 'Assigned',
                            s.status AS 'Status'
                        FROM interview_schedules s
                        WHERE 1=1 " . $filter('s.program_id', 's.interview_date') . "
                        ORDER BY s.interview_date DESC";
                break;

            case 'interview_attendance':
                $sql = "SELECT DATE(s.interview_date) AS 'Date', s.start_time AS 'Start',
                            u.full_name AS 'Applicant', a.application_code AS 'Code',
                            ia.assignment_status AS 'Status'
                        FROM interview_assignments ia
                        JOIN interview_schedules s ON s.id = ia.schedule_id
                        JOIN applications a ON a.id = ia.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 " . $filter('s.program_id', 's.interview_date') . "
                        ORDER BY s.interview_date DESC, u.full_name";
                break;

            case 'interview_results':
                $sql = "SELECT a.application_code AS 'Code', u.full_name AS 'Applicant',
                            ev.financial_need_score AS 'Financial', ev.academic_score AS 'Academic',
                            ev.motivation_score AS 'Motivation', ev.community_score AS 'Community',
                            ev.purpose_score AS 'Purpose', ev.total_score AS 'Total',
                            ev.rating AS 'Rating', ev.result AS 'Result',
                            DATE(ev.evaluated_at) AS 'Evaluated'
                        FROM interview_evaluations ev
                        JOIN interview_assignments ia ON ia.id = ev.assignment_id
                        JOIN interview_schedules s ON s.id = ia.schedule_id
                        JOIN applications a ON a.id = ia.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 " . $filter('s.program_id', 's.interview_date') . "
                        ORDER BY ev.total_score DESC";
                break;

            case 'noshow':
                $sql = "SELECT a.application_code AS 'Code', u.full_name AS 'Applicant',
                            DATE(s.interview_date) AS 'Interview Date', s.venue AS 'Venue'
                        FROM interview_assignments ia
                        JOIN interview_schedules s ON s.id = ia.schedule_id
                        JOIN applications a ON a.id = ia.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE ia.assignment_status = 'No Show' " . $filter('s.program_id', 's.interview_date') . "
                        ORDER BY s.interview_date DESC";
                break;

            case 'scholars':
                $sql = "SELECT s.scholar_code AS 'Scholar ID', u.full_name AS 'Name', u.email AS 'Email',
                            e.school_name AS 'School', e.course_name AS 'Course', e.year_level AS 'Year',
                            DATE(s.approval_date) AS 'Approved', s.status AS 'Status',
                            (SELECT COALESCE(SUM(amount),0) FROM scholarship_releases r WHERE r.scholar_id=s.id) AS 'Total Released'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE 1=1 " . $filter('s.program_id', 's.approval_date') . "
                        ORDER BY s.approval_date DESC";
                break;

            case 'scholars_school':
                $sql = "SELECT COALESCE(e.school_name, 'Unknown') AS 'School', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active' " . $filter('s.program_id', 's.approval_date') . "
                        GROUP BY e.school_name ORDER BY COUNT(*) DESC";
                break;

            case 'scholars_course':
                $sql = "SELECT COALESCE(e.course_name, 'Unknown') AS 'Course', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active' " . $filter('s.program_id', 's.approval_date') . "
                        GROUP BY e.course_name ORDER BY COUNT(*) DESC";
                break;

            case 'scholars_year':
                $sql = "SELECT COALESCE(e.year_level, 'Unknown') AS 'Year Level', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active' " . $filter('s.program_id', 's.approval_date') . "
                        GROUP BY e.year_level
                        ORDER BY FIELD(e.year_level,'1st Year','2nd Year','3rd Year','4th Year','5th Year','Other')";
                break;

            case 'academic':
                $sql = "SELECT s.scholar_code AS 'Scholar ID', u.full_name AS 'Scholar',
                            ar.academic_year AS 'Academic Year', ar.semester AS 'Semester',
                            ar.year_level AS 'Year Level', ar.school AS 'School', ar.course AS 'Course',
                            ar.gpa AS 'GPA', ar.academic_standing AS 'Standing'
                        FROM academic_records ar
                        JOIN scholars s ON s.id = ar.scholar_id
                        JOIN applicants ap ON ap.id = s.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 " . $filter('s.program_id', null) . "
                        ORDER BY ar.academic_year DESC, ar.gpa ASC";
                break;

            case 'releases':
                $sql = "SELECT r.release_code AS 'Release Code', s.scholar_code AS 'Scholar ID',
                            u.full_name AS 'Scholar', r.academic_year AS 'A.Y.', r.semester AS 'Semester',
                            r.amount AS 'Amount', DATE(r.release_date) AS 'Date',
                            r.payment_method AS 'Method', r.reference_number AS 'Reference'
                        FROM scholarship_releases r
                        JOIN scholars s ON s.id = r.scholar_id
                        JOIN applicants ap ON ap.id = s.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 " . $filter('s.program_id', 'r.release_date') . "
                        ORDER BY r.release_date DESC";
                break;

            case 'budget':
                $sql = "SELECT sp.program_name AS 'Program', COALESCE(b.academic_year, '-') AS 'Academic Year',
                            COALESCE(b.total_budget, 0) AS 'Total Budget',
                            COALESCE(b.allocated, 0) AS 'Allocated',
                            COALESCE(b.released, 0) AS 'Total Released',
                            COALESCE(b.total_budget, 0) - COALESCE(b.released, 0) AS 'Remaining',
                            CASE WHEN COALESCE(b.total_budget,0) > 0
                                 THEN ROUND(COALESCE(b.released,0) / b.total_budget * 100, 1)
                                 ELSE 0 END AS 'Utilization %'
                        FROM scholarship_programs sp
                        LEFT JOIN budgets b ON b.program_id = sp.id
                        WHERE 1=1 " . $filter('sp.id', null) . "
                        ORDER BY sp.id DESC";
                break;

            case 'renewals':
                $sql = "SELECT s.scholar_code AS 'Scholar ID', u.full_name AS 'Scholar',
                            r.academic_year AS 'A.Y.', r.semester AS 'Sem', r.gpa AS 'GPA',
                            r.status AS 'Status', DATE(r.evaluated_at) AS 'Evaluated'
                        FROM scholarship_renewals r
                        JOIN scholars s ON s.id = r.scholar_id
                        JOIN applicants ap ON ap.id = s.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE 1=1 " . $filter('s.program_id', null) . "
                        ORDER BY r.created_at DESC";
                break;

            case 'graduation':
                $sql = "SELECT s.scholar_code AS 'Scholar ID', u.full_name AS 'Scholar',
                            e.school_name AS 'School', e.course_name AS 'Course',
                            DATE(s.approval_date) AS 'First Approved',
                            (SELECT COALESCE(SUM(amount),0) FROM scholarship_releases r WHERE r.scholar_id=s.id) AS 'Total Received'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Graduated' " . $filter('s.program_id', null) . "
                        ORDER BY u.full_name";
                break;

            case 'annual':
                $k = Analytics::kpis($programId);
                return [
                    'title'   => 'Annual Scholarship Accomplishment Report',
                    'headers' => ['Metric', 'Value'],
                    'rows'    => [
                        ['Total Applicants',        $k['total_applicants']],
                        ['Approved',                $k['approved']],
                        ['Rejected',                $k['rejected']],
                        ['Waitlisted',              $k['waitlisted']],
                        ['Interviewed',             $k['interviewed']],
                        ['Total Scholars',          $k['total_scholars']],
                        ['Active Scholars',         $k['active_scholars']],
                        ['Graduated',               $k['graduated']],
                        ['On Probation',            $k['on_probation']],
                        ['Average GPA',             $k['avg_gpa'] ?? '—'],
                        ['Interview Attendance',    $k['attendance_rate'] . '%'],
                        ['Approval Rate',           $k['approval_rate'] . '%'],
                        ['Total Funds Released',    '₱' . number_format($k['total_released'], 2)],
                        ['Total Budget',            '₱' . number_format($k['total_budget'], 2)],
                        ['Remaining Budget',        '₱' . number_format($k['remaining_budget'], 2)],
                    ],
                    'meta' => [],
                ];

            default:
                $sql = "SELECT 1 AS 'N/A'";
        }

        // Bind ONLY the placeholders this report's SQL actually uses (binding an unused one throws SQLSTATE[HY093]).
        $uses = static fn(string $name): bool => preg_match('/:' . $name . '\b/', $sql) === 1;
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if ($uses($k)) $stmt->bindValue(":$k", $v, $k === 'pid' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $catalog = self::reportCatalog();
        $notApplied = 'Not applicable to this report';
        return [
            'title'   => $catalog[$type][0],
            'headers' => $rows ? array_keys($rows[0]) : [],
            'rows'    => array_map('array_values', $rows),
            'meta'    => [
                'Program' => $programId ? ($uses('pid') ? (self::programName($pdo, (int)$programId) ?: '—') : $notApplied) : 'All Programs',
                'From'    => $from ? ($uses('df') ? $from : $notApplied) : 'Any',
                'To'      => $to   ? ($uses('dt') ? $to   : $notApplied) : 'Any',
                'Generated' => date('F d, Y g:i A'),
            ],
        ];
    }

    private static function streamCsv(array $report): void
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($report['title']));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $slug . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
        fputcsv($out, [$report['title']]);
        foreach ($report['meta'] as $k => $v) fputcsv($out, [$k, $v]);
        fputcsv($out, []);
        fputcsv($out, $report['headers']);
        // Neutralise spreadsheet formula injection: text starting with = + - @ TAB CR would execute in Excel/Sheets.
        $safe = static fn($c) => (is_string($c) && $c !== '' && !is_numeric($c) && preg_match('/^[=+\-@\t\r]/', $c)) ? "'" . $c : $c;
        foreach ($report['rows'] as $row) fputcsv($out, array_map($safe, $row));
        fclose($out);
    }

    private static function streamPdf(array $report): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            flash('danger', 'DomPDF not installed. Run: composer require dompdf/dompdf');
            redirect('admin/reports');
        }

        // Build HTML
        ob_start();
        ?>
        <!doctype html>
        <html><head><meta charset="utf-8">
        <style>
        @page { margin: 30px 30px 50px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 16px; color: #1d4ed8; }
        .header h2 { margin: 4px 0 0; font-size: 13px; font-weight: normal; color: #334155; }
        .header .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
        .meta { margin-bottom: 10px; font-size: 9px; }
        .meta table { width: 100%; }
        .meta td { padding: 2px 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #1d4ed8; color: #fff; text-align: left;
            padding: 5px 6px; border: 1px solid #1e3a8a; font-size: 9px;
        }
        table.data td { padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 9px; }
        table.data tr:nth-child(even) td { background: #f1f5f9; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center;
                    font-size: 8px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 4px; }
        .sig { margin-top: 30px; width: 100%; }
        .sig td { width: 50%; text-align: center; padding-top: 40px; font-size: 9px; }
        .sig .line { border-top: 1px solid #475569; width: 70%; margin: 0 auto; padding-top: 4px; }
        </style>
        </head><body>

        <div class="header">
        <h1>SK Scholarship Management Information System</h1>
        <h2><?= htmlspecialchars($report['title']) ?></h2>
        <div class="sub">Sangguniang Kabataan · Barangay Estefania · Bacolod City</div>
        </div>

        <div class="meta">
        <table>
            <tr>
            <td><strong>Program:</strong> <?= htmlspecialchars($report['meta']['Program'] ?? '') ?></td>
            <td><strong>Date Range:</strong> <?= htmlspecialchars(($report['meta']['From'] ?? '') . ' to ' . ($report['meta']['To'] ?? '')) ?></td>
            <td><strong>Generated:</strong> <?= htmlspecialchars($report['meta']['Generated'] ?? '') ?></td>
            </tr>
        </table>
        </div>

        <table class="data">
        <thead>
            <tr><?php foreach ($report['headers'] as $h): ?><th><?= htmlspecialchars((string)$h) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
            <?php if (empty($report['rows'])): ?>
            <tr><td colspan="<?= count($report['headers']) ?>" style="text-align:center;">No records found.</td></tr>
            <?php else: foreach ($report['rows'] as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; endif; ?>
        </tbody>
        </table>

        <table class="sig">
        <tr>
            <td><div class="line">Prepared by</div></td>
            <td><div class="line">Approved by</div></td>
        </tr>
        </table>

        <div class="footer">
        Generated by SK Scholarship MIS · Page <span class="pageNumber"></span>
        </div>
        </body></html>
        <?php
        $html = ob_get_clean();

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Stream (I = inline, D = download)
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($report['title']));
        $dompdf->stream($slug . '-' . date('Ymd-His') . '.pdf', ['Attachment' => false]);
    }
    /* ================= USERS ================= */
    private function users(): void
    {
        // Only admin can manage users
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }

        $pdo = Database::conn();
        $filters = [
            'q'    => trim($_GET['q'] ?? ''),
            'role' => $_GET['role'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        // Explicit columns: password_hash / reset_token / verify_token must never reach the browser.
        $sql = "SELECT u.id, u.role_id, u.full_name, u.email, u.contact_number, u.is_active, u.last_login, u.created_at, r.role_name,
                    (SELECT COUNT(*) FROM applicants ap WHERE ap.user_id = u.id) AS is_applicant
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q1 OR u.email LIKE :q2)";
            $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['role'])) {
            $sql .= " AND r.role_name = :role";
            $params['role'] = $filters['role'];
        }
        $sql .= " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // count
        $countSql = "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
        $cp = [];
        if (!empty($filters['q'])) { $countSql .= " AND (u.full_name LIKE :q1 OR u.email LIKE :q2)"; $cp['q1'] = $cp['q2'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['role'])) { $countSql .= " AND r.role_name = :role"; $cp['role'] = $filters['role']; }
        $cStmt = $pdo->prepare($countSql); $cStmt->execute($cp);
        $total = (int)$cStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        $roles = $pdo->query("SELECT id, role_name, description FROM roles ORDER BY id")->fetchAll();

        $pageTitle = 'Users';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/users.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function userSave(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        verify_csrf();

        $pdo = Database::conn();
        $id       = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $contact  = trim($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($fullName === '' || $email === '' || $roleId <= 0) {
            flash('danger', 'Name, email, and role are required.');
            redirect('admin/users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Invalid email address.');
            redirect('admin/users');
        }
        $rc = $pdo->prepare("SELECT 1 FROM roles WHERE id = ?");
        $rc->execute([$roleId]);
        if (!$rc->fetchColumn()) {
            flash('danger', 'Invalid role.');
            redirect('admin/users');
        }
        if ($id > 0 && $id === (int)Auth::user()['id'] && $roleId !== (int)Auth::user()['role_id']) {
            flash('danger', 'You cannot change your own role.');
            redirect('admin/users');
        }

        if ($id > 0) {
            // Update
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) { flash('danger', 'Email already used.'); redirect('admin/users'); }

            $sql = "UPDATE users SET full_name=?, email=?, role_id=?, contact_number=? WHERE id=?";
            $pdo->prepare($sql)->execute([$fullName, $email, $roleId, $contact, $id]);

            if ($password !== '') {
                if (strlen($password) < PASSWORD_MIN_LENGTH) {
                    flash('warning', 'Password was not updated (too short).');
                } else {
                    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            }

            AuditLog::write('user_updated', 'users', $id, "Updated user $email");
            flash('success', 'User updated.');
        } else {
            // Create
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) { flash('danger', 'Email already exists.'); redirect('admin/users'); }
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
                redirect('admin/users');
            }

            $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role_id, contact_number, is_active)
                VALUES (?, ?, ?, ?, ?, 1)"
            )->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $roleId, $contact]);

            $newId = (int)$pdo->lastInsertId();
            AuditLog::write('user_created', 'users', $newId, "Created user $email");
            flash('success', 'User created.');
        }

        redirect('admin/users');
    }

    private function userToggle(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        verify_csrf();

        $id = (int)$_POST['id'];
        if ($id === (int)Auth::user()['id']) {
            flash('danger', 'You cannot deactivate your own account.');
            redirect('admin/users');
        }

        $pdo = Database::conn();
        $u = $pdo->prepare("SELECT is_active, email FROM users WHERE id = ?");
        $u->execute([$id]);
        $row = $u->fetch();
        if (!$row) { flash('danger', 'User not found.'); redirect('admin/users'); }

        $newState = $row['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newState, $id]);

        AuditLog::write('user_status_changed', 'users', $id,
            ($newState ? 'Activated' : 'Deactivated') . " user {$row['email']}");

        flash('success', $newState ? 'User activated.' : 'User deactivated.');
        redirect('admin/users');
    }

    private function userReset(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        verify_csrf();

        $id = (int)$_POST['id'];
        $newPassword = $_POST['new_password'] ?? '';

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            redirect('admin/users');
        }

        Database::conn()->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);

        AuditLog::write('user_password_reset', 'users', $id, 'Admin reset password');
        flash('success', 'Password reset successfully.');
        redirect('admin/users');
    }

    private function userDelete(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users');
        verify_csrf();

        $id = (int)$_POST['id'];
        if ($id === (int)Auth::user()['id']) {
            flash('danger', 'You cannot delete your own account.');
            redirect('admin/users');
        }

        $pdo = Database::conn();
        $chk = $pdo->prepare("SELECT email, role_id FROM users WHERE id = ?");
        $chk->execute([$id]);
        $row = $chk->fetch();
        if (!$row) { flash('danger', 'User not found.'); redirect('admin/users'); }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            AuditLog::write('user_deleted', 'users', $id, "Deleted user {$row['email']}");
            $pdo->commit();
            flash('success', 'User deleted.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', 'Cannot delete user — they may have dependent records. Deactivate instead.');
        }

        redirect('admin/users');
    }
    /* ================= SETTINGS ================= */
    private function settings(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }

        $pdo = Database::conn();
        $settings = $pdo->query("SELECT * FROM system_settings ORDER BY setting_key")->fetchAll();
        $mailStatus = Notification::mailConfigStatus();

        $pageTitle = 'System Settings';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/settings.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    /**
     * Admin-only development/testing helper (Task 10): sends a fixed test message to an address
     * the admin types in. POST + CSRF + admin role only — there is no public/unauthenticated route
     * for this. Never echoes SMTP credentials; in production shows a generic failure message.
     */
    private function settingsTestEmail(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/settings');
        verify_csrf();

        $to = strtolower(trim((string)($_POST['test_email'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Enter a valid e-mail address to send the test to.');
            redirect('admin/settings');
        }

        $result = Notification::sendTestEmail($to);
        AuditLog::write(
            $result['ok'] ? 'test_email_sent' : 'test_email_failed',
            'system_settings', null,
            $result['ok'] ? 'Test e-mail sent successfully' : 'Test e-mail failed to send'
        );

        if ($result['ok']) {
            flash('success', 'SUCCESS: Test email sent successfully.');
        } else {
            flash('danger', APP_DEBUG
                ? 'FAILED: Unable to send test email. Check storage/logs/app.log for the SMTP error.'
                : 'FAILED: Unable to send test email.');
        }
        redirect('admin/settings');
    }

    private function settingsSave(): void
    {
        if (!Auth::hasRole('admin')) { http_response_code(403); die('Admin access only.'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/settings');
        verify_csrf();

        $pdo = Database::conn();
        $allowedKeys = ['site_name','barangay_name','max_file_size_mb','allowed_doc_types',
                        'email_notifications','interview_reminder_hours','application_open'];

        foreach ($_POST as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) continue;

            if ($key === 'email_notifications' || $key === 'application_open') {
                $value = $value ? '1' : '0';
            }
            if (in_array($key, ['max_file_size_mb','interview_reminder_hours'], true)) {
                $value = (string)max(0, (int)$value);
            }

            $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([$key, (string)$value]);
        }

        AuditLog::write('settings_updated', 'system_settings', null, 'System settings updated');
        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }
    /* ================= AUDIT LOGS ================= */
    private function auditLogs(): void
    {
        if (!Auth::hasRole('admin')) { Auth::deny(); }

        $pdo = Database::conn();
        $filters = [
            'q'           => trim($_GET['q'] ?? ''),
            'action'      => $_GET['action'] ?? '',
            'entity_type' => $_GET['entity_type'] ?? '',
            'user_id'     => $_GET['user_id'] ?? '',
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT al.*, u.full_name AS actor_name, u.email AS actor_email
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (al.description LIKE :q1 OR al.action LIKE :q2)";
            $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['action']))      { $sql .= " AND al.action = :a"; $params['a'] = $filters['action']; }
        if (!empty($filters['entity_type'])) { $sql .= " AND al.entity_type = :et"; $params['et'] = $filters['entity_type']; }
        if (!empty($filters['user_id']))     { $sql .= " AND al.user_id = :uid"; $params['uid'] = (int)$filters['user_id']; }
        if (!empty($filters['date_from']))   { $sql .= " AND DATE(al.created_at) >= :df"; $params['df'] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $sql .= " AND DATE(al.created_at) <= :dt"; $params['dt'] = $filters['date_to']; }
        $sql .= " ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // counts
        $countSql = "SELECT COUNT(*) FROM audit_logs al WHERE 1=1";
        $cp = [];
        if (!empty($filters['q']))           { $countSql .= " AND (al.description LIKE :q1 OR al.action LIKE :q2)"; $cp['q1'] = $cp['q2'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['action']))      { $countSql .= " AND al.action = :a"; $cp['a'] = $filters['action']; }
        if (!empty($filters['entity_type'])) { $countSql .= " AND al.entity_type = :et"; $cp['et'] = $filters['entity_type']; }
        if (!empty($filters['user_id']))     { $countSql .= " AND al.user_id = :uid"; $cp['uid'] = (int)$filters['user_id']; }
        if (!empty($filters['date_from']))   { $countSql .= " AND DATE(al.created_at) >= :df"; $cp['df'] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $countSql .= " AND DATE(al.created_at) <= :dt"; $cp['dt'] = $filters['date_to']; }
        $cStmt = $pdo->prepare($countSql); $cStmt->execute($cp);
        $total = (int)$cStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        // Distinct filter options
        $actions      = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
        $entityTypes  = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll();
        $users        = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

        $pageTitle = 'Audit Logs';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/admin/audit_Logs.php';   // file on disk is audit_Logs.php (case-sensitive on Linux)
        require VIEW_PATH . '/layouts/footer.php';
    }
}