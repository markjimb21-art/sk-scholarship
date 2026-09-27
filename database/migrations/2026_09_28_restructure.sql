-- ============================================================================================
-- Migration: restructure the database around docs/PROCESS.md
-- ============================================================================================
--
-- BEFORE RUNNING
--   1. Back up the database:  C:\xampp\mysql\bin\mysqldump -u root sk_scholarship > backup_2026_09_28.sql
--   2. Apply the three 2026_09_27_*.sql migrations first (this file expects their columns).
--   3. Make sure the legacy files from public/uploads/documents/<id>/ are in storage/documents/<id>/
--      (same file names). This file rewrites their paths from "uploads/documents/..." to
--      "storage/documents/...".
--   4. This query must return NO rows (each semester may be released only once):
--        SELECT scholar_id, academic_year, semester, COUNT(*) FROM scholarship_releases
--        GROUP BY scholar_id, academic_year, semester HAVING COUNT(*) > 1;
--
-- Run it ONCE. MariaDB/MySQL can't roll back ALTER TABLE, which is why step 1 is required.
-- Order: (A) widen enums and add columns, (B) map old data to the new meanings, (C) create the
-- new tables and fill them from the old data, (D) narrow enums, (E) drop what the process doesn't use.
-- ============================================================================================

SET NAMES utf8mb4;

-- ============================================================================================
-- (A) Widen enums (old + new values) and add new columns
-- ============================================================================================

ALTER TABLE `applications`
  MODIFY `status` ENUM('Draft','Submitted','Under Initial Review','Incomplete','Documents Under Review',
                       'Documents Verified','For Interview','Interview Scheduled','Interview Completed',
                       'For Final Evaluation','Approved','Rejected','Waitlisted',
                       'Returned','Qualified','Interviewed') DEFAULT 'Draft',
  ADD COLUMN `qualified_at` DATETIME NULL COMMENT 'When screening qualified it (analytics: reached Qualified)' AFTER `reviewed_by`,
  ADD COLUMN `waitlisted_at` DATETIME NULL COMMENT 'When it was waitlisted (analytics: reached Waitlisted)' AFTER `decision_remarks`;

ALTER TABLE `documents`
  MODIFY `document_type` ENUM('Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID',
                              'Form 138 / Grades','2x2 Photo') NOT NULL;

ALTER TABLE `scholarship_programs`
  ADD COLUMN `amount_per_semester` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    COMMENT 'Cash released to each scholar per semester' AFTER `academic_year`;

ALTER TABLE `scholars`
  MODIFY `status` ENUM('Active','On Probation','Suspended','Graduated','Withdrawn','Disqualified','Ended') DEFAULT 'Active',
  ADD COLUMN `end_reason` ENUM('Graduated','Dropped out','Failed grade requirement','Did not renew') NULL AFTER `status`,
  ADD COLUMN `ended_at` DATETIME NULL AFTER `end_reason`;

-- One row per scheduling attempt (a no-show keeps its row; the reschedule gets a new one), so the
-- application_id key becomes a plain index. The current assignment is the application's latest row.
ALTER TABLE `interview_assignments`
  ADD COLUMN `attendance` ENUM('Scheduled','Attended','No-show') NOT NULL DEFAULT 'Scheduled' AFTER `application_id`,
  ADD COLUMN `reschedule_count` TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Earlier no-shows of this application when this row was created (1 = this is the one reschedule)' AFTER `attendance`,
  ADD KEY `idx_application` (`application_id`);
ALTER TABLE `interview_assignments`
  DROP INDEX `application_id`;

ALTER TABLE `scholarship_releases`
  MODIFY `payment_method` ENUM('Cash','Check','Bank Transfer','GCash','Other','Bank') DEFAULT 'Cash';

-- ============================================================================================
-- (B) Map old data to the new meanings
-- ============================================================================================

