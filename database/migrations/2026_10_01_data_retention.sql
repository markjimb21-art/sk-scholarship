-- Migration: keep correct data, mark invalid data (docs/PROCESS.md "Data retention").
--
-- documents        Application documents are now versioned like claim/renewal documents: a re-upload adds a row and
--                  supersedes the old one (is_current = 0) instead of overwriting it, so the one-row-per-type unique key
--                  goes (the application_id foreign key keeps its own index idx_app). New status 'Invalid': the file was
--                  explicitly flagged as invalid (by the admin or the applicant) — the row stays with the reason, only
--                  the file is removed (file_removed_at).
-- term_documents   Same 'Invalid' status and columns for claim/renewal documents.
-- applications     flagged_fields: JSON list of the fields/documents the admin ticked when returning the application
--                  (e.g. ["personal.contact_number","documents.School ID"]). Only these may change; everything else
--                  stays as submitted.
-- record_history   Old → new value of key applicant fields (GWA, school, course, year level, income) on every change.
--
-- Safe to run once. Existing rows keep their values (new columns are NULL).

ALTER TABLE `documents`
  DROP INDEX `uniq_doc_per_app`,
  ADD KEY `idx_doc_app_type` (`application_id`,`document_type`),
  MODIFY `status` ENUM('Not Submitted','Submitted','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
  ADD COLUMN `invalid_reason` TEXT NULL AFTER `superseded_at`,
  ADD COLUMN `invalidated_by` INT UNSIGNED NULL AFTER `invalid_reason`,
  ADD COLUMN `invalidated_at` DATETIME NULL AFTER `invalidated_by`,
  ADD COLUMN `file_removed_at` DATETIME NULL COMMENT 'File deleted because it was flagged invalid; the row is kept' AFTER `invalidated_at`;

ALTER TABLE `term_documents`
  MODIFY `status` ENUM('Submitted','Resubmit','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
  ADD COLUMN `invalid_reason` TEXT NULL AFTER `superseded_at`,
  ADD COLUMN `invalidated_by` INT UNSIGNED NULL AFTER `invalid_reason`,
  ADD COLUMN `invalidated_at` DATETIME NULL AFTER `invalidated_by`,
  ADD COLUMN `file_removed_at` DATETIME NULL COMMENT 'File deleted because it was flagged invalid; the row is kept' AFTER `invalidated_at`;

ALTER TABLE `applications`
  ADD COLUMN `flagged_fields` TEXT NULL COMMENT 'JSON list of fields/documents flagged when returned' AFTER `updated_at`;

CREATE TABLE `record_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(64) NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `applicant_id` INT UNSIGNED NULL,
  `field` VARCHAR(64) NOT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `changed_by` INT UNSIGNED NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_record` (`table_name`,`record_id`),
  KEY `idx_history_applicant` (`applicant_id`,`changed_at`),
  KEY `fk_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
