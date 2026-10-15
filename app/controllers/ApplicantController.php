<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/ApplicationEligibility.php';
require_once BASE_PATH . '/app/models/ApplicationSearch.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/DocumentStorage.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/InterviewSlots.php';
require_once BASE_PATH . '/app/models/InterviewAssignment.php';
require_once BASE_PATH . '/app/models/InterviewScore.php';
require_once BASE_PATH . '/app/models/Program.php';
require_once BASE_PATH . '/app/models/Scholar.php';
require_once BASE_PATH . '/app/models/Term.php';
require_once BASE_PATH . '/app/models/TermDocument.php';
require_once BASE_PATH . '/app/models/Renewal.php';
require_once BASE_PATH . '/app/models/AcademicYear.php';
require_once BASE_PATH . '/app/models/Budget.php';
require_once BASE_PATH . '/app/models/TermSchedule.php';
require_once BASE_PATH . '/app/models/SlotRequest.php';
require_once BASE_PATH . '/app/models/RecordHistory.php';
require_once BASE_PATH . '/app/models/User.php';
require_once BASE_PATH . '/app/models/Lookup.php';
require_once BASE_PATH . '/app/models/ApplicantJourney.php';

/** Applicant / scholar side of docs/PROCESS.md (A1 is in AuthController): the route table and the dashboard. */
final class ApplicantController
{
    /** route => [controller, method, arguments]. A route not listed is a 404. Every route is for the applicant role only. */
    public const ROUTES = [
        'dashboard'            => ['ApplicantController', 'dashboard'],                      // A3–A9 tracker
        'application'          => ['ApplicationFormController', 'application'],              // A2 form
        'application/autosave' => ['ApplicationFormController', 'autosave'],                 // A2 draft
        'application/submit'   => ['ApplicationFormController', 'submit'],                   // A2 / A3 resubmit
        'documents/upload'     => ['ApplicantDocumentController', 'uploadDocument'],         // A2 documents
        'documents/view'       => ['ApplicantDocumentController', 'viewDocument'],
        'documents/invalidate' => ['ApplicantDocumentController', 'invalidateDocument'],     // A2 "this file is wrong"
        'scholarship'          => ['ScholarshipController', 'scholarship'],                  // A7–A9
        'claim/upload'         => ['ScholarshipController', 'uploadTermDocument', ['claim']],
        'claim/submit'         => ['ScholarshipController', 'submitClaim'],                  // A8
        'renewal/upload'       => ['ScholarshipController', 'uploadTermDocument', ['renewal']],
        'renewal/submit'       => ['ScholarshipController', 'submitRenewal'],                // A9
        'term-documents/view'  => ['ScholarshipController', 'viewTermDocument'],
        'slot-request'         => ['ApplicantAccountController', 'requestSlot'],             // A4 pick a suggested interview slot
        'slot-request/cancel'  => ['ApplicantAccountController', 'cancelSlot'],
        'notifications'        => ['ApplicantAccountController', 'notifications'],
        'notifications/mark-read' => ['ApplicantAccountController', 'markNotificationsRead'],
        'profile'              => ['ApplicantAccountController', 'profile'],
    ];

    public function handle(string $action): void
    {
        $route = self::ROUTES[trim($action, '/')] ?? null;
        if ($route === null) {
            not_found();
        }
        [$class, $method] = $route;
        if ($class !== self::class) require_once BASE_PATH . "/app/controllers/$class.php";
        $controller = $class === self::class ? $this : new $class();
        $controller->$method(...($route[2] ?? []));
    }

    /* ================= DASHBOARD: one tracker + one "next step" box ================= */

    public function dashboard(): void
    {
        $applicant = Applicant::current();
        $app = Application::currentForApplicant((int)$applicant['id']);
        $openProgram = Program::open();
        $scholar = $app && $app['status'] === 'Approved' ? Scholar::forApplication((int)$app['id']) : null;
        $interview = $app ? InterviewAssignment::assignmentByApplication((int)$app['id']) : null;
        $term = $scholar ? Term::current((int)$scholar['id']) : null;
        $renewal = $term ? Renewal::forTerm((int)$term['id']) : null;

        $journey = ApplicantJourney::build($app, $openProgram, $interview, $scholar, $term, $renewal);
        $pastApplications = Application::pastForApplicant((int)$applicant['id'], $app);

        // A4: while waiting for a schedule (also after a first no-show), up to 3 suggested slots to pick from.
        $slotRequest = $app ? SlotRequest::latestFor((int)$app['id']) : null;
        $slotChoices = [];
        if ($app && $app['status'] === 'Qualified' && !($slotRequest && $slotRequest['status'] === 'Pending')) {
            $slotChoices = InterviewSlots::suggestSlots((int)$app['program_id'], SlotRequest::APPLICANT_CHOICES);
        }

        render('applicant/dashboard', compact('applicant', 'app', 'openProgram', 'scholar', 'interview', 'term', 'renewal', 'journey',
            'pastApplications', 'slotRequest', 'slotChoices'), 'My Dashboard');
    }
}