-- Interview attendance (old assignment_status).
UPDATE `interview_assignments` SET `attendance` = 'Attended' WHERE `assignment_status` = 'Completed';
UPDATE `interview_assignments` SET `attendance` = 'No-show'  WHERE `assignment_status` = 'No Show';
UPDATE `interview_assignments` ia
  JOIN `interview_evaluations` ev ON ev.`assignment_id` = ia.`id`
   SET ia.`attendance` = 'No-show'
 WHERE ev.`result` = 'No Show';
DELETE ev FROM `interview_evaluations` ev
  JOIN `interview_assignments` ia ON ia.`id` = ev.`assignment_id`
 WHERE ia.`attendance` = 'No-show';

-- The old code sent a no-show back to "Incomplete"; under the new rule it waits for its one reschedule.
UPDATE `applications` a
  JOIN `interview_assignments` ia ON ia.`application_id` = a.`id`
   SET a.`status` = 'Qualified', a.`updated_at` = a.`updated_at`
 WHERE ia.`attendance` = 'No-show' AND a.`status` IN ('Incomplete','Interview Scheduled','For Interview');

-- Cancelled assignments no longer exist: the applicant is simply waiting for a schedule again.
UPDATE `applications` a
  JOIN `interview_assignments` ia ON ia.`application_id` = a.`id`
   SET a.`status` = 'Qualified', a.`updated_at` = a.`updated_at`
 WHERE ia.`assignment_status` = 'Cancelled' AND a.`status` IN ('Interview Scheduled','For Interview');
DELETE FROM `interview_assignments` WHERE `assignment_status` = 'Cancelled';

-- Application statuses.
UPDATE `applications` SET `status` = 'Submitted',   `updated_at` = `updated_at` WHERE `status` IN ('Under Initial Review','Documents Under Review');
UPDATE `applications` SET `status` = 'Returned',    `updated_at` = `updated_at` WHERE `status` = 'Incomplete';
UPDATE `applications` SET `status` = 'Qualified',   `updated_at` = `updated_at` WHERE `status` IN ('Documents Verified','For Interview');
UPDATE `applications` SET `status` = 'Interviewed', `updated_at` = `updated_at` WHERE `status` IN ('Interview Completed','For Final Evaluation');

-- A scholar record means the application was approved (decision D4).
UPDATE `applications` a
  JOIN `scholars` s ON s.`application_id` = a.`id`
   SET a.`status` = 'Approved', a.`decided_at` = COALESCE(a.`decided_at`, s.`approval_date`), a.`updated_at` = a.`updated_at`
 WHERE a.`status` <> 'Approved';

-- "Scheduled" with no assignment row is really "waiting for a schedule".
UPDATE `applications` a
   SET a.`status` = 'Qualified', a.`updated_at` = a.`updated_at`
 WHERE a.`status` = 'Interview Scheduled'
   AND NOT EXISTS (SELECT 1 FROM `interview_assignments` ia WHERE ia.`application_id` = a.`id`);

-- Stage timestamps used by analytics ("reached Qualified / Waitlisted").
UPDATE `applications` a
   SET a.`qualified_at` = COALESCE(a.`reviewed_at`, a.`submitted_at`, a.`updated_at`), a.`updated_at` = a.`updated_at`
 WHERE a.`status` IN ('Qualified','Interview Scheduled','Interviewed','Approved','Waitlisted')
    OR EXISTS (SELECT 1 FROM `interview_assignments` ia WHERE ia.`application_id` = a.`id`);
UPDATE `applications` SET `waitlisted_at` = COALESCE(`decided_at`, `updated_at`), `updated_at` = `updated_at`
 WHERE `status` = 'Waitlisted';

-- Documents: new type names; one "uploaded" status (screening decides the application as a whole).
UPDATE `documents` SET `document_type` = '2x2 Photo'         WHERE `document_type` = 'Profile Picture';
UPDATE `documents` SET `document_type` = 'Form 138 / Grades' WHERE `document_type` = 'Form 138';
UPDATE `documents` SET `status` = 'Submitted' WHERE `status` IN ('Under Review','Rejected','Resubmission Required');
UPDATE `documents` SET `file_path` = CONCAT('storage/documents/', SUBSTRING(`file_path`, CHAR_LENGTH('uploads/documents/') + 1))
 WHERE `file_path` LIKE 'uploads/documents/%';

