-- Migration: optional General Weighted Average captured in Step 2 (Education) of the application
-- wizard, on the Philippine 1.00 (highest) – 5.00 scale, and checked against scholarship_programs.min_gpa.
--
-- Requires 2026_09_26_add_education_major.sql to have been run first (column is placed after `major`).
-- Safe to run once: only adds a nullable column, does not touch existing data.

ALTER TABLE `education_records`
  ADD COLUMN `general_average` DECIMAL(4,2) NULL
    COMMENT 'Optional GWA, 1.00 (highest) to 5.00'
    AFTER `major`;
