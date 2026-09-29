-- Migration: schedule-gated claims/renewals, document version history, restored personal fields
-- (docs/CHANGE_PLAN_2.md §1, §4, §5).
--
-- 1. applicants_personal_information: re-adds blood_type, religion, citizenship (dropped by
--    2026_09_28_restructure.sql). Their old values were dropped with them; existing rows get
--    NULL / NULL / 'Filipino'.
-- 2. term_schedules: one admin-created window per program + academic year + semester + purpose
--    ('release' = cash claim, 'renewal' = renewal for that target semester). A scholar can only
--    submit while the matching window is Open and inside opens_at .. submission_deadline.
-- 3. documents / term_documents: is_current + superseded_at. A new upload of a type marks the
--    applicant's older rows of that type (in BOTH tables) as not current instead of deleting them.
--    term_documents keeps several versions per term + purpose + type, so its unique key is replaced by
--    a plain index (added first: the term_id foreign key needs an index).
-- 4. Marks every older version per applicant + type as not current, so each applicant starts with
--    exactly one current row per document type.
--
-- Requires 2026_09_28_restructure.sql. Back up first. Safe to run once.

-- 1. Restored personal fields -------------------------------------------------------------------

ALTER TABLE `applicants_personal_information`
  ADD COLUMN `blood_type` VARCHAR(5) NULL AFTER `contact_number`,
  ADD COLUMN `religion` VARCHAR(80) NULL AFTER `blood_type`,
  ADD COLUMN `citizenship` VARCHAR(50) DEFAULT 'Filipino' AFTER `religion`;

-- 2. Claim / renewal submission windows ---------------------------------------------------------

CREATE TABLE `term_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id` INT UNSIGNED NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `semester` ENUM('1st Semester','2nd Semester') NOT NULL,
  `purpose` ENUM('release','renewal') NOT NULL COMMENT 'release = cash claim of this semester; renewal = renewal INTO this semester',
  `opens_at` DATETIME NOT NULL,
  `submission_deadline` DATETIME NOT NULL,
  `planned_date` DATE NULL COMMENT 'Planned release date (release) or semester start (renewal); information only',
  `status` ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
  `notes` TEXT NULL,
  `deadline_reminder_sent_at` DATETIME NULL COMMENT 'When the 48 h deadline reminder was sent (NULL = not yet / reset after a deadline change)',
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_schedule` (`program_id`,`academic_year`,`semester`,`purpose`),
  KEY `idx_deadline` (`status`,`submission_deadline`),
  KEY `fk_term_schedules_created_by` (`created_by`),
  CONSTRAINT `fk_term_schedules_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  CONSTRAINT `fk_term_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Document versions --------------------------------------------------------------------------

ALTER TABLE `documents`
  ADD COLUMN `is_current` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant' AFTER `remarks`,
  ADD COLUMN `superseded_at` DATETIME NULL AFTER `is_current`,
  ADD KEY `idx_doc_current` (`document_type`,`is_current`);

ALTER TABLE `term_documents`
  ADD COLUMN `is_current` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant' AFTER `uploaded_at`,
  ADD COLUMN `superseded_at` DATETIME NULL AFTER `is_current`,
  ADD KEY `idx_term_doc` (`term_id`,`purpose`,`type`),
  ADD KEY `idx_term_doc_current` (`type`,`is_current`);

ALTER TABLE `term_documents` DROP INDEX `uniq_term_doc`;

-- 4. One current version per applicant + type ---------------------------------------------------
-- Order key: upload time, then term documents after application documents, then id.

CREATE TEMPORARY TABLE `tmp_doc_versions` AS
  SELECT 'documents' AS tbl, d.id, a.applicant_id, CAST(d.document_type AS CHAR(40)) AS doc_type,
         CONCAT(DATE_FORMAT(COALESCE(d.uploaded_at, '1970-01-01'), '%Y%m%d%H%i%s'), '0', LPAD(d.id, 10, '0')) AS k,
         COALESCE(d.uploaded_at, '1970-01-01') AS uploaded_at
  FROM `documents` d JOIN `applications` a ON a.id = d.application_id
  WHERE d.file_path <> ''
  UNION ALL
  SELECT 'term_documents', td.id, s.applicant_id, CAST(td.type AS CHAR(40)),
         CONCAT(DATE_FORMAT(td.uploaded_at, '%Y%m%d%H%i%s'), '1', LPAD(td.id, 10, '0')),
         td.uploaded_at
  FROM `term_documents` td
  JOIN `scholar_terms` t ON t.id = td.term_id
  JOIN `scholars` s ON s.id = t.scholar_id;

CREATE TEMPORARY TABLE `tmp_doc_latest` AS
  SELECT applicant_id, doc_type, MAX(k) AS k, MAX(uploaded_at) AS latest_at
  FROM `tmp_doc_versions` GROUP BY applicant_id, doc_type;

UPDATE `documents` d
  JOIN `tmp_doc_versions` v ON v.tbl = 'documents' AND v.id = d.id
  JOIN `tmp_doc_latest` l ON l.applicant_id = v.applicant_id AND l.doc_type = v.doc_type
  SET d.is_current = 0, d.superseded_at = l.latest_at
  WHERE v.k <> l.k;

UPDATE `term_documents` td
  JOIN `tmp_doc_versions` v ON v.tbl = 'term_documents' AND v.id = td.id
  JOIN `tmp_doc_latest` l ON l.applicant_id = v.applicant_id AND l.doc_type = v.doc_type
  SET td.is_current = 0, td.superseded_at = l.latest_at
  WHERE v.k <> l.k;

DROP TEMPORARY TABLE `tmp_doc_versions`;
DROP TEMPORARY TABLE `tmp_doc_latest`;