-- Scholars: Active | Ended with a reason.
UPDATE `scholars` SET `status` = 'Active' WHERE `status` = 'On Probation';
UPDATE `scholars` SET `end_reason` = 'Graduated',               `ended_at` = `updated_at`, `status` = 'Ended' WHERE `status` = 'Graduated';
UPDATE `scholars` SET `end_reason` = 'Dropped out',             `ended_at` = `updated_at`, `status` = 'Ended' WHERE `status` = 'Withdrawn';
UPDATE `scholars` SET `end_reason` = 'Failed grade requirement',`ended_at` = `updated_at`, `status` = 'Ended' WHERE `status` IN ('Disqualified','Suspended');

-- Interview scores: old 5 criteria were 0-20 each; rescale to the 4-part rubric (40/30/20/10).
UPDATE `interview_evaluations`
   SET `financial_need_score` = LEAST(40, `financial_need_score` * 2),
       `academic_score`       = LEAST(30, `academic_score` * 1.5),
       `motivation_score`     = LEAST(20, `motivation_score`),
       `community_score`      = LEAST(10, `community_score` * 0.5);
UPDATE `interview_evaluations`
   SET `total_score` = `financial_need_score` + `academic_score` + `motivation_score` + `community_score`;

-- Interview schedules: Full and Completed are now derived (from the count and the date).
UPDATE `interview_schedules` SET `status` = 'Open' WHERE `status` IN ('Full','Completed');

-- Releases: payment methods Cash / GCash / Bank / Check.
UPDATE `scholarship_releases` SET `payment_method` = 'Bank' WHERE `payment_method` = 'Bank Transfer';
UPDATE `scholarship_releases`
   SET `remarks` = TRIM(CONCAT(COALESCE(`remarks`, ''), ' (payment method recorded as "Other")')), `payment_method` = 'Cash'
 WHERE `payment_method` = 'Other';

-- Staff and official accounts (decision D1): deactivated, moved to the admin role so the two
-- roles can be removed. An admin must explicitly re-activate them to give access back.
UPDATE `users` u JOIN `roles` r ON r.`id` = u.`role_id`
   SET u.`is_active` = 0, u.`role_id` = (SELECT `id` FROM (SELECT `id` FROM `roles` WHERE `role_name` = 'admin') x)
 WHERE r.`role_name` IN ('staff','official');
DELETE FROM `roles` WHERE `role_name` IN ('staff','official');

-- Settings: keep only the ones that do something.
DELETE FROM `system_settings` WHERE `setting_key` NOT IN ('email_notifications','interview_reminder_hours');

-- ============================================================================================
-- (C) New tables for semesters, claims and renewals, filled from the old data
-- ============================================================================================

CREATE TABLE `scholar_terms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scholar_id` INT UNSIGNED NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `semester` ENUM('1st Semester','2nd Semester') NOT NULL,
  `opened_via` ENUM('approval','renewal') NOT NULL,
  `claim_status` ENUM('Waiting for Requirements','Submitted','Resubmit','Verified','Released') NOT NULL DEFAULT 'Waiting for Requirements',
  `gwa` DECIMAL(4,2) NULL COMMENT 'GWA read from the Grades by the admin when verifying the claim',
  `remarks` TEXT NULL COMMENT 'Admin remarks when asking to resubmit',
  `submitted_at` DATETIME NULL COMMENT 'Last time the scholar submitted the claim',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term` (`scholar_id`, `academic_year`, `semester`),
  KEY `idx_claim_status` (`claim_status`),
  CONSTRAINT `fk_terms_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_terms_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `term_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id` INT UNSIGNED NOT NULL,
  `purpose` ENUM('claim','renewal') NOT NULL,
  `type` ENUM('School ID','Grades','Enrollment Form') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NULL,
  `mime_type` VARCHAR(100) NULL,
  `status` ENUM('Submitted','Resubmit','Verified') NOT NULL DEFAULT 'Submitted',
  `remarks` TEXT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_doc` (`term_id`, `purpose`, `type`),
  CONSTRAINT `fk_term_documents_term` FOREIGN KEY (`term_id`) REFERENCES `scholar_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `renewal_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scholar_id` INT UNSIGNED NOT NULL,
  `from_term_id` INT UNSIGNED NULL COMMENT 'Semester the request was made from (NULL only for migrated history)',
  `academic_year` VARCHAR(20) NOT NULL COMMENT 'Target semester',
  `semester` ENUM('1st Semester','2nd Semester') NOT NULL,
  `status` ENUM('Submitted','Resubmit','Approved') NOT NULL DEFAULT 'Submitted',
  `remarks` TEXT NULL,
  `submitted_at` DATETIME NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_renewal_from_term` (`from_term_id`),
  UNIQUE KEY `uniq_renewal_target` (`scholar_id`, `academic_year`, `semester`),
  KEY `idx_renewal_status` (`status`),
  CONSTRAINT `fk_renewals_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_renewals_from_term` FOREIGN KEY (`from_term_id`) REFERENCES `scholar_terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_renewals_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1. Every release becomes a Released semester. (Semester values other than 1st/2nd — e.g. Summer — count as 2nd.)
