-- Migration: restore height and weight on the applicant's personal information (application form, step I).
--
-- Both columns were dropped by 2026_09_28_restructure.sql. Same definitions as before; nullable, so existing rows get
-- NULL (the old values were dropped with the columns and can't be recovered).
--
-- Safe to run once.

ALTER TABLE `applicants_personal_information`
  ADD COLUMN `height_cm` DECIMAL(5,2) NULL AFTER `contact_number`,
  ADD COLUMN `weight_kg` DECIMAL(5,2) NULL AFTER `height_cm`;
