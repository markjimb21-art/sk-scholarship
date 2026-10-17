-- Migration: undo 2026_10_16_academic_years.sql — programs have their own academic year again.
--
-- scholarship_programs    academic_year and its index idx_year come back. Each program gets the academic year its
--                         scholars started in (earliest semester), else its earliest budget, else its earliest claim/
--                         renewal window, else the year that was marked current in academic_years.
-- academic_years          dropped (its labels are kept wherever they are used: semesters, budgets, windows, renewals).
--
-- Only for a database that ran 2026_10_16_academic_years.sql. BACK UP THE DATABASE FIRST. Safe to run once.

ALTER TABLE `scholarship_programs`
  ADD COLUMN `academic_year` VARCHAR(20) NULL AFTER `program_name`;

UPDATE `scholarship_programs` sp
SET sp.`academic_year` = COALESCE(
  (SELECT MIN(t.`academic_year`) FROM `scholar_terms` t JOIN `scholars` s ON s.`id` = t.`scholar_id` WHERE s.`program_id` = sp.`id`),
  (SELECT MIN(b.`academic_year`) FROM `budgets` b WHERE b.`program_id` = sp.`id`),
  (SELECT MIN(ts.`academic_year`) FROM `term_schedules` ts WHERE ts.`program_id` = sp.`id`),
  (SELECT `label` FROM `academic_years` WHERE `is_current` = 1 LIMIT 1),
  IF(MONTH(CURDATE()) >= 6, CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1), CONCAT(YEAR(CURDATE()) - 1, '-', YEAR(CURDATE())))
);

ALTER TABLE `scholarship_programs`
  MODIFY `academic_year` VARCHAR(20) NOT NULL,
  ADD KEY `idx_year` (`academic_year`);

DROP TABLE `academic_years`;