INSERT IGNORE INTO `scholar_terms` (`scholar_id`, `academic_year`, `semester`, `opened_via`, `claim_status`, `submitted_at`, `reviewed_at`, `created_at`)
SELECT r.`scholar_id`, r.`academic_year`, IF(r.`semester` = '1st Semester', '1st Semester', '2nd Semester'),
       'renewal', 'Released', r.`created_at`, r.`created_at`, r.`created_at`
  FROM `scholarship_releases` r;

-- 2. Every academic record becomes that semester's GWA (a Verified semester if nothing was released yet).
INSERT IGNORE INTO `scholar_terms` (`scholar_id`, `academic_year`, `semester`, `opened_via`, `claim_status`, `reviewed_by`, `reviewed_at`, `created_at`)
SELECT ar.`scholar_id`, ar.`academic_year`, IF(ar.`semester` = '1st Semester', '1st Semester', '2nd Semester'),
       'renewal', 'Verified', ar.`recorded_by`, ar.`recorded_at`, ar.`recorded_at`
  FROM `academic_records` ar;
UPDATE `scholar_terms` t
  JOIN `academic_records` ar
    ON ar.`scholar_id` = t.`scholar_id` AND ar.`academic_year` = t.`academic_year`
   AND IF(ar.`semester` = '1st Semester', '1st Semester', '2nd Semester') = t.`semester`
   SET t.`gwa` = ar.`gpa`
 WHERE ar.`id` = (SELECT MAX(ar2.`id`) FROM `academic_records` ar2
                   WHERE ar2.`scholar_id` = ar.`scholar_id` AND ar2.`academic_year` = ar.`academic_year`
                     AND ar2.`semester` = ar.`semester`);

-- 3. Renewals. Approved ones (Renewed / Eligible for Renewal) open their target semester.
INSERT IGNORE INTO `scholar_terms` (`scholar_id`, `academic_year`, `semester`, `opened_via`, `claim_status`, `created_at`)
SELECT rn.`scholar_id`, rn.`academic_year`, IF(rn.`semester` = '2nd Semester', '2nd Semester', '1st Semester'),
       'renewal', 'Waiting for Requirements', COALESCE(rn.`evaluated_at`, rn.`created_at`)
  FROM `scholarship_renewals` rn
 WHERE rn.`status` IN ('Renewed','Eligible for Renewal');

INSERT IGNORE INTO `renewal_requests` (`scholar_id`, `from_term_id`, `academic_year`, `semester`, `status`, `remarks`,
                                       `submitted_at`, `reviewed_by`, `reviewed_at`, `created_at`)
