<?php
declare(strict_types=1);

// System name (docs/CHANGE_PLAN_UI.md U1): every page title, the sidebar, e-mails and PDF reports use these.
const APP_NAME = 'SK EduKabataan: Educational Assistance for College Students';
const APP_NAME_SHORT = 'SK EduKabataan';
const APP_SUBTITLE = 'Sangguniang Kabataan · Barangay Estefania · Bacolod City';

// Password rule for every password that is CREATED or CHANGED (never at login): PASSWORD_MIN_LENGTH characters
// and at least one of these special characters. Checked by password_problems() and, live, by ui.js.
const PASSWORD_SPECIAL_CHARS = '!@#$%^&*()_+-=[]{};:\'",.<>/?\\|`~';

// Every status, list and rule value used by the process in docs/PROCESS.md lives here.

// Staff roles: these reach the admin area at all (AdminController::ROUTES then decides page by page).
const STAFF_ROLES = ['admin', 'reviewer', 'treasurer'];

// Application statuses (applications.status) => plain-language label shown to users.
const APP_STATUS_LABELS = [
    'Draft'               => 'Draft — not submitted',
    'Submitted'           => 'Submitted — waiting for screening',
    'Returned'            => 'Returned for correction',
    'Qualified'           => 'Qualified — waiting for interview schedule',
    'Interview Scheduled' => 'Interview scheduled',
    'Interviewed'         => 'Interviewed — waiting for final result',
    'Approved'            => 'Approved — scholar',
    'Waitlisted'          => 'Waitlisted',
    'Rejected'            => 'Not approved',
];
const APP_STATUS = [
    'Draft', 'Submitted', 'Returned', 'Qualified', 'Interview Scheduled',
    'Interviewed', 'Approved', 'Waitlisted', 'Rejected',
];
// Bootstrap colour per application status.
const APP_STATUS_COLORS = [
    'Draft' => 'secondary', 'Submitted' => 'info', 'Returned' => 'warning', 'Qualified' => 'primary',
    'Interview Scheduled' => 'primary', 'Interviewed' => 'primary', 'Approved' => 'success',
    'Waitlisted' => 'warning', 'Rejected' => 'danger',
];

// Bootstrap colour per interview attendance (interview_assignments.attendance).
const ATTENDANCE_COLORS = ['Scheduled' => 'primary', 'Attended' => 'success', 'No-show' => 'danger'];

// Bootstrap colour per eligibility check result (ApplicationEligibility::evaluate).
const ELIGIBILITY_COLORS = ['Pass' => 'success', 'Fail' => 'danger', 'Not provided' => 'warning', 'No limit' => 'secondary'];

// Application documents (A2), all required.
const DOCUMENT_TYPES = [
    'Certificate of Residency', 'Form 138 / Grades', 'Enrollment Form', 'School ID', '2x2 Photo',
];

// Document types a scholar updates later (claim/renewal); the newest version is shown everywhere, older ones in history.
const VERSIONED_DOCUMENT_TYPES = ['School ID', 'Enrollment Form'];

// Two-sided documents are uploaded as a Front and a Back picture (documents.side / term_documents.side): the School ID
// (application, claim, renewal) and the application's Form 138. side NULL = one file with the whole document, which also
// covers both sides (every upload made before sides existed). The claim's college grades stay one file.
const DOCUMENT_SIDES = ['Front', 'Back'];
const TWO_SIDED_DOCUMENT_TYPES = ['School ID', 'Form 138 / Grades'];

// Semesters: 1st -> 2nd (same A.Y.) -> 1st of the next A.Y.
const SEMESTERS = ['1st Semester', '2nd Semester'];
define('SEMESTERS_PER_YEAR', count(SEMESTERS));   // budgets and projections count a scholar's year as this many releases
// An academic year starts in this month (June): a date from June on belongs to that year's A.Y., before it to the A.Y.
// that began the year before (AcademicYear::labelFor — used to suggest the next year and by the 2026_10_16 migration).
const ACADEMIC_YEAR_START_MONTH = 6;

// Semester claim (scholar_terms.claim_status) => label shown to the scholar.
const CLAIM_STATUS_LABELS = [
    'Waiting for Requirements' => 'Upload your School ID and Grades',
    'Submitted'                => 'Submitted — waiting for verification',
    'Resubmit'                 => 'Please re-upload',
    'Verified'                 => 'Verified — waiting for release',
    'Released'                 => 'Released',
];
const CLAIM_STATUS_COLORS = [
    'Waiting for Requirements' => 'secondary', 'Submitted' => 'info', 'Resubmit' => 'warning',
    'Verified' => 'primary', 'Released' => 'success',
];

// Bootstrap colour per claim/renewal document status (term_documents.status).
const TERM_DOC_STATUS_COLORS = ['Submitted' => 'info', 'Resubmit' => 'warning', 'Verified' => 'success', 'Invalid' => 'danger'];

// Renewal request (renewal_requests.status) => label.
const RENEWAL_STATUS_LABELS = [
    'Submitted' => 'Submitted — waiting for review',
    'Resubmit'  => 'Please re-upload',
    'Approved'  => 'Approved — next semester open',
];

