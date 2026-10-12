<?php
declare(strict_types=1);

/**
 * The applicant dashboard's tracker (docs/PROCESS.md A3–A9): from the application, interview, scholar, semester and renewal
 * rows, which step the applicant is on and the ONE thing to do next. Reads nothing itself; pure logic.
 */
final class ApplicantJourney
{
    /**
     * Where the applicant is in the process and the ONE thing to do next.
     * Tracker steps: 0 Apply, 1 Screening, 2 Interview, 3 Result, 4 Claim, 5 Renewal.
     * Returns ['step','status','next' => text, 'action' => ['label','route'] | null, 'tone' => bootstrap colour, 'details' => []].
     */
    public static function build(?array $app, ?array $open, ?array $interview, ?array $scholar, ?array $term, ?array $renewal): array
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
}