SELECT rn.`scholar_id`,
       (SELECT t.`id` FROM `scholar_terms` t
         WHERE t.`scholar_id` = rn.`scholar_id`
           AND CONCAT(t.`academic_year`, '|', t.`semester`)
             < CONCAT(rn.`academic_year`, '|', IF(rn.`semester` = '2nd Semester', '2nd Semester', '1st Semester'))
         ORDER BY t.`academic_year` DESC, t.`semester` DESC LIMIT 1),
       rn.`academic_year`, IF(rn.`semester` = '2nd Semester', '2nd Semester', '1st Semester'),
       CASE rn.`status` WHEN 'Renewed' THEN 'Approved' WHEN 'Eligible for Renewal' THEN 'Approved'
                        WHEN 'For Review' THEN 'Submitted' ELSE 'Resubmit' END,
       NULLIF(rn.`remarks`, ''), rn.`created_at`,
       IF(rn.`status` = 'For Review', NULL, rn.`evaluated_by`),
       IF(rn.`status` = 'For Review', NULL, rn.`evaluated_at`),
       rn.`created_at`
  FROM `scholarship_renewals` rn;

-- 4. Active scholars without any semester get Semester 1 of their program's academic year.
INSERT IGNORE INTO `scholar_terms` (`scholar_id`, `academic_year`, `semester`, `opened_via`, `claim_status`, `created_at`)
SELECT s.`id`, sp.`academic_year`, '1st Semester', 'approval', 'Waiting for Requirements', s.`approval_date`
  FROM `scholars` s
  JOIN `scholarship_programs` sp ON sp.`id` = s.`program_id`
 WHERE s.`status` = 'Active'
   AND NOT EXISTS (SELECT 1 FROM `scholar_terms` t WHERE t.`scholar_id` = s.`id`);

-- 5. Each scholar's earliest semester was opened by approval.
UPDATE `scholar_terms` t
  JOIN (SELECT t1.`id` FROM `scholar_terms` t1
         WHERE NOT EXISTS (SELECT 1 FROM `scholar_terms` t2
                            WHERE t2.`scholar_id` = t1.`scholar_id`
                              AND CONCAT(t2.`academic_year`, '|', t2.`semester`) < CONCAT(t1.`academic_year`, '|', t1.`semester`))) first_term
    ON first_term.`id` = t.`id`
   SET t.`opened_via` = 'approval';

-- 6. Link releases to their semester — one release per semester.
ALTER TABLE `scholarship_releases`
  ADD COLUMN `term_id` INT UNSIGNED NULL AFTER `scholar_id`;
UPDATE `scholarship_releases` r
  JOIN `scholar_terms` t
    ON t.`scholar_id` = r.`scholar_id` AND t.`academic_year` = r.`academic_year`
   AND t.`semester` = IF(r.`semester` = '1st Semester', '1st Semester', '2nd Semester')
   SET r.`term_id` = t.`id`;