// Documents a scholar uploads for each purpose (term_documents).
const TERM_DOCUMENT_TYPES = [
    'claim'   => ['School ID', 'Grades'],
    'renewal' => ['Enrollment Form', 'School ID'],
];
// What the scholar and staff see for a claim/renewal document type (the stored type name stays the same).
const TERM_DOCUMENT_LABELS = ['Grades' => 'College grades (Certificate of Grades)'];

// Why a scholarship ends (B10).
const SCHOLAR_END_REASONS = ['Graduated', 'Dropped out', 'Failed grade requirement', 'Did not renew'];

// Interview rubric (B4). Change the maximums here only; the total is always their sum (100).
const INTERVIEW_RUBRIC = [
    'financial_need_score' => ['label' => 'Financial need',             'max' => 40],
    'academic_score'       => ['label' => 'Academic performance',       'max' => 30],
    'communication_score'  => ['label' => 'Communication / motivation', 'max' => 20],
    'community_score'      => ['label' => 'Community involvement',      'max' => 10],
];

// Release payment methods (B8).
const PAYMENT_METHODS = ['Cash', 'GCash', 'Bank', 'Check'];

// Scholarship eligibility is fixed to this barangay/city; the applicant's Complete Address
// is always composed server-side as "<unit> / <street or purok> / <barangay> / <city>" so the
// barangay/city portion can never be spoofed via a manually crafted request.
const ADDRESS_BARANGAY = 'BARANGAY ESTEFANIA';
const ADDRESS_CITY = 'BACOLOD CITY';

// Claim / renewal windows (term_schedules): the deadline reminder goes out this many hours before the deadline.
const TERM_SCHEDULE_REMINDER_HOURS = 48;

// Interview reminder (Settings → interview_reminder_hours): hours before the interview; 0 = off, at most one week.
const INTERVIEW_REMINDER_DEFAULT_HOURS = 24;
const INTERVIEW_REMINDER_MAX_HOURS = 168;

// Fields an admin can flag as wrong when returning an application (B2), per form step: POST name => label.
// When an application is Returned with flags, ONLY the flagged fields/documents may change (server-side).
const RETURN_FLAG_FIELDS = [
    'personal' => [
        'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'suffix' => 'Suffix',
        'date_of_birth' => 'Date of birth', 'address_unit' => 'Address (unit / house no.)', 'purok_id' => 'Purok / subdivision',
        'contact_number' => 'Contact number', 'height_cm' => 'Height', 'weight_kg' => 'Weight', 'blood_type' => 'Blood type', 'religion' => 'Religion', 'citizenship' => 'Citizenship',
        'sex' => 'Sex',
    ],
    'education' => [
        'school_name' => 'School', 'year_level' => 'Year level', 'year_level_other' => 'Year level (other)', 'course_name' => 'Course',
        'major' => 'Major', 'general_average' => 'GWA', 'previous_scholarship' => 'Previous scholarship',
        'previous_scholarship_details' => 'Previous scholarship details', 'current_scholarship' => 'Current scholarship',
        'current_scholarship_details' => 'Current scholarship details',
    ],
    'family' => [
        'father_name' => "Father's name", 'father_age' => "Father's age", 'father_occupation' => "Father's occupation",
        'father_address' => "Father's address", 'father_contact' => "Father's contact", 'father_income' => "Father's income",
        'father_status' => "Father's status", 'mother_name' => "Mother's name", 'mother_age' => "Mother's age",
        'mother_occupation' => "Mother's occupation", 'mother_address' => "Mother's address", 'mother_contact' => "Mother's contact",
        'mother_income' => "Mother's income", 'mother_status' => "Mother's status", 'family_size' => 'Family size',
    ],
];

// Key applicant fields whose every change is kept in record_history (shown as "History" to admins): table => [column => label].
const HISTORY_FIELDS = [
    'education_records' => ['general_average' => 'GWA', 'school_name' => 'School', 'course_name' => 'Course', 'year_level' => 'Year level'],
    'family_background' => ['father_income' => "Father's income", 'mother_income' => "Mother's income", 'total_family_income' => 'Total family income'],
];

// Optional sex on the application form (analytics only); blank = "Not given".
const SEX_OPTIONS = ['Female', 'Male'];

// Analytics bands (docs/PROCESS.md §6). label => [from (inclusive), to (exclusive)].
const AGE_BANDS = ['17 and under' => [0, 18], '18–20' => [18, 21], '21–23' => [21, 24], '24–26' => [24, 27], '27 and over' => [27, 200]];
// Total MONTHLY family income (₱).
const INCOME_BANDS = ['Below ₱10,000' => [0, 10000], '₱10,000–19,999' => [10000, 20000], '₱20,000–29,999' => [20000, 30000],
                      '₱30,000–49,999' => [30000, 50000], '₱50,000 and over' => [50000, PHP_INT_MAX]];
// GWA on the Philippine scale (1.00 best): label => [from, to] inclusive bounds.
const GWA_BANDS = ['1.00–1.50' => [1.00, 1.50], '1.51–2.00' => [1.51, 2.00], '2.01–2.50' => [2.01, 2.50], '2.51–3.00' => [2.51, 3.00], 'Above 3.00' => [3.01, 5.00]];

// Blood types offered on the application form (blank = not given).
const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Year levels
const YEAR_LEVELS = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Other'];
