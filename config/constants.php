<?php
declare(strict_types=1);

// Every status, list and rule value used by the process in docs/PROCESS.md lives here.

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

// Application documents (A2), all required.
const DOCUMENT_TYPES = [
    'Certificate of Residency', 'Form 138 / Grades', 'Enrollment Form', 'School ID', '2x2 Photo',
];

// Document types a scholar updates later (claim/renewal); the newest version is shown everywhere, older ones in history.
const VERSIONED_DOCUMENT_TYPES = ['School ID', 'Enrollment Form'];

// Semesters: 1st -> 2nd (same A.Y.) -> 1st of the next A.Y.
const SEMESTERS = ['1st Semester', '2nd Semester'];

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

// Blood types offered on the application form (blank = not given).
const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Year levels
const YEAR_LEVELS = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Other'];