ALTER TABLE `scholarship_releases`
  MODIFY `term_id` INT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uniq_release_term` (`term_id`),
  ADD CONSTRAINT `fk_releases_term` FOREIGN KEY (`term_id`) REFERENCES `scholar_terms` (`id`);

-- 7. Budgets: "released" is re-summed from the actual releases for the program + academic year.
UPDATE `budgets` b
   SET b.`released` = (SELECT COALESCE(SUM(r.`amount`), 0)
                         FROM `scholarship_releases` r
                         JOIN `scholar_terms` t ON t.`id` = r.`term_id`
                         JOIN `scholars` s ON s.`id` = t.`scholar_id`
                        WHERE s.`program_id` = b.`program_id` AND t.`academic_year` = b.`academic_year`);

-- ============================================================================================
-- (D) Narrow the enums to the new values only
-- ============================================================================================

ALTER TABLE `applications`
  MODIFY `status` ENUM('Draft','Submitted','Returned','Qualified','Interview Scheduled','Interviewed',
                       'Approved','Waitlisted','Rejected') NOT NULL DEFAULT 'Draft';

ALTER TABLE `documents`
  MODIFY `document_type` ENUM('Certificate of Residency','Form 138 / Grades','Enrollment Form','School ID','2x2 Photo') NOT NULL,
  MODIFY `status` ENUM('Not Submitted','Submitted','Verified') NOT NULL DEFAULT 'Submitted';

ALTER TABLE `scholars`
  MODIFY `status` ENUM('Active','Ended') NOT NULL DEFAULT 'Active';

ALTER TABLE `interview_schedules`
  MODIFY `status` ENUM('Open','Cancelled') NOT NULL DEFAULT 'Open';

ALTER TABLE `scholarship_releases`
  MODIFY `payment_method` ENUM('Cash','GCash','Bank','Check') NOT NULL DEFAULT 'Cash';

-- ============================================================================================
-- (E) Drop what the process doesn't use (see docs/CHANGE_PLAN.md §3)
-- ============================================================================================

ALTER TABLE `applications`
  DROP COLUMN `admin_override`,
  DROP COLUMN `override_reason`,
  DROP COLUMN `eligibility_score`,
  DROP COLUMN `eligibility_notes`;

ALTER TABLE `documents`
  DROP FOREIGN KEY `documents_ibfk_2`;
ALTER TABLE `documents`
  DROP COLUMN `reviewed_by`,
  DROP COLUMN `reviewed_at`;

ALTER TABLE `applicants`
  DROP COLUMN `application_code`,
  DROP COLUMN `is_resident`,
  DROP COLUMN `residency_verified`;

ALTER TABLE `applicants_personal_information`
  DROP COLUMN `age`,
  DROP COLUMN `civil_status`,
  DROP COLUMN `email`,
  DROP COLUMN `height_cm`,
  DROP COLUMN `weight_kg`,
  DROP COLUMN `blood_type`,
  DROP COLUMN `religion`,
  DROP COLUMN `citizenship`;

ALTER TABLE `education_records`
  DROP FOREIGN KEY `education_records_ibfk_2`,
  DROP FOREIGN KEY `education_records_ibfk_3`;
ALTER TABLE `education_records`
  DROP COLUMN `school_id`,
  DROP COLUMN `course_id`;

ALTER TABLE `family_background`
  DROP COLUMN `combined_income`;

ALTER TABLE `scholarship_programs`
  DROP COLUMN `description`,
  DROP COLUMN `semester`,
  DROP COLUMN `citizenship_required`,
  DROP COLUMN `required_residency`,
  DROP COLUMN `budget_amount`;

ALTER TABLE `interview_schedules`
  DROP COLUMN `title`,
  DROP COLUMN `interviewer`,
  DROP COLUMN `remarks`;

ALTER TABLE `interview_assignments`
  DROP FOREIGN KEY `interview_assignments_ibfk_3`;
ALTER TABLE `interview_assignments`
  DROP COLUMN `assignment_status`,
  DROP COLUMN `instructions`,
  DROP COLUMN `assigned_by`,
  DROP COLUMN `confirmed_at`;

ALTER TABLE `interview_evaluations`
  CHANGE `motivation_score` `communication_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  CHANGE `interview_remarks` `remarks` TEXT NULL,
  MODIFY `financial_need_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  MODIFY `academic_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  MODIFY `community_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  MODIFY `total_score` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  DROP COLUMN `purpose_score`,
  DROP COLUMN `overall_score`,
  DROP COLUMN `max_possible_score`,
  DROP COLUMN `rating`,
  DROP COLUMN `result`,
  DROP COLUMN `recommendation`,
  DROP COLUMN `additional_notes`;

ALTER TABLE `scholarship_releases`
  DROP INDEX `idx_year`,
  DROP COLUMN `academic_year`,
  DROP COLUMN `semester`,
  DROP COLUMN `recipient_confirmed`,
  DROP COLUMN `confirmed_at`;

ALTER TABLE `budgets`
  DROP COLUMN `allocated`,
  DROP COLUMN `notes`;

ALTER TABLE `users`
  DROP COLUMN `profile_picture`,
  DROP COLUMN `verify_token`,
  DROP COLUMN `reset_token`,
  DROP COLUMN `reset_expires`;

ALTER TABLE `puroks`
  DROP FOREIGN KEY `puroks_ibfk_1`;
ALTER TABLE `puroks`
  DROP COLUMN `barangay_id`;

DROP TABLE `academic_records`;
DROP TABLE `scholarship_renewals`;
DROP TABLE `barangays`;
