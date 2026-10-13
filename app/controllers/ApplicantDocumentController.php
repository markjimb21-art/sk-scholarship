<?php
declare(strict_types=1);

/** A2 the five application documents: upload (a new version each time), view one's own, flag a wrong file. */
final class ApplicantDocumentController
{
    /**
     * A2 document upload — from the wizard (AJAX, answered with JSON) or a plain form (redirect).
     * Only while the application is editable; replaces the previous file of that type.
     */
    public function uploadDocument(): void
    {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $fail = function (string $message, int $code = 400) use ($isAjax): never {
            if ($isAjax) json_response(['success' => false, 'message' => $message], $code);
            flash('danger', $message);
            redirect('applicant/application');
        };
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail('Invalid request.', 405);
        verify_csrf();

        $type = $_POST['document_type'] ?? '';
        if (!in_array($type, DOCUMENT_TYPES, true)) $fail('Invalid document type.');

        $applicant = Applicant::current();
        [$app, $error] = Application::editableFor($applicant);
        if (!$app) $fail($error);

        // Returned with flags: only the flagged documents may be uploaded again; the others stay as submitted.
        if (!Application::fieldEditable($app, "documents.$type")) {
            $fail("Your $type was accepted as submitted — only the items the SK office flagged can be changed.");
        }

        // A new version is added and the older one kept (Document::storeApplicationDocument — the one writer).
        $stored = Document::storeApplicationDocument($app, $type, $_FILES['file'] ?? [], (int)Auth::user()['id']);
        if (!$stored['ok']) $fail($stored['error']);
        $docId = (int)$stored['id'];

        if ($isAjax) {
            json_response(['success' => true, 'document' => [
                'id' => $docId, 'document_type' => $type, 'status' => 'Submitted', 'status_label' => 'Uploaded',
                'original_filename' => $stored['original'], 'uploaded_at' => date('Y-m-d H:i:s'),
                'view_url' => url('applicant/documents/view&id=' . $docId),
            ]]);
        }
        flash('success', "$type uploaded.");
        redirect('applicant/application');
    }

    /** An applicant may only open documents of their OWN applications (404 otherwise, so ids can't be probed). */
    public function viewDocument(): void
    {
        $applicant = Applicant::current();
        $doc = Document::find(get_int('id'));
        $owned = $doc && $doc['file_path'] !== '' && Application::isOwnedBy((int)$doc['application_id'], (int)$applicant['id']);
        if (!$owned) not_found('Document not found.');
        Document::sendApplicationDocument($doc, isset($_GET['download']));
    }

    /**
     * The applicant flags one of their own uploaded files as wrong (POST id, reason): the row is kept as Invalid with the
     * reason and only the file is removed; they upload the right one next. Only while that document may still change.
     */
    public function invalidateDocument(): void
    {
        require_post('applicant/application');
        $applicant = Applicant::current();
        $doc = Document::find(post_int('id'));
        $app = $doc ? Application::find((int)$doc['application_id']) : null;
        if (!$doc || !$app || (int)$app['applicant_id'] !== (int)$applicant['id']) { flash('danger', 'Document not found.'); redirect('applicant/application'); }
        if (!Application::fieldEditable($app, 'documents.' . $doc['document_type'])) {
            flash('danger', 'This document can’t be changed now.');
            redirect('applicant/application');
        }
        $reason = trim(post_str('reason')) ?: 'Wrong file uploaded';
        $r = Document::markInvalid('documents', (int)$doc['id'], 'Removed by the applicant: ' . $reason, (int)Auth::user()['id']);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? "{$doc['document_type']} removed. Upload the correct file." : $r['error']);
        redirect('applicant/application');
    }
}
