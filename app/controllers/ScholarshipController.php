<?php
declare(strict_types=1);

/**
 * A7–A9 My Scholarship: the semesters, the cash claim (School ID + Grades; only the Grades when this A.Y.'s School ID is
 * on file) and the renewal into the next academic year (Enrollment Form + School ID).
 */
final class ScholarshipController
{
    public function scholarship(): void
    {
        $applicant = Applicant::current();
        $scholar = Scholar::forApplicant((int)$applicant['id']);
        $terms = $scholar ? Term::forScholar((int)$scholar['id']) : [];
        $term = $scholar ? Term::current((int)$scholar['id']) : null;
        $claimDocs = $term ? TermDocument::forTerm((int)$term['id'], 'claim') : [];
        $renewalDocs = $term ? TermDocument::forTerm((int)$term['id'], 'renewal') : [];
        $renewal = $term ? Renewal::forTerm((int)$term['id']) : null;
        $nextSemester = $term ? Term::nextSemester($term['academic_year'], $term['semester']) : null;
        $claimWindow = $term ? Term::claimWindow($term) : null;
        // School ID once per A.Y. (D4): with a current one of this A.Y. on file the claim asks only for the grades.
        $claimTypes = $term ? Term::claimDocumentsToShow($term, $claimDocs) : [];
        $schoolIdOnFile = $term && !in_array('School ID', $claimTypes, true)
            ? Document::schoolIdOnFile((int)$applicant['id'], $term['academic_year'], (int)$term['id']) : null;
        // Renewal only from a 2nd Semester whose claim is Verified or Released (D2, D3), or one already requested.
        $showRenewal = $term && $scholar['status'] === 'Active' && Renewal::appliesTo($term);
        $renewalWindow = $showRenewal ? Renewal::window($term, $renewal) : null;
        $education = Applicant::education((int)$applicant['id']) ?? [];   // year level on file (pre-fills the renewal)

        render('applicant/scholarship', compact('applicant', 'scholar', 'terms', 'term', 'claimDocs', 'renewalDocs', 'renewal',
            'nextSemester', 'claimWindow', 'claimTypes', 'schoolIdOnFile', 'showRenewal', 'renewalWindow', 'education')
            + ['pageScripts' => [asset('js/pages/term-documents.js')]], 'My Scholarship');
    }

    /** The current semester of the logged-in scholar, only if $_POST['term_id'] is theirs. */
    private function ownTerm(): ?array
    {
        $term = Term::find(post_int('term_id'));
        return ($term && (int)$term['applicant_id'] === (int)Applicant::current()['id']) ? $term : null;
    }

    /**
     * A8/A9 upload of one claim or renewal file — saved as soon as it is chosen (pages/term-documents.js, AJAX → JSON with
     * whether the claim/renewal can now be submitted), or from the plain form without JavaScript (redirect).
     */
    public function uploadTermDocument(string $purpose): void
    {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        require_post('applicant/scholarship');
        $term = $this->ownTerm();
        $type = post_str('type');
        $side = in_array($_POST['side'] ?? '', DOCUMENT_SIDES, true) ? $_POST['side'] : null;   // School ID front/back
        $result = $term ? TermDocument::upload($term, $purpose, $type, $_FILES['file'] ?? [], $side)
                        : ['ok' => false, 'error' => 'Semester not found.'];
        $what = TermDocument::label($type) . ($side ? ' (' . strtolower($side) . ')' : '');

        if ($isAjax) {
            if (!$result['ok']) json_response(['success' => false, 'message' => $result['error']], 400);
            json_response(['success' => true, 'message' => "$what saved.", 'ready' => TermDocument::ready((int)$term['id'], $purpose,
                $purpose === 'claim' ? Term::claimDocumentTypes($term) : null),
                'document' => [
                    'id' => $result['id'], 'type' => $type, 'side' => $side, 'status_label' => 'Uploaded',
                    'original_filename' => $result['original'], 'uploaded_at' => fmt_datetime(date('Y-m-d H:i:s')),
                    'is_image' => in_array($result['mime'], ['image/jpeg', 'image/png'], true),
                    'view_url' => url('applicant/term-documents/view&id=' . (int)$result['id']),
                ]]);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? "$what uploaded." : $result['error']);
        redirect('applicant/scholarship');
    }

    public function submitClaim(): void
    {
        require_post('applicant/scholarship');
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        try {
            $result = Term::submitClaim($term, (int)Auth::user()['id']);
        } catch (DomainException $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($result['ok']) flash('success', 'Claim submitted. We will e-mail you when your documents are verified.');
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    public function submitRenewal(): void
    {
        require_post('applicant/scholarship');
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $result = Renewal::submit($term, (int)Auth::user()['id'], post_str('year_level'), post_str('year_level_other'));
        if ($result['ok']) flash('success', 'Renewal submitted. We will e-mail you when it is reviewed.');
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    public function viewTermDocument(): void
    {
        $doc = TermDocument::find(get_int('id'));
        if (!$doc || (int)$doc['applicant_id'] !== (int)Applicant::current()['id']) not_found('Document not found.');
        DocumentStorage::send($doc['file_path'], $doc['original_filename'], $doc['type'], 'term_documents', (int)$doc['id'], isset($_GET['download']));
    }
}
