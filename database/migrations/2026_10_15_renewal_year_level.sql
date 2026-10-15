-- Migration: the scholar's year level on a renewal (docs/PROCESS.md step A9).
--
-- renewal_requests   year_level / year_level_other: the year level the scholar gives for the semester they renew into
--                    (same values as education_records). On submit it also updates education_records, so the
--                    application form shows it; the change is kept in record_history. NULL for renewals made before.
--
-- Added as the last columns so the existing dump rows keep their order. Safe to run once; existing rows get NULL.

ALTER TABLE `renewal_requests`
  ADD COLUMN `year_level` ENUM('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NULL DEFAULT NULL COMMENT 'Year level for the target semester',
  ADD COLUMN `year_level_other` VARCHAR(50) NULL DEFAULT NULL;
