-- Test data for sk_scholarship_test (loaded after database/schema.sql by tests/support/TestDatabase.php).
-- Dates are relative to today, so the tests mean the same thing whenever they run.
-- The analytics tests (tests/integration/AnalyticsTest.php) work their expected numbers out by hand from THIS data:
-- change a row here and update the arithmetic there.
--
-- Every account's password is Test12345!   except submitted@test.local = oldpass12 (an old password with no special
-- character, which must still sign in).

SET FOREIGN_KEY_CHECKS = 0;

-- No e-mail during tests (the mail host is also unreachable in the test environment).
UPDATE system_settings SET setting_value = '0' WHERE setting_key IN ('email_notifications', 'daily_digest_enabled', 'admin_event_emails');

-- ---------- Accounts ----------
SET @pw  := '$2y$10$JflAoHNGoaik0lN9jPGnt.o4ghi3widuOpQZVNCP/cDe6QMBaEpOS';  -- Test12345!
SET @old := '$2y$10$hFXnkmPW6yo69/JRLCjLiObqR1H4fEWZ3dYQNksDo/Ntzje5vG./S';  -- oldpass12

INSERT INTO users (id, role_id, full_name, email, password_hash, contact_number, is_active, email_verified) VALUES
 (1,  (SELECT id FROM roles WHERE role_name = 'admin'),     'Ada Admin',        'admin@test.local',       @pw,  '09170000001', 1, 1),
 (2,  (SELECT id FROM roles WHERE role_name = 'reviewer'),  'Rey Reviewer',     'reviewer@test.local',    @pw,  '09170000002', 1, 1),
 (3,  (SELECT id FROM roles WHERE role_name = 'treasurer'), 'Tess Treasurer',   'treasurer@test.local',   @pw,  '09170000003', 1, 1),
 (4,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Dina Draft',       'draft@test.local',       @pw,  '09170000004', 1, 1),
 (5,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Sam Submitted',    'submitted@test.local',   @old, '09170000005', 1, 1),
 (6,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Rita Returned',    'returned@test.local',    @pw,  '09170000006', 1, 1),
 (7,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Quin Qualified',   'qualified@test.local',   @pw,  '09170000007', 1, 1),
 (8,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Sky Scheduled',    'scheduled@test.local',   @pw,  '09170000008', 1, 1),
 (9,  (SELECT id FROM roles WHERE role_name = 'applicant'), 'Ivy Interviewed',  'interviewed@test.local', @pw,  '09170000009', 1, 1),
 (10, (SELECT id FROM roles WHERE role_name = 'applicant'), 'Wes Waitlisted',   'waitlisted@test.local',  @pw,  '09170000010', 1, 1),
 (11, (SELECT id FROM roles WHERE role_name = 'applicant'), 'Scott Scholar',    'scholar@test.local',     @pw,  '09170000011', 1, 1),
 (12, (SELECT id FROM roles WHERE role_name = 'applicant'), 'Remy Rejected',    'rejected@test.local',    @pw,  '09170000012', 1, 1);

-- applicants.id = users.id - 3 (applicant 1 = Dina Draft … applicant 9 = Remy Rejected)
INSERT INTO applicants (id, user_id) VALUES (1,4),(2,5),(3,6),(4,7),(5,8),(6,9),(7,10),(8,11),(9,12);

INSERT INTO applicants_personal_information
 (applicant_id, first_name, last_name, date_of_birth, complete_address, purok_id, contact_number, sex, citizenship) VALUES
 (1,'DINA','DRAFT',    '2005-03-01','1 / ARAO / BARANGAY ESTEFANIA / BACOLOD CITY',1,'09170000004','Female','FILIPINO'),
 (2,'SAM','SUBMITTED', '2004-06-02','2 / ARABAY 1 / BARANGAY ESTEFANIA / BACOLOD CITY',2,'09170000005','Male','FILIPINO'),
 (3,'RITA','RETURNED', '2005-09-03','3 / ARABAY 2 / BARANGAY ESTEFANIA / BACOLOD CITY',3,'0917','Female','FILIPINO'),
 (4,'QUIN','QUALIFIED','2003-01-04','4 / BAGONG SILANG / BARANGAY ESTEFANIA / BACOLOD CITY',4,'09170000007','Male','FILIPINO'),
 (5,'SKY','SCHEDULED', '2004-11-05','5 / BETHANY COURT / BARANGAY ESTEFANIA / BACOLOD CITY',5,'09170000008','Female','FILIPINO'),
 (6,'IVY','INTERVIEWED','2002-12-06','6 / BUENA ROYALE / BARANGAY ESTEFANIA / BACOLOD CITY',6,'09170000009','Female','FILIPINO'),
 (7,'WES','WAITLISTED','2004-04-07','7 / CAMELOT RESIDENCES / BARANGAY ESTEFANIA / BACOLOD CITY',7,'09170000010','Male','FILIPINO'),
 (8,'SCOTT','SCHOLAR', '2003-07-08','8 / CAMELOT VILLAGE / BARANGAY ESTEFANIA / BACOLOD CITY',8,'09170000011','Male','FILIPINO'),
 (9,'REMY','REJECTED', '2005-02-09','9 / CAMINGAWAN PROPER / BARANGAY ESTEFANIA / BACOLOD CITY',9,'09170000012','Female','FILIPINO');

INSERT INTO education_records (applicant_id, school_name, year_level, course_name, general_average) VALUES
 (1,'University of St. La Salle','1st Year','Bachelor of Science in Nursing',NULL),
 (2,'University of St. La Salle','2nd Year','Bachelor of Science in Nursing',2.10),
 (3,'Bacolod City College','1st Year','Bachelor of Elementary Education',2.40),
 (4,'Riverside College','3rd Year','Bachelor of Science in Accountancy',1.90),
 (5,'Carlos Hilado Memorial State University','2nd Year','Bachelor of Science in Information Technology',2.00),
 (6,'University of Negros Occidental - Recoletos','4th Year','Bachelor of Science in Civil Engineering',1.60),
 (7,'STI West Negros University','2nd Year','Bachelor of Science in Computer Science',2.30),
 (8,'University of St. La Salle','3rd Year','Bachelor of Science in Accountancy',1.75),
 (9,'Bacolod City College','1st Year','Bachelor of Secondary Education',2.80);

INSERT INTO family_background (applicant_id, father_income, mother_income, total_family_income, family_size) VALUES
 (1,8000,4000,12000,4),(2,10000,5000,15000,5),(3,6000,6000,12000,3),(4,12000,0,12000,6),(5,9000,3000,12000,4),
 (6,7000,7000,14000,5),(7,15000,5000,20000,4),(8,8000,2000,10000,6),(9,20000,8000,28000,3);

-- ---------- Programs ----------
-- 1 = the open test program (3 slots, ₱5,000 per semester). 2 = another active program, used to block a day for the
-- interview suggestions (it started earlier, so program 1 stays "the open program" and the default on admin pages). One
-- program per academic year (uniq_program_year), so program 2 is the next year's intake, 2027-2028: renewals go into it.
INSERT INTO scholarship_programs (id, program_name, academic_year, amount_per_semester, min_gpa, max_family_income, max_age,
                                  slots_available, application_start, application_end, is_active, created_by) VALUES
 (1, 'EduKabataan Test Program', '2026-2027', 5000.00, 2.50, 30000.00, 30, 3,
     CURDATE() - INTERVAL 120 DAY, CURDATE() + INTERVAL 10 DAY, 1, 1),
 (2, 'Other Test Program',       '2027-2028', 4000.00, NULL, NULL, NULL, 5,
     CURDATE() - INTERVAL 150 DAY, CURDATE() + INTERVAL 40 DAY, 1, 1);

-- ---------- Applications of program 1, one per status ----------
INSERT INTO applications (id, application_code, applicant_id, program_id, status, submitted_at, reviewed_at, reviewed_by,
                          qualified_at, decided_at, decided_by, decision_remarks, waitlisted_at,
                          review_remarks, review_remarks_at, review_remarks_by, flagged_fields) VALUES
 (1, 'APP-TEST-0001', 1, 1, 'Draft',               NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
 (2, 'APP-TEST-0002', 2, 1, 'Submitted',           NOW() - INTERVAL 3 DAY,  NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
 (3, 'APP-TEST-0003', 3, 1, 'Returned',            NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 5 DAY, 2, NULL, NULL, NULL, NULL, NULL,
     'Your contact number is incomplete.', NOW() - INTERVAL 5 DAY, 2, '["personal.contact_number"]'),
 (4, 'APP-TEST-0004', 4, 1, 'Qualified',           NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 9 DAY, 2, NOW() - INTERVAL 9 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
 (5, 'APP-TEST-0005', 5, 1, 'Interview Scheduled', NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 11 DAY, 2, NOW() - INTERVAL 11 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
 (6, 'APP-TEST-0006', 6, 1, 'Interviewed',         NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY, 2, NOW() - INTERVAL 19 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
 (7, 'APP-TEST-0007', 7, 1, 'Waitlisted',          NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY, 2, NOW() - INTERVAL 19 DAY, NOW() - INTERVAL 2 DAY, 1, NULL,
     NOW() - INTERVAL 2 DAY, NULL, NULL, NULL, NULL),
 (8, 'APP-TEST-0008', 8, 1, 'Approved',            NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 88 DAY, 2, NOW() - INTERVAL 88 DAY, NOW() - INTERVAL 80 DAY, 1, NULL, NULL, NULL, NULL, NULL, NULL),
 (9, 'APP-TEST-0009', 9, 1, 'Rejected',            NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY, 2, NOW() - INTERVAL 19 DAY, NOW() - INTERVAL 3 DAY, 2,
     'Missed the rescheduled interview.', NULL, NULL, NULL, NULL, NULL);

-- Documents: the Draft has the 5 empty placeholder rows; every other application has 5 uploaded PDFs (the files are
-- not on disk — only the rows matter to these tests).
INSERT INTO documents (application_id, document_type, file_path, original_filename, status)
SELECT 1, t.type, '', '', 'Not Submitted'
FROM (SELECT 'Certificate of Residency' AS type UNION ALL SELECT 'Form 138 / Grades' UNION ALL SELECT 'Enrollment Form'
      UNION ALL SELECT 'School ID' UNION ALL SELECT '2x2 Photo') t;

INSERT INTO documents (application_id, document_type, file_path, original_filename, file_size, mime_type, status, uploaded_at, is_current)
SELECT a.id, t.type, CONCAT('storage/documents/', a.id, '/seed-', t.n, '.pdf'), CONCAT(t.n, '.pdf'), 2048, 'application/pdf',
       IF(a.status IN ('Submitted', 'Returned'), 'Submitted', 'Verified'), a.submitted_at, 1
FROM applications a
JOIN (SELECT 1 AS n, 'Certificate of Residency' AS type UNION ALL SELECT 2, 'Form 138 / Grades' UNION ALL SELECT 3, 'Enrollment Form'
      UNION ALL SELECT 4, 'School ID' UNION ALL SELECT 5, '2x2 Photo') t
WHERE a.id BETWEEN 2 AND 9;

-- ---------- Interviews ----------
-- @mon = the first Monday on/after application_end + 1 day; the suggestion tests use that week.
SET @mon := CURDATE() + INTERVAL (11 + MOD(7 - WEEKDAY(CURDATE() + INTERVAL 11 DAY), 7)) DAY;

INSERT INTO interview_schedules (id, program_id, interview_date, start_time, end_time, venue, max_slots, status, created_by) VALUES
 (1, 1, CURDATE() - INTERVAL 85 DAY, '09:00', '11:00', 'Barangay Hall', 10, 'Open', 2),
 (2, 1, CURDATE() - INTERVAL 15 DAY, '09:00', '11:00', 'Barangay Hall', 10, 'Open', 2),
 (3, 1, CURDATE() - INTERVAL 8 DAY,  '13:00', '15:00', 'Barangay Hall', 10, 'Open', 2),
 (4, 1, @mon,                        '08:00', '10:00', 'Barangay Hall', 10, 'Open', 2),       -- future, 1 of 10 taken
 (5, 2, @mon + INTERVAL 2 DAY,       '08:00', '10:00', 'Covered Court', 10, 'Open', 1),       -- Wednesday: other program's day
 (6, 1, @mon + INTERVAL 3 DAY,       '13:00', '15:00', 'Barangay Hall', 10, 'Cancelled', 2);  -- Thursday: cancelled, blocks nothing

-- Tuesday of that week is a holiday.
INSERT INTO holidays (holiday_date, name, created_by) VALUES (@mon + INTERVAL 1 DAY, 'Test Holiday', 1);

INSERT INTO interview_assignments (id, schedule_id, application_id, attendance, reschedule_count) VALUES
 (1, 1, 8, 'Attended', 0),   -- Scott (scholar)
 (2, 2, 6, 'Attended', 0),   -- Ivy (Interviewed)
 (3, 2, 7, 'Attended', 0),   -- Wes (Waitlisted)
 (4, 2, 9, 'No-show',  0),   -- Remy, first attempt
 (5, 3, 9, 'No-show',  1),   -- Remy, the one reschedule → Rejected
 (6, 4, 5, 'Scheduled', 0);  -- Sky (Interview Scheduled)

-- Scores (40 / 30 / 20 / 10): 90, 80, 60.
INSERT INTO interview_evaluations (assignment_id, financial_need_score, academic_score, communication_score, community_score, total_score, interviewer_id) VALUES
 (1, 36, 27, 18, 9, 90, 2),
 (2, 32, 24, 16, 8, 80, 2),
 (3, 24, 18, 12, 6, 60, 2);

-- Quin (Qualified) picked a suggested slot; waiting for the office.
INSERT INTO slot_requests (application_id, schedule_id, proposed_date, proposed_start, proposed_end, status) VALUES
 (4, 4, @mon, '08:00', '10:00', 'Pending');

-- ---------- Scholar: Scott, from application 8 ----------
INSERT INTO scholars (id, scholar_code, applicant_id, application_id, program_id, approval_date, status) VALUES
 (1, 'SCH-TEST-0001', 8, 8, 1, CURDATE() - INTERVAL 80 DAY, 'Active');

-- 1st semester: claimed on time and released. 2nd semester (opened by renewal): claim submitted late, not yet released.
INSERT INTO scholar_terms (id, scholar_id, program_id, academic_year, semester, opened_via, claim_status, gwa, submitted_at, reviewed_by, reviewed_at) VALUES
 (1, 1, 1, '2026-2027', '1st Semester', 'approval', 'Released',  1.80, NOW() - INTERVAL 50 DAY, 3, NOW() - INTERVAL 47 DAY),
 (2, 1, 1, '2026-2027', '2nd Semester', 'renewal',  'Submitted', NULL, NOW() - INTERVAL 5 DAY,  NULL, NULL);

INSERT INTO scholarship_releases (release_code, scholar_id, term_id, amount, release_date, payment_method, staff_id) VALUES
 ('REL-TEST-0001', 1, 1, 5000.00, CURDATE() - INTERVAL 45 DAY, 'Cash', 3);

INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at, reviewed_by, reviewed_at) VALUES
 (1, 1, 1, '2026-2027', '2nd Semester', 'Approved', NOW() - INTERVAL 40 DAY, 3, NOW() - INTERVAL 35 DAY);

-- Claim/renewal windows. Release window of the 1st semester: closed, Scott submitted inside it (on time).
-- Release window of the 2nd semester: still Open, deadline passed 10 days ago; Scott submitted 5 days ago (late).
INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by) VALUES
 (1, '2026-2027', '1st Semester', 'release', NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 40 DAY, 'Closed', 3),
 (1, '2026-2027', '2nd Semester', 'renewal', NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 35 DAY, 'Closed', 3),
 (1, '2026-2027', '2nd Semester', 'release', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 10 DAY, 'Open', 3);

-- Scott's document versions. School ID: application (oldest) → claim 1 → renewal → claim 2 (newest = current).
-- Enrollment Form: application → renewal (current). Grades: claim 1 → claim 2 (current).
INSERT INTO term_documents (term_id, purpose, type, file_path, original_filename, file_size, mime_type, status, uploaded_at, is_current, superseded_at) VALUES
 (1, 'claim',   'School ID',       'storage/term-documents/1/seed-sid1.pdf',  'school-id-1.pdf',  2048, 'application/pdf', 'Verified',  NOW() - INTERVAL 55 DAY, 0, NOW() - INTERVAL 40 DAY),
 (1, 'claim',   'Grades',          'storage/term-documents/1/seed-gr1.pdf',   'grades-1.pdf',     2048, 'application/pdf', 'Verified',  NOW() - INTERVAL 55 DAY, 0, NOW() - INTERVAL 5 DAY),
 (1, 'renewal', 'Enrollment Form', 'storage/term-documents/1/seed-ef.pdf',    'enrollment.pdf',   2048, 'application/pdf', 'Verified',  NOW() - INTERVAL 40 DAY, 1, NULL),
 (1, 'renewal', 'School ID',       'storage/term-documents/1/seed-sid2.pdf',  'school-id-2.pdf',  2048, 'application/pdf', 'Verified',  NOW() - INTERVAL 40 DAY, 0, NOW() - INTERVAL 5 DAY),
 (2, 'claim',   'School ID',       'storage/term-documents/2/seed-sid3.pdf',  'school-id-3.pdf',  2048, 'application/pdf', 'Submitted', NOW() - INTERVAL 5 DAY,  1, NULL),
 (2, 'claim',   'Grades',          'storage/term-documents/2/seed-gr2.pdf',   'grades-2.pdf',     2048, 'application/pdf', 'Submitted', NOW() - INTERVAL 5 DAY,  1, NULL);

UPDATE documents SET is_current = 0, superseded_at = NOW() - INTERVAL 55 DAY WHERE application_id = 8 AND document_type = 'School ID';
UPDATE documents SET is_current = 0, superseded_at = NOW() - INTERVAL 40 DAY WHERE application_id = 8 AND document_type = 'Enrollment Form';

-- ---------- Budget: ₱50,000 for program 1, 2026-2027 (₱5,000 released) ----------
INSERT INTO budgets (program_id, academic_year, total_budget, released) VALUES (1, '2026-2027', 50000.00, 5000.00);

-- ---------- A little history so the list pages have rows ----------
INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES
 (11, 'Released', 'Your cash for 2026-2027 1st Semester was released.', 'success', 'applicant/scholarship', 1),
 (11, 'Claim received', 'Your 2nd Semester claim was received.', 'info', 'applicant/scholarship', 0),
 (1,  'New submission', 'APP-TEST-0002 was submitted.', 'info', 'admin/screening', 0);

INSERT INTO record_history (table_name, record_id, applicant_id, field, old_value, new_value, changed_by, changed_at) VALUES
 ('education_records', 8, 8, 'general_average', '1.90', '1.75', 11, NOW() - INTERVAL 30 DAY);

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES
 (1, 'program_created', 'scholarship_programs', 1, 'Created EduKabataan Test Program'),
 (2, 'application_qualify', 'applications', 4, 'Status: Submitted → Qualified'),
 (3, 'release_recorded', 'scholarship_releases', 1, 'Released ₱5,000.00');

SET FOREIGN_KEY_CHECKS = 1;
