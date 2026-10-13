<?php
declare(strict_types=1);

/** A7–A9 My Scholarship: the semesters, the cash claim (School ID + Grades) and the renewal (Enrollment Form + School ID). */
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
        $renewalWindow = $term && $term['claim_status'] === 'Released' ? Renewal::window($term, $renewal) : null;

        render('applicant/scholarship', compact('applicant', 'scholar', 'terms', 'term', 'claimDocs', 'renewalDocs', 'renewal',
            'nextSemester', 'claimWindow', 'renewalWindow'), 'My Scholarship');
    }

    /** The current semester of the logged-in scholar, only if $_POST['term_id'] is theirs. */
    private function ownTerm(): ?array
    {
        $term = Term::find(post_int('term_id'));
        return ($term && (int)$term['applicant_id'] === (int)Applicant::current()['id']) ? $term : null;
    }

    public function uploadTermDocument(string $purpose): void
    {
        require_post('applicant/scholarship');
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $type = post_str('type');
        $result = TermDocument::upload($term, $purpose, $type, $_FILES['file'] ?? []);
        if ($result['ok']) flash('success', "$type uploaded.");
        else               flash('danger', $result['error']);
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
        $result = Renewal::submit($term, (int)Auth::user()['id']);
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
