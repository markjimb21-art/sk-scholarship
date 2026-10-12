<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/ApplicationEligibility.php';
require_once BASE_PATH . '/app/models/ApplicationSearch.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/DocumentStorage.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Program.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Term.php';
require_once BASE_PATH . '/app/models/TermDocument.php';
require_once BASE_PATH . '/app/models/Renewal.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/TermSchedule.php';
require_once BASE_PATH . '/app/models/SlotRequest.php';
require_once BASE_PATH . '/app/models/RecordHistory.php';
require_once BASE_PATH . '/app/models/User.php';
require_once BASE_PATH . '/app/models/Lookup.php';

/** Applicant / scholar side of docs/PROCESS.md (A1 is in AuthController). */
final class ApplicantController
{
    public function handle(string $action): void
    {
        switch (trim($action, '/')) {
            case 'dashboard':            $this->dashboard(); break;               // A3–A9 tracker
            case 'application':          $this->application(); break;             // A2 form
            case 'application/autosave': $this->autosave(); break;                // A2 draft
            case 'application/submit':   $this->submit(); break;                  // A2 / A3 resubmit
            case 'documents/upload':     $this->uploadDocument(); break;          // A2 documents
            case 'documents/view':       $this->viewDocument(); break;
            case 'documents/invalidate': $this->invalidateDocument(); break;      // A2 "this file is wrong"
            case 'scholarship':          $this->scholarship(); break;             // A7–A9
            case 'claim/upload':         $this->uploadTermDocument('claim'); break;
            case 'claim/submit':         $this->submitClaim(); break;             // A8
            case 'renewal/upload':       $this->uploadTermDocument('renewal'); break;
            case 'renewal/submit':       $this->submitRenewal(); break;           // A9
            case 'term-documents/view':  $this->viewTermDocument(); break;
            case 'slot-request':         $this->requestSlot(); break;             // A4 pick a suggested interview slot
            case 'slot-request/cancel':  $this->cancelSlot(); break;
            case 'notifications':        $this->notifications(); break;
            case 'profile':              $this->profile(); break;
            default:
                http_response_code(404);
                require VIEW_PATH . '/errors/404.php';
        }
    }

    private function applicant(): array
    {
        return Applicant::forUser((int)Auth::user()['id']);
    }

    /* ================= DASHBOARD: one tracker + one "next step" box ================= */

    private function dashboard(): void
    {
        $applicant = $this->applicant();
        $app = Application::currentForApplicant((int)$applicant['id']);
        $openProgram = Program::open();
        $scholar = $app && $app['status'] === 'Approved' ? Scholar::forApplication((int)$app['id']) : null;
        $interview = $app ? Interview::assignmentByApplication((int)$app['id']) : null;
        $term = $scholar ? Term::current((int)$scholar['id']) : null;
        $renewal = $term ? Renewal::forTerm((int)$term['id']) : null;

        $journey = $this->journey($app, $openProgram, $interview, $scholar, $term, $renewal);
        $pastApplications = Application::pastForApplicant((int)$applicant['id'], $app);

        // A4: while waiting for a schedule (also after a first no-show), up to 3 suggested slots to pick from.
        $slotRequest = $app ? SlotRequest::latestFor((int)$app['id']) : null;
        $slotChoices = [];
        if ($app && $app['status'] === 'Qualified' && !($slotRequest && $slotRequest['status'] === 'Pending')) {
            $slotChoices = Interview::suggestSlots((int)$app['program_id'], SlotRequest::APPLICANT_CHOICES);
        }

        render('applicant/dashboard', compact('applicant', 'app', 'openProgram', 'scholar', 'interview', 'term', 'renewal', 'journey',
            'pastApplications', 'slotRequest', 'slotChoices'), 'My Dashboard');
    }

