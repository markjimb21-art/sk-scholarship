-- Migration: store the readable result of the program eligibility check (age, family income,
-- citizenship, general average) next to the existing numeric `eligibility_score`.
-- Both are written when the applicant submits and shown to staff on the application page; the
-- check never rejects automatically.
--
-- Safe to run once: only adds a nullable column, does not touch existing data.

ALTER TABLE `applications`
  ADD COLUMN `eligibility_notes` TEXT NULL
    COMMENT 'Failed / unchecked eligibility criteria, one per line (NULL = all criteria met or not yet evaluated)'
    AFTER `eligibility_score`;
