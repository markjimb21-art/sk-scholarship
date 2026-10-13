<?php
declare(strict_types=1);

/** A2/A3 the application form: show it, autosave each step, submit (or resubmit after a return). */
final class ApplicationFormController
{
    public function application(): void
    {
        $applicant = Applicant::current();

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

        $pageScripts = [asset('js/application/shared.js'), asset('js/application/field-autosave.js'), asset('js/application/steps.js'),
                        asset('js/application/document-upload.js'), asset('js/application/review.js')];
        if ($flags) $pageScripts[] = asset('js/application/flags.js');   // last: it must lock fields before app.js's DOMContentLoaded code
        render('applicant/application_form', compact('applicant', 'app', 'openProgram', 'personal', 'education', 'family', 'documents',
            'editable', 'completeness', 'flags', 'puroks', 'schools', 'courses', 'pageScripts'), 'My Application');
    }

    /** A2 autosave: saves one section; always JSON. */
    public function autosave(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid request.'], 405);
        verify_csrf();

        $step = $_POST['step'] ?? '';
        if (!in_array($step, ['personal', 'education', 'family'], true)) {
            json_response(['success' => false, 'message' => 'Unknown step.'], 400);
        }
        $applicant = Applicant::current();
        $app = $error = null;
        try {
            // One transaction: the Draft (created on the first save) and the step's data are saved together or not at all.
            Database::transaction(function () use ($applicant, $step, &$app, &$error): void {
                [$app, $error] = Application::editableFor($applicant);
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
    public function submit(): void
    {
        require_post('applicant/application');
        $applicant = Applicant::current();
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
}