    /**
     * Where the applicant is in the process and the ONE thing to do next.
     * Tracker steps: 0 Apply, 1 Screening, 2 Interview, 3 Result, 4 Claim, 5 Renewal.
     * Returns ['step','status','next' => text, 'action' => ['label','route'] | null, 'tone' => bootstrap colour, 'details' => []].
     */
    private function journey(?array $app, ?array $open, ?array $interview, ?array $scholar, ?array $term, ?array $renewal): array
    {
        $j = ['step' => 0, 'status' => '', 'next' => '', 'action' => null, 'tone' => 'primary', 'details' => []];
        $wait = static fn(string $when): string => "Nothing to do — we will e-mail you when $when.";

        if (!$app) {
            if ($open) {
                $j['status'] = 'Not started';
                $j['next'] = "Apply to {$open['program_name']} before " . date('F j, Y', strtotime($open['application_end']))
                    . ': fill in the form and upload the 5 required documents.';
                $j['action'] = ['label' => 'Start my application', 'route' => 'applicant/application'];
            } else {
                $j['status'] = 'No program is accepting applications';
                $j['next'] = 'Nothing to do right now. Applications open when the SK announces the next scholarship program.';
                $j['tone'] = 'secondary';
            }
            return $j;
        }

        switch ($app['status']) {
            case 'Draft':
                $j['status'] = Application::label('Draft');
                if ($app['program_open'] && Program::windowOpen($app)) {
                    $j['next'] = 'Complete every section and upload the 5 documents, then submit before '
                        . date('F j, Y', strtotime($app['application_end'])) . '.';
                    $j['action'] = ['label' => 'Continue my application', 'route' => 'applicant/application'];
                } else {
                    $j['next'] = 'The application period ended on ' . date('F j, Y', strtotime($app['application_end'])) . '.';
                    $j['tone'] = 'secondary';
                }
                return $j;
            case 'Submitted':
                return ['step' => 1, 'status' => Application::label('Submitted'), 'next' => $wait('screening is done'),
                        'action' => null, 'tone' => 'info', 'details' => []];
            case 'Returned':
                return ['step' => 1, 'status' => Application::label('Returned'),
                        'next' => 'Fix the items the SK office listed below, then submit again.',
                        'action' => ['label' => 'Fix and resubmit', 'route' => 'applicant/application'], 'tone' => 'warning',
                        'details' => ['Items to correct' => (string)$app['review_remarks']]];
            case 'Rejected':
                $atScreening = empty($app['qualified_at']);
                return ['step' => $atScreening ? 1 : 3, 'status' => Application::label('Rejected'),
                        'next' => 'Nothing to do for this application. You may apply again when a new program opens.',
                        'action' => null, 'tone' => 'danger',
                        'details' => ['Reason' => (string)($app['decision_remarks'] ?: 'Not given')]];
            case 'Qualified':
                $missed = $interview && $interview['attendance'] === 'No-show';
                return ['step' => 2, 'status' => $missed ? 'Missed interview — waiting for your one new schedule' : Application::label('Qualified'),
                        'next' => $wait('your interview date, time and venue are set'), 'action' => null,
                        'tone' => $missed ? 'warning' : 'info',
                        'details' => $missed ? ['Important' => 'You get one reschedule. Missing the next interview ends your application.'] : []];
            case 'Interview Scheduled':
                $details = ['When and where' => $interview ? Interview::describe($interview) : '—'];
                if ($interview && (int)$interview['reschedule_count'] > 0) {
                    $details['Important'] = 'This is your rescheduled interview. Missing it ends your application.';
                }
                return ['step' => 2, 'status' => Application::label('Interview Scheduled'),
                        'next' => 'Attend your interview. Bring a valid ID and arrive 15 minutes early.',
                        'action' => null, 'tone' => 'primary', 'details' => $details];
            case 'Interviewed':
                return ['step' => 3, 'status' => Application::label('Interviewed'), 'next' => $wait('the final result is out'),
                        'action' => null, 'tone' => 'info', 'details' => []];
            case 'Waitlisted':
                return ['step' => 3, 'status' => Application::label('Waitlisted'), 'next' => $wait('a slot opens for you'),
                        'action' => null, 'tone' => 'warning', 'details' => []];
        }

        // Approved: the scholar part (A7–A9).
        $j['step'] = 4;
        if (!$scholar || !$term) {
            $j['status'] = 'Approved';
            $j['next'] = $wait('your first semester opens');
            return $j;
        }
        $sem = "{$term['academic_year']} {$term['semester']}";
        if ($scholar['status'] === 'Ended') {
            return ['step' => 5, 'status' => 'Scholarship ended', 'tone' => 'secondary', 'action' => null,
                    'next' => 'Nothing to do. Your past semesters stay available on My Scholarship.',
                    'details' => ['Reason' => (string)$scholar['end_reason']]];
        }
        $toScholarship = ['label' => 'Go to My Scholarship', 'route' => 'applicant/scholarship'];
        switch ($term['claim_status']) {
            case 'Waiting for Requirements':
                $window = Term::claimWindow($term);
                if (!$window['can_upload']) {
                    return ['step' => 4, 'status' => "$sem — claim " . lcfirst(TermSchedule::STATE_LABELS[$window['state']]),
                            'tone' => in_array($window['state'], ['deadline_passed', 'closed', 'cancelled'], true) ? 'danger' : 'secondary',
                            'action' => null, 'next' => $window['message'], 'details' => []];
                }
                return ['step' => 4, 'status' => "$sem — claim not submitted", 'tone' => 'primary', 'action' => $toScholarship,
                        'next' => "Upload your School ID and Grades to claim your cash for $sem. " . $window['message'], 'details' => []];
            case 'Resubmit':
                $window = Term::claimWindow($term);
                return ['step' => 4, 'status' => "$sem — please re-upload", 'tone' => 'warning',
                        'action' => $window['can_upload'] ? $toScholarship : null,
                        'next' => $window['can_upload'] ? 'Re-upload the documents the SK office asked for, then submit again.' : $window['message'],
                        'details' => ['Remarks' => (string)$term['remarks']]];
            case 'Submitted':
                return ['step' => 4, 'status' => "$sem — claim submitted", 'tone' => 'info', 'action' => null,
                        'next' => $wait('your documents are verified'), 'details' => []];
            case 'Verified':
                return ['step' => 4, 'status' => "$sem — verified, waiting for release", 'tone' => 'info', 'action' => null,
                        'next' => $wait('your cash is released'), 'details' => []];
        }

        // Released: renewal for the next semester.
        $next = Term::nextSemester($term['academic_year'], $term['semester']);
        $nextSem = "{$next['academic_year']} {$next['semester']}";
        $released = '₱' . number_format((float)$term['released_amount'], 2) . ' on '
            . date('F j, Y', strtotime((string)$term['release_date'])) . ' (' . $term['payment_method']
            . ($term['reference_number'] ? ', ref. ' . $term['reference_number'] : '') . ')';
        $details = ["Released for $sem" => $released];
        if (!$renewal || $renewal['status'] === 'Resubmit') {
            $window = Renewal::window($term, $renewal);
            if (!$renewal) {
                return ['step' => 5, 'status' => "$sem — cash released", 'tone' => 'success',
                        'action' => $window['can_upload'] ? $toScholarship : null,
                        'next' => $window['can_upload']
                            ? "Renew for $nextSem: upload your updated Enrollment Form and School ID. " . $window['message']
                            : "Renewal for $nextSem: " . $window['message'],
                        'details' => $details];
            }
            return ['step' => 5, 'status' => "Renewal for $nextSem — please re-upload", 'tone' => 'warning',
                    'action' => $window['can_upload'] ? $toScholarship : null,
                    'next' => $window['can_upload'] ? 'Re-upload the documents the SK office asked for, then submit again.' : $window['message'],
                    'details' => $details + ['Remarks' => (string)$renewal['remarks']]];
        }
        return ['step' => 5, 'status' => "Renewal for $nextSem submitted", 'tone' => 'info', 'action' => null,
                'next' => $wait('your renewal is reviewed'), 'details' => $details];
    }

