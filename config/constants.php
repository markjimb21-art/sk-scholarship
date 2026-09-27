<?php
declare(strict_types=1);

// Application statuses
const APP_STATUS = [
    'Draft','Submitted','Under Initial Review','Incomplete',
    'Documents Under Review','Documents Verified','For Interview',
    'Interview Scheduled','Interview Completed','For Final Evaluation',
    'Approved','Rejected','Waitlisted'
];

// Document types
const DOCUMENT_TYPES = [
    'Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID'
];

// Document statuses
const DOC_STATUS = ['Not Submitted','Submitted','Under Review','Verified','Rejected','Resubmission Required'];

// Interview assignment statuses
const INTERVIEW_STATUS = ['For Interview','Scheduled','Confirmed','Completed','No Show','Rescheduled','Cancelled'];

// Scholarship eligibility is fixed to this barangay/city; the applicant's Complete Address
// is always composed server-side as "<unit> / <street or purok> / <barangay> / <city>" so the
// barangay/city portion can never be spoofed via a manually crafted request.
const ADDRESS_BARANGAY = 'BARANGAY ESTEFANIA';
const ADDRESS_CITY = 'BACOLOD CITY';

// Year levels
const YEAR_LEVELS = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Other'];

// Scholar statuses
const SCHOLAR_STATUS = ['Active','On Probation','Suspended','Graduated','Withdrawn','Disqualified'];