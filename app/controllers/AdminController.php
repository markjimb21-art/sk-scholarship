<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Analytics.php';

final class AdminController
{
    public function handle(string $action): void
    {
        $action = trim($action, '/');

        // Any user in admin area must be admin/staff/official
        Auth::requireRole('admin', 'staff', 'official');

        switch ($action) {
            case 'dashboard':                  $this->dashboard(); break;
            case 'applications':               $this->applications(); break;
            case 'applications/view':          $this->viewApplication(); break;
            case 'applications/update':        $this->updateApplication(); break;
            case 'applicants':                 $this->applicants(); break;
            case 'documents':                  $this->documents(); break;
            case 'documents/view':             $this->viewDocument(); break;
            case 'documents/review':           $this->reviewDocument(); break;
            case 'notifications':              $this->notifications(); break;
            case 'interview-scheduling':       $this->interviewScheduling(); break;
            case 'interview-schedule-save':    $this->saveInterviewSchedule(); break;
            case 'interview-schedule-update':  $this->updateInterviewSchedule(); break;
            case 'interview-schedule-cancel':  $this->cancelInterviewSchedule(); break;
            case 'interview-schedule-detail':  $this->interviewScheduleDetail(); break;
            case 'interview-assign':           $this->assignInterview(); break;
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
            case 'analytics':                  $this->analytics(); break;
            case 'reports':                    $this->reports(); break;
            case 'reports/generate':           $this->generateReport(); break;
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

        $userId = (int)Auth::user()['id'];
        $pdo = Database::conn();

        try {
            $pdo->beginTransaction();

            switch ($action) {
                case 'start_review':
                    Application::updateStatus($id, 'Under Initial Review', $userId);
                    Notification::send((int)$app['applicant_id'] ? $this->userIdFromApplicant((int)$app['applicant_id']) : 0,
                        'Application Under Review',
                        'Your application is now being reviewed by the SK office.',
                        'info', url('applicant/dashboard'));
                    flash('success', 'Marked as under review.');
                    break;

                case 'mark_incomplete':
                    Application::updateStatus($id, 'Incomplete', $userId);
                    flash('warning', 'Marked as incomplete.');
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
                    flash('success', 'Documents marked verified.');
                    break;

                case 'for_interview':
                    Application::updateStatus($id, 'For Interview', $userId);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Eligible for Interview',
                        'You are now eligible for an interview. Please wait for scheduling.',
                        'success', url('applicant/interview'));
                    flash('success', 'Marked as For Interview.');
                    break;

                case 'for_final':
                    Application::updateStatus($id, 'For Final Evaluation', $userId);
                    flash('success', 'Moved to final evaluation.');
                    break;

                case 'approve':
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
                        'danger', url('applicant/dashboard'));
                    flash('warning', 'Application rejected.');
                    break;

                case 'waitlist':
                    Application::updateStatus($id, 'Waitlisted', $userId);
                    $pdo->prepare("UPDATE applications SET decided_at=NOW(), decided_by=?, decision_remarks=? WHERE id=?")
                        ->execute([$userId, $_POST['remarks'] ?? 'Placed on waitlist.', $id]);
                    Notification::send($this->userIdFromApplicant((int)$app['applicant_id']),
                        'Waitlisted',
                        'You have been placed on the waitlist. We will notify you if a slot opens.',
                        'warning', url('applicant/dashboard'));
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
        $id = (int)($_GET['id'] ?? 0);
        $doc = Document::find($id);
        if (!$doc || !$doc['file_path']) { http_response_code(404); die('Document not found.'); }

        $path = PUBLIC_PATH . '/' . $doc['file_path'];
        if (!is_file($path)) { http_response_code(404); die('File missing on disk.'); }

        $download = isset($_GET['download']);
        $mime = $doc['mime_type'] ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        if ($download) {
            header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
        } else {
            header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
        }
        readfile($path);
        exit;
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
            Notification::send(
                (int)$row['user_id'], $title,
                "Your {$row['document_type']} has been marked as: $status." .
                ($remarks ? " Remarks: $remarks" : ''),
                $type, url('applicant/documents')
            );
        }

        flash('success', "Document marked as $status.");
        redirect($_POST['redirect'] ?? 'admin/documents');
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
        $program   = $programId ? $pdo->query("SELECT * FROM scholarship_programs WHERE id = $programId")->fetch() : null;
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

            $events[] = [
                'id'    => (int)$s['id'],
                'title' => sprintf('%s – %s (%d/%d)',
                    date('g:iA', strtotime($s['start_time'])),
                    date('g:iA', strtotime($s['end_time'])),
                    (int)$s['assigned_count'],
                    (int)$s['max_slots']
                ),
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
        if (!empty($filters['q'])) { $sql .= " AND (u.full_name LIKE :q OR s.scholar_code LIKE :q OR r.release_code LIKE :q)"; $params['q'] = '%'.$filters['q'].'%'; }
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
        if (!empty($filters['q'])) { $countSql .= " AND (u.full_name LIKE :q OR s.scholar_code LIKE :q)"; $cp['q'] = '%'.$filters['q'].'%'; }
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
        $dateFrom  = $_POST['date_from'] ?? null;
        $dateTo    = $_POST['date_to']   ?? null;

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

    private static function buildReport(string $type, ?int $programId, ?string $from, ?string $to): array
    {
        $pdo = Database::conn();
        $params = [];
        $w = "";
        if ($programId) { $w .= " AND a.program_id = :pid"; $params['pid'] = $programId; }
        if ($from)      { $w .= " AND DATE(a.created_at) >= :df"; $params['df'] = $from; }
        if ($to)        { $w .= " AND DATE(a.created_at) <= :dt"; $params['dt'] = $to; }

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
                $status = ucfirst($type);
                $sql = "SELECT a.application_code AS 'Application Code', u.full_name AS 'Name',
                            u.email AS 'Email', a.status AS 'Status',
                            DATE(a.decided_at) AS 'Decided', a.decision_remarks AS 'Remarks'
                        FROM applications a
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
                        WHERE a.status = '$status' $w
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
                        JOIN applications a ON a.id = ia.application_id
                        JOIN applicants ap ON ap.id = a.applicant_id
                        JOIN users u ON u.id = ap.user_id
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
                        WHERE ia.assignment_status = 'No Show'
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
                        ORDER BY s.approval_date DESC";
                break;

            case 'scholars_school':
                $sql = "SELECT COALESCE(e.school_name, 'Unknown') AS 'School', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active'
                        GROUP BY e.school_name ORDER BY COUNT(*) DESC";
                break;

            case 'scholars_course':
                $sql = "SELECT COALESCE(e.course_name, 'Unknown') AS 'Course', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active'
                        GROUP BY e.course_name ORDER BY COUNT(*) DESC";
                break;

            case 'scholars_year':
                $sql = "SELECT COALESCE(e.year_level, 'Unknown') AS 'Year Level', COUNT(*) AS 'Scholar Count'
                        FROM scholars s
                        JOIN applicants ap ON ap.id = s.applicant_id
                        LEFT JOIN education_records e ON e.applicant_id = ap.id
                        WHERE s.status = 'Active'
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
                        ORDER BY r.release_date DESC";
                break;

            case 'budget':
                $sql = "SELECT sp.program_name AS 'Program', COALESCE(b.academic_year, '-') AS 'Academic Year',
                            COALESCE(b.total_budget, 0) AS 'Total Budget',
                            COALESCE(b.released, 0) AS 'Released',
                            COALESCE(b.total_budget, 0) - COALESCE(b.released, 0) AS 'Remaining'
                        FROM scholarship_programs sp
                        LEFT JOIN budgets b ON b.program_id = sp.id
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
                        WHERE s.status = 'Graduated'
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

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $catalog = self::reportCatalog();
        return [
            'title'   => $catalog[$type][0],
            'headers' => $rows ? array_keys($rows[0]) : [],
            'rows'    => array_map('array_values', $rows),
            'meta'    => [
                'Program' => $programId ? ($pdo->query("SELECT program_name FROM scholarship_programs WHERE id=$programId")->fetchColumn() ?: '—') : 'All Programs',
                'From'    => $from ?: 'Any',
                'To'      => $to   ?: 'Any',
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
        foreach ($report['rows'] as $row) fputcsv($out, $row);
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
}