    /* ================= A2: APPLICATION FORM ================= */

    private function application(): void
    {
        $applicant = $this->applicant();

        $app = Application::currentForApplicant((int)$applicant['id']);
        $openProgram = Program::open();
        $personal = Applicant::personalInfo((int)$applicant['id']) ?? [];
        $personal['address_unit'] = Applicant::addressUnit($personal['complete_address'] ?? '');
        $education = Applicant::education((int)$applicant['id']) ?? [];
        $family = Applicant::familyBackground((int)$applicant['id']) ?? [];
        $documents = $app ? Document::byApplication((int)$app['id']) : [];
        // Editable: a Draft/Returned application, or none yet while a program is open (and not already a scholar).
        $editable = $app ? Application::isEditable($app) : ($openProgram !== null && !Scholar::hasActive((int)$applicant['id']));
        $completeness = $app ? Application::completeness((int)$app['id']) : null;
        // Returned with flags: only these fields/documents may change (enforced again in autosave/upload).
        $flags = $app && $app['status'] === 'Returned' ? Application::flags($app) : [];

        $puroks = Lookup::puroks();
        $schools = Lookup::schools();
        $courses = Lookup::courses();

        $pageScripts = [asset('js/application-autosave.js'), asset('js/application-review.js')];
        render('applicant/application_form', compact('applicant', 'app', 'openProgram', 'personal', 'education', 'family', 'documents',
            'editable', 'completeness', 'flags', 'puroks', 'schools', 'courses', 'pageScripts'), 'My Application');
    }

