-- Migration: optional sex on the applicant profile, for the profile analytics (docs/PROCESS.md §6 (5)).
--
-- The system never collected sex/gender, so the "by gender" breakdown needs this field. It is optional on the form;
-- applicants who don't give it are counted as "Not given". Existing rows get NULL.
--
-- Safe to run once.

ALTER TABLE `applicants_personal_information`
  ADD COLUMN `sex` ENUM('Female','Male') NULL COMMENT 'Optional; analytics only' AFTER `citizenship`;
