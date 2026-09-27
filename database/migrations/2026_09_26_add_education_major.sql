-- Migration: add an optional "Major" field to education_records, shown right after Course
-- in Step 2 of the application wizard (e.g. course = "Bachelor of Elementary Education",
-- major = "English").
--
-- Safe to run once: only adds a nullable column, does not touch existing data.

ALTER TABLE `education_records`
  ADD COLUMN `major` VARCHAR(200) NULL
    COMMENT 'Optional major/specialization within the selected course'
    AFTER `course_name`;