    /**
     * The current application if it is editable, creating a Draft for the OPEN program when there is none.
     * Returns [app|null, error|null].
     */
    private function editableApplication(array $applicant): array
    {
        $app = Application::currentForApplicant((int)$applicant['id']);
        if ($app) {
            return Application::isEditable($app) ? [$app, null] : [null, 'This application can no longer be edited.'];
        }
        if (Scholar::hasActive((int)$applicant['id'])) return [null, 'You already have an active scholarship.'];
        $program = Program::open();
        if (!$program) return [null, 'No scholarship program is accepting applications right now.'];

        return [Application::find(Application::createDraft((int)$applicant['id'], $program)), null];
    }

    /** A2 autosave: saves one section; always JSON. */
    private function autosave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid request.'], 405);
        verify_csrf();

        $step = $_POST['step'] ?? '';
        if (!in_array($step, ['personal', 'education', 'family'], true)) {
            json_response(['success' => false, 'message' => 'Unknown step.'], 400);
        }
        $applicant = $this->applicant();
        $app = $error = null;
        try {
            // One transaction: the Draft (created on the first save) and the step's data are saved together or not at all.
            Database::transaction(function () use ($applicant, $step, &$app, &$error): void {
                [$app, $error] = $this->editableApplication($applicant);
                if (!$app) return;   // nothing was written
                $post = $this->lockUnflagged($app, $step, (int)$applicant['id'], $_POST);
                $userId = (int)Auth::user()['id'];
                if ($post !== null) match ($step) {
                    'personal'  => Applicant::savePersonal((int)$applicant['id'], $post),
                    'education' => Applicant::saveEducation((int)$applicant['id'], $post, $userId),
                    'family'    => Applicant::saveFamily((int)$applicant['id'], $post, $userId),
                };
            });
        } catch (Throwable $e) {
            error_log('Autosave failed: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Unable to save to the server.']);
        }
        if (!$app) json_response(['success' => false, 'message' => $error]);
        json_response(['success' => true, 'saved_at' => date('Y-m-d H:i:s'), 'status' => $app['status'], 'application_id' => (int)$app['id']]);
    }

    /** A2 submit / A3 resubmit. */
    private function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/application');
        verify_csrf();
        $applicant = $this->applicant();
        $app = Application::currentForApplicant((int)$applicant['id']);
        if (!$app) { flash('danger', 'Please start your application first.'); redirect('applicant/application'); }

        $result = Application::submit((int)$app['id'], (int)Auth::user()['id']);
        if (!$result['ok']) { flash('danger', $result['error']); redirect('applicant/application'); }

        flash('success', $result['resubmitted']
            ? 'Application resubmitted. The SK office will screen it again.'
            : 'Application submitted. We will e-mail you when screening is done.');
        redirect('applicant/dashboard');
    }

    /**
     * Returned WITH flagged fields: every field of $step that was NOT flagged is reset to its stored value, so the data the
     * SK office accepted stays exactly as submitted whatever the browser sends (server-side lock). Returns null when the
     * step has no flagged field at all (nothing may be saved). Drafts and unflagged returns pass $post through.
     */
    private function lockUnflagged(array $app, string $step, int $applicantId, array $post): ?array
    {
        $flags = Application::flags($app);
        if ($app['status'] !== 'Returned' || !$flags) return $post;
        $fields = RETURN_FLAG_FIELDS[$step] ?? [];
        $flagged = array_filter(array_keys($fields), static fn($f) => in_array("$step.$f", $flags, true));
        if (!$flagged) return null;
        $stored = match ($step) {
            'personal'  => Applicant::personalInfo($applicantId) ?? [],
            'education' => Applicant::education($applicantId) ?? [],
            'family'    => Applicant::familyBackground($applicantId) ?? [],
        };
        if ($step === 'personal') $stored['address_unit'] = Applicant::addressUnit($stored['complete_address'] ?? '');
        foreach (array_keys($fields) as $f) {
            if (in_array($f, $flagged, true)) continue;
            $v = $stored[$f] ?? '';
            $post[$f] = in_array($f, ['previous_scholarship', 'current_scholarship'], true) ? ((int)$v ? '1' : '') : (string)$v;
        }
        return $post;
    }

    /**
     * A2 document upload — from the wizard (AJAX, answered with JSON) or a plain form (redirect).
     * Only while the application is editable; replaces the previous file of that type.
     */
    private function uploadDocument(): void
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

        $applicant = $this->applicant();
        [$app, $error] = $this->editableApplication($applicant);
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

    /**
     * The applicant flags one of their own uploaded files as wrong (POST id, reason): the row is kept as Invalid with the
     * reason and only the file is removed; they upload the right one next. Only while that document may still change.
     */
    private function invalidateDocument(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/application');
        verify_csrf();
        $applicant = $this->applicant();
        $doc = Document::find((int)($_POST['id'] ?? 0));
        $app = $doc ? Application::find((int)$doc['application_id']) : null;
        if (!$doc || !$app || (int)$app['applicant_id'] !== (int)$applicant['id']) { flash('danger', 'Document not found.'); redirect('applicant/application'); }
        if (!Application::fieldEditable($app, 'documents.' . $doc['document_type'])) {
            flash('danger', 'This document can’t be changed now.');
            redirect('applicant/application');
        }
        $reason = trim((string)($_POST['reason'] ?? '')) ?: 'Wrong file uploaded';
        $r = Document::markInvalid('documents', (int)$doc['id'], 'Removed by the applicant: ' . $reason, (int)Auth::user()['id']);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? "{$doc['document_type']} removed. Upload the correct file." : $r['error']);
        redirect('applicant/application');
    }

    /** An applicant may only open documents of their OWN applications (404 otherwise, so ids can't be probed). */
    private function viewDocument(): void
    {
        $applicant = $this->applicant();
        $doc = Document::find((int)($_GET['id'] ?? 0));
        $owned = $doc && $doc['file_path'] !== '' && Application::isOwnedBy((int)$doc['application_id'], (int)$applicant['id']);
        if (!$owned) { http_response_code(404); die('Document not found.'); }
        Document::sendApplicationDocument($doc, isset($_GET['download']));
    }

    /* ================= A7–A9: MY SCHOLARSHIP ================= */

    private function scholarship(): void
    {
        $applicant = $this->applicant();
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
        $term = Term::find((int)($_POST['term_id'] ?? 0));
        return ($term && (int)$term['applicant_id'] === (int)$this->applicant()['id']) ? $term : null;
    }

    private function uploadTermDocument(string $purpose): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $type = (string)($_POST['type'] ?? '');
        $result = TermDocument::upload($term, $purpose, $type, $_FILES['file'] ?? []);
        if ($result['ok']) flash('success', "$type uploaded.");
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    private function submitClaim(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
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

    private function submitRenewal(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/scholarship');
        verify_csrf();
        $term = $this->ownTerm();
        if (!$term) { flash('danger', 'Semester not found.'); redirect('applicant/scholarship'); }
        $result = Renewal::submit($term, (int)Auth::user()['id']);
        if ($result['ok']) flash('success', 'Renewal submitted. We will e-mail you when it is reviewed.');
        else               flash('danger', $result['error']);
        redirect('applicant/scholarship');
    }

    private function viewTermDocument(): void
    {
        $doc = TermDocument::find((int)($_GET['id'] ?? 0));
        if (!$doc || (int)$doc['applicant_id'] !== (int)$this->applicant()['id']) { http_response_code(404); die('Document not found.'); }
        DocumentStorage::send($doc['file_path'], $doc['original_filename'], $doc['type'], 'term_documents', (int)$doc['id'], isset($_GET['download']));
    }

    /* ================= A4: INTERVIEW SLOT REQUEST ================= */

    /** POST kind, schedule_id, date, start: one of the applicant's current suggestions (checked again on the server). */
    private function requestSlot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/dashboard');
        verify_csrf();
        $app = Application::currentForApplicant((int)$this->applicant()['id']);
        if (!$app) { flash('danger', 'Please start your application first.'); redirect('applicant/dashboard'); }
        [$kind, $scheduleId, $date, $start] = array_pad(explode('|', (string)($_POST['slot'] ?? '')), 4, '');
        $r = SlotRequest::create($app, ['kind' => $kind, 'schedule_id' => (int)$scheduleId, 'date' => $date, 'start' => $start], (int)Auth::user()['id']);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Slot requested. The SK office will confirm it — we will e-mail you.' : $r['error']);
        redirect('applicant/dashboard');
    }

    private function cancelSlot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('applicant/dashboard');
        verify_csrf();
        $app = Application::currentForApplicant((int)$this->applicant()['id']);
        $r = $app ? SlotRequest::cancel((int)$app['id'], (int)Auth::user()['id']) : ['ok' => false, 'error' => 'Nothing to cancel.'];
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Slot request withdrawn. You can pick another slot.' : $r['error']);
        redirect('applicant/dashboard');
    }

    /* ================= ACCOUNT ================= */

    private function notifications(): void
    {
        $userId = (int)Auth::user()['id'];
        if (!empty($_GET['mark_read'])) {
            Notification::markAllRead($userId);
            json_response(['ok' => true]);
        }
        $notifications = Notification::forUser($userId);
        render('applicant/notifications', compact('userId', 'notifications'), 'Notifications');
    }

    private function profile(): void
    {
        $user = Auth::user();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $name = trim($_POST['full_name'] ?? '');
            $contact = digits_only($_POST['contact_number'] ?? '');
            if ($name === '') { flash('danger', 'Full name required.'); redirect('applicant/profile'); }
            if (!empty($_POST['new_password'])) {
                if ($problems = password_problems((string)$_POST['new_password'])) { flash('danger', password_rule_message($problems)); redirect('applicant/profile'); }
                if ((string)$_POST['new_password'] !== (string)($_POST['new_password_confirm'] ?? '')) { flash('danger', 'The two new passwords do not match.'); redirect('applicant/profile'); }
                if (!password_verify($_POST['current_password'] ?? '', $user['password_hash'])) { flash('danger', 'Current password incorrect.'); redirect('applicant/profile'); }
                User::changePassword((int)$user['id'], (string)$_POST['new_password']);
            }
            User::updateProfile((int)$user['id'], $name, $contact);
            AuditLog::write('profile_updated', 'users', (int)$user['id'], 'Profile updated');
            flash('success', 'Profile updated.');
            redirect('applicant/profile');
        }
        render('applicant/Profile', compact('user'), 'My Profile');   // file on disk is Profile.php (case-sensitive on Linux)
    }
}
