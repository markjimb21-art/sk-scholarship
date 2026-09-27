-- Migration: add dedicated "Incomplete" review-remarks fields to `applications`.
--
-- Why not reuse `decision_remarks`? That column is written by the FINAL decision actions
-- (approve / reject / waitlist) together with `decided_at` / `decided_by`, and is overwritten
-- each time one of those actions runs. Marking an application "Incomplete" is a separate,
-- earlier, repeatable step in the workflow (an application can be marked Incomplete, corrected,
-- and resubmitted multiple times before a final decision is ever made), so it needs its own
-- fields instead of colliding with the final-decision columns.
--
-- Safe to run once against the existing `sk_scholarship` database: it only ADDS nullable
-- columns and does not touch or drop any existing column or data.

ALTER TABLE `applications`
  ADD COLUMN `review_remarks` TEXT NULL
    COMMENT 'Reason/remarks entered by staff when marking the application Incomplete'
    AFTER `decision_remarks`,
  ADD COLUMN `review_remarks_at` DATETIME NULL
    COMMENT 'When the application was last marked Incomplete'
    AFTER `review_remarks`,
  ADD COLUMN `review_remarks_by` INT UNSIGNED NULL
    COMMENT 'User (admin/staff) who last marked the application Incomplete'
    AFTER `review_remarks_at`,
  ADD CONSTRAINT `fk_applications_review_remarks_by`
    FOREIGN KEY (`review_remarks_by`) REFERENCES `users` (`id`);
