<?php
declare(strict_types=1);

/** B2 Screening: the Submitted queue and the one page per application (also used read-only for any status). */
final class ScreeningController
{
    private const QUEUE_LIMIT = 200;   // the screening queue lists at most this many applications

    /** Queue: Submitted applications, oldest first. */
    public function queue(): void
    {
        $programId = get_id('program_id');
        $rows = ApplicationSearch::search(['status' => 'Submitted', 'program_id' => $programId], self::QUEUE_LIMIT);
        $programs = Program::options();
        render('admin/screening', compact('rows', 'programs', 'programId'), 'Screening');
    }

    /** One application: form data, every document, the eligibility check, and the buttons its status allows. */
    public function view(): void
    {
        $app = Application::withDetails(get_int('id'));
        if (!$app) not_found();

        $eligibility = ApplicationEligibility::evaluate($app);
        $scholar = Scholar::forApplication((int)$app['id']);
        $attempts = InterviewAssignment::attemptsForApplication((int)$app['id']);
        $auditTrail = AuditLog::forApplication((int)$app['id']);

        // School ID / Enrollment Form: the applicant's newest version (maybe from a claim or renewal). History: every
        // version of every document type, including replaced and invalid ones.
        $currentDocs = $docHistory = [];
        foreach (VERSIONED_DOCUMENT_TYPES as $type) $currentDocs[$type] = Document::current((int)$app['applicant_id'], $type);
        foreach (array_unique(array_merge(DOCUMENT_TYPES, VERSIONED_DOCUMENT_TYPES)) as $type) {
            $docHistory[$type] = Document::history((int)$app['applicant_id'], $type);
        }
        $fieldHistory = RecordHistory::forApplicant((int)$app['applicant_id']);
        $flags = Application::flags($app);

        render('admin/application_view', compact('app', 'eligibility', 'scholar', 'attempts', 'auditTrail', 'currentDocs', 'docHistory', 'fieldHistory', 'flags'),
            'Application ' . $app['application_code']);
    }


    /** Qualify for interview | Return for correction (remarks) | Reject (reason). */
    public function decide(): void
    {
        $id = post_int('id');
        require_post('admin/application&id=' . $id);
        $result = match ($_POST['decision'] ?? '') {
            'qualify' => Application::qualify($id, admin_id()),
            'return'  => Application::returnForCorrection($id, admin_id(), post_str('remarks'),
                             array_map('strval', (array)($_POST['flags'] ?? [])), array_map('strval', (array)($_POST['invalid_docs'] ?? []))),
            'reject'  => Application::reject($id, admin_id(), post_str('reason')),
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

    /**
     * Mark one document INVALID with a reason (POST table documents|term_documents, id, reason, back). The row is kept;
     * only the file is removed. Audit-logged in Document::markInvalid.
     */
    public function invalidateDocument(): void
    {
        $back = (string)($_POST['back'] ?? 'admin/dashboard');
        if (!preg_match('#^admin/[a-z/-]+(&id=\d+)?$#', $back)) $back = 'admin/dashboard';
        require_post($back);
        // Application documents belong to the reviewer's work, claim/renewal documents to the treasurer's.
        $table = post_str('table');
        if (!AdminController::canAccess($table === 'term_documents' ? 'claims' : 'screening')) not_found();
        $r = Document::markInvalid(post_str('table'), post_int('id'), post_str('reason'), admin_id());
        flash($r['ok'] ? 'warning' : 'danger', $r['ok'] ? 'Document marked invalid; its file was removed and the record kept.' : $r['error']);
        redirect($back);
    }

    /** Stream an application document (admin can open any). */
    public function document(): void
    {
        $doc = Document::find(get_int('id'));
        if (!$doc || $doc['file_path'] === '') not_found('Document not found.');
        Document::sendApplicationDocument($doc, isset($_GET['download']));
    }
}
