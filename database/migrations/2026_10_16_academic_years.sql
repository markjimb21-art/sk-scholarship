-- Migration: academic years get their own table; programs no longer have one (docs/CHANGE_PLAN_4.md §1).
--
-- academic_years          One row per academic year (label YYYY-YYYY, second year = first + 1). Created by an admin or
--                         treasurer under Releasing of budget → Academic years. Exactly one is current: new scholars
--                         start in its 1st Semester. "At most one current" is enforced here by a UNIQUE index on the
--                         generated column current_flag (1 for the current year, NULL otherwise — NULLs don't collide).
--                         created_by is NULL for the rows this migration backfills.
-- scholarship_programs    academic_year and its index idx_year are dropped: a program is a standing scholarship.
--
-- Backfill: every academic year already used by programs, semesters, budgets, claim/renewal windows and renewal
-- requests. Current = the year containing today (an A.Y. starts in June: Oct 2026 → 2026-2027), else the latest one.
--
-- BACK UP THE DATABASE FIRST: the program column is dropped (its values are kept as rows of academic_years).
-- Safe to run once.

CREATE TABLE `academic_years` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(20) NOT NULL COMMENT 'YYYY-YYYY, second year = first + 1',
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `current_flag` TINYINT(1) GENERATED ALWAYS AS (IF(`is_current` = 1, 1, NULL)) STORED COMMENT 'At most one current year (UNIQUE)',
  `created_by` INT UNSIGNED NULL COMMENT 'NULL = backfilled by the migration',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ay_label` (`label`),
  UNIQUE KEY `uniq_ay_current` (`current_flag`),
  CONSTRAINT `fk_ay_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `academic_years` (`label`)
SELECT `academic_year` FROM `scholarship_programs`
UNION SELECT `academic_year` FROM `scholar_terms`
UNION SELECT `academic_year` FROM `budgets`
UNION SELECT `academic_year` FROM `term_schedules`
UNION SELECT `academic_year` FROM `renewal_requests`;

SET @today_ay := IF(MONTH(CURDATE()) >= 6, CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1),
                                           CONCAT(YEAR(CURDATE()) - 1, '-', YEAR(CURDATE())));
SET @pick := COALESCE((SELECT `label` FROM `academic_years` WHERE `label` = @today_ay),
                      (SELECT MAX(`label`) FROM `academic_years`));
UPDATE `academic_years` SET `is_current` = 1 WHERE `label` = @pick;

-- (stage 3 of plan 4 adds: drop scholarship_programs.academic_year)
