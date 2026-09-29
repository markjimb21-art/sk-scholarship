<?php
declare(strict_types=1);

/** B2 Screening: the Submitted queue and the one page per application (also used read-only for any status). */
final class ScreeningController
{
    /** Queue: Submitted applications, oldest first. */
    public function queue(): void
    {
        $programId = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;
        $rows = Application::search(['status' => 'Submitted', 'program_id' => $programId], 200);
        $programs = Program::options();
        render('admin/screening', compact('rows', 'programs', 'programId'), 'Screening');
    }

    /** One application: form data, every document, the eligibility check, and the buttons its status allows. */
    public function view(): void
    {
        $app = Application::withDetails((int)($_GET['id'] ?? 0));
        if (!$app) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }

        $eligibility = Application::eligibility($app);
        $scholar = Scholar::forApplication((int)$app['id']);
        $attempts = $this->attempts((int)$app['id']);
        $audit = Database::conn()->prepare(
            "SELECT al.*, u.full_name AS actor FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE (al.entity_type = 'applications' AND al.entity_id = ?)
                OR (al.entity_type = 'documents' AND al.entity_id IN (SELECT id FROM documents WHERE application_id = ?))
             ORDER BY al.created_at DESC, al.id DESC LIMIT 50"
        );
        $audit->execute([(int)$app['id'], (int)$app['id']]);
        $auditTrail = $audit->fetchAll();

        // School ID / Enrollment Form: the applicant's newest version (maybe from a claim or renewal) + every version.
        $currentDocs = $docHistory = [];
        foreach (VERSIONED_DOCUMENT_TYPES as $type) {
            $currentDocs[$type] = Document::current((int)$app['applicant_id'], $type);
            $docHistory[$type] = Document::history((int)$app['applicant_id'], $type);
        }

        render('admin/application_view', compact('app', 'eligibility', 'scholar', 'attempts', 'auditTrail', 'currentDocs', 'docHistory'),
            'Application ' . $app['application_code']);
    }

    /** Every interview attempt of the application, with its score. */
    private function attempts(int $applicationId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.status AS schedule_status, ev.total_score
             FROM interview_assignments ia
             JOIN interview_schedules s ON s.id = ia.schedule_id
             LEFT JOIN interview_evaluations ev ON ev.assignment_id = ia.id
             WHERE ia.application_id = ? ORDER BY ia.id"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    /** Qualify for interview | Return for correction (remarks) | Reject (reason). */
    public function decide(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        require_post('admin/application&id=' . $id);
        $result = match ($_POST['decision'] ?? '') {
            'qualify' => Application::qualify($id, admin_id()),
            'return'  => Application::returnForCorrection($id, admin_id(), (string)($_POST['remarks'] ?? '')),
            'reject'  => Application::reject($id, admin_id(), (string)($_POST['reason'] ?? '')),
            default   => ['ok' => false, 'error' => 'Unknown decision.'],
        };
        if (!$result['ok']) {
            flash('danger', $result['error']);
            redirect('admin/application&id=' . $id);
        }
        $done = ['qualify' => 'Qualified for interview.', 'return' => 'Returned for correction.', 'reject' => 'Application rejected.'];
        flash('success', $done[$_POST['decision']] . ' The applicant was notified.');
        redirect('admin/screening');
    }

    /** Stream an application document (admin can open any). */
    public function document(): void
    {
        $doc = Document::find((int)($_GET['id'] ?? 0));
        if (!$doc || $doc['file_path'] === '') { http_response_code(404); die('Document not found.'); }
        Document::sendApplicationDocument($doc, isset($_GET['download']));
    }
}
