-- Migration: add an optional display Title to interview_schedules (e.g. "Batch 1 Screening"),
-- shown in the schedules list and on the interview calendar instead of a generic time string.
--
-- Safe to run once: only adds a nullable column, does not touch existing data.

ALTER TABLE `interview_schedules`
  ADD COLUMN `title` VARCHAR(200) NULL
    COMMENT 'Optional display name for this interview schedule/batch'
    AFTER `program_id`;
