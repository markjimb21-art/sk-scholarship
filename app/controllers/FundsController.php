<?php
declare(strict_types=1);

/** B6 budget, B7 claims, B8 releases, B9 renewals, B10 scholars / end scholarship. */
final class FundsController
{
    /* ---------------- B6 budget ---------------- */

    public function budget(): void
    {
        $budgets = Budget::all();
        $programs = Program::options();
        $years = Budget::academicYears();
        $suggestions = [];
        foreach ($programs as $p) $suggestions[(int)$p['id']] = Budget::suggestedTotal((int)$p['id']);
        render('admin/budget', compact('budgets', 'programs', 'years', 'suggestions'), 'Budget');
    }

    public function budgetSave(): void
    {
        require_post('admin/budget');
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $r = Budget::save($_POST, $id);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? ($id ? 'Budget updated.' : 'Budget created.') : $r['error']);
        redirect('admin/budget');
    }

    public function budgetDelete(): void
    {
        require_post('admin/budget');
        $r = Budget::delete((int)($_POST['id'] ?? 0));
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Budget deleted.' : $r['error']);
        redirect('admin/budget');
    }

    /* ---------------- B7 claims ---------------- */

    public function claims(): void
    {
        $rows = Term::byClaimStatus('Submitted');
        $missed = TermSchedule::missedDeadline('release');
        render('admin/claims', compact('rows', 'missed'), 'Claims');
    }

    public function claimView(): void
    {
        $term = Term::find((int)($_GET['id'] ?? 0));
        if (!$term) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $docs = Term::documents((int)$term['id'], 'claim');
        $history = Term::forScholar((int)$term['scholar_id']);
        render('admin/claim_view', compact('term', 'docs', 'history'), 'Claim · ' . $term['full_name']);
    }

    public function claimDecide(): void
    {
        $id = (int)($_POST['term_id'] ?? 0);
        require_post('admin/claims/view&id=' . $id);
        $r = match ($_POST['decision'] ?? '') {
            'verify'   => Term::verifyClaim($id, (string)($_POST['gwa'] ?? ''), admin_id()),
            'resubmit' => Term::resubmitClaim($id, (string)($_POST['remarks'] ?? ''), (array)($_POST['types'] ?? []), admin_id(), !empty($_POST['invalid'])),
            default    => ['ok' => false, 'error' => 'Unknown decision.'],
        };
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/claims/view&id=' . $id); }
        flash('success', ($_POST['decision'] === 'verify' ? 'Claim verified — it is now in Releases.' : 'Sent back for re-upload.') . ' The scholar was notified.');
        $term = Term::find($id);
        if ($_POST['decision'] === 'verify' && $term && $term['min_gpa'] !== null && (float)$term['gwa'] > (float)$term['min_gpa']) {
            flash('warning', "Note: {$term['full_name']}'s GWA " . number_format((float)$term['gwa'], 2) . ' is below the program minimum of '
                . number_format((float)$term['min_gpa'], 2) . '. Nothing happens automatically — end the scholarship from the scholar page if needed.');
        }
        redirect('admin/claims');
    }

    /** Admin can open any semester document. */
    public function termDocument(): void
    {
        $doc = Term::document((int)($_GET['id'] ?? 0));
        if (!$doc) { http_response_code(404); die('Document not found.'); }
        Document::send($doc['file_path'], $doc['original_filename'], $doc['type'], 'term_documents', (int)$doc['id'], isset($_GET['download']));
    }

    /* ---------------- B8 releases ---------------- */

    public function releases(): void
    {
        $ready = Term::byClaimStatus('Verified');
        $filters = ['program_id' => (string)($_GET['program_id'] ?? ''), 'academic_year' => (string)($_GET['academic_year'] ?? '')];
        $sql = "SELECT r.*, t.academic_year, t.semester, s.scholar_code, u.full_name, sp.program_name, st.full_name AS released_by
                FROM scholarship_releases r
                JOIN scholar_terms t ON t.id = r.term_id
                JOIN scholars s ON s.id = r.scholar_id
                JOIN scholarship_programs sp ON sp.id = s.program_id
                JOIN applicants ap ON ap.id = s.applicant_id
                JOIN users u ON u.id = ap.user_id
                LEFT JOIN users st ON st.id = r.staff_id
                WHERE 1=1";
        $params = [];
        if ($filters['program_id'] !== '')    { $sql .= " AND s.program_id = :pid"; $params['pid'] = (int)$filters['program_id']; }
        if ($filters['academic_year'] !== '') { $sql .= " AND t.academic_year = :ay"; $params['ay'] = $filters['academic_year']; }
        $stmt = Database::conn()->prepare($sql . " ORDER BY r.release_date DESC, r.id DESC LIMIT 300");
        $stmt->execute($params);
        $history = $stmt->fetchAll();
        $historyTotal = array_sum(array_map(static fn($r) => (float)$r['amount'], $history));
        $programs = Program::options();
        $years = Budget::academicYears();
        render('admin/releases', compact('ready', 'history', 'historyTotal', 'filters', 'programs', 'years'), 'Releases');
    }

    public function releaseSave(): void
    {
        require_post('admin/releases');
        $r = Term::release((int)($_POST['term_id'] ?? 0), $_POST, admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Cash released. The scholar was notified.' : $r['error']);
        redirect('admin/releases');
    }

    /* ---------------- B9 renewals ---------------- */

    public function renewals(): void
    {
        $rows = Renewal::byStatus('Submitted');
        $missed = TermSchedule::missedDeadline('renewal');
        render('admin/renewals', compact('rows', 'missed'), 'Renewals');
    }

    public function renewalView(): void
    {
        $req = Renewal::find((int)($_GET['id'] ?? 0));
        if (!$req) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $docs = $req['from_term_id'] ? Term::documents((int)$req['from_term_id'], 'renewal') : [];
        $history = Term::forScholar((int)$req['scholar_id']);
        render('admin/renewal_view', compact('req', 'docs', 'history'), 'Renewal · ' . $req['full_name']);
    }

    public function renewalDecide(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        require_post('admin/renewals/view&id=' . $id);
        $r = match ($_POST['decision'] ?? '') {
            'approve'  => Renewal::approve($id, admin_id()),
            'resubmit' => Renewal::resubmit($id, (string)($_POST['remarks'] ?? ''), (array)($_POST['types'] ?? []), admin_id(), !empty($_POST['invalid'])),
            default    => ['ok' => false, 'error' => 'Unknown decision.'],
        };
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/renewals/view&id=' . $id); }
        flash('success', ($_POST['decision'] === 'approve' ? 'Renewal approved — the next semester is open.' : 'Sent back for re-upload.') . ' The scholar was notified.');
        redirect('admin/renewals');
    }

    /* ---------------- Claim / renewal windows (term_schedules) ---------------- */

    public function releaseSchedule(): void { $this->schedulePage('release'); }

    public function renewalSchedule(): void { $this->schedulePage('renewal'); }

    /** One page per purpose: windows with counts, semesters that still need one, and missed deadlines. */
    private function schedulePage(string $purpose): void
    {
        $schedules = TermSchedule::all($purpose);
        $needed = TermSchedule::needed($purpose);
        $missed = TermSchedule::missedDeadline($purpose);
        $programs = Program::options();
        $years = TermSchedule::academicYearOptions();
        render('admin/term_schedules', compact('purpose', 'schedules', 'needed', 'missed', 'programs', 'years'),
            $purpose === 'release' ? 'Claim schedule' : 'Renewal schedule');
    }

    public function termScheduleSave(): void
    {
        $purpose = ($_POST['purpose'] ?? '') === 'renewal' ? 'renewal' : 'release';
        $back = "admin/$purpose-schedule";
        require_post($back);
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        if ($id && ($old = TermSchedule::find($id))) $back = "admin/{$old['purpose']}-schedule";
        $r = TermSchedule::save($_POST, $id, admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect($back); }
        flash('success', ($id ? 'Schedule updated.' : 'Schedule created.')
            . ($r['notified'] ? " {$r['notified']} scholar(s) were notified by e-mail and in-app." : ''));
        redirect($back);
    }

    /* ---------------- B10 scholars ---------------- */

    public function scholars(): void
    {
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'status' => (string)($_GET['status'] ?? 'Active'),
            'program_id' => (string)($_GET['program_id'] ?? ''),
        ];
        $rows = Scholar::search($filters);
        $programs = Program::options();
        render('admin/scholars', compact('rows', 'filters', 'programs'), 'Scholars');
    }

    public function scholar(): void
    {
        $scholar = Scholar::find((int)($_GET['id'] ?? 0));
        if (!$scholar) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $terms = Term::forScholar((int)$scholar['id']);
        $stmt = Database::conn()->prepare("SELECT * FROM renewal_requests WHERE scholar_id = ? ORDER BY id DESC");
        $stmt->execute([(int)$scholar['id']]);
        $renewals = $stmt->fetchAll();
        $totalReleased = array_sum(array_map(static fn($t) => (float)($t['released_amount'] ?? 0), $terms));
        $currentDocs = $docHistory = [];
        foreach (VERSIONED_DOCUMENT_TYPES as $type) {
            $currentDocs[$type] = Document::current((int)$scholar['applicant_id'], $type);
            $docHistory[$type] = Document::history((int)$scholar['applicant_id'], $type);
        }
        $fieldHistory = RecordHistory::forApplicant((int)$scholar['applicant_id']);
        render('admin/scholar_view', compact('scholar', 'terms', 'renewals', 'totalReleased', 'currentDocs', 'docHistory', 'fieldHistory'),
            'Scholar · ' . $scholar['scholar_code']);
    }

    public function scholarEnd(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        require_post('admin/scholar&id=' . $id);
        $r = Scholar::end($id, (string)($_POST['reason'] ?? ''), admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/scholar&id=' . $id); }
        flash('warning', 'Scholarship ended. The scholar was notified.');
        if ($r['slot_freed']) {
            flash('info', 'It ended during the first semester, so a slot opened. Promote the next waitlisted applicant on the Decisions page.');
            redirect('admin/decisions&program_id=' . $r['program_id']);
        }
        redirect('admin/scholar&id=' . $id);
    }
}
